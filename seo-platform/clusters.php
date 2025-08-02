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

function updateKeywordStats(PDO $pdo, int $client_id): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS keyword_stats (
        client_id INT PRIMARY KEY,
        total INT DEFAULT 0,
        grouped INT DEFAULT 0,
        clustered INT DEFAULT 0,
        structured INT DEFAULT 0
    )");
    $stmt = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN COALESCE(cluster_name,'') <> '' THEN 1 ELSE 0 END) AS clustered
        FROM keywords WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'clustered'=>0];
    $up = $pdo->prepare("REPLACE INTO keyword_stats (client_id,total,grouped,clustered,structured) VALUES (?,?,?,?,?)");
    $up->execute([
        $client_id,
        (int)$stats['total'],
        0,
        (int)$stats['clustered'],
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

function loadUnclustered(PDO $pdo, int $client_id): array {
    $stmt = $pdo->prepare("SELECT keyword FROM keywords WHERE client_id = ? AND (cluster_name = '' OR cluster_name IS NULL) ORDER BY id");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$action = $_GET['action'] ?? '';
if ($action === 'list') {
    $q = $_GET['q'] ?? '';
    $terms = array_values(array_filter(array_map('trim', explode('|', $q)), 'strlen'));
    $clusters = loadClusters($pdo, $client_id);
    $singlesOnly = isset($_GET['single']) && $_GET['single'] === '1';
    if ($singlesOnly) {
        $clusters = array_values(array_filter($clusters, function($c) { return count($c) === 1; }));
    }
    if ($terms) {
        $clusters = array_values(array_filter($clusters, function($cluster) use ($terms) {
            foreach ($cluster as $kw) {
                foreach ($terms as $t) {
                    if (stripos($kw, $t) !== false) return true;
                }
            }
            return false;
        }));
    }
    echo json_encode([
        'clusters' => $clusters,
        'unclustered' => loadUnclustered($pdo, $client_id),
        'allKeywords' => loadAllKeywords($pdo, $client_id)
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
    $env = ['INSTRUCTIONS' => $instructions, 'EXISTING' => json_encode($existing)];
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
<div class="mb-3">
  <button class="btn btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#instructionsBox" aria-expanded="false" aria-controls="instructionsBox">Cluster Instructions</button>
  <div class="collapse" id="instructionsBox">
    <textarea id="clusterInstructions" class="form-control" rows="6">If you are using one of the keywords in the cluster don’t repeat it on another cluster
Try to split as possible the same meaning groups, and cluster only the same meaning, if not keep it in a standalone cluster
Don’t ever make a one cluster for all keywords. I need them split for the same meaning or they can be merged in one cluster to be used in a one page for SEO content creation. Please minimum 2 keywords per cluster, don't make a cluster for 1 keyword
Group commercial keywords (like company, agency, services) **together only when they are of the same intent**.</textarea>
    <button id="updateBtn" class="btn btn-secondary btn-sm mt-2">Update Clusters</button>
  </div>
</div>

<div class="mb-3 d-flex justify-content-between">
  <div>
    <button id="addClusterBtn" class="btn btn-secondary btn-sm me-2">+ Add Cluster</button>
    <button id="generateBtn" class="btn btn-primary btn-sm">Generate Clusters</button>
    <button id="saveBtn" class="btn btn-success btn-sm" disabled>Save Clusters</button>
    <button id="clearBtn" class="btn btn-danger btn-sm">Clear Clusters</button>
  </div>
  <form id="clusterFilterForm" method="GET" class="d-flex">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="slug" value="<?= $slug ?>">
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

<script src="https://cdn.jsdelivr.net/npm/masonry-layout@4.2.2/dist/masonry.pkgd.min.js" integrity="sha384-GNFwBvfVxBkLMJpYMOABq3c+d3KnQxudP/mGPkzpZSTYykLBNsZEnG2D9G/X/+7D" crossorigin="anonymous" async onload="applyMasonry()"></script>
<script>
let currentClusters = [];
let orderMap = {};
let loadedKeywords = [];
const singleFilterActive = <?= isset($_GET['single']) ? 'true' : 'false' ?>;
const filterTerms = (document.getElementById('clusterFilter').value || '')
  .toLowerCase()
  .split('|')
  .map(s => s.trim())
  .filter(Boolean);

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
    .map(k => `<button type="button" class="btn btn-link btn-sm kw-copy" data-kw="${escapeHtml(k)}">${escapeHtml(k)}</button>`)
    .join('');
}

function getUnclusteredKeywords() {
  const clustered = new Set();
  currentClusters.forEach(c => c.forEach(k => clustered.add(k.toLowerCase())));
  return loadedKeywords.filter(k => !clustered.has(k.toLowerCase()));
}

function updateStatusBars(unclustered, singles=[]) {
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

  if (unclustered.length) {
    const bar = document.createElement('div');
    bar.className = 'alert alert-warning d-flex align-items-center flex-wrap';
    const span = document.createElement('span');
    span.innerHTML = `Unclustered keywords (${unclustered.length}): ${renderKeywordButtons(unclustered)}`;
    bar.appendChild(span);
    const fixBtn = document.createElement('button');
    fixBtn.id = 'fixUnclusteredBtn';
    fixBtn.className = 'btn btn-sm btn-primary ms-auto';
    fixBtn.textContent = 'Fix now';
    const rmBtn = document.createElement('button');
    rmBtn.id = 'removeUnclusteredBtn';
    rmBtn.className = 'btn btn-sm btn-warning ms-2';
    rmBtn.textContent = 'Remove now';
    bar.appendChild(fixBtn);
    bar.appendChild(rmBtn);
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

  if (!unclustered.length && !singles.length && !dupes.length) {
    const bar = document.createElement('div');
    bar.className = 'alert alert-success';
    bar.textContent = 'All keywords are clustered.';
    area.appendChild(bar);
  }

  area.querySelectorAll('.kw-copy').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
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
          updateStatusBars(data.unclustered || [], singles);
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
  const children = Array.from(textDiv.children).filter(el => el.tagName === 'DIV');
  if (children.length > 1) {
    return children.map(d => d.textContent.trim()).filter(Boolean);
  }
  return textDiv.innerText.split(/\n+/).map(s => s.trim()).filter(Boolean);
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
    titleSpan.textContent = cluster[0] || 'Unnamed';
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-sm btn-danger ms-auto';
    removeBtn.textContent = '-';
    removeBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const wrap = document.getElementById('clustersContainer');
      const top = window.scrollY;
      wrap.style.minHeight = wrap.offsetHeight + 'px';
      currentClusters.splice(idx, 1);
      const processed = processClusters(currentClusters);
      renderClusters(processed.clusters);
      updateStatusBars(getUnclusteredKeywords(), processed.singles);
      window.scrollTo(0, top);
      wrap.style.minHeight = '';
    });
    header.appendChild(countSpan);
    header.appendChild(titleSpan);
    header.appendChild(removeBtn);
    const textDiv = document.createElement('div');
    textDiv.className = 'form-control cluster-edit';
    textDiv.contentEditable = 'true';
    textDiv.innerHTML = cluster.map(k => {
      const match = filterTerms.some(t => k.toLowerCase().includes(t));
      return `<div${match ? ' class="bg-warning-subtle"' : ''}>${escapeHtml(k)}</div>`;
    }).join('');
    textDiv.addEventListener('input', function() {
      const lines = getLines(this);
      currentClusters[idx] = lines;
      countSpan.textContent = lines.length;
      titleSpan.textContent = lines[0] || 'Unnamed';
      if (lines.length === 1) card.classList.add('border', 'border-danger');
      else card.classList.remove('border', 'border-danger');
      if (window.msnry) {
        window.msnry.reloadItems();
        window.msnry.layout();
      }
      const processed = processClusters(currentClusters);
      updateStatusBars(getUnclusteredKeywords(), processed.singles);
    });
    card.appendChild(header);
    card.appendChild(textDiv);
    col.appendChild(card);
    wrap.appendChild(col);
  });
  document.getElementById('saveBtn').disabled = currentClusters.length === 0;
  applyMasonry();
}

document.addEventListener('DOMContentLoaded', function() {
  const msgArea = document.getElementById('msgArea');
  msgArea.innerHTML = '<p>Loading clusters…</p>';
  fetch('clusters.php?action=list&client_id=<?= $client_id ?>&q=<?= urlencode($q) ?><?= isset($_GET['single']) ? "&single=1" : '' ?>')
    .then(r => r.json()).then(data => {
      loadedKeywords = [];
      (data.clusters || []).forEach(c => loadedKeywords.push(...c));
      setOrder(data.allKeywords || []);
      const processed = processClusters(data.clusters || []);
      renderClusters(processed.clusters);
      updateStatusBars(data.unclustered || [], processed.singles);
      msgArea.innerHTML = '';
    }).catch(() => {
      msgArea.innerHTML = '<pre class="text-danger">Failed to load clusters.</pre>';
    });
});

function refreshKeywordData() {
  fetch('clusters.php?action=list&client_id=<?= $client_id ?>')
    .then(r => r.json()).then(data => {
      setOrder(data.allKeywords || []);
      const processed = processClusters(currentClusters);
      updateStatusBars(data.unclustered || [], processed.singles);
    });
}

window.addEventListener('focus', refreshKeywordData);

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
          updateStatusBars(data.unclustered || [], processed.singles);
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
document.getElementById('updateBtn').addEventListener('click', function() {
  runClustering(document.getElementById('clusterInstructions').value, true);
});
document.getElementById('addClusterBtn').addEventListener('click', function() {
  currentClusters.push([]);
  renderClusters(currentClusters);
  updateStatusBars(getUnclusteredKeywords(), processClusters(currentClusters).singles);
});

document.getElementById('saveBtn').addEventListener('click', function() {
  const msgArea = document.getElementById('msgArea');
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
</script>

<?php include 'footer.php'; ?>
