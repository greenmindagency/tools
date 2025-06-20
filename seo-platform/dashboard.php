<?php
require 'config.php';
$client_id = $_GET['client_id'] ?? 0;
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
maybeUpdateKeywordGroups($pdo, $client_id);
updateKeywordStats($pdo, $client_id);

$backupDir = __DIR__ . '/backups/client_' . $client_id;
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.csv');
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
        if (($handle = fopen($path, 'r')) !== false) {
            fgetcsv($handle); // skip header
            $ins = $pdo->prepare("INSERT INTO keywords (client_id, keyword, volume, form, content_link, page_type, group_name, group_count, cluster_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            while (($data = fgetcsv($handle)) !== false) {
                $ins->execute([
                    $client_id,
                    $data[0] ?? '',
                    $data[1] ?? '',
                    $data[2] ?? '',
                    $data[3] ?? '',
                    $data[4] ?? '',
                    $data[5] ?? '',
                    (int)($data[6] ?? 0),
                    $data[7] ?? ''
                ]);
            }
            fclose($handle);
        }
        maybeUpdateKeywordGroups($pdo, $client_id);
        updateKeywordStats($pdo, $client_id);
        $restored = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    createCsvBackup($pdo, $client_id, $backupDir);
    $files = glob($backupDir . '/*.csv');
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $backups = array_map('basename', $files);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS keyword_stats (
    client_id INT PRIMARY KEY,
    total INT DEFAULT 0,
    grouped INT DEFAULT 0,
    clustered INT DEFAULT 0,
    structured INT DEFAULT 0
)");
$statsStmt = $pdo->prepare("SELECT total, grouped, clustered, structured FROM keyword_stats WHERE client_id = ?");
$statsStmt->execute([$client_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'grouped'=>0,'clustered'=>0,'structured'=>0];

include 'header.php';
?>

<h5 class="mb-1"><?= htmlspecialchars($client['name']) ?> – Keywords</h5>
<div class="mb-3 d-flex justify-content-between align-items-center">
  <div>
    <span class="me-3">All keywords: <?= (int)$stats['total'] ?></span>
    <span class="me-3">Grouped Keywords: <?= (int)$stats['grouped'] ?></span>
    <span class="me-3">Clustered Keywords: <?= (int)$stats['clustered'] ?></span>
    <span class="me-3">Structured Keywords: <?= (int)$stats['structured'] ?></span>
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
<?php if (!empty($restored)): ?>
<p class="text-success">Backup restored.</p>
<?php endif; ?>

<!-- Add Keyword Form -->
<form method="POST" id="addKeywordsForm" class="mb-4" style="display:none;">
    <textarea name="keywords" class="form-control" placeholder="Paste keywords with optional volume and form" rows="6"></textarea>
    <button type="submit" name="add_keywords" class="btn btn-primary mt-2">Add Keywords</button>
</form>

<!-- Import Plan Form -->
<form method="POST" id="importForm" enctype="multipart/form-data" class="mb-4" style="display:none;">
    <input type="file" name="csv_file" accept=".csv" class="form-control">
    <button type="submit" name="import_plan" class="btn btn-primary mt-2">Import Plan</button>
</form>

<!-- Add Cluster Form -->
<form method="POST" id="addClustersForm" class="mb-4" style="display:none;">
    <textarea name="clusters" class="form-control" placeholder="keyword1|keyword2 per line" rows="4"></textarea>
    <button type="submit" name="add_clusters" class="btn btn-primary mt-2">Add Clusters</button>
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
maybeUpdateKeywordGroups($pdo, $client_id);
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
    maybeUpdateKeywordGroups($pdo, $client_id);
    updateKeywordStats($pdo, $client_id);
}

if (isset($_POST['import_plan'])) {
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $pdo->prepare("DELETE FROM keywords WHERE client_id = ?")->execute([$client_id]);
        $insert = $pdo->prepare(
            "INSERT INTO keywords (client_id, keyword, volume, form, content_link, page_type, group_name, group_count, cluster_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (($handle = fopen($_FILES['csv_file']['tmp_name'], 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                if (!isset($data[0])) continue;
                if (stripos($data[0], 'keyword') === 0) continue; // skip header
                $keyword = $data[0];
                $vol = $data[1] ?? '';
                $form = $data[2] ?? '';
                $link = $data[3] ?? '';
                $type = trim($data[4] ?? '');

                $group = trim($data[5] ?? '');
                $groupCnt = is_numeric($data[6] ?? '') ? (int)$data[6] : 0;
                $cluster = trim($data[7] ?? '');

                if ($type !== '') {
                    foreach ($pageTypes as $pt) {
                        if (strcasecmp($pt, $type) === 0) { $type = $pt; break; }
                    }
                }
                $insert->execute([$client_id, $keyword, $vol, $form, $link, $type, $group, $groupCnt, $cluster]);
            }
            fclose($handle);
        }
        echo "<p>Plan imported.</p>";
        maybeUpdateKeywordGroups($pdo, $client_id);
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


    maybeUpdateKeywordGroups($pdo, $client_id);
    updateKeywordStats($pdo, $client_id);

    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        $params = [
            'client_id' => $client_id,
            'slug' => $slug,
            'page' => $_GET['page'] ?? null,
            'field' => $_GET['field'] ?? null,
            'q' => $_GET['q'] ?? null,
        ];
        $qs = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
        header('Location: dashboard.php' . ($qs ? "?$qs" : ''));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}

