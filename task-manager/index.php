<?php
session_start();
require __DIR__ . '/config.php';

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

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
    $stmt->execute([$user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
    } else {
        $loginError = 'Invalid username or password.';
    }
}

if (!isset($_SESSION['user_id'])) {
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
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" name="login" class="btn btn-primary">Login</button>
    </form>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

function next_due_date($current, $recurrence) {
    $date = new DateTime($current);
    switch ($recurrence) {
        case 'everyday':
            $date->modify('+1 day');
            break;
        case 'working':
            do {
                $date->modify('+1 day');
            } while (in_array($date->format('w'), [5,6]));
            break;
        default:
            if (strpos($recurrence, 'custom:') === 0) {
                $days = explode(',', substr($recurrence, 7));
                $map = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6];
                $targets = array_map(fn($d) => $map[$d], $days);
                do {
                    $date->modify('+1 day');
                } while (!in_array((int)$date->format('w'), $targets));
            } else { // weekly
                $date->modify('+7 day');
            }
            break;
    }
    return $date->format('Y-m-d');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['reorder'])) {
        $order = $_POST['order'] ?? '';
        $stmt = $pdo->prepare('UPDATE tasks SET order_index=? WHERE id=?');
        foreach (explode(',', $order) as $pair) {
            if (!$pair) continue;
            [$id, $idx] = explode(':', $pair);
            $stmt->execute([(int)$idx, (int)$id]);
        }
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
        $titleTask = trim($_POST['title'] ?? '');
        $assigned = (int)($_POST['assigned'] ?? 0);
        $due = $_POST['due_date'] ?? date('Y-m-d');
        $priority = $_POST['priority'] ?? 'Normal';
        $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
        $desc = trim($_POST['description'] ?? '');
        $recurrence = $_POST['recurrence'] ?? 'none';
        if ($recurrence === 'custom') {
            $days = $_POST['days'] ?? [];
            $recurrence = 'custom:' . implode(',', array_map('htmlspecialchars', $days));
        }
        if ($titleTask !== '' && $assigned) {
            $orderIdx = strtotime($due);
            $stmt = $pdo->prepare('INSERT INTO tasks (title, description, assigned_to, client_id, priority, due_date, recurrence, order_index) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$titleTask, $desc, $assigned, $clientId, $priority, $due, $recurrence, $orderIdx]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
        $taskId = (int)$_POST['update_task'];
        $desc = trim($_POST['description'] ?? '');
        $assigned = (int)($_POST['assigned'] ?? 0);
        $priority = $_POST['priority'] ?? 'Normal';
        $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
        $titleTask = trim($_POST['title'] ?? '');
        $due = $_POST['due_date'] ?? date('Y-m-d');
        if ($taskId && $assigned && $titleTask !== '') {
            $stmtCur = $pdo->prepare('SELECT order_index FROM tasks WHERE id=?');
            $stmtCur->execute([$taskId]);
            $currIdx = (int)$stmtCur->fetchColumn();
            $orderIdx = $currIdx > 100000 ? strtotime($due) : $currIdx;
            $stmt = $pdo->prepare('UPDATE tasks SET title=?, description=?, assigned_to=?, client_id=?, priority=?, due_date=?, order_index=? WHERE id=?');
            $stmt->execute([$titleTask, $desc, $assigned, $clientId, $priority, $due, $orderIdx, $taskId]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_complete'])) {
        $taskId = (int)$_POST['toggle_complete'];
        $stmt = $pdo->prepare('SELECT recurrence, due_date FROM tasks WHERE id=?');
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($task) {
            if ($task['recurrence'] !== 'none') {
                $next = next_due_date($task['due_date'], $task['recurrence']);
                $stmt2 = $pdo->prepare('UPDATE tasks SET due_date=?, status="pending" WHERE id=?');
                $stmt2->execute([$next, $taskId]);
            } else {
                $status = isset($_POST['completed']) ? 'done' : 'pending';
                $stmt2 = $pdo->prepare('UPDATE tasks SET status=? WHERE id=?');
                $stmt2->execute([$status, $taskId]);
            }
        }
    }

    $filterUser = isset($_GET['user']) ? (int)$_GET['user'] : null;
    $filterClient = isset($_GET['client']) ? (int)$_GET['client'] : null;

    $users = $pdo->query('SELECT id, username FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $today = date('Y-m-d');
    $cond = [];
    $params = [];
    if ($filterUser) { $cond[] = 't.assigned_to = ?'; $params[] = $filterUser; }
    if ($filterClient) { $cond[] = 't.client_id = ?'; $params[] = $filterClient; }
    $where = $cond ? ' AND ' . implode(' AND ', $cond) : '';

    $todayTasks = $pdo->prepare('SELECT t.*, u.username, c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE due_date <= ?' . $where . ' ORDER BY order_index, due_date');
    $todayTasks->execute(array_merge([$today], $params));
    $todayTasks = $todayTasks->fetchAll(PDO::FETCH_ASSOC);
    $upcomingTasks = $pdo->prepare('SELECT t.*, u.username, c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE due_date > ?' . $where . ' ORDER BY order_index, due_date');
    $upcomingTasks->execute(array_merge([$today], $params));
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
<div class="d-flex justify-content-between mb-3">
  <?php if ($_SESSION['username'] === ADMIN_USER): ?>
  <a href="admin.php" class="btn btn-secondary">Admin Panel</a>
  <?php endif; ?>
  <a href="?logout=1" class="btn btn-outline-secondary">Logout</a>
 </div>
<div class="row">
  <div class="col-md-3">
    <ul class="list-group mb-4">
      <li class="list-group-item <?= !$filterClient && !$filterUser ? 'active' : '' ?>">
        <a href="index.php" class="text-decoration-none<?= !$filterClient && !$filterUser ? ' text-white' : '' ?>">All Tasks</a>
      </li>
    </ul>
    <h4>Clients</h4>
    <ul class="list-group mb-4">
      <?php foreach ($clients as $client): ?>
      <li class="list-group-item <?= $filterClient == $client['id'] ? 'active' : '' ?>">
        <a href="index.php?client=<?= $client['id'] ?>" class="text-decoration-none<?= $filterClient == $client['id'] ? ' text-white' : '' ?>"><?= htmlspecialchars($client['name']) ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
    <h4>Team Members</h4>
    <ul class="list-group">
      <?php foreach ($users as $user): ?>
      <li class="list-group-item <?= $filterUser == $user['id'] ? 'active' : '' ?>">
        <a href="index.php?user=<?= $user['id'] ?>" class="text-decoration-none<?= $filterUser == $user['id'] ? ' text-white' : '' ?>"><?= htmlspecialchars($user['username']) ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="col-md-9">
    <button class="btn btn-success mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#addTask">New Task</button>
    <div id="addTask" class="collapse mb-4">
      <form method="post" class="row g-2">
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
        <div class="col-md-2 mt-2">
          <select name="recurrence" id="recurrence" class="form-select">
            <option value="none" selected>No repeat</option>
            <option value="everyday">Every Day</option>
            <option value="working">Every Working Day (Sun-Thu)</option>
            <option value="custom">Specific Days</option>
          </select>
        </div>
        <div class="col-md-12 mt-2 d-none" id="day-select">
          <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
          <label class="me-2"><input type="checkbox" name="days[]" value="<?= $d ?>"> <?= $d ?></label>
          <?php endforeach; ?>
        </div>
        <div class="col-md-12 mt-2"><textarea name="description" class="form-control" placeholder="Description"></textarea></div>
        <div class="col-md-2 mt-2"><button class="btn btn-success w-100">Add</button></div>
      </form>
    </div>
    <h3>Today's & Overdue Tasks</h3>
    <ul id="today-list" class="list-group mb-4">
      <?php foreach ($todayTasks as $task): ?>
      <li class="list-group-item d-flex align-items-start <?= $task['status']==='done'?'opacity-50':'' ?>" draggable="true" data-task-id="<?= $task['id'] ?>">
        <form method="post" class="me-2">
          <input type="hidden" name="toggle_complete" value="<?= $task['id'] ?>">
          <input type="checkbox" name="completed" onchange="this.form.submit()" <?= $task['status']==='done'?'checked':'' ?>>
        </form>
        <div class="flex-grow-1">
          <div data-bs-toggle="collapse" data-bs-target="#task-<?= $task['id'] ?>" class="task-header">
            <?php if ($task['client_name']) echo '<strong>'.htmlspecialchars($task['client_name']).'</strong> '; ?><?= htmlspecialchars($task['title']) ?><br>
            <small>Assigned to <?= htmlspecialchars($task['username']) ?> &mdash; <?= htmlspecialchars($task['priority']) ?> &mdash; due <?= htmlspecialchars($task['due_date']) ?></small>
          </div>
          <div class="collapse mt-2" id="task-<?= $task['id'] ?>">
            <form method="post" class="row g-2 mt-2">
              <input type="hidden" name="update_task" value="<?= $task['id'] ?>">
              <div class="col-md-6"><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($task['title']) ?>"></div>
              <div class="col-md-6"><input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($task['due_date']) ?>"></div>
              <div class="col-12 mt-2"><textarea name="description" class="form-control" placeholder="Description"><?= htmlspecialchars($task['description'] ?? '') ?></textarea></div>
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
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <h3>Upcoming Tasks</h3>
    <ul id="upcoming-list" class="list-group mb-4">
      <?php foreach ($upcomingTasks as $task): ?>
      <li class="list-group-item d-flex align-items-start <?= $task['status']==='done'?'opacity-50':'' ?>" draggable="true" data-task-id="<?= $task['id'] ?>">
        <form method="post" class="me-2">
          <input type="hidden" name="toggle_complete" value="<?= $task['id'] ?>">
          <input type="checkbox" name="completed" onchange="this.form.submit()" <?= $task['status']==='done'?'checked':'' ?>>
        </form>
        <div class="flex-grow-1">
          <div data-bs-toggle="collapse" data-bs-target="#task-<?= $task['id'] ?>" class="task-header">
            <?php if ($task['client_name']) echo '<strong>'.htmlspecialchars($task['client_name']).'</strong> '; ?><?= htmlspecialchars($task['title']) ?><br>
            <small>Assigned to <?= htmlspecialchars($task['username']) ?> &mdash; <?= htmlspecialchars($task['priority']) ?> &mdash; due <?= htmlspecialchars($task['due_date']) ?></small>
          </div>
          <div class="collapse mt-2" id="task-<?= $task['id'] ?>">
            <form method="post" class="row g-2 mt-2">
              <input type="hidden" name="update_task" value="<?= $task['id'] ?>">
              <div class="col-md-6"><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($task['title']) ?>"></div>
              <div class="col-md-6"><input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($task['due_date']) ?>"></div>
              <div class="col-12 mt-2"><textarea name="description" class="form-control" placeholder="Description"><?= htmlspecialchars($task['description'] ?? '') ?></textarea></div>
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
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function saveOrder(id){
  const order = Array.from(document.getElementById(id).children).map((el,idx)=>el.dataset.taskId+':'+idx).join(',');
  fetch('index.php?reorder=1', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'order='+order});
}
new Sortable(document.getElementById('today-list'), {group:'tasks', animation:150, onEnd:()=>saveOrder('today-list')});
new Sortable(document.getElementById('upcoming-list'), {group:'tasks', animation:150, onEnd:()=>saveOrder('upcoming-list')});
document.getElementById('recurrence').addEventListener('change', function(){
  document.getElementById('day-select').classList.toggle('d-none', this.value !== 'custom');
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
