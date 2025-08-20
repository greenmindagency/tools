<?php
session_start();
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS_HASH)) {
        $_SESSION['user'] = ADMIN_USER;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

if (!isset($_SESSION['user'])) {
    $title = 'Task Manager - Login';
    include __DIR__ . '/header.php';
    if (isset($loginError)) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($loginError) . '</div>';
    }
    ?>
    <h2>Login</h2>
    <form method="post" class="mb-5" style="max-width:400px;">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control">
      </div>
      <button type="submit" name="login" class="btn btn-primary">Login</button>
    </form>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

try {
    $pdo = get_pdo();
    init_db($pdo);
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

if (isset($dbError)) {
    $title = 'Task Manager - Error';
    include __DIR__ . '/header.php';
    echo '<div class="alert alert-danger">Database connection failed: ' . htmlspecialchars($dbError) . '</div>';
    include __DIR__ . '/footer.php';
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
        $titleTask = trim($_POST['title'] ?? '');
        $assigned = (int)($_POST['assigned'] ?? 0);
        $due = $_POST['due_date'] ?? date('Y-m-d');
        $priority = $_POST['priority'] ?? 'Normal';
        $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
        $desc = trim($_POST['description'] ?? '');
        if ($titleTask !== '' && $assigned) {
            $stmt = $pdo->prepare('INSERT INTO tasks (title, description, assigned_to, client_id, priority, due_date) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$titleTask, $desc, $assigned, $clientId, $priority, $due]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
        $taskId = (int)$_POST['update_task'];
        $desc = trim($_POST['description'] ?? '');
        $assigned = (int)($_POST['assigned'] ?? 0);
        $priority = $_POST['priority'] ?? 'Normal';
        $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
        if ($taskId && $assigned) {
            $stmt = $pdo->prepare('UPDATE tasks SET description=?, assigned_to=?, client_id=?, priority=? WHERE id=?');
            $stmt->execute([$desc, $assigned, $clientId, $priority, $taskId]);
        }
    }

    $users = $pdo->query('SELECT id, username FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $today = date('Y-m-d');
    $todayTasks = $pdo->prepare('SELECT t.*, u.username, c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE due_date <= ? ORDER BY due_date');
    $todayTasks->execute([$today]);
    $todayTasks = $todayTasks->fetchAll(PDO::FETCH_ASSOC);
    $upcomingTasks = $pdo->prepare('SELECT t.*, u.username, c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE due_date > ? ORDER BY due_date');
    $upcomingTasks->execute([$today]);
    $upcomingTasks = $upcomingTasks->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $title = 'Task Manager - Error';
    include __DIR__ . '/header.php';
    echo '<div class="alert alert-danger">Database query failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    include __DIR__ . '/footer.php';
    exit;
}

$title = 'Task Manager';
include __DIR__ . '/header.php';
?>
<a href="admin.php" class="btn btn-secondary mb-3">Admin Panel</a>
<div class="row">
  <div class="col-md-3">
    <h3>Today's & Overdue Tasks</h3>
    <ul id="today-list" class="list-group mb-4">
      <?php foreach ($todayTasks as $task): ?>
      <li class="list-group-item" draggable="true">
        <div class="d-flex justify-content-between">
          <div>
            <?= htmlspecialchars($task['title']) ?><br>
            <small><?= htmlspecialchars($task['username']) ?><?php if ($task['client_name']) echo ' for ' . htmlspecialchars($task['client_name']); ?> &mdash; <?= htmlspecialchars($task['priority']) ?> &mdash; due <?= htmlspecialchars($task['due_date']) ?></small>
          </div>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#task-<?= $task['id'] ?>">Edit</button>
        </div>
        <div class="collapse mt-2" id="task-<?= $task['id'] ?>">
          <form method="post" class="row g-2">
            <input type="hidden" name="update_task" value="<?= $task['id'] ?>">
            <div class="col-12">
              <textarea name="description" class="form-control" placeholder="Description"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
              <select name="assigned" class="form-select">
                <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $task['assigned_to']==$user['id']?'selected':'' ?>><?= htmlspecialchars($user['username']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <?php $p = $task['priority']; ?>
              <select name="priority" class="form-select">
                <option value="Low" <?= $p==='Low'?'selected':'' ?>>Low</option>
                <option value="Normal" <?= $p==='Normal'?'selected':'' ?>>Normal</option>
                <option value="High" <?= $p==='High'?'selected':'' ?>>High</option>
              </select>
            </div>
            <div class="col-md-4">
              <select name="client_id" class="form-select">
                <option value="">Client</option>
                <?php foreach ($clients as $client): ?>
                <option value="<?= $client['id'] ?>" <?= $task['client_id']==$client['id']?'selected':'' ?>><?= htmlspecialchars($client['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 mt-2"><button class="btn btn-primary btn-sm">Save</button></div>
          </form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="col-md-9">
    <h3>Upcoming Tasks</h3>
    <ul id="upcoming-list" class="list-group mb-4">
      <?php foreach ($upcomingTasks as $task): ?>
      <li class="list-group-item" draggable="true">
        <div class="d-flex justify-content-between">
          <div>
            <?= htmlspecialchars($task['title']) ?><br>
            <small><?= htmlspecialchars($task['username']) ?><?php if ($task['client_name']) echo ' for ' . htmlspecialchars($task['client_name']); ?> &mdash; <?= htmlspecialchars($task['priority']) ?> &mdash; due <?= htmlspecialchars($task['due_date']) ?></small>
          </div>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#task-<?= $task['id'] ?>">Edit</button>
        </div>
        <div class="collapse mt-2" id="task-<?= $task['id'] ?>">
          <form method="post" class="row g-2">
            <input type="hidden" name="update_task" value="<?= $task['id'] ?>">
            <div class="col-12">
              <textarea name="description" class="form-control" placeholder="Description"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
              <select name="assigned" class="form-select">
                <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $task['assigned_to']==$user['id']?'selected':'' ?>><?= htmlspecialchars($user['username']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <?php $p = $task['priority']; ?>
              <select name="priority" class="form-select">
                <option value="Low" <?= $p==='Low'?'selected':'' ?>>Low</option>
                <option value="Normal" <?= $p==='Normal'?'selected':'' ?>>Normal</option>
                <option value="High" <?= $p==='High'?'selected':'' ?>>High</option>
              </select>
            </div>
            <div class="col-md-4">
              <select name="client_id" class="form-select">
                <option value="">Client</option>
                <?php foreach ($clients as $client): ?>
                <option value="<?= $client['id'] ?>" <?= $task['client_id']==$client['id']?'selected':'' ?>><?= htmlspecialchars($client['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 mt-2"><button class="btn btn-primary btn-sm">Save</button></div>
          </form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<h4>Add Task</h4>
<form method="post" class="row g-2 mb-5">
  <input type="hidden" name="add_task" value="1">
  <div class="col-md-3"><input type="text" name="title" class="form-control" placeholder="Task title" required></div>
  <div class="col-md-2">
    <select name="assigned" class="form-select" required>
      <option value="">Assign to</option>
      <?php foreach ($users as $user): ?>
      <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select name="client_id" class="form-select">
      <option value="">Client</option>
      <?php foreach ($clients as $client): ?>
      <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select name="priority" class="form-select">
      <option value="Low">Low</option>
      <option value="Normal" selected>Normal</option>
      <option value="High">High</option>
    </select>
  </div>
  <div class="col-md-2"><input type="date" name="due_date" class="form-control" required></div>
  <div class="col-md-1"><button class="btn btn-success w-100">Add</button></div>
</form>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
new Sortable(document.getElementById('today-list'), {group:'tasks', animation:150});
new Sortable(document.getElementById('upcoming-list'), {group:'tasks', animation:150});
</script>
<?php include __DIR__ . '/footer.php'; ?>
