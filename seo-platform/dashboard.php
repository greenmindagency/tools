<?php require 'config.php';
$client_id = $_GET['client_id'] ?? 0;

// Load client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) die("Client not found");
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($client['name']) ?> Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        textarea { width: 100%; margin-top: 10px; }
        button { padding: 10px 15px; margin-top: 10px; }
        .success { color: green; margin-top: 10px; }
    </style>
</head>
<body>
<h2><?= htmlspecialchars($client['name']) ?> – Keywords Dashboard</h2>

<!-- Add Keyword Form -->
<form method="POST">
    <label><strong>Paste Keywords (tab-separated or stacked):</strong></label>
    <textarea name="keywords" placeholder="Keyword\tVolume\tForm OR stacked format" rows="6"></textarea><br>
    <button type="submit" name="add_keywords">Add Keywords</button>
</form>

<!-- Group Keywords -->
<form method="POST" style="margin-top:20px;">
    <label><strong>Target keywords for grouping (separated by |):</strong></label>
    <input type="text" name="target_keywords" placeholder="keyword1|keyword2">
    <button type="submit" name="group_keywords">Group Keywords</button>
</form>

<?php

function keywordClustering(PDO $pdo, int $client_id) {
    $stmt = $pdo->prepare("SELECT id, keyword FROM keywords WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $keywords = [];
    foreach ($rows as $r) {
        $keywords[$r['id']] = trim($r['keyword']);
    }

    $phraseCount = [];

    $getBasePhrases = function($phrase) {
        $words = explode(' ', $phrase);
        $base = [];
        if (count($words) >= 8) $base[] = implode(' ', array_slice($words, 0, 8));
        if (count($words) >= 7) $base[] = implode(' ', array_slice($words, 0, 7));
        if (count($words) >= 6) $base[] = implode(' ', array_slice($words, 0, 6));
        if (count($words) >= 5) $base[] = implode(' ', array_slice($words, 0, 5));
        if (count($words) >= 4) $base[] = implode(' ', array_slice($words, 0, 4));
        if (count($words) >= 3) $base[] = implode(' ', array_slice($words, 0, 3));
        if (count($words) >= 2) $base[] = implode(' ', array_slice($words, 0, 2));
        return $base;
    };

    foreach ($keywords as $kw) {
        foreach ($getBasePhrases(strtolower($kw)) as $phrase) {
            $phraseCount[$phrase] = ($phraseCount[$phrase] ?? 0) + 1;
        }
    }

    $phraseGroupMap = [];
    foreach ($phraseCount as $phrase => $count) {
        if ($count > 1) {
            $phraseGroupMap[$phrase] = $phrase;
        }
    }

    $findGroup = function($keyword) use ($phraseGroupMap) {
        $best = '';
        $maxWords = 0;
        $kwWords = count(explode(' ', $keyword));
        foreach ($phraseGroupMap as $phrase => $_) {
            $num = count(explode(' ', $phrase));
            if ($num > $kwWords) continue;
            if (preg_match('/(^|\s)'.preg_quote($phrase, '/').'(?=\s|$)/i', $keyword)) {
                if ($num > $maxWords) {
                    $best = $phrase;
                    $maxWords = $num;
                }
            }
        }
        return $best;
    };

    $groups = [];
    foreach ($keywords as $id => $kw) {
        $group = $findGroup(strtolower($kw));
        $groups[$id] = $group;
    }

    $counts = array_count_values($groups);
    $update = $pdo->prepare("UPDATE keywords SET group_name = ?, group_count = ? WHERE id = ? AND client_id = ?");
    foreach ($groups as $id => $grp) {
        $update->execute([$grp, $counts[$grp] ?? 0, $id, $client_id]);
    }
}

if (isset($_POST['add_keywords'])) {
    function normalizeVolume($text) {
        $map = [
            "1M – 10M" => 5000000,
            "100K – 1M" => 500000,
            "10K – 100K" => 50000,
            "1K – 10K" => 5000,
            "100 – 1K" => 500,
            "10 – 100" => 50,
            "—" => 0,
            "" => 0,
        ];
        return isset($map[$text]) ? $map[$text] : (is_numeric($text) ? (int)$text : 0);
    }

    $lines = preg_split('/\r\n|\r|\n/', trim($_POST['keywords']));
    $insert = $pdo->prepare("INSERT INTO keywords (client_id, keyword, volume, form) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE volume = VALUES(volume), form = VALUES(form)");

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);

        // FORMAT 1: tab-separated
        if (strpos($line, "\t") !== false) {
            [$kw, $vol, $form] = explode("\t", $line) + ["", "", ""];
            $vol = normalizeVolume($vol);
            $form = is_numeric($form) ? (int)$form : 0;
            if ($kw) $insert->execute([$client_id, $kw, $vol, $form]);
        }
        // FORMAT 2: stacked format
        elseif (!empty($line) && isset($lines[$i+1], $lines[$i+2])) {
            $kw = $line;
            $vol = normalizeVolume(trim($lines[$i+1]));
            $form = is_numeric(trim($lines[$i+2])) ? (int)trim($lines[$i+2]) : 0;
            $insert->execute([$client_id, $kw, $vol, $form]);
            $i += 2;
        }
    }
    echo "<p class='success'>Keywords added successfully.</p>";

    // Remove duplicates for this client
    $pdo->query("DELETE k1 FROM keywords k1
        JOIN keywords k2 ON k1.keyword = k2.keyword AND k1.id > k2.id
        WHERE k1.client_id = $client_id AND k2.client_id = $client_id");

    keywordClustering($pdo, $client_id);
}


