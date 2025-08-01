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
        "SELECT keyword, cluster_name FROM keywords WHERE client_id = ? AND cluster_name <> '' ORDER BY cluster_name, id"
    );
    $stmt->execute([$client_id]);
    $clusters = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $clusters[$row['cluster_name']][] = $row['keyword'];
    }
    return array_values($clusters);
}

$action = $_GET['action'] ?? '';
if ($action === 'run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT keyword FROM keywords WHERE client_id = ? ORDER BY id");
    $stmt->execute([$client_id]);
    $keywords = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $input = implode("\n", $keywords);

    $descriptorspec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $process = proc_open('python3 clustering/run_cluster.py', $descriptorspec, $pipes, __DIR__);
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
            echo json_encode(['clusters' => json_decode($raw, true) ?: []]);
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
    $pdo->prepare("UPDATE keywords SET cluster_name = '' WHERE client_id = ?")
        ->execute([$client_id]);
    $update = $pdo->prepare(
        "UPDATE keywords SET cluster_name = ? WHERE client_id = ? AND keyword = ?"
    );
    foreach ($clusters as $cluster) {
        if (!$cluster) continue;
        $name = $cluster[0];
        foreach ($cluster as $kw) {
            $update->execute([$name, $client_id, $kw]);
        }
    }
    updateKeywordStats($pdo, $client_id);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE keywords SET cluster_name = '' WHERE client_id = ?")
        ->execute([$client_id]);
    updateKeywordStats($pdo, $client_id);
    echo json_encode(['success' => true]);
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
  <button id="generateBtn" class="btn btn-primary">Generate Clusters</button>
  <button id="saveBtn" class="btn btn-success" disabled>Save Clusters</button>
  <button id="clearBtn" class="btn btn-danger">Clear Clusters</button>
</div>
<div id="progressWrap" class="progress mb-3 d-none">
  <div id="clusterProgress" class="progress-bar" role="progressbar" style="width:0%">0%</div>
</div>
<div id="clustersContainer" class="row"></div>
<div id="msgArea"></div>

<script>
function renderClusters(data) {
  const wrap = document.getElementById('clustersContainer');
  wrap.innerHTML = '';
  data.forEach(cluster => {
    const col = document.createElement('div');
    col.className = 'col-md-6';
    const card = document.createElement('div');
    card.className = 'card mb-3';
    const header = document.createElement('div');
    header.className = 'card-header';
    header.textContent = cluster[0] || 'Unnamed';
    const textarea = document.createElement('textarea');
    textarea.className = 'form-control';
    textarea.rows = 4;
    textarea.value = cluster.join('\n');
    textarea.addEventListener('input', function() {
      const first = this.value.split(/\n+/).map(s => s.trim()).filter(Boolean)[0] || 'Unnamed';
      header.textContent = first;
    });
    card.appendChild(header);
    card.appendChild(textarea);
    col.appendChild(card);
    wrap.appendChild(col);
  });
  document.getElementById('saveBtn').disabled = data.length === 0;
}

const existingClusters = <?= json_encode($clusters) ?>;
if (existingClusters.length) {
  renderClusters(existingClusters);
}

document.getElementById('generateBtn').addEventListener('click', function() {
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
  fetch('clusters.php?action=run&client_id=<?= $client_id ?>', {method:'POST'})
    .then(r => r.json()).then(data => {
      clearInterval(timer);
      bar.style.width = '100%';
      bar.textContent = '100%';
      if (data.error) {
        document.getElementById('msgArea').innerHTML = '<pre class="text-danger">'+data.error+'</pre>';
      } else {
        renderClusters(data.clusters);
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
});

document.getElementById('saveBtn').addEventListener('click', function() {
  const texts = Array.from(document.querySelectorAll('#clustersContainer textarea'));
  const clusters = texts.map(t => t.value.split(/\n+/).map(s => s.trim()).filter(Boolean));
  fetch('clusters.php?action=save&client_id=<?= $client_id ?>', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'clusters=' + encodeURIComponent(JSON.stringify(clusters))
  }).then(r => r.json()).then(data => {
    if (data.success) {
      document.getElementById('msgArea').innerHTML = '<p class="text-success">Clusters saved.</p>';
    } else {
      document.getElementById('msgArea').innerHTML = '<pre class="text-danger">'+data.error+'</pre>';
    }
  });
});

document.getElementById('clearBtn').addEventListener('click', function() {
  if (!confirm('Remove all clusters?')) return;
  fetch('clusters.php?action=clear&client_id=<?= $client_id ?>', {method:'POST'})
    .then(r => r.json()).then(data => {
      if (data.success) {
        renderClusters([]);
        document.getElementById('msgArea').innerHTML = '<p class="text-success">Clusters removed.</p>';
      }
    });
});
</script>

<?php include 'footer.php'; ?>
