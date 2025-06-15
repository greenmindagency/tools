<?php
require 'config.php';
$client_id = $_GET['client_id'] ?? 0;

// Load client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) die("Client not found");

$title = $client['name'] . ' Dashboard';
include 'header.php';
?>

<h5 class="mb-3"><?= htmlspecialchars($client['name']) ?> – Keywords</h5>

<!-- Add Keyword Form -->
<form method="POST" class="mb-4">
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
        header("Location: dashboard.php?client_id=$client_id");
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);


    header("Location: dashboard.php?client_id=$client_id");

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

<form method="POST" id="updateForm">
  <div class="d-flex justify-content-between mb-2">
    <button type="submit" name="update_keywords" class="btn btn-success">Update</button>
    <div class="d-flex">
      <select id="filterField" class="form-select form-select-sm me-2" style="width:auto;">
        <option value="keyword">Keyword</option>
        <option value="group_name">Group</option>
        <option value="cluster_name">Cluster</option>

        <option value="content_link">Link</option>


      </select>
      <input type="text" id="filterInput" class="form-control form-control-sm w-auto" placeholder="Filter..." style="max-width:200px;">
    </div>
  </div>

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
$stmt = $pdo->prepare("SELECT * FROM keywords WHERE client_id = ? ORDER BY volume DESC, form ASC");
$stmt->execute([$client_id]);
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

    echo "<tr data-id='{$row['id']}'>
        <td class='text-center'><button type='button' class='btn btn-sm btn-outline-danger remove-row'>-</button><input type='hidden' name='delete[{$row['id']}]' value='0' class='delete-flag'></td>
        <td>" . htmlspecialchars($row['keyword']) . "</td>
        <td class='text-center' style='background-color: $volBg'>" . $volume . "</td>
        <td class='text-center' style='background-color: $formBg'>" . $form . "</td>
        <td><input type='text' name='link[{$row['id']}]' value='" . htmlspecialchars($row['content_link']) . "' class='form-control form-control-sm' style='max-width:200px;'></td>
        <td class='text-center'>" . htmlspecialchars($row['page_type']) . "</td>
        <td>" . htmlspecialchars($row['group_name']) . "</td>
        <td class='text-center'>" . $row['group_count'] . "</td>
        <td>" . htmlspecialchars($row['cluster_name']) . "</td>
    </tr>";
}
?>
</tbody>
  </table>
</form>
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

const filterInput = document.getElementById('filterInput');
const filterField = document.getElementById('filterField');

const updateForm = document.getElementById('updateForm');


function fetchRows() {
  const q = filterInput.value;
  const field = filterField.value;
  const url = 'fetch_keywords.php?client_id=<?=$client_id?>&q=' +
              encodeURIComponent(q) + '&field=' + encodeURIComponent(field);
  fetch(url)
    .then(r => r.text())
    .then(html => { document.getElementById('kwTableBody').innerHTML = html; });
}


updateForm.addEventListener('submit', function(e) {
  e.preventDefault();
  const fd = new FormData(updateForm);
  fd.append('client_id', <?=$client_id?>);
  fetch('dashboard.php?client_id=<?=$client_id?>', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: fd
  }).then(r => r.json()).then(() => fetchRows());
});


filterInput.addEventListener('input', fetchRows);
filterField.addEventListener('change', fetchRows);
</script>

<?php include 'footer.php'; ?>
