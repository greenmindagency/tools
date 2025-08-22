<?php
session_start();
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
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
        if (isset($_POST['reorder_users'])) {
            $order = $_POST['order'] ?? '';
            $stmt = $pdo->prepare('UPDATE users SET sort_order=? WHERE id=?');
            foreach (explode(',', $order) as $pair) {
                if (!$pair) continue;
                [$id,$idx] = explode(':',$pair);
                $stmt->execute([(int)$idx,(int)$id]);
            }
            exit;
        } elseif (isset($_POST['reorder_clients'])) {
            $order = $_POST['order'] ?? '';
            $stmt = $pdo->prepare('UPDATE clients SET sort_order=? WHERE id=?');
            foreach (explode(',', $order) as $pair) {
                if (!$pair) continue;
                [$id,$idx] = explode(':',$pair);
                $stmt->execute([(int)$idx,(int)$id]);
            }
            exit;
        } elseif (isset($_POST['add_user'])) {
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
            $namesText = trim($_POST['client_names'] ?? '');
            if ($namesText !== '') {
                $insertStmt = $pdo->prepare('INSERT INTO clients (name) VALUES (?)');
                $updateStmt = $pdo->prepare('UPDATE clients SET name=? WHERE id=?');
                $selectStmt = $pdo->prepare('SELECT id FROM clients WHERE name=?');
                foreach (preg_split('/\r\n|\r|\n/', $namesText) as $n) {
                    $name = trim($n);
                    if ($name === '') continue;
                    $selectStmt->execute([$name]);
                    $id = $selectStmt->fetchColumn();
                    if ($id) {
                        $updateStmt->execute([$name, $id]);
                    } else {
                        $insertStmt->execute([$name]);
                    }
                }
            }
        } elseif (isset($_POST['save_client'])) {
            $id = (int)$_POST['save_client'];
            $name = trim($_POST['client_name'] ?? '');
            if ($name !== '') {
                $stmt = $pdo->prepare('UPDATE clients SET name=? WHERE id=?');
                $stmt->execute([$name, $id]);
            }
        } elseif (isset($_POST['archive_client'])) {
            $id = (int)$_POST['archive_client'];
            $stmt = $pdo->prepare('UPDATE tasks SET status="archived" WHERE client_id=? OR parent_id IN (SELECT id FROM (SELECT id FROM tasks WHERE client_id=?) AS t)');
            $stmt->execute([$id, $id]);
        } elseif (isset($_POST['unarchive_client'])) {
            $id = (int)$_POST['unarchive_client'];
            $stmt = $pdo->prepare('UPDATE tasks SET status="pending" WHERE client_id=? OR parent_id IN (SELECT id FROM (SELECT id FROM tasks WHERE client_id=?) AS t)');
            $stmt->execute([$id, $id]);
        } elseif (isset($_POST['delete_client'])) {
            $id = (int)$_POST['delete_client'];
            $stmt = $pdo->prepare('DELETE FROM tasks WHERE client_id=?');
            $stmt->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM clients WHERE id=?');
            $stmt->execute([$id]);
        } elseif (isset($_POST['import_priorities'])) {
            refresh_client_priorities($pdo);
        }
    }

    $users = $pdo->query('SELECT id, username FROM users ORDER BY sort_order, username')->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query('SELECT c.id, c.name, c.priority, c.progress_percent, COALESCE(SUM(t.status != "archived"),0) AS active_count, COALESCE(SUM(t.status = "archived"),0) AS archived_count FROM clients c LEFT JOIN tasks t ON t.client_id=c.id GROUP BY c.id,c.name,c.priority,c.sort_order,c.progress_percent ORDER BY (c.priority IS NULL), c.sort_order, c.name')->fetchAll(PDO::FETCH_ASSOC);

    $workerTotals = [];
    $clientStmt = $pdo->query('SELECT id, progress_percent FROM clients WHERE progress_percent IS NOT NULL');
    $wStmt = $pdo->prepare('SELECT u.username, COUNT(*) AS task_count FROM tasks t JOIN users u ON t.assigned_to=u.id WHERE t.client_id=? AND t.status!="archived" GROUP BY u.username');
    while ($c = $clientStmt->fetch(PDO::FETCH_ASSOC)) {
        $wStmt->execute([$c['id']]);
        $rows = $wStmt->fetchAll(PDO::FETCH_ASSOC);
        $totalTasks = array_sum(array_column($rows, 'task_count'));
        if ($totalTasks === 0) continue;
        foreach ($rows as $row) {
            $share = $c['progress_percent'] * ($row['task_count'] / $totalTasks);
            $name = $row['username'];
            $workerTotals[$name] = ($workerTotals[$name] ?? 0) + $share;
        }
    }
    arsort($workerTotals);
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
<ul class="list-group mb-2" id="user-list">
  <?php foreach ($users as $u): ?>
  <li class="list-group-item" data-id="<?= $u['id'] ?>">
    <form method="post" class="row g-2 align-items-center">
      <div class="col-md-4"><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username']) ?>"></div>
      <div class="col-md-4"><input type="password" name="password" class="form-control" placeholder="New password"></div>
      <div class="col-md-2"><button class="btn btn-primary w-100 btn-sm" name="save_user" value="<?= $u['id'] ?>">Save</button></div>
      <div class="col-md-2"><button class="btn btn-danger w-100 btn-sm" name="delete_user" value="<?= $u['id'] ?>" onclick="return confirm('Delete user?')">Delete</button></div>
    </form>
  </li>
  <?php endforeach; ?>
