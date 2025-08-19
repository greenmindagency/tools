<?php
session_start();
require 'config.php';

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$isAdmin = $_SESSION['is_admin'] ?? false;
if (!$isAdmin) {
    $allowed = $_SESSION['client_ids'] ?? [];
    if ($allowed) {
        if (!in_array($client_id, $allowed)) {
            header('Location: login.php');
            exit;
        }
        $_SESSION['client_id'] = $client_id;
    } elseif (!isset($_SESSION['client_id']) || $_SESSION['client_id'] != $client_id) {
        header('Location: login.php');
        exit;
    }
}

$slugify = function(string $name): string {
    $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    $name = preg_replace('/[^a-zA-Z0-9]+/', '-', $name);
    return strtolower(trim($name, '-'));
};

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) die("Client not found");

$slug = $slugify($client['name']);
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "dashboard.php?client_id=$client_id&slug=$slug",
];

$title = $client['name'] . ' Clusters';
$q = $_GET['q'] ?? '';
$mode = $_GET['mode'] ?? 'keyword';
if (!in_array($mode, ['keyword', 'exact'], true)) $mode = 'keyword';

function updateKeywordStats(PDO $pdo, int $client_id): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS keyword_stats (
        client_id INT PRIMARY KEY,
        total INT DEFAULT 0,
        grouped INT DEFAULT 0,
        clustered INT DEFAULT 0,
        structured INT DEFAULT 0
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = $pdo->prepare("SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT NULLIF(cluster_name,'')) AS clusters
        FROM keywords WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'clusters'=>0];
    $up = $pdo->prepare("REPLACE INTO keyword_stats (client_id,total,grouped,clustered,structured) VALUES (?,?,?,?,?)");
    $up->execute([
        $client_id,
        (int)$stats['total'],
        0,
        (int)$stats['clusters'],
        0
    ]);
}

function loadClusters(PDO $pdo, int $client_id): array {
    $stmt = $pdo->prepare(
        "SELECT keyword, cluster_name FROM keywords WHERE client_id = ? AND cluster_name IS NOT NULL AND cluster_name <> '' ORDER BY id"
    );
    $stmt->execute([$client_id]);
    $clusters = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $clusters[$row['cluster_name']][] = $row['keyword'];
    }
    return array_values($clusters);
}

function loadAllKeywords(PDO $pdo, int $client_id): array {
    $stmt = $pdo->prepare("SELECT keyword FROM keywords WHERE client_id = ? ORDER BY id");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function loadKeywordLinks(PDO $pdo, int $client_id): array {
    $stmt = $pdo->prepare("SELECT keyword, content_link FROM keywords WHERE client_id = ? AND content_link <> ''");
    $stmt->execute([$client_id]);
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[$row['keyword']] = $row['content_link'];
    }
    return $map;
}

