<?php
require_once __DIR__ . '/session.php';
require 'config.php';
$pdo->exec("ALTER TABLE keywords ADD COLUMN IF NOT EXISTS priority VARCHAR(10) DEFAULT ''");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$client_id = $_GET['client_id'] ?? 0;
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
 
// Load client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) die("Client not found");

$slug = $slugify($client['name']);
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "dashboard.php?client_id=$client_id&slug=$slug",
];

$title = $client['name'] . ' Dashboard';
$restored = false;
$pageTypes = ['', 'Home', 'Service', 'Blog', 'Page', 'Article', 'Product', 'Other'];
$priorityOptions = ['', 'Low', 'Mid', 'High'];
updateKeywordStats($pdo, $client_id);

$backupDir = __DIR__ . '/backups/client_' . $client_id;
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.xlsx');
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach ($files as $f) {
        $backups[] = basename($f);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $sel = basename($_POST['backup_file'] ?? '');
    $path = $backupDir . '/' . $sel;
    if (is_file($path)) {
        $pdo->prepare("DELETE FROM keywords WHERE client_id = ?")->execute([$client_id]);
        $pdo->prepare("DELETE FROM keyword_positions WHERE client_id = ?")->execute([$client_id]);
        require_once __DIR__ . '/lib/SimpleXLSX.php';
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($path)) {
            $rows = $xlsx->rows(0);
            $ins = $pdo->prepare("INSERT INTO keywords (client_id, keyword, volume, form, content_link, page_type, priority, group_name, group_count, cluster_name) VALUES (?, ?, ?, ?, ?, ?, ?, '', 0, ?)");
            for ($i = 1; $i < count($rows); $i++) {
                $data = $rows[$i];
                $keyword = $data[0] ?? '';
                $volume  = $data[1] ?? '';
                $form    = $data[2] ?? '';
                $link    = $data[3] ?? '';
                $type    = $data[4] ?? '';
                if (count($data) >= 7) {
                    $priority = $data[5] ?? '';
                    $cluster  = $data[6] ?? '';
                } else {
                    $priority = '';
                    $cluster  = $data[5] ?? '';
                }
                $ins->execute([
                    $client_id,
                    $keyword,
                    $volume,
                    $form,
                    $link,
                    $type,
                    $priority,
                    $cluster
                ]);
            }

            if ($xlsx->sheetsCount() > 1) {
                $pRows = $xlsx->rows(1);
                $pIns = $pdo->prepare("INSERT INTO keyword_positions (client_id, keyword, sort_order, m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12,m13) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                for ($i = 1; $i < count($pRows); $i++) {
                    $r = $pRows[$i];
                    $kw = $r[0] ?? '';
                    $sort = $r[1] !== '' ? (int)$r[1] : null;
                    $vals = [];
                    for ($j = 2; $j <= 13; $j++) {
                        $vals[] = ($r[$j] === '' || $r[$j] === null) ? null : (float)$r[$j];
                    }
                    $pIns->execute(array_merge([$client_id, $kw, $sort], $vals));
                }
            }
        }
        updateKeywordStats($pdo, $client_id);
        $restored = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    createXlsxBackup($pdo, $client_id, $backupDir, $client['name']);
    $files = glob($backupDir . '/*.xlsx');
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $backups = array_map('basename', $files);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS keyword_stats (
    client_id INT PRIMARY KEY,
    total INT DEFAULT 0,
    grouped INT DEFAULT 0,
    clustered INT DEFAULT 0,
    structured INT DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$statsStmt = $pdo->prepare("SELECT total, clustered AS clusters FROM keyword_stats WHERE client_id = ?");
$statsStmt->execute([$client_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'clusters'=>0];

$pdo->exec("CREATE TABLE IF NOT EXISTS sc_domains (
    client_id INT PRIMARY KEY,
    domain VARCHAR(255)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$scDomainStmt = $pdo->prepare("SELECT domain FROM sc_domains WHERE client_id = ?");
$scDomainStmt->execute([$client_id]);
$scDomain = $scDomainStmt->fetchColumn() ?: '';

include 'header.php';
?>
<style>
  .highlight-cell { background-color: #e9e9e9 !important; }
  .existing-kw { background-color: #fff3cd; }
</style>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" href="dashboard.php?client_id=<?php echo $client_id; ?>&slug=<?php echo $slug; ?>">Keywords</a></li>
  <li class="nav-item"><a class="nav-link" href="positions.php?client_id=<?php echo $client_id; ?>&slug=<?php echo $slug; ?>">Keyword Position</a></li>
  <li class="nav-item"><a class="nav-link" href="clusters.php?client_id=<?php echo $client_id; ?>&slug=<?php echo $slug; ?>">Clusters</a></li>
</ul>

<input type="hidden" id="scDomain" value="<?= htmlspecialchars($scDomain) ?>">

<div class="mb-3 d-flex justify-content-between align-items-center">
  <div>
    <span class="me-3">All keywords: <?= (int)$stats['total'] ?></span>
    <span class="me-3">Clusters: <?= (int)$stats['clusters'] ?></span>
  </div>
  <div class="d-flex flex-column align-items-end">
    <div class="mb-2">
      <button type="button" id="toggleImportForm" class="btn btn-primary btn-sm me-2">Import Plan</button>
      <a href="export.php?client_id=<?= $client_id ?>" class="btn btn-outline-primary btn-sm">Export XLSX</a>
    </div>
    <form method="POST" class="d-flex">
      <select name="backup_file" class="form-select form-select-sm me-2" style="width:auto;">
        <?php foreach ($backups as $b): ?>
          <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" name="restore_backup" class="btn btn-sm btn-secondary me-2">Restore</button>
      <button type="submit" name="create_backup" class="btn btn-sm btn-primary">Backup Now</button>
    </form>
  </div>
</div>
<?php if (!empty($restored)): ?>
<p class="text-success">Backup restored.</p>
<?php endif; ?>

<!-- Add Keyword Form -->
<form method="POST" id="addKeywordsForm" class="mb-4" style="display:none;">
    <textarea name="keywords" class="form-control" placeholder="Paste keywords with optional volume and form" rows="6"></textarea>
    <button type="submit" name="add_keywords" class="btn btn-primary btn-sm mt-2">Add Keywords</button>
</form>

<!-- Import Plan Form -->
<form method="POST" id="importForm" enctype="multipart/form-data" class="mb-4" style="display:none;">
    <input type="file" name="csv_file" accept=".xlsx" class="form-control">
    <button type="submit" name="import_plan" class="btn btn-primary btn-sm mt-2">Import Plan</button>
</form>

<!-- Add Cluster Form -->
<form method="POST" id="addClustersForm" class="mb-4" style="display:none;">
    <textarea name="clusters" class="form-control" placeholder="keyword1|keyword2 per line" rows="4"></textarea>
    <button type="submit" name="add_clusters" class="btn btn-primary btn-sm mt-2">Add Clusters</button>
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
        "INSERT INTO keywords (client_id, keyword, volume, form)
         VALUES (?, ?, ?, ?)"
    );
    $update = $pdo->prepare(
        "UPDATE keywords SET volume = ?, form = ?
         WHERE client_id = ? AND keyword = ?"
    );
    $entries = [];

    if (!empty($lines) && preg_match('/\d+$/', $lines[0])) {
        // Format: keyword volume form per line
        foreach ($lines as $line) {
            if (preg_match('/^(.*?)\s+(\S+)\s+(\S+)$/', $line, $m)) {
                $entries[] = [$m[1], $convertVolume($m[2]), $m[3]];
            } else {
                $entries[] = [$line, '', ''];
            }
        }
    } else {
        // Format: keyword line, volume line, form line
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
        list($k, $v, $f) = $e;
        $update->execute([$v, $f, $client_id, $k]);
        if ($update->rowCount() === 0) {
            $insert->execute([$client_id, $k, $v, $f]);
        }
    }
    echo "<p>Keywords added.</p>";
}

// Remove duplicates for this client
$pdo->query("DELETE k1 FROM keywords k1
JOIN keywords k2 ON k1.keyword = k2.keyword AND k1.id > k2.id
WHERE k1.client_id = $client_id AND k2.client_id = $client_id");
updateKeywordStats($pdo, $client_id);

if (isset($_POST['add_clusters'])) {
    $text = trim($_POST['clusters']);
    $lines = preg_split('/\r\n|\n|\r/', $text);
    $update = $pdo->prepare("UPDATE keywords SET cluster_name = ? WHERE client_id = ? AND keyword = ?");
    foreach ($lines as $line) {
        $parts = array_values(array_filter(array_map('trim', explode('|', $line)), 'strlen'));
        if (!$parts) continue;
        $cluster = $parts[0];
        foreach ($parts as $kw) {
            $update->execute([$cluster, $client_id, $kw]);
        }
    }
    echo "<p>Clusters updated.</p>";
    updateKeywordStats($pdo, $client_id);
}

if (isset($_POST['import_plan'])) {
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $pdo->prepare("DELETE FROM keywords WHERE client_id = ?")->execute([$client_id]);
        $pdo->prepare("DELETE FROM keyword_positions WHERE client_id = ?")->execute([$client_id]);
        $insert = $pdo->prepare(
            "INSERT INTO keywords (client_id, keyword, volume, form, content_link, page_type, priority, group_name, group_count, cluster_name) VALUES (?, ?, ?, ?, ?, ?, ?, '', 0, ?)"
        );
        require_once __DIR__ . '/lib/SimpleXLSX.php';
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($_FILES['csv_file']['tmp_name'])) {
            $rows = $xlsx->rows(0);
        } else {
            $rows = [];
        }
        foreach ($rows as $data) {
            if (!isset($data[0])) continue;
            if (stripos($data[0], 'keyword') === 0) continue; // skip header
            $keyword = $data[0];
            $vol = $data[1] ?? '';
            $form = $data[2] ?? '';
            $link = $data[3] ?? '';
            $type = trim($data[4] ?? '');
            $priority = '';
            if (count($data) >= 7) {
                $priority = trim($data[5] ?? '');
                $cluster = trim($data[6] ?? '');
            } else {
                $cluster = trim($data[5] ?? '');
            }

            if ($type !== '') {
                foreach ($pageTypes as $pt) {
                    if (strcasecmp($pt, $type) === 0) { $type = $pt; break; }
                }
            }
            $insert->execute([$client_id, $keyword, $vol, $form, $link, $type, $priority, $cluster]);
        }

        if ($xlsx && $xlsx->sheetsCount() > 1) {
            $pRows = $xlsx->rows(1);
            $pIns = $pdo->prepare("INSERT INTO keyword_positions (client_id, keyword, sort_order, m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12,m13) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            for ($i = 1; $i < count($pRows); $i++) {
                $r = $pRows[$i];
                $kw = $r[0] ?? '';
                $sort = $r[1] !== '' ? (int)$r[1] : null;
                $vals = [];
                for ($j = 2; $j <= 13; $j++) {
                    $vals[] = ($r[$j] === '' || $r[$j] === null) ? null : (float)$r[$j];
                }
                $pIns->execute(array_merge([$client_id, $kw, $sort], $vals));
            }
        }

        echo "<p>Plan imported.</p>";
        updateKeywordStats($pdo, $client_id);
    }
}
if (isset($_POST['update_keywords'])) {
    $deleteIds = array_keys(array_filter($_POST['delete'] ?? [], fn($v) => $v === '1'));
    if ($deleteIds) {
        $in  = implode(',', array_fill(0, count($deleteIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM keywords WHERE client_id = ? AND id IN ($in)");
        $stmt->execute(array_merge([$client_id], $deleteIds));
    }

    if (!empty($_POST['link'])) {
        $update = $pdo->prepare("UPDATE keywords SET content_link = ? WHERE id = ? AND client_id = ?");
        foreach ($_POST['link'] as $id => $link) {
            $update->execute([$link, $id, $client_id]);
        }
    }

    if (!empty($_POST['page_type'])) {
        $update = $pdo->prepare("UPDATE keywords SET page_type = ? WHERE id = ? AND client_id = ?");
        foreach ($_POST['page_type'] as $id => $type) {
            $t = trim($type);
            if ($t !== '') {
                foreach ($pageTypes as $pt) {
                    if (strcasecmp($pt, $t) === 0) { $t = $pt; break; }
                }
            }
            $update->execute([$t, $id, $client_id]);
        }
    }

    if (!empty($_POST['priority'])) {
        $update = $pdo->prepare("UPDATE keywords SET priority = ? WHERE id = ? AND client_id = ?");
        foreach ($_POST['priority'] as $id => $prio) {
            $p = trim($prio);
            if ($p !== '') {
                foreach ($priorityOptions as $opt) {
                    if (strcasecmp($opt, $p) === 0) { $p = $opt; break; }
                }
            }
            $update->execute([$p, $id, $client_id]);
        }
    }


    updateKeywordStats($pdo, $client_id);

    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        $params = [
            'client_id' => $client_id,
            'slug' => $slug,
            'page' => $_GET['page'] ?? null,
            'field' => $_GET['field'] ?? null,
            'q' => $_GET['q'] ?? null,
            'mode' => $_GET['mode'] ?? null,
        ];
        $qs = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
        header('Location: dashboard.php' . ($qs ? "?$qs" : ''));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}

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

function createXlsxBackup(PDO $pdo, int $client_id, string $dir, string $client_name): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    date_default_timezone_set('Africa/Cairo');
    $ts = date('d-m-Y_H-i');
    $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $client_name)));
    $file = "$dir/{$slug}_$ts.xlsx";
    $kwRows = [];
    $kwRows[] = ['Keyword','Volume','Form','Link','Type','Priority','Cluster'];
    $stmt = $pdo->prepare("SELECT keyword, volume, form, content_link, page_type, priority, cluster_name FROM keywords WHERE client_id = ? ORDER BY id");
    $stmt->execute([$client_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $kwRows[] = [
            $row['keyword'],
            $row['volume'],
            $row['form'],
            $row['content_link'],
            $row['page_type'],
            $row['priority'],
            $row['cluster_name']
        ];
    }
    $posRows = [];
    $header = ['Keyword','Sort'];
    for ($i = 1; $i <= 12; $i++) {
        $header[] = 'M'.$i;
    }
    $posRows[] = $header;
    $pstmt = $pdo->prepare("SELECT keyword, sort_order, m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12,m13 FROM keyword_positions WHERE client_id = ? ORDER BY sort_order IS NULL, sort_order, id DESC");
    $pstmt->execute([$client_id]);
    while ($row = $pstmt->fetch(PDO::FETCH_ASSOC)) {
        $line = [
            $row['keyword'],
            $row['sort_order']
        ];
        for ($i = 2; $i <= 13; $i++) {
            $line[] = $row['m'.$i];
        }
        $posRows[] = $line;
    }

    require_once __DIR__ . '/lib/SimpleXLSXGen.php';
    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($kwRows, 'Keywords');
    $xlsx->addSheet($posRows, 'Positions');
    $xlsx->saveAs($file);

    $files = glob($dir . '/*.xlsx');
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    if (count($files) > 7) {
        foreach (array_slice($files, 7) as $old) {
            unlink($old);
        }
    }
}

?>

<?php
$perPage = 100;
$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');
$field = $_GET['field'] ?? 'keyword';
$mode = $_GET['mode'] ?? 'keyword';
if (!in_array($mode, ['keyword', 'exact'], true)) {
    $mode = 'keyword';
}
$allowedFields = ['keyword', 'cluster_name', 'content_link', 'keyword_empty_cluster'];
if (!in_array($field, $allowedFields, true)) {
    $field = 'keyword';
}

$baseQuery = "FROM keywords WHERE client_id = ?";
$params = [$client_id];
$terms = array_values(array_filter(array_map('trim', explode('|', $q)), 'strlen'));
if ($field === 'keyword_empty_cluster') {
    if ($terms) {
        $likeParts = [];
        foreach ($terms as $t) {
            $likeParts[] = "keyword LIKE ?";
            $params[] = "%$t%";
        }
        $baseQuery .= " AND (" . implode(' OR ', $likeParts) . ")";
    }
    $baseQuery .= " AND (cluster_name = '' OR cluster_name IS NULL)";
} elseif ($terms) {
    $column = $field;
    if ($field === 'cluster_name') {
        $placeholders = implode(',', array_fill(0, count($terms), '?'));
        $baseQuery .= " AND {$column} IN ($placeholders)";
        array_push($params, ...$terms);
    } elseif ($field === 'keyword' && $mode === 'exact') {
        $placeholders = implode(',', array_fill(0, count($terms), '?'));
        $baseQuery .= " AND keyword IN ($placeholders)";
        array_push($params, ...$terms);
    } else {
        $likeParts = [];
        foreach ($terms as $t) {
            $likeParts[] = "{$column} LIKE ?";
            $params[] = "%$t%";
        }
        $baseQuery .= " AND (" . implode(' OR ', $likeParts) . ")";
    }
}
$countStmt = $pdo->prepare("SELECT COUNT(*) $baseQuery");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = $q === '' ? (int)ceil($totalRows / $perPage) : 1;
$offset = ($page - 1) * $perPage;
$limit = $q === '' ? " LIMIT $perPage OFFSET $offset" : '';
$query = "SELECT * $baseQuery ORDER BY volume DESC, form ASC$limit";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$posMap = [];
if ($rows) {
    $placeholders = implode(',', array_fill(0, count($rows), '?'));
    $posStmt = $pdo->prepare("SELECT keyword, m2 FROM keyword_positions WHERE client_id = ? AND country = '' AND keyword IN ($placeholders)");
    $kwParams = array_merge([$client_id], array_column($rows, 'keyword'));
    $posStmt->execute($kwParams);
    while ($r = $posStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($r['m2'] !== null && $r['m2'] !== '') {
            $posMap[strtolower($r['keyword'])] = $r['m2'];
        }
    }
}

$kwStmt = $pdo->prepare("SELECT LOWER(keyword) FROM keywords WHERE client_id = ?");
$kwStmt->execute([$client_id]);
$existingKeywords = $kwStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex justify-content-between mb-2 sticky-controls">
  <div class="d-flex">
    <button type="submit" form="updateForm" name="update_keywords" class="btn btn-success btn-sm me-2">Update</button>
    <button type="button" id="toggleAddForm" class="btn btn-warning btn-sm me-2">Update Keywords</button>
    <button type="button" id="toggleClusterForm" class="btn btn-info btn-sm me-2">Update Clusters</button>
    <button type="button" id="openImportKw" class="btn btn-secondary btn-sm me-2">Import Keywords</button>
    <button type="button" id="copyLinks" class="btn btn-dark btn-sm me-2">Copy Links</button>
  </div>
  <form id="filterForm" method="GET" class="d-flex">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="slug" value="<?= $slug ?>">
    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
    <select name="field" id="filterField" class="form-select form-select-sm me-2" style="width:auto;">
      <option value="keyword"<?= $field==='keyword' ? ' selected' : '' ?>>Keyword</option>
      <option value="cluster_name"<?= $field==='cluster_name' ? ' selected' : '' ?>>Cluster</option>
      <option value="content_link"<?= $field==='content_link' ? ' selected' : '' ?>>Link</option>
      <option value="keyword_empty_cluster"<?= $field==='keyword_empty_cluster' ? ' selected' : '' ?>>Keyword/Empty Cluster</option>
    </select>
    <input type="text" name="q" id="filterInput" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm w-auto" placeholder="Filter..." style="max-width:200px;">
    <button type="submit" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-search"></i></button>

    <a href="dashboard.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>" class="btn btn-outline-secondary btn-sm ms-1 d-flex align-items-center" title="Clear filter"><i class="bi bi-x-lg"></i></a>
  </form>
</div>

<form method="POST" id="updateForm">
  <input type="hidden" name="client_id" value="<?= $client_id ?>">

  <table class="table table-bordered table-sm">
    <thead class="table-light">
    <tr>
        <th style="width:1px;"><button type="button" id="removeAllKeywords" class="btn btn-sm btn-outline-danger" title="Remove all on page">-</button></th>
        <th>Keyword</th>
        <th class="text-center">Volume</th>
        <th class="text-center">Form</th>
        <th>Link</th>
        <th class="text-center">Type</th>
        <th class="text-center">Priority</th>
        <th>Cluster</th>
        <th class="text-center">Position</th>
    </tr>
    </thead>
<tbody id="kwTableBody">
<?php
foreach ($rows as $row) {
    $volume = $row['volume'];
    $form   = $row['form'];

    $formBg = '';
    if (is_numeric($form)) {
        $f = (int)$form;
        if ($f < 33) {
            $formBg = '#beddce';
        } elseif ($f < 66) {
            $formBg = '#f9e9b4';
        } else {
            $formBg = '#f5c6c2';
        }
    }

    $volBg = '';
    if (is_numeric($volume)) {
        $v = (int)$volume;
        if ($v >= 5000000) {
            $volBg = '#9b9797';
        } elseif ($v >= 500000) {
            $volBg = '#b9b3c4';
        } elseif ($v >= 50000) {
            $volBg = '#cccccd';
        } elseif ($v >= 5000) {
            $volBg = '#d7dad9';
        } elseif ($v >= 500) {
            $volBg = '#f2ecf0';
        } else {
            $volBg = '';
        }
    }

    $options = '';
    foreach ($pageTypes as $pt) {
        $sel = strcasecmp(trim($row['page_type']), $pt) === 0 ? ' selected' : '';
        $options .= "<option value=\"$pt\"$sel>$pt</option>";
    }
    $prioOptions = '';
    foreach ($priorityOptions as $po) {
        $sel = strcasecmp(trim($row['priority']), $po) === 0 ? ' selected' : '';
        $prioOptions .= "<option value=\"$po\"$sel>$po</option>";
    }

    $kwKey = strtolower($row['keyword']);
    $kwClass = isset($posMap[$kwKey]) ? 'highlight-cell' : '';
    $posVal = $posMap[$kwKey] ?? '';
    $posBg = '';
    if ($posVal !== '') {
        $posBg = ((float)$posVal <= 10) ? '#d4edda' : '#f8d7da';
    }
    echo "<tr data-id='{$row['id']}'>
        <td class='text-center'><button type='button' class='btn btn-sm btn-outline-danger remove-row'>-</button><input type='hidden' name='delete[{$row['id']}]' value='0' class='delete-flag'></td>
        <td class='$kwClass'>" . htmlspecialchars($row['keyword']) . "</td>
        <td class='text-center' style='background-color: $volBg'>" . $volume . "</td>
        <td class='text-center' style='background-color: $formBg'>" . $form . "</td>
        <td><div class='d-flex align-items-center'><input type='text' name='link[{$row['id']}]' value='" . htmlspecialchars($row['content_link']) . "' class='form-control form-control-sm' style='max-width:200px;'>" .
        ($row['content_link'] ? "<a href='" . htmlspecialchars($row['content_link']) . "' target='_blank' class='ms-1'><i class='bi bi-box-arrow-up-right'></i></a>" : '') .
        "</div></td>
        <td class='text-center'><select name='page_type[{$row['id']}]' class='form-select form-select-sm'>$options</select></td>
        <td class='text-center'><select name='priority[{$row['id']}]' class='form-select form-select-sm'>$prioOptions</select></td>
        <td>" . htmlspecialchars($row['cluster_name']) . "</td>
        <td class='text-center' style='background-color: $posBg'>" . htmlspecialchars($posVal) . "</td>
    </tr>";
}
?>
</tbody>
  </table>
</form>
<nav class="my-3">
<?php if ($totalPages > 1): ?>
  <ul class="pagination pagination-sm">
  <?php for ($i = 1; $i <= $totalPages; $i++): $active = $i === $page ? ' active' : '';
        $qs = http_build_query(['client_id'=>$client_id,'slug'=>$slug,'page'=>$i,'field'=>$field,'q'=>$q,'mode'=>$mode]); ?>
    <li class="page-item<?= $active ?>"><a class="page-link" href="dashboard.php?<?= $qs ?>"><?= $i ?></a></li>
  <?php endfor; ?>
  </ul>
<?php endif; ?>
</nav>

<p><a href="index.php">&larr; Back to Clients</a></p>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="copyToast" class="toast" role="status" aria-live="assertive" aria-atomic="true" data-bs-delay="1500">
    <div class="toast-body">Copied to clipboard</div>
  </div>
</div>

<div class="modal fade" id="gscKwModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Keywords</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex">
          <div class="nav flex-column nav-pills me-3" id="kwModalTabs" role="tablist">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#kwGscList" type="button" role="tab">GSC Keywords</button>
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#kwUpdatePane" type="button" role="tab">Import Keywords</button>
          </div>
          <div class="tab-content flex-grow-1" id="kwModalTabContent">
            <div class="tab-pane fade show active" id="kwGscList" role="tabpanel">
              <input type="text" id="kwFilter" class="form-control mb-2" placeholder="Filter keywords...">
              <div class="table-responsive" style="max-height:60vh;">
                <table class="table table-sm table-hover" id="kwTable">
                  <thead class="table-light">
                    <tr>
                      <th><input type="checkbox" id="kwSelectAll"></th>
                      <th data-sort="keyword">Keyword</th>
                      <th data-sort="clicks" class="text-end">Clicks</th>
                      <th data-sort="impressions" class="text-end">Impressions</th>
                      <th data-sort="ctr" class="text-end">CTR</th>
                      <th data-sort="position" class="text-end">Position</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
            <div class="tab-pane fade" id="kwUpdatePane" role="tabpanel">
              <form method="POST" id="kwUpdateForm" class="mt-3">
                <textarea name="keywords" id="kwUpdateTextarea" class="form-control" rows="6" placeholder="keyword volume form per line"></textarea>
                <button type="submit" name="add_keywords" class="btn btn-success btn-sm mt-2">Update Keywords</button>
              </form>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="showKwUpdate">Update Keyword</button>
        <button type="button" class="btn btn-primary" id="copyToPlanner">Copy To Keyword Planner</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('remove-row')) {
    const tr = e.target.closest('tr');
    const flag = tr.querySelector('.delete-flag');
    const marked = flag.value === '1';
    flag.value = marked ? '0' : '1';
    tr.classList.toggle('text-decoration-line-through', !marked);
  }
});
const removeAllBtn = document.getElementById('removeAllKeywords');
if (removeAllBtn) {
  removeAllBtn.addEventListener('click', () => {
    const rows = document.querySelectorAll('#kwTableBody tr');
    if (rows.length && confirm('Remove all keywords on this page?')) {
      rows.forEach(tr => {
        const flag = tr.querySelector('.delete-flag');
        if (flag) {
          flag.value = '1';
          tr.classList.add('text-decoration-line-through');
        }
      });
    }
  });
}
document.getElementById('toggleAddForm').addEventListener('click', function() {
  const form = document.getElementById('addKeywordsForm');
  if (form.style.display === 'none' || form.style.display === '') {
    form.style.display = 'block';
  } else {
    form.style.display = 'none';
  }
});
document.getElementById('toggleClusterForm').addEventListener('click', function() {
  const form = document.getElementById('addClustersForm');
  if (form.style.display === 'none' || form.style.display === '') {
    form.style.display = 'block';
  } else {
    form.style.display = 'none';
  }
});
document.getElementById('toggleImportForm').addEventListener('click', function() {
  const form = document.getElementById('importForm');
  if (form.style.display === 'none' || form.style.display === '') {
    form.style.display = 'block';
  } else {
    form.style.display = 'none';
  }
});

