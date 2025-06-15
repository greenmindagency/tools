<?php
require 'config.php';

// Handle new client submission before any output
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['client_name'] ?? '');
    if ($name === '') {
        $message = "<p style='color:red;'>Please enter a client name.</p>";
    } else {
        // Prevent duplicate names
        $check = $pdo->prepare('SELECT id FROM clients WHERE name = ?');
        $check->execute([$name]);
        if ($check->fetch()) {
            $message = "<p style='color:red;'>Client already exists.</p>";
        } else {
            $insert = $pdo->prepare('INSERT INTO clients (name) VALUES (?)');
            $insert->execute([$name]);
            $message = "<p style='color:green;'>Client added successfully.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Select Client</title>
</head>
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

    <h3>Add a New Client</h3>
    <form method="POST">
        <input type="text" name="client_name" placeholder="Enter client name" required>
        <button type="submit">Add Client</button>
    </form>
    <?= $message ?>
</body>
</html>
