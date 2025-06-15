<?php require 'config.php'; ?>
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

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['client_name']);
        if ($name) {
            $insert = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
            $insert->execute([$name]);
            header("Location: index.php");
            exit;
        } else {
            echo "<p style='color:red;'>Please enter a client name.</p>";
        }
    }
    ?>
</body>
</html>