function loadKeywordTypes(PDO $pdo, int $client_id): array {
    $stmt = $pdo->prepare("SELECT DISTINCT page_type FROM keywords WHERE client_id = ? AND page_type <> '' ORDER BY page_type");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function loadUnclustered(PDO $pdo, int $client_id): array {
    $stmt = $pdo->prepare("SELECT keyword FROM keywords WHERE client_id = ? AND (cluster_name = '' OR cluster_name IS NULL) ORDER BY id");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$action = $_GET['action'] ?? '';
if ($action === 'list') {
    $q = $_GET['q'] ?? '';
    $mode = $_GET['mode'] ?? 'keyword';
    if (!in_array($mode, ['keyword', 'exact'], true)) $mode = 'keyword';
    $terms = array_values(array_filter(array_map('trim', explode('|', $q)), 'strlen'));
    $clusters = loadClusters($pdo, $client_id);
    $singlesOnly = isset($_GET['single']) && $_GET['single'] === '1';
    if ($singlesOnly) {
        $clusters = array_values(array_filter($clusters, function($c) { return count($c) === 1; }));
    }
    if ($terms) {
        if ($mode === 'exact') {
            $lower = array_map('mb_strtolower', $terms);
            $clusters = array_values(array_filter($clusters, function($cluster) use ($lower) {
                foreach ($cluster as $kw) {
                    if (in_array(mb_strtolower($kw), $lower, true)) return true;
                }
                return false;
            }));
        } else {
            $clusters = array_values(array_filter($clusters, function($cluster) use ($terms) {
                foreach ($cluster as $kw) {
                    foreach ($terms as $t) {
                        if (stripos($kw, $t) !== false) return true;
                    }
                }
                return false;
            }));
        }
    }
    echo json_encode([
        'clusters' => $clusters,
        'unclustered' => loadUnclustered($pdo, $client_id),
        'allKeywords' => loadAllKeywords($pdo, $client_id),
        'links' => loadKeywordLinks($pdo, $client_id),
        'types' => loadKeywordTypes($pdo, $client_id)
    ]);
    exit;
}
if ($action === 'run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $existing = loadClusters($pdo, $client_id);
    $unclustered = loadUnclustered($pdo, $client_id);
    if (!$unclustered) {
        echo json_encode(['clusters' => $existing, 'unclustered' => []]);
        exit;
    }
    $input = implode("\n", $unclustered);
    $instructions = $_POST['instructions'] ?? '';

    $descriptorspec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $env = [
        'PYTHONIOENCODING' => 'utf-8',
        'INSTRUCTIONS' => $instructions,
        'EXISTING' => json_encode($existing, JSON_UNESCAPED_UNICODE)
    ];
    $process = proc_open('python3 clustering/run_cluster.py', $descriptorspec, $pipes, __DIR__, $env);
    if (is_resource($process)) {
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $raw = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);
        if ($error) {
            echo json_encode(['error' => $error]);
        } else {
            $new = json_decode($raw, true) ?: [];
            echo json_encode(['clusters' => $new, 'unclustered' => []]);
        }
    } else {
        echo json_encode(['error' => 'Could not run clustering script']);
    }
    exit;
}

if ($action === 'split' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cluster = json_decode($_POST['cluster'] ?? '[]', true) ?: [];
    if (!$cluster) {
        echo json_encode(['clusters' => []]);
        exit;
    }
    $input = implode("\n", $cluster);

    $descriptorspec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $env = ['PYTHONIOENCODING' => 'utf-8'];
    $process = proc_open('python3 clustering/run_cluster.py', $descriptorspec, $pipes, __DIR__, $env);
    if (is_resource($process)) {
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $raw = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);
        if ($error) {
            echo json_encode(['error' => $error]);
        } else {
            $new = json_decode($raw, true) ?: [];
            $flat = [];
            foreach ($new as $c) {
                foreach ($c as $kw) {
                    $flat[] = $kw;
                }
            }
            $missing = array_diff($cluster, $flat);
            foreach ($missing as $kw) {
                $new[] = [$kw];
            }
            echo json_encode(['clusters' => $new]);
        }
    } else {
        echo json_encode(['error' => 'Could not run clustering script']);
    }
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clusters = json_decode($_POST['clusters'] ?? '[]', true) ?: [];
    $affected = json_decode($_POST['affected'] ?? '[]', true) ?: [];
    $seen = [];
    $dupes = [];
    foreach ($clusters as $cluster) {
        foreach ($cluster as $kw) {
            $key = mb_strtolower($kw);
            if (isset($seen[$key])) {
                $dupes[] = $kw;
            } else {
                $seen[$key] = true;
            }
        }
    }
    if ($dupes) {
        echo json_encode([
            'success' => false,
            'error' => 'Duplicate keywords: ' . implode(', ', array_unique($dupes))
        ]);
        exit;
    }
    $pdo->beginTransaction();
    if ($affected) {
        $placeholders = implode(',', array_fill(0, count($affected), '?'));
        $pdo->prepare("UPDATE keywords SET cluster_name = '' WHERE client_id = ? AND keyword IN ($placeholders)")
            ->execute(array_merge([$client_id], $affected));
    } else {
        $pdo->prepare("UPDATE keywords SET cluster_name = '' WHERE client_id = ?")
            ->execute([$client_id]);
    }
    foreach ($clusters as $cluster) {
        if (!$cluster) continue;
        $name = $cluster[0];
        $placeholders = implode(',', array_fill(0, count($cluster), '?'));
        $stmt = $pdo->prepare(
            "UPDATE keywords SET cluster_name = ? WHERE client_id = ? AND keyword IN ($placeholders)"
        );
        $stmt->execute(array_merge([$name, $client_id], $cluster));
    }
    $pdo->commit();
    updateKeywordStats($pdo, $client_id);
    echo json_encode(['success' => true, 'unclustered' => loadUnclustered($pdo, $client_id)]);
    exit;
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE keywords SET cluster_name = '' WHERE client_id = ?")
        ->execute([$client_id]);
    updateKeywordStats($pdo, $client_id);
    echo json_encode(['success' => true, 'unclustered' => loadUnclustered($pdo, $client_id)]);
    exit;
}