// ---------- Auto Grouping Logic ----------
function getBasePhrases(string $phrase): array {
    $words = preg_split('/\s+/', trim($phrase));
    $bases = [];
    $count = count($words);
    for ($n = 2; $n <= 8; $n++) {
        if ($count >= $n) {
            $bases[] = implode(' ', array_slice($words, 0, $n));
        }
    }
    return $bases;
}

function findGroup(string $keyword, array $phraseCount): string {
    $bestGroup = '';
    $maxWords = 0;
    $kwWordCount = count(preg_split('/\s+/', $keyword));
    foreach ($phraseCount as $phrase => $cnt) {
        $phraseWords = preg_split('/\s+/', $phrase);
        $numWords = count($phraseWords);
        if ($numWords > $kwWordCount) {
            continue;
        }
        $regex = '/(^|\s)' . preg_quote($phrase, '/') . '(\s|$)/i';
        if (preg_match($regex, $keyword) && $numWords > $maxWords) {
            $bestGroup = $phrase;
            $maxWords = $numWords;
        }
    }
    return $bestGroup;
}


/**
 * Update keyword grouping only when keywords have changed.
 */
function maybeUpdateKeywordGroups(PDO $pdo, int $client_id): void {
    $stmt = $pdo->prepare("SELECT id, keyword FROM keywords WHERE client_id = ? ORDER BY id");
    $stmt->execute([$client_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return;
    }

    $hashInput = '';
    foreach ($rows as $r) {
        $hashInput .= $r['id'] . ':' . $r['keyword'] . ';';
    }
    $hash = md5($hashInput);
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    $cacheFile = $cacheDir . "/groups_{$client_id}.txt";
    $prev = file_exists($cacheFile) ? trim(file_get_contents($cacheFile)) : '';
    if ($hash === $prev) {
        return; // nothing changed
    }
    file_put_contents($cacheFile, $hash);

    $keywords = [];
    foreach ($rows as $r) {
        $keywords[$r['id']] = $r['keyword'];
    }

    $phraseCount = [];
    foreach ($keywords as $kw) {
        foreach (getBasePhrases($kw) as $base) {
            $phraseCount[$base] = ($phraseCount[$base] ?? 0) + 1;
        }
    }

    $phraseCount = array_filter($phraseCount, fn($c) => $c > 1);

    $update = $pdo->prepare("UPDATE keywords SET group_name = ?, group_count = 0 WHERE id = ?");
    foreach ($keywords as $id => $kw) {
        $group = findGroup($kw, $phraseCount);
        $update->execute([$group, $id]);
    }

    updateGroupCounts($pdo, $client_id);
}

function updateGroupCounts(PDO $pdo, int $client_id): void {
    $stmt = $pdo->prepare("SELECT group_name, COUNT(*) as cnt FROM keywords WHERE client_id = ? GROUP BY group_name");
    $stmt->execute([$client_id]);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $update = $pdo->prepare("UPDATE keywords SET group_count = ? WHERE client_id = ? AND group_name = ?");
    foreach ($counts as $group => $cnt) {
        $update->execute([$cnt, $client_id, $group]);
    }
}

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

function createCsvBackup(PDO $pdo, int $client_id, string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    date_default_timezone_set('Africa/Cairo');
    $ts = date('d-m-Y_H-i');
    $file = "$dir/$ts.csv";
    $out = fopen($file, 'w');
    fputcsv($out, ['Keyword','Volume','Form','Link','Page Type','Group','# in Group','Cluster']);
    $stmt = $pdo->prepare("SELECT keyword, volume, form, content_link, page_type, group_name, group_count, cluster_name FROM keywords WHERE client_id = ? ORDER BY id");
    $stmt->execute([$client_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['keyword'],
            $row['volume'],
            $row['form'],
            $row['content_link'],
            $row['page_type'],
            $row['group_name'],
            $row['group_count'],
            $row['cluster_name']
        ]);
    }
    fclose($out);

    $files = glob($dir . '/*.csv');
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
$allowedFields = ['keyword', 'group_name', 'group_exact', 'cluster_name', 'content_link'];
if (!in_array($field, $allowedFields, true)) {
    $field = 'keyword';
}

$baseQuery = "FROM keywords WHERE client_id = ?";
$params = [$client_id];
if ($q !== '') {
    if ($field === 'group_exact') {
        $baseQuery .= " AND group_name = ?";
        $params[] = $q;
    } elseif ($field === 'cluster_name') {
        $baseQuery .= " AND cluster_name = ?";
        $params[] = $q;
    } else {
        $baseQuery .= " AND {$field} LIKE ?";
        $params[] = "%$q%";
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
?>

<div class="d-flex justify-content-between mb-2 sticky-controls">
  <div class="d-flex">
    <button type="submit" form="updateForm" name="update_keywords" class="btn btn-success me-2">Update</button>
    <button type="button" id="toggleAddForm" class="btn btn-warning me-2">Update Keywords</button>
    <button type="button" id="toggleClusterForm" class="btn btn-info me-2">Update Clusters</button>
    <button type="button" id="copyKeywords" class="btn btn-secondary me-2">Copy Table</button>
    <button type="button" id="toggleImportForm" class="btn btn-primary me-2">Import Plan</button>
    <a href="export.php?client_id=<?= $client_id ?>" class="btn btn-outline-primary">Export CSV</a>
  </div>
  <form id="filterForm" method="GET" class="d-flex">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="slug" value="<?= $slug ?>">
    <select name="field" id="filterField" class="form-select form-select-sm me-2" style="width:auto;">
      <option value="keyword"<?= $field==='keyword' ? ' selected' : '' ?>>Keyword</option>
      <option value="group_name"<?= $field==='group_name' ? ' selected' : '' ?>>Group</option>
      <option value="group_exact"<?= $field==='group_exact' ? ' selected' : '' ?>>Group Exact</option>
      <option value="cluster_name"<?= $field==='cluster_name' ? ' selected' : '' ?>>Cluster</option>
      <option value="content_link"<?= $field==='content_link' ? ' selected' : '' ?>>Link</option>
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
        <th style="width:1px;"></th>
        <th>Keyword</th>
        <th class="text-center">Volume</th>
        <th class="text-center">Form</th>
        <th>Link</th>
        <th class="text-center">Page Type</th>
        <th>Group</th>
        <th class="text-center"># in Group</th>
        <th>Cluster</th>
    </tr>
    </thead>
<tbody id="kwTableBody">
<?php
foreach ($stmt as $row) {
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

    $groupBg = '';
    if (is_numeric($row['group_count']) && (int)$row['group_count'] > 5) {
        $groupBg = '#fff9c4';
    }

    $clusterBg = '';
    if ($q !== '' && $row['cluster_name'] === '') {
        $clusterBg = '#efefef';
    }

    $options = '';
    foreach ($pageTypes as $pt) {
        $sel = strcasecmp(trim($row['page_type']), $pt) === 0 ? ' selected' : '';
        $options .= "<option value=\"$pt\"$sel>$pt</option>";
    }

    echo "<tr data-id='{$row['id']}'>
        <td class='text-center'><button type='button' class='btn btn-sm btn-outline-danger remove-row'>-</button><input type='hidden' name='delete[{$row['id']}]' value='0' class='delete-flag'></td>
        <td>" . htmlspecialchars($row['keyword']) . "</td>
        <td class='text-center' style='background-color: $volBg'>" . $volume . "</td>
        <td class='text-center' style='background-color: $formBg'>" . $form . "</td>
        <td><input type='text' name='link[{$row['id']}]' value='" . htmlspecialchars($row['content_link']) . "' class='form-control form-control-sm' style='max-width:200px;'></td>
        <td class='text-center'><select name='page_type[{$row['id']}]' class='form-select form-select-sm'>$options</select></td>
        <td>" . htmlspecialchars($row['group_name']) . "</td>
        <td class='text-center' style='background-color: $groupBg'>" . $row['group_count'] . "</td>
        <td style='background-color: $clusterBg'>" . htmlspecialchars($row['cluster_name']) . "</td>
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
        $qs = http_build_query(['client_id'=>$client_id,'slug'=>$slug,'page'=>$i,'field'=>$field,'q'=>$q]); ?>
    <li class="page-item<?= $active ?>"><a class="page-link" href="dashboard.php?<?= $qs ?>"><?= $i ?></a></li>
  <?php endfor; ?>
  </ul>
<?php endif; ?>
</nav>

<p><a href="index.php">&larr; Back to Clients</a></p>

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
document.getElementById('copyKeywords').addEventListener('click', function() {
  const rows = document.querySelectorAll('#kwTableBody tr');
  const keywords = [];
  rows.forEach(tr => {
    const cell = tr.querySelector('td:nth-child(2)');
    if (cell) {
      keywords.push(cell.innerText.trim());
    }
  });
  navigator.clipboard.writeText(keywords.join('\n')).then(() => {
    alert('Keywords copied to clipboard');
  });
});
</script>

<?php include 'footer.php'; ?>