</ul>
<button id="saveUserOrder" class="btn btn-success btn-sm mb-5">Save Order</button>

<h4>Clients</h4>
<form method="post" class="row g-2 mb-3">
  <input type="hidden" name="add_client" value="1">
  <div class="col-md-6">
    <textarea name="client_names" class="form-control" rows="4" placeholder="One client per line" required></textarea>
  </div>
  <div class="col-md-2"><button class="btn btn-success w-100">Add</button></div>
</form>
<ul class="list-group mb-2" id="client-list">
  <?php foreach ($clients as $c): ?>
  <li class="list-group-item" data-id="<?= $c['id'] ?>">
    <form method="post" class="row g-2 align-items-center">
      <div class="col-md-3"><input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>"></div>
      <div class="col-md-2">
        <span class="client-priority <?= strtolower($c['priority'] ?? '') ?>"><?= $c['priority'] ? htmlspecialchars($c['priority']) : '&nbsp;' ?></span>
      </div>
      <div class="col-md-1">
        <?php if ($c['progress_percent'] !== null): ?>
          <span><?= number_format($c['progress_percent'], 2) ?>%</span>
        <?php endif; ?>
      </div>
      <div class="col-md-2"><button class="btn btn-primary w-100 btn-sm" name="save_client" value="<?= $c['id'] ?>">Save</button></div>
      <?php $archived = ($c['active_count'] == 0 && $c['archived_count'] > 0); ?>
      <div class="col-md-2">
        <?php if ($archived): ?>
        <button class="btn btn-warning w-100 btn-sm" name="unarchive_client" value="<?= $c['id'] ?>" onclick="return confirm('Unarchive all tasks for this client?')">Unarchive</button>
        <?php else: ?>
        <button class="btn btn-warning w-100 btn-sm" name="archive_client" value="<?= $c['id'] ?>" onclick="return confirm('Archive all tasks for this client?')">Archive</button>
        <?php endif; ?>
      </div>
      <div class="col-md-2"><button class="btn btn-danger w-100 btn-sm" name="delete_client" value="<?= $c['id'] ?>" onclick="return confirm('Delete client?')">Delete</button></div>
    </form>
  </li>
  <?php endforeach; ?>
</ul>
<button id="saveClientOrder" class="btn btn-success btn-sm mb-5">Save Order</button>
<div class="mb-5">
  <form method="post" class="d-inline">
    <input type="hidden" name="import_priorities" value="1">
    <button class="btn btn-info btn-sm ms-2">Import Priorities &amp; Sorting</button>
  </form>
  <a href="index.php" class="btn btn-dark btn-sm ms-2">Back to Tasks</a>
</div>

<h4>Status</h4>
<table class="table table-borderless w-auto">
  <thead><tr><th>Team Member</th><th>Achieved %</th></tr></thead>
  <tbody>
    <?php foreach ($workerTotals as $name => $pct):
        $ratio = $pct / 100;
        if ($ratio >= 0.75) $class = 'priority-critical';
        elseif ($ratio >= 0.5) $class = 'priority-high';
        elseif ($ratio >= 0.25) $class = 'priority-intermed';
        else $class = 'priority-low';
    ?>
    <tr class="<?= $class ?>"><td><?= htmlspecialchars($name) ?></td><td><?= number_format($pct, 2) ?>%</td></tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
new Sortable(document.getElementById('user-list'), {animation:150});
new Sortable(document.getElementById('client-list'), {animation:150});
document.getElementById('saveUserOrder').addEventListener('click', ()=>{
  const order = Array.from(document.querySelectorAll('#user-list li')).map((el,idx)=>el.dataset.id+':'+idx).join(',');
  fetch('admin.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'reorder_users=1&order='+order}).then(()=>location.reload());
});
document.getElementById('saveClientOrder').addEventListener('click', ()=>{
  const order = Array.from(document.querySelectorAll('#client-list li')).map((el,idx)=>el.dataset.id+':'+idx).join(',');
  fetch('admin.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'reorder_clients=1&order='+order}).then(()=>location.reload());
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