if ($action === 'bulk_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $keywords = json_decode($_POST['keywords'] ?? '[]', true) ?: [];
    $link = $_POST['link'] ?? '';
    $type = $_POST['type'] ?? '';
    if ($keywords) {
        $placeholders = implode(',', array_fill(0, count($keywords), '?'));
        $params = array_merge([$link, $type, $client_id], $keywords);
        $pdo->prepare("UPDATE keywords SET content_link = ?, page_type = ? WHERE client_id = ? AND keyword IN ($placeholders)")
            ->execute($params);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'remove_unclustered' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("DELETE FROM keywords WHERE client_id = ? AND (cluster_name = '' OR cluster_name IS NULL)")
        ->execute([$client_id]);
    updateKeywordStats($pdo, $client_id);
    echo json_encode(['success' => true, 'unclustered' => loadUnclustered($pdo, $client_id)]);
    exit;
}

include 'header.php';
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="dashboard.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>">Keywords</a></li>
  <li class="nav-item"><a class="nav-link" href="positions.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>">Keyword Position</a></li>
  <li class="nav-item"><a class="nav-link active" href="clusters.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>">Clusters</a></li>
</ul>
<!-- Add Keyword Form -->
<form method="POST" id="addKeywordsForm" class="mb-4" style="display:none;">
    <textarea name="keywords" class="form-control" placeholder="Paste keywords with optional volume and form" rows="6"></textarea>
    <button type="submit" name="add_keywords" class="btn btn-primary btn-sm mt-2">Add Keywords</button>
</form>

<?php
if (isset($_POST['add_keywords'])) {
    $convertVolume = function(string $vol): string {
        $v = trim($vol);
        if ($v === '') return '';
        $v = str_replace([',', '–'], ['', '-'], $v);
        $key = preg_replace('/\s+/', '', $v);
        $map = [
            '1M-10M'   => 5000000,
            '100K-1M'  => 500000,
            '10K-100K' => 50000,
            '1K-10K'   => 5000,
            '100-1K'   => 500,
            '10-100'   => 50,
        ];
        if (isset($map[$key])) {
            return (string)$map[$key];
        }
        if (preg_match('/^(\d+(?:\.\d+)?)([KM]?)$/i', $key, $m)) {
            $num = (float)$m[1];
            $suffix = strtoupper($m[2]);
            if ($suffix === 'K') $num *= 1000;
            elseif ($suffix === 'M') $num *= 1000000;
            return (string)(int)$num;
        }
        return $v;
    };
    $text = trim($_POST['keywords']);
    $lines = preg_split('/\r\n|\n|\r/', $text);
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));

    $insert = $pdo->prepare(
        "INSERT INTO keywords (client_id, keyword, volume, form) VALUES (?, ?, ?, ?)"
    );
    $update = $pdo->prepare(
        "UPDATE keywords SET volume = ?, form = ? WHERE client_id = ? AND keyword = ?"
    );
    $entries = [];

    if (!empty($lines) && preg_match('/\d+$/', $lines[0])) {
        foreach ($lines as $line) {
            if (preg_match('/^(.*?)\s+(\S+)\s+(\S+)$/', $line, $m)) {
                $entries[] = [$m[1], $convertVolume($m[2]), $m[3]];
            } else {
                $entries[] = [$line, '', ''];
            }
        }
    } else {
        for ($i = 0; $i < count($lines); $i += 3) {
            $keyword = $lines[$i] ?? '';
            $volume  = $lines[$i + 1] ?? '';
            $form    = $lines[$i + 2] ?? '';
            if ($keyword !== '') {
                $entries[] = [$keyword, $convertVolume($volume), $form];
            }
        }
    }

    foreach ($entries as $e) {
        [$k, $v, $f] = $e;
        $update->execute([$v, $f, $client_id, $k]);
        if ($update->rowCount() === 0) {
            $insert->execute([$client_id, $k, $v, $f]);
        }
    }
    echo "<p>Keywords added.</p>";
}

