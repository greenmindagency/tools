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
        SUM(CASE WHEN COALESCE(group_name,'') <> '' THEN 1 ELSE 0 END) AS grouped,
        SUM(CASE WHEN COALESCE(cluster_name,'') <> '' THEN 1 ELSE 0 END) AS clustered,
        SUM(CASE WHEN COALESCE(group_name,'') <> '' AND group_count <= 5 THEN 1 ELSE 0 END) AS structured
        FROM keywords WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'grouped'=>0,'clustered'=>0,'structured'=>0];
    $up = $pdo->prepare("REPLACE INTO keyword_stats (client_id,total,grouped,clustered,structured) VALUES (?,?,?,?,?)");
    $up->execute([
        $client_id,
        (int)$stats['total'],
        (int)$stats['grouped'],
        (int)$stats['clustered'],
        (int)$stats['structured']
    ]);
}

function loadClusters(PDO $pdo, int $client_id): array {
    $stmt = $pdo->prepare(
        "SELECT keyword, cluster_name FROM keywords WHERE client_id = ? AND cluster_name <> '' ORDER BY id"
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
    $stmt = $pdo->prepare("SELECT keyword FROM keywords WHERE client_id = ? AND cluster_name = '' ORDER BY id");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$action = $_GET['action'] ?? '';
if ($action === 'list') {
    echo json_encode([
        'clusters' => loadClusters($pdo, $client_id),
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
    $pdo->prepare("UPDATE keywords SET cluster_name = '' WHERE client_id = ?")
        ->execute([$client_id]);
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

$clusters = loadClusters($pdo, $client_id);

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
    <button id="updateBtn" class="btn btn-secondary mt-2">Update Clusters</button>
  </div>
</div>

<div class="mb-3">
  <button id="addClusterBtn" class="btn btn-secondary me-2">+ Add Cluster</button>
  <button id="generateBtn" class="btn btn-primary">Generate Clusters</button>
  <button id="saveBtn" class="btn btn-success" disabled>Save Clusters</button>
  <button id="clearBtn" class="btn btn-danger">Clear Clusters</button>
</div>
<div id="progressWrap" class="progress mb-3 d-none">
  <div id="clusterProgress" class="progress-bar" role="progressbar" style="width:0%">0%</div>
</div>
<div id="statusBar" class="alert alert-info"></div>
<div id="msgArea"></div>
<div id="clustersContainer" class="row" data-masonry='{"percentPosition": true }'></div>

<script src="https://cdn.jsdelivr.net/npm/masonry-layout@4.2.2/dist/masonry.pkgd.min.js" integrity="sha384-GNFwBvfVxBkLMJpYMOABq3c+d3KnQxudP/mGPkzpZSTYykLBNsZEnG2D9G/X/+7D" crossorigin="anonymous" async onload="applyMasonry()"></script>
<script>
let currentClusters = [];
let orderMap = {};

function setOrder(list) {
  orderMap = {};
  list.forEach((kw, idx) => orderMap[kw.toLowerCase()] = idx);
}

function sortCluster(cluster) {
  return cluster.slice().sort((a,b) => (orderMap[a.toLowerCase()] ?? 0) - (orderMap[b.toLowerCase()] ?? 0));
}

function processClusters(list) {
  let cleaned = list.map(c => c.filter(Boolean)).filter(c => c.length);
  let singles = [];
  let multi = [];
  cleaned.forEach(c => {
    if (c.length === 1) singles.push(c[0]);
    else multi.push(c);
  });
  let leftover = [];
  singles.forEach(kw => {
    const words = kw.toLowerCase().split(/\s+/);
    let merged = false;
    for (let cluster of multi) {
      if (cluster.some(other => words.some(w => other.toLowerCase().includes(w)))) {
        cluster.push(kw);
        merged = true;
        break;
      }
    }
    if (!merged) {
      multi.push([kw]);
      leftover.push(kw);
    }
  });
  multi = multi.map(sortCluster);
  return {clusters: multi, singles: leftover};
}

function updateStatusBar(unclustered, singles=[]) {
  const bar = document.getElementById('statusBar');
  const messages = [];
  if (unclustered.length) messages.push('Unclustered keywords: ' + unclustered.join(', '));
  if (singles.length) messages.push('Single keyword clusters: ' + singles.join(', '));
  if (messages.length) {
    bar.className = 'alert alert-warning';
    bar.textContent = messages.join(' | ');
  } else {
    bar.className = 'alert alert-success';
    bar.textContent = 'All keywords are clustered.';
  }
}

function applyMasonry() {
  const wrap = document.getElementById('clustersContainer');
  if (!window.Masonry) return;
  if (window.msnry) window.msnry.destroy();
  window.msnry = new Masonry(wrap, {itemSelector: '.col-md-4', percentPosition: true});
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
    const header = document.createElement('div');
    header.className = 'card-header d-flex align-items-center';
    const countSpan = document.createElement('span');
    countSpan.className = 'badge bg-secondary me-2';
    countSpan.textContent = cluster.length;
    const titleSpan = document.createElement('span');
    titleSpan.textContent = cluster[0] || 'Unnamed';
    const removeBtn = document.createElement('button');
    removeBtn.className = 'btn btn-sm btn-danger ms-auto';
    removeBtn.textContent = '-';
    removeBtn.addEventListener('click', function() {
      currentClusters.splice(idx, 1);
      const processed = processClusters(currentClusters);
      renderClusters(processed.clusters);
      updateStatusBar([], processed.singles);
    });
    header.appendChild(countSpan);
    header.appendChild(titleSpan);
    header.appendChild(removeBtn);
    const textDiv = document.createElement('div');
    textDiv.className = 'form-control cluster-edit';
    textDiv.contentEditable = 'true';
    textDiv.style.whiteSpace = 'pre';
    textDiv.textContent = cluster.join('\n');
    textDiv.addEventListener('input', function() {
      const lines = this.innerText.split(/\n+/).map(s => s.trim()).filter(Boolean);
      countSpan.textContent = lines.length;
      titleSpan.textContent = lines[0] || 'Unnamed';
      if (window.msnry) window.msnry.layout();
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
  fetch('clusters.php?action=list&client_id=<?= $client_id ?>')
    .then(r => r.json()).then(data => {
      setOrder(data.allKeywords || []);
      const processed = processClusters(data.clusters || []);
      renderClusters(processed.clusters);
      updateStatusBar(data.unclustered || [], processed.singles);
      msgArea.innerHTML = '';
    }).catch(() => {
      msgArea.innerHTML = '<pre class="text-danger">Failed to load clusters.</pre>';
    });
});

function runClustering(instructions) {
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
      } else {
        const processed = processClusters(data.clusters || []);
        renderClusters(processed.clusters);
        updateStatusBar(data.unclustered || [], processed.singles);
        document.getElementById('msgArea').innerHTML = '';
      }
      setTimeout(() => progressWrap.classList.add('d-none'), 500);
    }).catch(() => {
      clearInterval(timer);
      bar.style.width = '100%';
      bar.textContent = '100%';
      document.getElementById('msgArea').innerHTML = '<pre class="text-danger">Request failed</pre>';
      setTimeout(() => progressWrap.classList.add('d-none'), 500);
    });
}

document.getElementById('generateBtn').addEventListener('click', function() {
  runClustering('');
});
document.getElementById('updateBtn').addEventListener('click', function() {
  runClustering(document.getElementById('clusterInstructions').value);
});
document.getElementById('addClusterBtn').addEventListener('click', function() {
  currentClusters.push([]);
  renderClusters(currentClusters);
});

document.getElementById('saveBtn').addEventListener('click', function() {
  const msgArea = document.getElementById('msgArea');
  const texts = Array.from(document.querySelectorAll('#clustersContainer .cluster-edit'));
  let clusters = texts.map(t => t.innerText.split(/\n+/).map(s => s.trim()));
  const processed = processClusters(clusters);
  clusters = processed.clusters;
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
  fetch('clusters.php?action=save&client_id=<?= $client_id ?>', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'clusters=' + encodeURIComponent(JSON.stringify(clusters))
  }).then(r => r.json()).then(data => {
    clearInterval(timer);
    bar.style.width = '100%';
    bar.textContent = '100%';
    if (data.success) {
      msgArea.innerHTML = '<p class="text-success">Clusters saved.</p>';
    } else {
      msgArea.innerHTML = '<pre class="text-danger">' + data.error + '</pre>';
    }
    updateStatusBar(data.unclustered || [], singles);
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
});

document.getElementById('clearBtn').addEventListener('click', function() {
  if (!confirm('Remove all clusters?')) return;
  fetch('clusters.php?action=clear&client_id=<?= $client_id ?>', {method:'POST'})
    .then(r => r.json()).then(data => {
      if (data.success) {
        renderClusters([]);
        updateStatusBar(data.unclustered || [], []);
        document.getElementById('msgArea').innerHTML = '<p class="text-success">Clusters removed.</p>';
      }
    });
});
</script>

<?php include 'footer.php'; ?>