function showCopiedToast(msg) {
  const el = document.getElementById('copyToast');
  el.querySelector('.toast-body').textContent = msg;
  const toast = bootstrap.Toast.getOrCreateInstance(el);
  toast.show();
}

document.getElementById('copyLinks').addEventListener('click', function() {
  const rows = document.querySelectorAll('#kwTableBody tr');
  const links = new Set();
  rows.forEach(tr => {
    const input = tr.querySelector('td:nth-child(5) input');
    if (input) {
      const val = input.value.trim();
      if (val) links.add(val);
    }
  });
  navigator.clipboard.writeText(Array.from(links).join('\n')).then(() => {
    showCopiedToast('Links copied to clipboard.');
  });
});

let kwData = [];
let kwFilterVal = '';
let selectedKws = new Set();
let sortKey = 'impressions';
let sortDir = 'desc';
const existingKeywords = new Set(<?= json_encode($existingKeywords) ?>);

document.getElementById('openImportKw')?.addEventListener('click', () => {
  const site = document.getElementById('scDomain').value.trim();
  if (!site) { alert('No Search Console property connected'); return; }
  const modalEl = document.getElementById('gscKwModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
  const tbody = document.querySelector('#kwTable tbody');
  tbody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
  fetch('gsc_keywords.php?client_id=<?= $client_id ?>&site=' + encodeURIComponent(site))
    .then(r => r.json()).then(data => {
      if (data.status === 'ok') {
        kwData = data.rows;
        renderKwTable();
      } else {
        tbody.innerHTML = '<tr><td colspan="5">Failed to load</td></tr>';
        alert(data.error || 'Failed to load');
      }
    }).catch(err => {
      tbody.innerHTML = '<tr><td colspan="5">Error</td></tr>';
      alert('Error: ' + err);
    });
});

function renderKwTable() {
  const tbody = document.querySelector('#kwTable tbody');
  tbody.innerHTML = '';
  const rows = kwData.filter(r => (r.keys[0]||'').toLowerCase().includes(kwFilterVal));
  rows.forEach(r=>{
    const kw = r.keys[0] || '';
    const checked = selectedKws.has(kw) ? 'checked' : '';
    const clicks = r.clicks ?? 0;
    const impr = r.impressions ?? 0;
    const ctr = r.ctr ? (r.ctr*100).toFixed(2)+'%' : '';
    const pos = r.position ? r.position.toFixed(2) : '';
    const tr = document.createElement('tr');
    if (existingKeywords.has(kw.toLowerCase())) tr.classList.add('table-warning');
    tr.innerHTML = `<td><input type="checkbox" class="kw-check" data-kw="${kw}" ${checked}></td><td>${kw}</td><td class="text-end">${clicks}</td><td class="text-end">${impr}</td><td class="text-end">${ctr}</td><td class="text-end">${pos}</td>`;
    tbody.appendChild(tr);
  });
  const all = document.getElementById('kwSelectAll');
  all.checked = rows.length && rows.every(r=>selectedKws.has(r.keys[0]||''));
}

const kwFilter = document.getElementById('kwFilter');
if (kwFilter) {
  kwFilter.addEventListener('input', function() {
    kwFilterVal = this.value.toLowerCase();
    renderKwTable();
  });
}

document.querySelectorAll('#kwTable thead th[data-sort]').forEach((th) => {
  th.dataset.label = th.textContent;
  th.addEventListener('click', () => {
    const key = th.dataset.sort;
    if (!key) return;
    sortDir = (sortKey === key && sortDir === 'asc') ? 'desc' : 'asc';
    sortKey = key;
    kwData.sort((a, b) => {
      let va, vb;
      if (key === 'keyword') {
        va = (a.keys[0] || '').toLowerCase();
        vb = (b.keys[0] || '').toLowerCase();
      } else {
        va = a[key] || 0;
        vb = b[key] || 0;
      }
      if (va < vb) return sortDir === 'asc' ? -1 : 1;
      if (va > vb) return sortDir === 'asc' ? 1 : -1;
      return 0;
    });
    document.querySelectorAll('#kwTable thead th[data-sort]').forEach((h) => {
      h.textContent = h.dataset.label;
      delete h.dataset.dir;
    });
    th.dataset.dir = sortDir;
    th.textContent = th.dataset.label + (sortDir === 'asc' ? ' \u25B2' : ' \u25BC');
    renderKwTable();
  });
});

document.getElementById('kwSelectAll').addEventListener('change', function(){
  const checked = this.checked;
  document.querySelectorAll('#kwTable tbody .kw-check').forEach(cb=>{
    cb.checked = checked;
    const kw = cb.dataset.kw;
    if (checked) selectedKws.add(kw); else selectedKws.delete(kw);
  });
});

document.addEventListener('change', function(e){
  if (e.target.classList.contains('kw-check')) {
    const kw = e.target.dataset.kw;
    if (e.target.checked) selectedKws.add(kw); else selectedKws.delete(kw);
    const rows = kwData.filter(r => (r.keys[0]||'').toLowerCase().includes(kwFilterVal));
    const all = document.getElementById('kwSelectAll');
    all.checked = rows.length && rows.every(r=>selectedKws.has(r.keys[0]||''));
  }
});

const showUpdateBtn = document.getElementById('showKwUpdate');
if (showUpdateBtn) {
  const updateTabBtn = document.querySelector('#kwModalTabs [data-bs-target="#kwUpdatePane"]');
  showUpdateBtn.addEventListener('click', () => {
    if (!selectedKws.size) { alert('No keywords selected'); return; }
    const ta = document.getElementById('kwUpdateTextarea');
    ta.value = Array.from(selectedKws).join('\n');
    const tab = new bootstrap.Tab(updateTabBtn);
    tab.show();
  });
}

const copyBtn = document.getElementById('copyToPlanner');
if (copyBtn) {
  copyBtn.addEventListener('click', function(){
    if (!selectedKws.size) { alert('No keywords selected'); return; }
    navigator.clipboard.writeText(Array.from(selectedKws).join('\n')).then(()=>{
      showCopiedToast('Keywords copied to clipboard');
    });
  });
}

document.getElementById('gscKwModal').addEventListener('hidden.bs.modal', () => {
  const firstTab = document.querySelector('#kwModalTabs [data-bs-target="#kwGscList"]');
  const tab = new bootstrap.Tab(firstTab);
  tab.show();
});
</script>

<?php include 'footer.php'; ?>