$pdo->query("DELETE k1 FROM keywords k1
JOIN keywords k2 ON k1.keyword = k2.keyword AND k1.id > k2.id
WHERE k1.client_id = $client_id AND k2.client_id = $client_id");
updateKeywordStats($pdo, $client_id);
?>

<div class="mb-3 d-flex justify-content-between">
  <div>
    <button id="saveBtn" class="btn btn-success btn-sm me-2" disabled>Update Clusters</button>
    <button type="button" id="toggleAddForm" class="btn btn-warning btn-sm me-2">Update Keywords</button>
    <button id="addClusterBtn" class="btn btn-secondary btn-sm me-2">+ Add Cluster</button>
    <button id="generateBtn" class="btn btn-primary btn-sm me-2">Generate Clusters</button>
    <button id="clearBtn" class="btn btn-danger btn-sm">Clear Clusters</button>
  </div>
  <form id="clusterFilterForm" method="GET" class="d-flex">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="slug" value="<?= $slug ?>">
    <select name="mode" id="clusterFilterMode" class="form-select form-select-sm me-1" style="width:auto;">
      <option value="keyword"<?= $mode === 'keyword' ? ' selected' : '' ?>>Keyword</option>
      <option value="exact"<?= $mode === 'exact' ? ' selected' : '' ?>>Keyword/Exact</option>
    </select>
    <input type="text" name="q" id="clusterFilter" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm w-auto" placeholder="Filter..." style="max-width:200px;">
    <button type="submit" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-search"></i></button>
    <a href="clusters.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>" class="btn btn-outline-secondary btn-sm ms-1 d-flex align-items-center" title="Clear filter"><i class="bi bi-x-lg"></i></a>
  </form>
</div>
<div id="progressWrap" class="progress mb-3 d-none">
  <div id="clusterProgress" class="progress-bar" role="progressbar" style="width:0%">0%</div>
</div>
<div id="statusArea"></div>
<div id="msgArea"></div>
<div id="clustersContainer" class="row" data-masonry='{"percentPosition": true }'></div>

<div class="modal fade" id="contentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Content Creation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="contentFrame" src="" style="border:0;width:100%;height:80vh;"></iframe>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="focusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Focus Keyword</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <select id="focusSelect" class="form-select"></select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="focusNext">Next</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Update Keywords</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="bulkLinkInput" class="form-label">Link</label>
          <input type="text" id="bulkLinkInput" class="form-control">
        </div>
        <div class="mb-3">
          <label for="bulkTypeSelect" class="form-label">Type</label>
          <select id="bulkTypeSelect" class="form-select"></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="bulkSave">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/masonry-layout@4.2.2/dist/masonry.pkgd.min.js" integrity="sha384-GNFwBvfVxBkLMJpYMOABq3c+d3KnQxudP/mGPkzpZSTYykLBNsZEnG2D9G/X/+7D" crossorigin="anonymous" async onload="applyMasonry()"></script>
<script>
let currentClusters = [];
let orderMap = {};
let loadedKeywords = [];
let currentUnclustered = [];
let keywordLinks = {};
let keywordTypes = [];
let pendingKeywords = [];
let focusModalInstance;
let bulkModalInstance;
let currentBulkIndex = null;
const singleFilterActive = <?= isset($_GET['single']) ? 'true' : 'false' ?>;
const filterTerms = (document.getElementById('clusterFilter').value || '')
  .toLowerCase()
  .split('|')
  .map(s => s.trim())
  .filter(Boolean);

document.getElementById('focusNext').addEventListener('click', function() {
  const select = document.getElementById('focusSelect');
  const focused = select.value;
  const others = pendingKeywords.filter(k => k !== focused).join('\n');
  const src = `../prompt-generator/content-creation.php?embed=1&focus=${encodeURIComponent(focused)}&keywords=${encodeURIComponent(others)}`;
  document.getElementById('contentFrame').src = src;
  if (focusModalInstance) focusModalInstance.hide();
  const contentEl = document.getElementById('contentModal');
  new bootstrap.Modal(contentEl).show();
});
const modeEl = document.getElementById('clusterFilterMode');
const filterMode = modeEl ? modeEl.value : 'keyword';

function escapeHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function setOrder(list) {
  orderMap = {};
  list.forEach((kw, idx) => orderMap[kw.toLowerCase()] = idx);
}

function sortCluster(cluster) {
  return cluster.slice().sort((a,b) => (orderMap[a.toLowerCase()] ?? 0) - (orderMap[b.toLowerCase()] ?? 0));
}

function processClusters(list) {
  let cleaned = list.map(c => c.filter(Boolean)).filter(c => c.length).map(sortCluster);
  let singles = cleaned.filter(c => c.length === 1).map(c => c[0]);
  return {clusters: cleaned, singles};
}

function findDuplicates(clusters) {
  const seen = {};
  const dupes = [];
  clusters.forEach(cluster => {
    cluster.forEach(kw => {
      const key = kw.toLowerCase();
      if (seen[key]) dupes.push(kw);
      else seen[key] = true;
    });
  });
  return Array.from(new Set(dupes));
}

function renderKeywordButtons(list) {
  return list
    .map(k => {
      const link = keywordLinks[k];
      const text = escapeHtml(k);
      const extra = link ? ` data-link="${encodeURIComponent(link)}"` : '';
      const cls = link ? ' text-primary' : ' text-body';
      return `<button type="button" class="btn btn-link btn-sm kw-copy me-1 text-decoration-none${cls}"${extra} data-kw="${text}">${text}</button>`;
    })
    .join('');
}

function openBulkModal(idx) {
  currentBulkIndex = idx;
  const linkInput = document.getElementById('bulkLinkInput');
  const typeSelect = document.getElementById('bulkTypeSelect');
  linkInput.value = '';
  typeSelect.innerHTML = '<option value=""></option>' + keywordTypes.map(t => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join('');
  const modalEl = document.getElementById('bulkModal');
  bulkModalInstance = new bootstrap.Modal(modalEl);
  bulkModalInstance.show();
}

document.getElementById('bulkSave').addEventListener('click', function() {
  if (currentBulkIndex === null) return;
  const link = document.getElementById('bulkLinkInput').value || '';
  const type = document.getElementById('bulkTypeSelect').value || '';
  const kws = currentClusters[currentBulkIndex] ? currentClusters[currentBulkIndex].slice() : [];
  fetch('clusters.php?action=bulk_update&client_id=<?= $client_id ?>', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'keywords=' + encodeURIComponent(JSON.stringify(kws)) + '&link=' + encodeURIComponent(link) + '&type=' + encodeURIComponent(type)
  }).then(r => r.json()).then(res => {
    if (res.success) {
      kws.forEach(k => {
        if (link) keywordLinks[k] = link;
        else delete keywordLinks[k];
      });
      if (type && !keywordTypes.includes(type)) keywordTypes.push(type);
      renderClusters(currentClusters);
      if (bulkModalInstance) bulkModalInstance.hide();
      currentBulkIndex = null;
    }
  });
});

function updateStatusBars(unclustered = null, singles = []) {
  if (unclustered !== null) currentUnclustered = unclustered;
  const area = document.getElementById('statusArea');
  area.innerHTML = '';
  const dupes = findDuplicates(currentClusters);

  if (singles.length) {
    const bar = document.createElement('div');
    bar.className = 'alert alert-warning d-flex align-items-center';
    bar.textContent = `Single keyword clusters: ${singles.length}`;
    if (!singleFilterActive) {
      const link = document.createElement('a');
      link.className = 'btn btn-sm btn-secondary ms-2';
      link.textContent = 'Filter now';
      link.href = `clusters.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>&single=1`;
      bar.appendChild(link);
    }
    area.appendChild(bar);
  }

  if (currentUnclustered.length) {
    const bar = document.createElement('div');
    bar.className = 'alert alert-warning d-flex align-items-center flex-wrap';
    const span = document.createElement('span');
    span.innerHTML = `Unclustered keywords (${currentUnclustered.length}): ${renderKeywordButtons(currentUnclustered)}`;
    bar.appendChild(span);
    const fix = document.createElement('button');
    fix.id = 'fixUnclusteredBtn';
    fix.className = 'btn btn-sm btn-secondary ms-2';
    fix.textContent = 'Fix';
    bar.appendChild(fix);
    const rm = document.createElement('button');
    rm.id = 'removeUnclusteredBtn';
    rm.className = 'btn btn-sm btn-danger ms-2';
    rm.textContent = 'Remove';
    bar.appendChild(rm);
    area.appendChild(bar);
  }

  if (dupes.length) {
    const bar = document.createElement('div');
    bar.className = 'alert alert-danger d-flex align-items-center flex-wrap';
    const span = document.createElement('span');
    span.innerHTML = `Duplicate keywords (${dupes.length}): ${renderKeywordButtons(dupes)}`;
    bar.appendChild(span);
    const filter = document.createElement('a');
    filter.className = 'btn btn-sm btn-secondary ms-auto';
    filter.textContent = 'Filter now';
    filter.href = `clusters.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>&q=${encodeURIComponent(dupes.join('|'))}`;
    bar.appendChild(filter);
    area.appendChild(bar);
  }

  if (!currentUnclustered.length && !singles.length && !dupes.length) {
    const bar = document.createElement('div');
    bar.className = 'alert alert-success';
    bar.textContent = 'All keywords are clustered.';
    area.appendChild(bar);
  }

  area.querySelectorAll('.kw-copy').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      navigator.clipboard.writeText(btn.dataset.kw);
    });
  });

  const fixBtn = document.getElementById('fixUnclusteredBtn');
  if (fixBtn) fixBtn.addEventListener('click', () => runClustering('', true));
  const rmBtn = document.getElementById('removeUnclusteredBtn');
  if (rmBtn) rmBtn.addEventListener('click', () => {
    if (!confirm('Remove all unclustered keywords?')) return;
    fetch('clusters.php?action=remove_unclustered&client_id=<?= $client_id ?>', {method:'POST'})
      .then(r => r.json()).then(data => {
        if (data.success) {
          const singles = processClusters(currentClusters).singles;
          currentUnclustered = data.unclustered || [];
          updateStatusBars(currentUnclustered, singles);
          document.getElementById('msgArea').innerHTML = '<p class="text-success">Unclustered keywords removed.</p>';
        }
      });
  });
}