if (isset($_POST['group_keywords'])) {
    $targets = array_filter(array_map('trim', explode('|', strtolower($_POST['target_keywords'] ?? ''))));
    $stmt = $pdo->prepare("SELECT id, keyword FROM keywords WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $rows = $stmt->fetchAll();
    $groups = [];
    $map = [];
    foreach ($rows as $r) {
        $original = $r['keyword'];
        $lower = strtolower($original);
        if (in_array($lower, $targets, true)) {
            $group = '**Parent Keyword**';
        } else {
            $words = array_values(array_filter(explode(' ', $lower), function($w) use ($targets) { return $w !== '' && !in_array($w, $targets, true); }));
            if (!empty($words)) {
                $common = $words[0];
                if (!isset($groups[$common])) {
                    $groups[$common] = $original;
                }
                $group = $groups[$common];
            } else {
                $group = $original;
            }
        }
        $map[$r['id']] = $group;
    }
    $update = $pdo->prepare("UPDATE keywords SET group_name = ? WHERE id = ? AND client_id = ?");
    foreach ($map as $id => $g) {
        $update->execute([$g, $id, $client_id]);
    }
    $counts = array_count_values($map);
    $updateCount = $pdo->prepare("UPDATE keywords SET group_count = ? WHERE id = ? AND client_id = ?");
    foreach ($map as $id => $g) {
        $updateCount->execute([$counts[$g], $id, $client_id]);
    }
    echo "<p class='success'>Keywords grouped successfully.</p>";
}

if (isset($_POST['update_keywords']) && isset($_POST['ids'])) {
    $update = $pdo->prepare("UPDATE keywords SET content_link = ?, page_type = ?, group_name = ?, group_count = ?, cluster_name = ? WHERE id = ? AND client_id = ?");
    foreach ($_POST['ids'] as $id) {
        $link = trim($_POST['content_link'][$id] ?? '');
        $pageType = trim($_POST['page_type'][$id] ?? '');
        $groupName = trim($_POST['group_name'][$id] ?? '');
        $groupCount = isset($_POST['group_count'][$id]) && is_numeric($_POST['group_count'][$id]) ? (int)$_POST['group_count'][$id] : 0;
        $clusterName = trim($_POST['cluster_name'][$id] ?? '');
        $update->execute([$link, $pageType, $groupName, $groupCount, $clusterName, $id, $client_id]);
    }
    echo "<p class='success'>Keywords updated successfully.</p>";
}

if (isset($_POST['delete_keyword'])) {
    $del = $pdo->prepare("DELETE FROM keywords WHERE id = ? AND client_id = ?");
    $del->execute([(int)$_POST['delete_keyword'], $client_id]);
    echo "<p class='success'>Keyword deleted.</p>";
}

?>

<!-- Keywords Table -->
<form method="POST">
<table>
    <thead>
        <tr>
            <th>-</th>
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
            <td><button type='submit' name='delete_keyword' value='{$row['id']}'>-</button></td>
            <td>" . htmlspecialchars($row['keyword']) . "<input type='hidden' name='ids[]' value='{$row['id']}'></td>
            <td>" . $row['volume'] . "</td>
            <td>" . $row['form'] . "</td>
            <td><input type='text' name='content_link[{$row['id']}]' value='" . htmlspecialchars($row['content_link']) . "'></td>
            <td><input type='text' name='page_type[{$row['id']}]' value='" . htmlspecialchars($row['page_type']) . "'></td>
            <td><input type='text' name='group_name[{$row['id']}]' value='" . htmlspecialchars($row['group_name']) . "'></td>
            <td><input type='number' name='group_count[{$row['id']}]' value='" . $row['group_count'] . "'></td>
            <td><input type='text' name='cluster_name[{$row['id']}]' value='" . htmlspecialchars($row['cluster_name']) . "'></td>
        </tr>";
    }
    ?>
    </tbody>
</table>
<button type="submit" name="update_keywords">Update</button>
</form>

<p><a href="index.php">&larr; Back to Clients</a></p>
</body>
</html>
