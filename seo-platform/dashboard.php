<?php ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);




require 'config.php';
$client_id = $_GET['client_id'] ?? 0;

// Load client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) die("Client not found");

// Handle keyword deletion
if (isset($_POST['delete_keyword'])) {
    $delete = $pdo->prepare("DELETE FROM keywords WHERE id = ? AND client_id = ?");
    $delete->execute([$_POST['delete_keyword'], $client_id]);
}

// Normalize volume ranges
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

if (isset($_POST['add_keywords'])) {
    $lines = preg_split('/\r\n|\r|\n/', trim($_POST['keywords']));
    $insert = $pdo->prepare("INSERT INTO keywords (client_id, keyword, volume, form) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE volume = VALUES(volume), form = VALUES(form)");

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);

        if (strpos($line, "\t") !== false) {
            [$kw, $vol, $form] = explode("\t", $line) + ["", "", ""];
            $vol = normalizeVolume($vol);
            $form = is_numeric($form) ? (int)$form : 0;
            if ($kw) $insert->execute([$client_id, $kw, $vol, $form]);
        }
        elseif (!empty($line) && isset($lines[$i+1], $lines[$i+2])) {
            $kw = $line;
            $vol = normalizeVolume(trim($lines[$i+1]));
            $form = is_numeric(trim($lines[$i+2])) ? (int)trim($lines[$i+2]) : 0;
            $insert->execute([$client_id, $kw, $vol, $form]);
            $i += 2;
        }
    }

    // Remove duplicates for this client
    $pdo->query("DELETE k1 FROM keywords k1
        JOIN keywords k2 ON k1.keyword = k2.keyword AND k1.id > k2.id
        WHERE k1.client_id = $client_id AND k2.client_id = $client_id");
}

if (isset($_POST['group_keywords'])) {
    $targetWords = [];
    if (!empty($_POST['target_words'])) {
        $targetWords = array_map('trim', explode('|', strtolower($_POST['target_words'])));
    }

    $stmt = $pdo->prepare("SELECT id, keyword FROM keywords WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $keywords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = [];

    foreach ($keywords as $row) {
        $words = array_filter(explode(' ', strtolower($row['keyword'])), function($w) use ($targetWords) {
            return !in_array($w, $targetWords);
        });
        $common = $words[0] ?? $row['keyword'];
        if (!isset($groups[$common])) $groups[$common] = $row['keyword'];

        $update = $pdo->prepare("UPDATE keywords SET group_name = ? WHERE id = ?");
        $update->execute([$groups[$common], $row['id']]);
    }
}
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
    </style>
</head>
<body>
<h2><?= htmlspecialchars($client['name']) ?> – Keywords Dashboard</h2>

<form method="POST">
    <label><strong>Paste Keywords (tab-separated or stacked):</strong></label>
    <textarea name="keywords" rows="6"></textarea><br>
    <button type="submit" name="add_keywords">Add Keywords</button>
</form>

<form method="POST" style="margin-top: 20px;">
    <label>Group by removing these target words (separate by |):</label><br>
    <input type="text" name="target_words" placeholder="e.g. for|to|a|the" style="width: 100%;"><br>
    <button type="submit" name="group_keywords">Group Keywords</button>
</form>

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
        foreach ($stmt as $row): ?>
            <tr>
                <td>
                    <button type="submit" name="delete_keyword" value="<?= $row['id'] ?>" style="color:red;">-</button>
                </td>
                <td><?= htmlspecialchars($row['keyword']) ?></td>
                <td><?= $row['volume'] ?></td>
                <td><?= $row['form'] ?></td>
                <td><a href="<?= htmlspecialchars($row['content_link']) ?>" target="_blank">Link</a></td>
                <td><?= htmlspecialchars($row['page_type']) ?></td>
                <td><?= htmlspecialchars($row['group_name']) ?></td>
                <td><?= $row['group_count'] ?></td>
                <td><?= htmlspecialchars($row['cluster_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</form>

<p><a href="index.php">&larr; Back to Clients</a></p>
</body>
</html>