function saveClusters(clusters, singles) {
  const msgArea = document.getElementById('msgArea');
  const progressWrap = document.getElementById('progressWrap');
  const bar = document.getElementById('clusterProgress');
  progressWrap.classList.remove('d-none');
  let pct = 10;
  bar.style.width = pct + '%';
  bar.textContent = pct + '%';
  const timer = setInterval(() => {
    pct = Math.min(pct + 10, 90);
    bar.style.width = pct + '%';
    bar.textContent = pct + '%';
  }, 500);
  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  let affected = loadedKeywords.slice();
  clusters.forEach(c => affected.push(...c));
  affected = Array.from(new Set(affected));
  fetch('clusters.php?action=save&client_id=<?= $client_id ?>', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'clusters=' + encodeURIComponent(JSON.stringify(clusters)) +
          '&affected=' + encodeURIComponent(JSON.stringify(affected))
  }).then(r => r.json()).then(data => {
    clearInterval(timer);
    bar.style.width = '100%';
    bar.textContent = '100%';
    if (data.success) {
      msgArea.innerHTML = '<p class="text-success">Clusters saved.</p>';
      loadedKeywords = [];
      clusters.forEach(c => loadedKeywords.push(...c));
      renderClusters(clusters);
    } else {
      msgArea.innerHTML = '<pre class="text-danger">' + data.error + '</pre>';
    }
    updateStatusBars(data.unclustered || [], singles);
    btn.disabled = false;
    msgArea.scrollIntoView({behavior:'smooth'});
    setTimeout(() => progressWrap.classList.add('d-none'), 500);
  }).catch(() => {
    clearInterval(timer);
    bar.style.width = '100%';
    bar.textContent = '100%';
    btn.disabled = false;
    msgArea.innerHTML = '<pre class="text-danger">Save failed.</pre>';
    msgArea.scrollIntoView({behavior:'smooth'});
    setTimeout(() => progressWrap.classList.add('d-none'), 500);
  });
}

