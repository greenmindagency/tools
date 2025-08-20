<?php
session_start();
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== ADMIN_USER) {
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
            $pass = $_POST['password'] ?? '';
            if ($name !== '' && $pass !== '') {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$name, $hash]);
            }
        } elseif (isset($_POST['save_user'])) {
            $id = (int)$_POST['save_user'];
            $name = trim($_POST['username'] ?? '');
            $pass = $_POST['password'] ?? '';
            if ($name !== '') {
                $stmt = $pdo->prepare('UPDATE users SET username=? WHERE id=?');
                $stmt->execute([$name, $id]);
            }
            if ($pass !== '') {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
                $stmt->execute([$hash, $id]);
            }
        } elseif (isset($_POST['delete_user'])) {
            $id = (int)$_POST['delete_user'];
            $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
            $stmt->execute([$id]);
        } elseif (isset($_POST['add_client'])) {
            $name = trim($_POST['client_name'] ?? '');
            if ($name !== '') {
                $stmt = $pdo->prepare('INSERT INTO clients (name) VALUES (?)');
                $stmt->execute([$name]);
            }
        } elseif (isset($_POST['save_client'])) {
            $id = (int)$_POST['save_client'];
            $name = trim($_POST['client_name'] ?? '');
            if ($name !== '') {
                $stmt = $pdo->prepare('UPDATE clients SET name=? WHERE id=?');
                $stmt->execute([$name, $id]);
            }
        } elseif (isset($_POST['delete_client'])) {
            $id = (int)$_POST['delete_client'];
            $stmt = $pdo->prepare('DELETE FROM clients WHERE id=?');
            $stmt->execute([$id]);
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
  <div class="col-md-4"><input type="text" name="username" class="form-control" placeholder="Name" required></div>
  <div class="col-md-4"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
  <div class="col-md-2"><button class="btn btn-success w-100">Add</button></div>
</form>
<ul class="list-group mb-5">
  <?php foreach ($users as $u): ?>
  <li class="list-group-item">
    <form method="post" class="row g-2 align-items-center">
      <div class="col-md-4"><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username']) ?>"></div>
      <div class="col-md-4"><input type="password" name="password" class="form-control" placeholder="New password"></div>
      <div class="col-md-2"><button class="btn btn-primary w-100 btn-sm" name="save_user" value="<?= $u['id'] ?>">Save</button></div>
      <div class="col-md-2"><button class="btn btn-danger w-100 btn-sm" name="delete_user" value="<?= $u['id'] ?>" onclick="return confirm('Delete user?')">Delete</button></div>
    </form>
  </li>
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
  <li class="list-group-item">
    <form method="post" class="row g-2 align-items-center">
      <div class="col-md-8"><input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>"></div>
      <div class="col-md-2"><button class="btn btn-primary w-100 btn-sm" name="save_client" value="<?= $c['id'] ?>">Save</button></div>
      <div class="col-md-2"><button class="btn btn-danger w-100 btn-sm" name="delete_client" value="<?= $c['id'] ?>" onclick="return confirm('Delete client?')">Delete</button></div>
    </form>
  </li>
  <?php endforeach; ?>
</ul>

<a href="index.php" class="btn btn-secondary">Back to Tasks</a>

<?php include __DIR__ . '/footer.php'; ?>
