<?php require 'config.php'; ?>
<!DOCTYPE html>
<html>
<head><title>Select Client</title></head>
<body>
<h2>Select a Client</h2>
<ul>
<?php
$stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
foreach ($stmt as $client) {
    echo "<li><a href='dashboard.php?client_id={$client['id']}'>" . htmlspecialchars($client['name']) . "</a></li>";
}
?>
</ul>
<hr>
<form method="POST">
    <input type="text" name="client_name" placeholder="Add new client..." required>
    <button type="submit">Add Client</button>
</form>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['client_name'];
    $insert = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
    $insert->execute([$name]);
    header("Location: index.php");
}
?>
</body>
</html>