function applyMasonry() {
  const wrap = document.getElementById('clustersContainer');
  if (!window.Masonry) return;
  if (window.msnry) window.msnry.destroy();
  window.msnry = new Masonry(wrap, {itemSelector: '.col-md-4', percentPosition: true});
}

function getLines(textDiv) {
  const lines = [];
  const gather = el => {
    Array.from(el.childNodes).forEach(node => {
      if (node.nodeType === Node.ELEMENT_NODE) {
        if (node.tagName === 'DIV') {
          gather(node);
        } else {
          node.textContent.split(/\r?\n+/).forEach(part => {
            part = part.trim();
            if (part) lines.push(part);
          });
        }
      } else if (node.nodeType === Node.TEXT_NODE) {
        node.textContent.split(/\r?\n+/).forEach(part => {
          part = part.trim();
          if (part) lines.push(part);
        });
      }
    });
  };
  gather(textDiv);
  if (lines.length) return lines;
  return textDiv.innerText.split(/\r?\n+/).map(s => s.trim()).filter(Boolean);
}

function renderClusters(data) {
  currentClusters = data.map(sortCluster);
  const wrap = document.getElementById('clustersContainer');
  wrap.innerHTML = '';
  currentClusters.forEach((cluster, idx) => {
    const col = document.createElement('div');
    col.className = 'col-md-4 mb-4';
    const card = document.createElement('div');
    card.className = 'card';
    if (cluster.length === 1) card.classList.add('border', 'border-danger');
    const header = document.createElement('div');
    header.className = 'card-header d-flex align-items-center';
    const countSpan = document.createElement('span');
    countSpan.className = 'badge bg-secondary me-2';
    countSpan.textContent = cluster.length;
    const titleSpan = document.createElement('span');
    const firstTitle = cluster[0] || 'Unnamed';
    titleSpan.textContent = firstTitle;
    const contentBtn = document.createElement('button');
    contentBtn.type = 'button';
    contentBtn.className = 'btn btn-sm btn-outline-secondary ms-auto me-1';
    contentBtn.innerHTML = '<i class="bi bi-pencil-square"></i>';
    contentBtn.setAttribute('data-bs-toggle', 'tooltip');
    contentBtn.setAttribute('title', 'Create Content');
    contentBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      pendingKeywords = currentClusters[idx].slice();
      const select = document.getElementById('focusSelect');
      select.innerHTML = '';
      pendingKeywords.forEach(k => {
        const opt = document.createElement('option');
        opt.value = k;
        opt.textContent = k;
        select.appendChild(opt);
      });
      const modalEl = document.getElementById('focusModal');
      focusModalInstance = new bootstrap.Modal(modalEl);
      focusModalInstance.show();
    });
    const filterBtn = document.createElement('button');
    filterBtn.type = 'button';
    filterBtn.className = 'btn btn-sm btn-outline-primary me-1';
    filterBtn.innerHTML = '<i class="bi bi-funnel"></i>';
    filterBtn.setAttribute('data-bs-toggle', 'tooltip');
    filterBtn.setAttribute('title', 'Filter Keywords');
    filterBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const kws = currentClusters[idx].slice();
      const url = `dashboard.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>&field=keyword&mode=exact&q=${encodeURIComponent(kws.join('|'))}`;
      window.location.href = url;
    });
    const bulkBtn = document.createElement('button');
    bulkBtn.type = 'button';
    bulkBtn.className = 'btn btn-sm btn-outline-success me-1';
    bulkBtn.innerHTML = '<i class="bi bi-link-45deg"></i>';
    bulkBtn.setAttribute('data-bs-toggle', 'tooltip');
    bulkBtn.setAttribute('title', 'Update Link & Type');
    bulkBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      openBulkModal(idx);
    });
    const splitBtn = document.createElement('button');
    splitBtn.type = 'button';
    splitBtn.className = 'btn btn-sm btn-secondary me-1';
    splitBtn.innerHTML = '<i class="bi bi-scissors"></i>';
    splitBtn.setAttribute('data-bs-toggle', 'tooltip');
    splitBtn.setAttribute('title', 'Split Cluster');
    splitBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const target = currentClusters[idx].slice();
      if (target.length < 2) return;
      fetch('clusters.php?action=split&client_id=<?= $client_id ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'cluster=' + encodeURIComponent(JSON.stringify(target))
      }).then(r => r.json()).then(data => {
        if (data.error) {
          document.getElementById('msgArea').innerHTML = '<pre class="text-danger">' + data.error + '</pre>';
          return;
        }
        let newClusters = data.clusters || [];
        const flat = newClusters.flat();
        target.forEach(kw => {
          if (!flat.includes(kw)) newClusters.push([kw]);
        });
        currentClusters.splice(idx, 1, ...newClusters);
        const processed = processClusters(currentClusters);
        renderClusters(processed.clusters);
        updateStatusBars(null, processed.singles);
        document.getElementById('msgArea').innerHTML = `<p class="text-success">Cluster "${escapeHtml(target[0])}" split into ${newClusters.length} clusters.</p>`;
      }).catch(() => {
        document.getElementById('msgArea').innerHTML = '<pre class="text-danger">Split failed.</pre>';
      });
    });
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-sm btn-danger';
    removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
    removeBtn.setAttribute('data-bs-toggle', 'tooltip');
    removeBtn.setAttribute('title', 'Remove Cluster');
    removeBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const wrap = document.getElementById('clustersContainer');
      const top = window.scrollY;
      wrap.style.minHeight = wrap.offsetHeight + 'px';
      currentClusters.splice(idx, 1);
      const processed = processClusters(currentClusters);
      renderClusters(processed.clusters);
      updateStatusBars(null, processed.singles);
      window.scrollTo(0, top);
      wrap.style.minHeight = '';
    });
    header.appendChild(countSpan);
    header.appendChild(titleSpan);
    header.appendChild(contentBtn);
    header.appendChild(filterBtn);
    header.appendChild(bulkBtn);
    header.appendChild(splitBtn);
    header.appendChild(removeBtn);
    header.style.cursor = 'pointer';
    header.addEventListener('click', function(e) {
      if (e.target.closest('button')) return;
      const first = currentClusters[idx][0];
      if (!first) return;
      const url = `clusters.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>&q=${encodeURIComponent(first)}&mode=exact`;
      window.location.href = url;
    });
    const textDiv = document.createElement('div');
    textDiv.className = 'form-control cluster-edit';
    textDiv.contentEditable = 'true';
    textDiv.innerHTML = cluster.map(k => {
      const lc = k.toLowerCase();
      const match = filterMode === 'exact'
        ? filterTerms.some(t => lc === t)
        : filterTerms.some(t => lc.includes(t));
      const link = keywordLinks[k];
      const kwHtml = link ? `<a href="${escapeHtml(link)}" target="_blank" rel="noopener">${escapeHtml(k)}</a>` : escapeHtml(k);
      return `<div${match ? ' class="bg-warning-subtle"' : ''}>${kwHtml}</div>`;
    }).join('');
    const syncCluster = () => {
      const lines = getLines(textDiv);
      currentClusters[idx] = lines;
      countSpan.textContent = lines.length;
      const first = lines[0] || 'Unnamed';
      titleSpan.textContent = first;
      if (lines.length === 1) card.classList.add('border', 'border-danger');
      else card.classList.remove('border', 'border-danger');
      if (window.msnry) {
        window.msnry.reloadItems();
        window.msnry.layout();
      }
      const processed = processClusters(currentClusters);
      updateStatusBars(null, processed.singles);
    };
    textDiv.addEventListener('input', syncCluster);
    textDiv.addEventListener('paste', function(e) {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text');
      const lines = text.split(/\r?\n+/).map(s => s.trim()).filter(Boolean);
      const sel = window.getSelection();
      if (!sel.rangeCount) return;
      const range = sel.getRangeAt(0);
      range.deleteContents();
      const frag = document.createDocumentFragment();
      lines.forEach(line => {
        const div = document.createElement('div');
        const lc = line.toLowerCase();
        if (filterMode === 'exact' ? filterTerms.some(t => lc === t) : filterTerms.some(t => lc.includes(t))) div.classList.add('bg-warning-subtle');
        div.textContent = line;
        frag.appendChild(div);
      });
      range.insertNode(frag);
      range.collapse(false);
      syncCluster();
    });
    textDiv.addEventListener('dragover', e => e.preventDefault());
    textDiv.addEventListener('drop', function(e) {
      e.preventDefault();
      const text = e.dataTransfer.getData('text');
      const lines = text.split(/\r?\n+/).map(s => s.trim()).filter(Boolean);
      const sel = window.getSelection();
      if (!sel.rangeCount) return;
      const range = sel.getRangeAt(0);
      range.deleteContents();
      const frag = document.createDocumentFragment();
      lines.forEach(line => {
        const div = document.createElement('div');
        const lc = line.toLowerCase();
        if (filterMode === 'exact' ? filterTerms.some(t => lc === t) : filterTerms.some(t => lc.includes(t))) div.classList.add('bg-warning-subtle');
        div.textContent = line;
        frag.appendChild(div);
      });
      range.insertNode(frag);
      range.collapse(false);
      syncCluster();
    });
    textDiv.addEventListener('click', function(e) {
      const a = e.target.closest('a');
      if (a) {
        e.preventDefault();
        window.open(a.href, '_blank');
      }
    });
    card.appendChild(header);
    card.appendChild(textDiv);
    col.appendChild(card);
    wrap.appendChild(col);
  });
  document.getElementById('saveBtn').disabled = currentClusters.length === 0;
  const tooltipTriggerList = [].slice.call(wrap.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
  applyMasonry();
}

