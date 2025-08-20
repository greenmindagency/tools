<?php
session_start();
require __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = get_pdo();
    init_db($pdo);
} catch (PDOException $e) {
    $error = $e->getMessage();
}

if (isset($error)) {
    $title = 'Task Manager - Error';
    include __DIR__ . '/header.php';
    echo '<div class="alert alert-danger">Database connection failed: ' . htmlspecialchars($error) . '</div>';
    include __DIR__ . '/footer.php';
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_user'])) {
            $name = trim($_POST['username'] ?? '');
            if ($name !== '') {
                $stmt = $pdo->prepare('INSERT IGNORE INTO users (username) VALUES (?)');
                $stmt->execute([$name]);
            }
        } elseif (isset($_POST['add_client'])) {
            $name = trim($_POST['client_name'] ?? '');
            if ($name !== '') {
                $stmt = $pdo->prepare('INSERT INTO clients (name) VALUES (?)');
                $stmt->execute([$name]);
            }
        }
    }

    $users = $pdo->query('SELECT id, username FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}

if (isset($error)) {
    $title = 'Task Manager - Error';
    include __DIR__ . '/header.php';
    echo '<div class="alert alert-danger">Database query failed: ' . htmlspecialchars($error) . '</div>';
    include __DIR__ . '/footer.php';
    exit;
}

$title = 'Task Manager - Admin';
include __DIR__ . '/header.php';
?>
<h2>Admin Panel</h2>

<h4 class="mt-4">Team Members</h4>
<form method="post" class="row g-2 mb-3">
  <input type="hidden" name="add_user" value="1">
  <div class="col-md-6"><input type="text" name="username" class="form-control" placeholder="Name" required></div>
  <div class="col-md-2"><button class="btn btn-success w-100">Add</button></div>
</form>
<ul class="list-group mb-5">
  <?php foreach ($users as $u): ?>
  <li class="list-group-item"><?= htmlspecialchars($u['username']) ?></li>
  <?php endforeach; ?>
</ul>

<h4>Clients</h4>
<form method="post" class="row g-2 mb-3">
  <input type="hidden" name="add_client" value="1">
  <div class="col-md-6"><input type="text" name="client_name" class="form-control" placeholder="Client name" required></div>
  <div class="col-md-2"><button class="btn btn-success w-100">Add</button></div>
</form>
<ul class="list-group mb-5">
  <?php foreach ($clients as $c): ?>
  <li class="list-group-item"><?= htmlspecialchars($c['name']) ?></li>
  <?php endforeach; ?>
</ul>

<a href="index.php" class="btn btn-secondary">Back to Tasks</a>

<?php include __DIR__ . '/footer.php'; ?>
