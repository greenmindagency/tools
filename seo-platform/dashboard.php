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
include 'header.php';
?>

<h5 class="mb-3"><?= htmlspecialchars($client['name']) ?> – Keywords</h5>

<!-- Add Keyword Form -->
<form method="POST" id="addKeywordsForm" class="mb-4" style="display:none;">
    <textarea name="keywords" class="form-control" placeholder="Paste keywords with optional volume and form" rows="6"></textarea>
    <button type="submit" name="add_keywords" class="btn btn-primary mt-2">Add Keywords</button>
</form>

<?php
if (isset($_POST['add_keywords'])) {
    $text = trim($_POST['keywords']);
    $lines = preg_split('/\r\n|\n|\r/', $text);
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));

    $insert = $pdo->prepare("INSERT IGNORE INTO keywords (client_id, keyword, volume, form) VALUES (?, ?, ?, ?)");
    $entries = [];

    if (!empty($lines) && preg_match('/\d+$/', $lines[0])) {
        // Format: keyword volume form per line
        foreach ($lines as $line) {
            if (preg_match('/^(.*?)\s+(\S+)\s+(\S+)$/', $line, $m)) {
                $entries[] = [$m[1], $m[2], $m[3]];
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
                $entries[] = [$keyword, $volume, $form];
            }
        }
    }

    foreach ($entries as $e) {
        list($k, $v, $f) = $e;
        $insert->execute([$client_id, $k, $v, $f]);
    }
    echo "<p>Keywords added.</p>";
}

// Remove duplicates for this client
$pdo->query("DELETE k1 FROM keywords k1
JOIN keywords k2 ON k1.keyword = k2.keyword AND k1.id > k2.id
WHERE k1.client_id = $client_id AND k2.client_id = $client_id");

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

    updateGroupCounts($pdo, $client_id);

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


function autoUpdateKeywordGroups(PDO $pdo, int $client_id): void {
    $stmt = $pdo->prepare("SELECT id, keyword FROM keywords WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return;
    }

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

// Run grouping on every page load
autoUpdateKeywordGroups($pdo, $client_id);
updateGroupCounts($pdo, $client_id);

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
    <button type="button" id="toggleAddForm" class="btn btn-warning me-2">Update Keywords</button>
    <button type="submit" form="updateForm" name="update_keywords" class="btn btn-success">Update</button>
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
<<<<<<< ours
    <a href="dashboard.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>" class="btn btn-outline-secondary btn-sm ms-1" title="Clear filter"><i class="bi bi-x"></i></a>
=======
    <a href="dashboard.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>" class="btn btn-outline-secondary btn-sm ms-1 d-flex align-items-center" title="Clear filter"><i class="bi bi-x-lg"></i></a>
>>>>>>> theirs
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

    echo "<tr data-id='{$row['id']}'>
        <td class='text-center'><button type='button' class='btn btn-sm btn-outline-danger remove-row'>-</button><input type='hidden' name='delete[{$row['id']}]' value='0' class='delete-flag'></td>
        <td>" . htmlspecialchars($row['keyword']) . "</td>
        <td class='text-center' style='background-color: $volBg'>" . $volume . "</td>
        <td class='text-center' style='background-color: $formBg'>" . $form . "</td>
        <td><input type='text' name='link[{$row['id']}]' value='" . htmlspecialchars($row['content_link']) . "' class='form-control form-control-sm' style='max-width:200px;'></td>
        <td class='text-center'>" . htmlspecialchars($row['page_type']) . "</td>
        <td>" . htmlspecialchars($row['group_name']) . "</td>
        <td class='text-center' style='background-color: $groupBg'>" . $row['group_count'] . "</td>
        <td>" . htmlspecialchars($row['cluster_name']) . "</td>
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
</script>

<?php include 'footer.php'; ?>