document.addEventListener('DOMContentLoaded', function() {
  const msgArea = document.getElementById('msgArea');
  msgArea.innerHTML = '<p>Loading clusters…</p>';
  fetch('clusters.php?action=list&client_id=<?= $client_id ?>&q=<?= urlencode($q) ?>&mode=<?= urlencode($mode) ?><?= isset($_GET['single']) ? "&single=1" : '' ?>')
    .then(r => r.json()).then(data => {
      loadedKeywords = [];
      (data.clusters || []).forEach(c => loadedKeywords.push(...c));
      setOrder(data.allKeywords || []);
      keywordLinks = data.links || {};
      keywordTypes = data.types || [];
      const processed = processClusters(data.clusters || []);
      renderClusters(processed.clusters);
      updateStatusBars(data.unclustered || [], processed.singles);
      msgArea.innerHTML = '';
    }).catch(() => {
      msgArea.innerHTML = '<pre class="text-danger">Failed to load clusters.</pre>';
    });
});

function runClustering(instructions, autoSave = false) {
  const progressWrap = document.getElementById('progressWrap');
  const bar = document.getElementById('clusterProgress');
  progressWrap.classList.remove('d-none');
  let pct = 10;
  bar.style.width = pct + '%';
  bar.textContent = pct + '%';
  const timer = setInterval(() => {
    pct = Math.min(pct + 10, 90);
    bar.style.width = pct + '%';
    bar.textContent = pct + '%';
  }, 500);
  fetch('clusters.php?action=run&client_id=<?= $client_id ?>', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'instructions=' + encodeURIComponent(instructions || '')
  })
    .then(r => r.json()).then(data => {
      clearInterval(timer);
      bar.style.width = '100%';
      bar.textContent = '100%';
      if (data.error) {
        document.getElementById('msgArea').innerHTML = '<pre class="text-danger">'+data.error+'</pre>';
        setTimeout(() => progressWrap.classList.add('d-none'), 500);
      } else {
        const processed = processClusters(data.clusters || []);
        loadedKeywords = [];
        processed.clusters.forEach(c => loadedKeywords.push(...c));
        renderClusters(processed.clusters);
        document.getElementById('msgArea').innerHTML = '';
        if (autoSave) {
          saveClusters(processed.clusters, processed.singles);
        } else {
          updateStatusBars(null, processed.singles);
          setTimeout(() => progressWrap.classList.add('d-none'), 500);
        }
      }
    }).catch(() => {
      clearInterval(timer);
      bar.style.width = '100%';
      bar.textContent = '100%';
      document.getElementById('msgArea').innerHTML = '<pre class="text-danger">Request failed</pre>';
      setTimeout(() => progressWrap.classList.add('d-none'), 500);
    });
}

