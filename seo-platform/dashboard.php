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

// Fetch keywords for grouping
$stmt = $pdo->prepare("SELECT id, keyword FROM keywords WHERE client_id = ?");
$stmt->execute([$client_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$keywords = [];
foreach ($rows as $r) {
    $keywords[$r['id']] = $r['keyword'];
}

$phraseCount = [];
foreach ($keywords as $kw) {
    foreach (getBasePhrases($kw) as $base) {
        if (!isset($phraseCount[$base])) {
            $phraseCount[$base] = 0;
        }
        $phraseCount[$base]++;
    }
}

$phraseCount = array_filter($phraseCount, fn($c) => $c > 1);

$update = $pdo->prepare("UPDATE keywords SET group_name = ?, group_count = ? WHERE id = ?");
foreach ($keywords as $id => $kw) {
    $group = findGroup($kw, $phraseCount);
    $count = $group && isset($phraseCount[$group]) ? $phraseCount[$group] : 0;
    $update->execute([$group, $count, $id]);
}
?>

<!-- Keywords Table -->
<table class="table table-bordered table-sm">
    <thead class="table-light">
    <tr>
        <th>Keyword</th>
        <th>Volume</th>
        <th>Form</th>
        <th>Link</th>
        <th>Page Type</th>
        <th>Group</th>
        <th># in Group</th>
        <th>Cluster</th>
    </tr>
    </thead>
    <tbody>
<?php
$stmt = $pdo->prepare("SELECT * FROM keywords WHERE client_id = ? ORDER BY volume DESC, form ASC");
$stmt->execute([$client_id]);
foreach ($stmt as $row) {
    echo "<tr>
        <td>" . htmlspecialchars($row['keyword']) . "</td>
        <td>" . $row['volume'] . "</td>
        <td>" . $row['form'] . "</td>
        <td><a href='" . htmlspecialchars($row['content_link']) . "' target='_blank'>Link</a></td>
        <td>" . htmlspecialchars($row['page_type']) . "</td>
        <td>" . htmlspecialchars($row['group_name']) . "</td>
        <td>" . $row['group_count'] . "</td>
        <td>" . htmlspecialchars($row['cluster_name']) . "</td>
    </tr>";
}
?>
</tbody>
</table>
<p><a href="index.php">&larr; Back to Clients</a></p>

<?php include 'footer.php'; ?>
