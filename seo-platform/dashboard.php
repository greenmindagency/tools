<?php
session_start();
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
maybeUpdateKeywordGroups($pdo, $client_id);
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
            $ins = $pdo->prepare("INSERT INTO keywords (client_id, keyword, volume, form, content_link, page_type, priority, group_name, group_count, cluster_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            for ($i = 1; $i < count($rows); $i++) {
                $data = $rows[$i];
                $keyword = $data[0] ?? '';
                $volume  = $data[1] ?? '';
                $form    = $data[2] ?? '';
                $link    = $data[3] ?? '';
                $type    = $data[4] ?? '';
                if (count($data) >= 9) {
                    $priority = $data[5] ?? '';
                    $group    = $data[6] ?? '';
                    $count    = (int)($data[7] ?? 0);
                    $cluster  = $data[8] ?? '';
                } else {
                    $priority = '';
                    $group    = $data[5] ?? '';
                    $count    = (int)($data[6] ?? 0);
                    $cluster  = $data[7] ?? '';
                }
                $ins->execute([
                    $client_id,
                    $keyword,
                    $volume,
                    $form,
                    $link,
                    $type,
                    $priority,
                    $group,
                    $count,
                    $cluster
                ]);
            }

            if ($xlsx->sheetsCount() > 1) {
                $pRows = $xlsx->rows(1);
                $pIns = $pdo->prepare("INSERT INTO keyword_positions (client_id, keyword, sort_order, m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
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
        maybeUpdateKeywordGroups($pdo, $client_id);
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
)");
$statsStmt = $pdo->prepare("SELECT total, grouped, clustered, structured FROM keyword_stats WHERE client_id = ?");
$statsStmt->execute([$client_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'grouped'=>0,'clustered'=>0,'structured'=>0];

include 'header.php';
?>
<style>
  .highlight-cell { background-color: #e9e9e9 !important; }
</style>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" href="dashboard.php?client_id=<?php echo $client_id; ?>&slug=<?php echo $slug; ?>">Keywords</a></li>
  <li class="nav-item"><a class="nav-link" href="positions.php?client_id=<?php echo $client_id; ?>&slug=<?php echo $slug; ?>">Keyword Position</a></li>
</ul>

<div class="mb-3 d-flex justify-content-between align-items-center">
  <div>
    <span class="me-3">All keywords: <?= (int)$stats['total'] ?></span>
    <span class="me-3">Grouped Keywords: <?= (int)$stats['grouped'] ?></span>
    <span class="me-3">Clustered Keywords: <?= (int)$stats['clustered'] ?></span>
    <span class="me-3">Structured Keywords: <?= (int)$stats['structured'] ?></span>
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
        $pdo->prepare("DELETE FROM keyword_positions WHERE client_id = ?")->execute([$client_id]);
        $insert = $pdo->prepare(
            "INSERT INTO keywords (client_id, keyword, volume, form, content_link, page_type, priority, group_name, group_count, cluster_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
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
            if (count($data) >= 9) {
                $priority = trim($data[5] ?? '');
                $group = trim($data[6] ?? '');
                $groupCnt = is_numeric($data[7] ?? '') ? (int)$data[7] : 0;
                $cluster = trim($data[8] ?? '');
            } else {
                $group = trim($data[5] ?? '');
                $groupCnt = is_numeric($data[6] ?? '') ? (int)$data[6] : 0;
                $cluster = trim($data[7] ?? '');
            }

            if ($type !== '') {
                foreach ($pageTypes as $pt) {
                    if (strcasecmp($pt, $type) === 0) { $type = $pt; break; }
                }
            }
            $insert->execute([$client_id, $keyword, $vol, $form, $link, $type, $priority, $group, $groupCnt, $cluster]);
        }

        if ($xlsx && $xlsx->sheetsCount() > 1) {
            $pRows = $xlsx->rows(1);
            $pIns = $pdo->prepare("INSERT INTO keyword_positions (client_id, keyword, sort_order, m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
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

function createXlsxBackup(PDO $pdo, int $client_id, string $dir, string $client_name): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    date_default_timezone_set('Africa/Cairo');
    $ts = date('d-m-Y_H-i');
    $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $client_name)));
    $file = "$dir/{$slug}_$ts.xlsx";
    $kwRows = [];
    $kwRows[] = ['Keyword','Volume','Form','Link','Type','Priority','Group','#','Cluster'];
    $stmt = $pdo->prepare("SELECT keyword, volume, form, content_link, page_type, priority, group_name, group_count, cluster_name FROM keywords WHERE client_id = ? ORDER BY id");
    $stmt->execute([$client_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $kwRows[] = [
            $row['keyword'],
            $row['volume'],
            $row['form'],
            $row['content_link'],
            $row['page_type'],
            $row['priority'],
            $row['group_name'],
            $row['group_count'],
            $row['cluster_name']
        ];
    }
    $posRows = [];
    $header = ['Keyword','Sort'];
    for ($i = 1; $i <= 12; $i++) {
        $header[] = 'M'.$i;
    }
    $posRows[] = $header;
    $pstmt = $pdo->prepare("SELECT keyword, sort_order, m1,m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12 FROM keyword_positions WHERE client_id = ? ORDER BY sort_order IS NULL, sort_order, id DESC");
    $pstmt->execute([$client_id]);
    while ($row = $pstmt->fetch(PDO::FETCH_ASSOC)) {
        $line = [
            $row['keyword'],
            $row['sort_order']
        ];
        for ($i = 1; $i <= 12; $i++) {
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
$allowedFields = ['keyword', 'group_name', 'group_exact', 'cluster_name', 'content_link', 'keyword_empty_cluster', 'keyword_empty_group'];
if (!in_array($field, $allowedFields, true)) {
    $field = 'keyword';
}

$baseQuery = "FROM keywords WHERE client_id = ?";
$params = [$client_id];
$terms = array_values(array_filter(array_map('trim', explode('|', $q)), 'strlen'));
if ($field === 'keyword_empty_cluster' || $field === 'keyword_empty_group') {
    if ($terms) {
        $likeParts = [];
        foreach ($terms as $t) {
            $likeParts[] = "keyword LIKE ?";
            $params[] = "%$t%";
        }
        $baseQuery .= " AND (" . implode(' OR ', $likeParts) . ")";
    }
    if ($field === 'keyword_empty_cluster') {
        $baseQuery .= " AND (cluster_name = '' OR cluster_name IS NULL)";
    } else {
        $baseQuery .= " AND (group_name = '' OR group_name IS NULL)";
    }
} elseif ($terms) {
    $column = ($field === 'group_exact') ? 'group_name' : $field;
    if ($field === 'group_exact' || $field === 'cluster_name') {
        $placeholders = implode(',', array_fill(0, count($terms), '?'));
        $baseQuery .= " AND {$column} IN ($placeholders)";
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

$posMap = [];
$posStmt = $pdo->prepare("SELECT keyword, m1 FROM keyword_positions WHERE client_id = ?");
$posStmt->execute([$client_id]);
while ($r = $posStmt->fetch(PDO::FETCH_ASSOC)) {
    if ($r['m1'] !== null && $r['m1'] !== '') {
        $posMap[strtolower($r['keyword'])] = $r['m1'];
    }
}
?>

<div class="d-flex justify-content-between mb-2 sticky-controls">
  <div class="d-flex">
    <button type="submit" form="updateForm" name="update_keywords" class="btn btn-success btn-sm me-2">Update</button>
    <button type="button" id="toggleAddForm" class="btn btn-warning btn-sm me-2">Update Keywords</button>
    <button type="button" id="toggleClusterForm" class="btn btn-info btn-sm me-2">Update Clusters</button>
    <button type="button" id="copyKeywords" class="btn btn-secondary btn-sm me-2">Copy Keywords</button>
    <button type="button" id="copyLinks" class="btn btn-dark btn-sm me-2">Copy Links</button>
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
      <option value="keyword_empty_cluster"<?= $field==='keyword_empty_cluster' ? ' selected' : '' ?>>Keyword/Empty Cluster</option>
      <option value="keyword_empty_group"<?= $field==='keyword_empty_group' ? ' selected' : '' ?>>Keyword/Empty Group</option>
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
        <th>Group</th>
        <th class="text-center">#</th>
        <th>Cluster</th>
        <th class="text-center">Position</th>
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
        <td>" . htmlspecialchars($row['group_name']) . "</td>
        <td class='text-center' style='background-color: $groupBg'>" . $row['group_count'] . "</td>
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
        $qs = http_build_query(['client_id'=>$client_id,'slug'=>$slug,'page'=>$i,'field'=>$field,'q'=>$q]); ?>
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
    showCopiedToast('Keywords copied to clipboard');
  });
});

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
</script>

<?php include 'footer.php'; ?>