document.getElementById('generateBtn').addEventListener('click', function() {
  runClustering('', true);
});
document.getElementById('addClusterBtn').addEventListener('click', function() {
  currentClusters.push([]);
      renderClusters(currentClusters);
      updateStatusBars(null, processClusters(currentClusters).singles);
});

document.getElementById('saveBtn').addEventListener('click', function() {
  const msgArea = document.getElementById('msgArea');
  document.querySelectorAll('.cluster-edit').forEach((div, idx) => {
    currentClusters[idx] = getLines(div);
  });
  const processed = processClusters(currentClusters);
  currentClusters = processed.clusters;
  const clusters = currentClusters;
  const singles = processed.singles;
  const seen = {};
  const dupes = [];
  clusters.forEach(cluster => {
    cluster.forEach(kw => {
      const key = kw.toLowerCase();
      if (seen[key]) {
        dupes.push(kw);
      } else {
        seen[key] = true;
      }
    });
  });
  if (dupes.length) {
    msgArea.innerHTML = '<pre class="text-danger">Duplicate keywords: ' + Array.from(new Set(dupes)).join(', ') + '</pre>';
    msgArea.scrollIntoView({behavior:'smooth'});
    return;
  }
  saveClusters(clusters, singles);
});

document.getElementById('clearBtn').addEventListener('click', function() {
  if (!confirm('Are you sure you want to remove all clusters?')) return;
  fetch('clusters.php?action=clear&client_id=<?= $client_id ?>', {method:'POST'})
    .then(r => r.json()).then(data => {
      if (data.success) {
        renderClusters([]);
        updateStatusBars(data.unclustered || [], []);
        document.getElementById('msgArea').innerHTML = '<p class="text-success">Clusters removed.</p>';
      }
    });
});

document.getElementById('toggleAddForm').addEventListener('click', function() {
  const form = document.getElementById('addKeywordsForm');
  if (form.style.display === 'none' || form.style.display === '') {
    form.style.display = 'block';
  } else {
    form.style.display = 'none';
  }
});
</script>

<?php include 'footer.php'; ?>
