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

<?php
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
    $insert = $pdo->prepare("INSERT IGNORE INTO keywords (client_id, keyword, volume, form) VALUES (?, ?, ?, ?)");

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
}
?>

<!-- Keywords Table -->
<table>
    <thead>
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
</body>
</html>