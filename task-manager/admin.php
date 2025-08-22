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
        } elseif (isset($_POST['import_priorities'])) {
            $url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQiesZdFZ-jCIcuz5J53buN5ACEepvOiKcDDbn66fQpxEe9dfKBL86FLXIPH1dYUR9N6pFaUcjPw0CX/pub?gid=542324811&single=true&output=csv';
            $csv = @file_get_contents($url);
            if ($csv !== false) {
                $rows = array_map('str_getcsv', explode("\n", trim($csv)));
                $stmt = $pdo->prepare('UPDATE clients SET priority=? WHERE name=?');
                foreach ($rows as $i => $row) {
                    if ($i === 0) continue; // skip header
                    if (count($row) >= 18) {
                        $client = trim($row[16]);
                        $prio = trim($row[17]);
                        if ($client !== '' && $prio !== '') {
                            $stmt->execute([$prio, $client]);
                        }
                    }
                }
            }
        }
    }

    $users = $pdo->query('SELECT id, username FROM users ORDER BY sort_order, username')->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query('SELECT id, name, priority FROM clients ORDER BY sort_order, name')->fetchAll(PDO::FETCH_ASSOC);
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
  <div class="col-md-6"><input type="text" name="client_name" class="form-control" placeholder="Client name" required></div>
  <div class="col-md-2"><button class="btn btn-success w-100">Add</button></div>
</form>
<ul class="list-group mb-2" id="client-list">
  <?php foreach ($clients as $c): ?>
  <li class="list-group-item" data-id="<?= $c['id'] ?>">
    <form method="post" class="row g-2 align-items-center">
      <div class="col-md-6"><input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>"></div>
      <div class="col-md-2">
        <?php if (!empty($c['priority'])): ?>
          <span class="client-priority <?= strtolower($c['priority']) ?>"><?= htmlspecialchars($c['priority']) ?></span>
        <?php endif; ?>
      </div>
      <div class="col-md-2"><button class="btn btn-primary w-100 btn-sm" name="save_client" value="<?= $c['id'] ?>">Save</button></div>
      <div class="col-md-2"><button class="btn btn-danger w-100 btn-sm" name="delete_client" value="<?= $c['id'] ?>" onclick="return confirm('Delete client?')">Delete</button></div>
    </form>
  </li>
  <?php endforeach; ?>
</ul>
<button id="saveClientOrder" class="btn btn-success btn-sm mb-5">Save Order</button>
<form method="post" class="d-inline">
  <input type="hidden" name="import_priorities" value="1">
  <button class="btn btn-info btn-sm mb-5 ms-2">Import Priorities</button>
</form>

<a href="index.php" class="btn btn-secondary">Back to Tasks</a>

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
