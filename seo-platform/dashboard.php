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
<head><title><?= htmlspecialchars($client['name']) ?> Dashboard</title></head>
<body>
<h2><?= htmlspecialchars($client['name']) ?> – Keywords</h2>

<!-- Add Keyword Form -->
<form method="POST">
    <textarea name="keywords" placeholder="Paste keywords – one per line" rows="6" cols="50"></textarea><br>
    <button type="submit" name="add_keywords">Add Keywords</button>
</form>

<?php
if (isset($_POST['add_keywords'])) {
    $lines = explode("\n", trim($_POST['keywords']));
    $insert = $pdo->prepare("INSERT IGNORE INTO keywords (client_id, keyword) VALUES (?, ?)");
    foreach ($lines as $line) {
        $keyword = trim($line);
        if ($keyword) $insert->execute([$client_id, $keyword]);
    }
    echo "<p>Keywords added.</p>";
}

// Remove duplicates for this client
$pdo->query("DELETE k1 FROM keywords k1
JOIN keywords k2 ON k1.keyword = k2.keyword AND k1.id > k2.id
WHERE k1.client_id = $client_id AND k2.client_id = $client_id");
?>

<!-- Keywords Table -->
<table border="1" cellpadding="5">
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
</table>
<p><a href="index.php">← Back to Clients</a></p>
</body>
</html>
