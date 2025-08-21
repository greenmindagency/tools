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
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username=?');
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
    if (strpos($recurrence, 'interval:') === 0) {
        [, $cnt, $unit] = explode(':', $recurrence);
        $date->modify('+' . (int)$cnt . ' ' . $unit);
    } else {
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
                    $days = explode(',', substr($recurrence,7));
                    $map = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6];
                    $targets = array_map(fn($d)=>$map[$d], $days);
                    do {
                        $date->modify('+1 day');
                    } while (!in_array((int)$date->format('w'), $targets));
                } else {
                    $date->modify('+7 day');
                }
                break;
        }
    }
    return $date->format('Y-m-d');
}
function render_task($t, $users, $clients) {
    $today = date('Y-m-d');
    $overdue = $t['due_date'] < $today && $t['status'] !== 'done';
    ob_start();
    ?>
    <li class="list-group-item d-flex align-items-start <?= $t['status']==='done'?'opacity-50':'' ?> <?= $overdue?'border border-danger':'' ?>" data-task-id="<?= $t['id'] ?>" data-recurrence="<?= htmlspecialchars($t['recurrence']) ?>">
      <form method="post" class="me-2 complete-form" data-bs-toggle="tooltip" title="Complete">
        <input type="hidden" name="toggle_complete" value="<?= $t['id'] ?>">
        <input type="checkbox" class="complete-checkbox" name="completed" <?= $t['status']==='done'?'checked':'' ?>>
      </form>
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between">
          <div class="task-main" data-bs-toggle="collapse" data-bs-target="#task-<?= $t['id'] ?>">
            <strong class="client"><?= $t['client_name'] ? htmlspecialchars($t['client_name']) : 'Others' ?></strong>
            <span class="task-title-text"><?= htmlspecialchars($t['title']) ?></span>
            <div class="small text-muted due-date"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($t['due_date']) ?></div>
          </div>
          <div class="text-end ms-2">
            <div class="mb-1">
              <button type="button" class="btn btn-success btn-sm save-btn d-none" data-id="<?= $t['id'] ?>" title="Save" data-bs-toggle="tooltip"><i class="bi bi-check"></i></button>
              <button type="button" class="btn btn-light btn-sm edit-btn" data-id="<?= $t['id'] ?>" title="Edit" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></button>
              <button type="button" class="btn btn-warning btn-sm archive-btn" data-id="<?= $t['id'] ?>" title="Archive" data-bs-toggle="tooltip"><i class="bi bi-archive"></i></button>
            </div>
            <?php $pc = strtolower($t['priority']); ?>
            <div><span class="fw-bold assignee"><?= htmlspecialchars($t['username']) ?></span> - <span class="priority <?= $pc ?>"><?= htmlspecialchars($t['priority']) ?></span></div>
          </div>
        </div>
        <div class="collapse mt-2" id="task-<?= $t['id'] ?>">
          <div class="description mb-2"><?= nl2br(htmlspecialchars($t['description'])) ?></div>
          <form method="post" class="row g-2 mt-2 task-form d-none">
            <input type="hidden" name="update_task" value="<?= $t['id'] ?>">
            <div class="col-12"><div class="form-control editable" data-field="title" contenteditable="true"><?= htmlspecialchars($t['title']) ?></div></div>
            <div class="col-12 mt-2"><div class="form-control editable" data-field="description" contenteditable="true"><?= htmlspecialchars($t['description'] ?? '') ?></div></div>
            <div class="col-md-3"><input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($t['due_date']) ?>"></div>
            <div class="col-md-3">
              <select name="assigned" class="form-select">
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $t['assigned_to']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <select name="client_id" class="form-select">
                <option value="">Client</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $t['client_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <?php $p=$t['priority']; ?>
              <select name="priority" class="form-select">
                <option value="Low" <?= $p==='Low'?'selected':'' ?>>Low</option>
                <option value="Normal" <?= $p==='Normal'?'selected':'' ?>>Normal</option>
                <option value="High" <?= $p==='High'?'selected':'' ?>>High</option>
              </select>
            </div>
            <div class="col-md-3 mt-2">
              <?php $r=$t['recurrence']; $rcount=1;$runit='week'; if(strpos($r,'interval:')===0){[, $rcount,$runit]=explode(':',$r);} ?>
              <select name="recurrence" class="form-select recurrence-select">
                <option value="none" <?= $r==='none'?'selected':'' ?>>No repeat</option>
                <option value="everyday" <?= $r==='everyday'?'selected':'' ?>>Every Day</option>
                <option value="working" <?= $r==='working'?'selected':'' ?>>Working Days</option>
                <option value="interval" <?= strpos($r,'interval:')===0?'selected':'' ?>>Every N...</option>
                <option value="custom" <?= strpos($r,'custom:')===0?'selected':'' ?>>Specific Days</option>
              </select>
            </div>
            <div class="col-md-3 mt-2 recurrence-interval <?= strpos($r,'interval:')===0?'':'d-none' ?>">
              <input type="number" min="1" name="interval_count" value="<?= $rcount ?>" class="form-control">
            </div>
            <div class="col-md-3 mt-2 recurrence-unit <?= strpos($r,'interval:')===0?'':'d-none' ?>">
              <select name="interval_unit" class="form-select">
                <option value="week" <?= $runit==='week'?'selected':'' ?>>week(s)</option>
                <option value="month" <?= $runit==='month'?'selected':'' ?>>month(s)</option>
                <option value="year" <?= $runit==='year'?'selected':'' ?>>year(s)</option>
              </select>
            </div>
            <div class="col-md-12 mt-2 recurrence-days <?= strpos($r,'custom:')===0?'':'d-none' ?>">
              <?php $selDays=strpos($r,'custom:')===0?explode(',',substr($r,7)):[]; foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
              <label class="me-2"><input type="checkbox" name="days[]" value="<?= $d ?>" <?= in_array($d,$selDays)?'checked':'' ?>> <?= $d ?></label>
              <?php endforeach; ?>
            </div>
            <div class="col-md-3 mt-2">
              <select class="form-select quick-date">
                <option value="">Quick date</option>
                <option value="tomorrow">Tomorrow</option>
                <option value="nextweek">Next Week</option>
                <option value="nextmonth">Next Month</option>
              </select>
            </div>
          </form>
          <button class="btn btn-outline-secondary btn-sm add-subtask-toggle mt-2 d-none" type="button" data-bs-toggle="collapse" data-bs-target="#subtask-form-<?= $t['id'] ?>">Add Subtask</button>
          <div class="collapse" id="subtask-form-<?= $t['id'] ?>">
            <form method="post" class="row g-2 mt-2 ajax">
              <input type="hidden" name="add_task" value="1">
              <input type="hidden" name="parent_id" value="<?= $t['id'] ?>">
              <div class="col-12"><input type="text" name="title" class="form-control" placeholder="Subtask title" required></div>
              <div class="col-12"><textarea name="description" class="form-control" placeholder="Description"></textarea></div>
              <div class="col-md-3"><input type="date" name="due_date" class="form-control" required></div>
              <div class="col-md-3">
                <select class="form-select quick-date">
                  <option value="">Quick date</option>
                  <option value="tomorrow">Tomorrow</option>
                  <option value="nextweek">Next Week</option>
                  <option value="nextmonth">Next Month</option>
                </select>
              </div>
              <div class="col-md-3">
                <select name="assigned" class="form-select" required>
                  <option value="">Assign to</option>
                  <?php foreach ($users as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <select name="client_id" class="form-select">
                  <option value="">Client</option>
                  <?php foreach ($clients as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <select name="priority" class="form-select">
                  <option value="Low">Low</option>
                  <option value="Normal" selected>Normal</option>
                  <option value="High">High</option>
                </select>
              </div>
              <div class="col-md-2 mt-2"><button class="btn btn-success w-100">Add</button></div>
            </form>
          </div>
          <?php
          global $pdo;
          $childStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE parent_id=? ORDER BY t.order_index, t.due_date');
          $childStmt->execute([$t['id']]);
          $children = $childStmt->fetchAll(PDO::FETCH_ASSOC);
          if ($children): ?>
          <ul class="list-group ms-4 mt-2">
            <?php foreach ($children as $ch): ?>
            <?= render_task($ch, $users, $clients); ?>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
      </div>
    </li>
    <?php
    return ob_get_clean();
}



try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['reorder'])) {
        $order = $_POST['order'] ?? '';
        $stmt = $pdo->prepare('UPDATE tasks SET order_index=? WHERE id=?');
        foreach (explode(',', $order) as $pair) {
            if (!$pair) continue;
            [$id,$idx] = explode(':',$pair);
            $stmt->execute([(int)$idx,(int)$id]);
        }
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
        $title = trim($_POST['title'] ?? '');
        $assigned = (int)($_POST['assigned'] ?? 0);
        $due = $_POST['due_date'] ?? date('Y-m-d');
        $priority = $_POST['priority'] ?? 'Normal';
        $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
        $desc = trim($_POST['description'] ?? '');
        $rec = $_POST['recurrence'] ?? 'none';
        $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($rec === 'custom') {
            $days = $_POST['days'] ?? [];
            $rec = 'custom:' . implode(',', array_map('htmlspecialchars',$days));
        } elseif ($rec === 'interval') {
            $cnt = max(1,(int)($_POST['interval_count'] ?? 1));
            $unit = $_POST['interval_unit'] ?? 'week';
            $rec = 'interval:' . $cnt . ':' . $unit;
        }
        if ($title !== '' && $assigned) {
            $orderIdx = strtotime($due);
            $stmt = $pdo->prepare('INSERT INTO tasks (title,description,assigned_to,client_id,priority,due_date,recurrence,parent_id,order_index) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$title,$desc,$assigned,$clientId,$priority,$due,$rec,$parentId,$orderIdx]);
        }
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
        $id = (int)$_POST['update_task'];
        $desc = trim($_POST['description'] ?? '');
        $assigned = (int)($_POST['assigned'] ?? 0);
        $priority = $_POST['priority'] ?? 'Normal';
        $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
        $title = trim($_POST['title'] ?? '');
        $due = $_POST['due_date'] ?? date('Y-m-d');
        $rec = $_POST['recurrence'] ?? 'none';
        if ($rec === 'custom') {
            $days = $_POST['days'] ?? [];
            $rec = 'custom:' . implode(',', array_map('htmlspecialchars',$days));
        } elseif ($rec === 'interval') {
            $cnt = max(1,(int)($_POST['interval_count'] ?? 1));
            $unit = $_POST['interval_unit'] ?? 'week';
            $rec = 'interval:' . $cnt . ':' . $unit;
        }
        if ($id && $assigned && $title !== '') {
            $stmtCur = $pdo->prepare('SELECT order_index FROM tasks WHERE id=?');
            $stmtCur->execute([$id]);
            $currIdx = (int)$stmtCur->fetchColumn();
            $orderIdx = $currIdx > 100000 ? strtotime($due) : $currIdx;
            $stmt = $pdo->prepare('UPDATE tasks SET title=?,description=?,assigned_to=?,client_id=?,priority=?,due_date=?,recurrence=?,order_index=? WHERE id=?');
            $stmt->execute([$title,$desc,$assigned,$clientId,$priority,$due,$rec,$orderIdx,$id]);
        }
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_complete'])) {
        $id = (int)$_POST['toggle_complete'];
        $stmt = $pdo->prepare('SELECT recurrence,due_date FROM tasks WHERE id=?');
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($task) {
              if ($task['recurrence'] !== 'none') {
                  $next = next_due_date($task['due_date'],$task['recurrence']);
                  $stmt2 = $pdo->prepare('UPDATE tasks SET due_date=?, status="pending" WHERE id=?');
                  $stmt2->execute([$next,$id]);
                  echo $next;
              } else {
                  $status = isset($_POST['completed']) ? 'done' : 'pending';
                  $stmt2 = $pdo->prepare('UPDATE tasks SET status=? WHERE id=?');
                  $stmt2->execute([$status,$id]);
                  echo $status;
              }
          }
          exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_task'])) {
        $id = (int)$_POST['archive_task'];
        $stmt = $pdo->prepare('UPDATE tasks SET status="archived" WHERE id=?');
        $stmt->execute([$id]);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unarchive_task'])) {
        $id = (int)$_POST['unarchive_task'];
        $stmt = $pdo->prepare('UPDATE tasks SET status="pending" WHERE id=?');
        $stmt->execute([$id]);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
        $id = (int)$_POST['delete_task'];
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id=?');
        $stmt->execute([$id]);
        exit;
    }

    $filterUser = isset($_GET['user']) ? (int)$_GET['user'] : null;
    $filterClient = isset($_GET['client']) ? (int)$_GET['client'] : null;
    $filterArchived = isset($_GET['archived']);

    $users = $pdo->query('SELECT id,username FROM users ORDER BY sort_order, username')->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query('SELECT id,name FROM clients ORDER BY sort_order, name')->fetchAll(PDO::FETCH_ASSOC);

    $cond = [];
    $params = [];
    if ($filterUser) { $cond[] = 't.assigned_to=?'; $params[] = $filterUser; }
    if ($filterClient) { $cond[] = 't.client_id=?'; $params[] = $filterClient; }
    if ($filterArchived) { $cond[] = 't.status="archived"'; } else { $cond[] = 't.status!="archived"'; }
    $where = $cond ? ' AND '.implode(' AND ',$cond) : '';
    $order = (!$filterUser && !$filterClient && !$filterArchived) ? 'ORDER BY order_index, due_date' : 'ORDER BY due_date';

    $today = date('Y-m-d');
    if (!$filterUser && !$filterClient && !$filterArchived) {
        $allStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE parent_id IS NULL'.$where.' '.$order);
        $allStmt->execute($params);
        $allTasks = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($filterArchived) {
        $archivedStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE parent_id IS NULL'.$where.' '.$order);
        $archivedStmt->execute($params);
        $archivedTasks = $archivedStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $todayStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE parent_id IS NULL AND due_date <= ?'.$where.' '.$order);
        $todayStmt->execute(array_merge([$today],$params));
        $todayTasks = $todayStmt->fetchAll(PDO::FETCH_ASSOC);
        $upcomingStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE parent_id IS NULL AND due_date > ?'.$where.' '.$order);
        $upcomingStmt->execute(array_merge([$today],$params));
        $upcomingTasks = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
<div class="row">
  <div class="col-md-3">
    <ul class="list-group mb-4">
      <li class="list-group-item <?= !$filterClient && !$filterUser && !$filterArchived ? 'active' : '' ?>">
        <a href="index.php" class="text-decoration-none<?= !$filterClient && !$filterUser && !$filterArchived ? ' text-white' : '' ?>">All Tasks</a>
      </li>
      <li class="list-group-item <?= $filterArchived ? 'active' : '' ?>">
        <a href="index.php?archived=1" class="text-decoration-none<?= $filterArchived ? ' text-white' : '' ?>">Archive</a>
      </li>
    </ul>
    <h4>Clients</h4>
    <ul class="list-group mb-4">
      <?php foreach ($clients as $c): ?>
      <li class="list-group-item <?= $filterClient == $c['id'] ? 'active' : '' ?>">
        <a href="index.php?client=<?= $c['id'] ?>" class="text-decoration-none<?= $filterClient == $c['id'] ? ' text-white' : '' ?>"><?= htmlspecialchars($c['name']) ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
    <h4>Team Members</h4>
    <ul class="list-group">
      <?php foreach ($users as $u): ?>
      <li class="list-group-item <?= $filterUser == $u['id'] ? 'active' : '' ?>">
        <a href="index.php?user=<?= $u['id'] ?>" class="text-decoration-none<?= $filterUser == $u['id'] ? ' text-white' : '' ?>"><?= htmlspecialchars($u['username']) ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between mb-3">
      <div>
        <button id="addBtn" class="btn btn-success btn-sm" data-bs-toggle="collapse" data-bs-target="#addTask" title="Add Task"><i class="bi bi-plus"></i></button>
        <?php if(!$filterUser && !$filterClient && !$filterArchived): ?>
        <button id="saveOrderBtn" class="btn btn-primary btn-sm ms-2">Save Order</button>
        <?php endif; ?>
      </div>
      <div>
        <a href="admin.php" class="btn btn-secondary btn-sm me-2" data-bs-toggle="tooltip" title="Admin">Admin</a>
        <a href="?logout=1" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Logout">Logout</a>
      </div>
    </div>
    <div id="addTask" class="collapse mb-4">
      <form method="post" class="row g-2 ajax">
        <input type="hidden" name="add_task" value="1">
        <div class="col-12"><input type="text" name="title" class="form-control" placeholder="Task title" required></div>
        <div class="col-12"><textarea name="description" class="form-control" placeholder="Description"></textarea></div>
        <div class="col-md-3"><input type="date" name="due_date" class="form-control" required></div>
        <div class="col-md-3">
          <select class="form-select quick-date">
            <option value="">Quick date</option>
            <option value="tomorrow">Tomorrow</option>
            <option value="nextweek">Next Week</option>
            <option value="nextmonth">Next Month</option>
          </select>
        </div>
        <div class="col-md-3">
          <select name="assigned" class="form-select" required>
            <option value="">Assign to</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="client_id" class="form-select">
            <option value="">Client</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="priority" class="form-select">
            <option value="Low">Low</option>
            <option value="Normal" selected>Normal</option>
            <option value="High">High</option>
          </select>
        </div>
        <div class="col-md-3 mt-2">
          <select name="recurrence" id="recurrence" class="form-select">
            <option value="none" selected>No repeat</option>
            <option value="everyday">Every Day</option>
            <option value="working">Every Working Day (Sun-Thu)</option>
            <option value="interval">Every N...</option>
            <option value="custom">Specific Days</option>
          </select>
        </div>
        <div class="col-md-3 mt-2 d-none" id="interval-count">
          <input type="number" min="1" name="interval_count" value="1" class="form-control">
        </div>
        <div class="col-md-3 mt-2 d-none" id="interval-unit">
          <select name="interval_unit" class="form-select">
            <option value="week">week(s)</option>
            <option value="month">month(s)</option>
            <option value="year">year(s)</option>
          </select>
        </div>
        <div class="col-md-12 mt-2 d-none" id="day-select">
          <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
          <label class="me-2"><input type="checkbox" name="days[]" value="<?= $d ?>"> <?= $d ?></label>
          <?php endforeach; ?>
        </div>
        <div class="col-md-2 mt-2"><button class="btn btn-success w-100">Add</button></div>
      </form>
    </div>
    <?php if ($filterArchived): ?>
      <h3 class="mb-4">Archived Tasks</h3>
      <ul class="list-group" id="archived-list">
        <?php foreach ($archivedTasks as $t): ?>
        <li class="list-group-item d-flex align-items-start justify-content-between" data-task-id="<?= $t['id'] ?>">
          <div class="flex-grow-1">
            <strong><?= $t['client_name'] ? htmlspecialchars($t['client_name']) : 'Others' ?></strong> <?= htmlspecialchars($t['title']) ?>
            <?php if ($t['description']) echo '<div class="text-muted small">'.htmlspecialchars($t['description']).'</div>'; ?>
            <small><?= htmlspecialchars($t['username']) ?> — <?= htmlspecialchars($t['priority']) ?> — <?= htmlspecialchars($t['due_date']) ?></small>
          </div>
          <div class="ms-2 text-nowrap">
            <button type="button" class="btn btn-success btn-sm unarchive-btn" data-id="<?= $t['id'] ?>" title="Unarchive" data-bs-toggle="tooltip"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button type="button" class="btn btn-danger btn-sm delete-btn" data-id="<?= $t['id'] ?>" title="Delete" data-bs-toggle="tooltip"><i class="bi bi-trash"></i></button>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php elseif (!$filterUser && !$filterClient): ?>
      <ul id="all-list" class="list-group mb-4">
        <?php foreach ($allTasks as $t): ?>
        <?= render_task($t, $users, $clients); ?>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <h3 class="mb-4">Today's & Overdue Tasks</h3>
      <ul id="today-list" class="list-group mb-4">
        <?php foreach ($todayTasks as $t): ?>
        <?= render_task($t, $users, $clients); ?>
        <?php endforeach; ?>
      </ul>
      <h3 class="mb-4">Upcoming Tasks</h3>
      <ul id="upcoming-list" class="list-group mb-4">
        <?php foreach ($upcomingTasks as $t): ?>
        <?= render_task($t, $users, $clients); ?>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function saveOrder(id){
  const order = Array.from(document.getElementById(id).children).map((el,idx)=>el.dataset.taskId+':'+idx).join(',');
  return fetch('index.php?reorder=1', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'order='+order});
}
  <?php if(!$filterUser && !$filterClient && !$filterArchived): ?>
  new Sortable(document.getElementById('all-list'), {animation:150, handle:'.task-main'});
  <?php endif; ?>

document.getElementById('recurrence').addEventListener('change', function(){
  document.getElementById('day-select').classList.toggle('d-none', this.value !== 'custom');
  document.getElementById('interval-count').classList.toggle('d-none', this.value !== 'interval');
  document.getElementById('interval-unit').classList.toggle('d-none', this.value !== 'interval');
});

document.querySelectorAll('form.ajax').forEach(f=>{
  f.addEventListener('submit', async e=>{
    e.preventDefault();
    const fd = new FormData(f);
    await fetch('index.php', {method:'POST', body:fd});
    if (fd.has('add_task')) {
      f.reset();
      showToast('Task added');
      location.reload();
    }
  });
});

document.querySelectorAll('.complete-checkbox').forEach(cb=>{
  cb.addEventListener('change', async ()=>{
    const form = cb.closest('form');
    const fd = new FormData(form);
    if(cb.checked) fd.set('completed','1');
    const res = await fetch('index.php', {method:'POST', body:fd});
    const text = (await res.text()).trim();
    const li = form.closest('li');
    if(/^\d{4}-\d{2}-\d{2}$/.test(text)){
      li.querySelector('.due-date').textContent = text;
      cb.checked = false;
      li.classList.remove('opacity-50');
    } else {
      li.classList.toggle('opacity-50', cb.checked);
    }
    showToast('Updated');
  });
});

document.querySelectorAll('.task-main').forEach(main=>{
  main.addEventListener('click', ()=>{
    const target = document.querySelector(main.dataset.bsTarget);
    document.querySelectorAll('.collapse.show').forEach(c=>{ if(c!==target) new bootstrap.Collapse(c,{toggle:false}).hide(); });
  });
});

document.querySelectorAll('.edit-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const id = btn.dataset.id;
    const li = btn.closest('li');
    const collapse = document.getElementById('task-'+id);
    const form = collapse.querySelector('form');
    const desc = collapse.querySelector('.description');
    const saveBtn = li.querySelector('.save-btn');
    const subBtn = li.querySelector('.add-subtask-toggle');
    document.querySelectorAll('.collapse.show').forEach(c=>{ if(c!==collapse) new bootstrap.Collapse(c,{toggle:false}).hide(); });
    form.classList.toggle('d-none');
    desc.classList.toggle('d-none');
    const editing = !form.classList.contains('d-none');
    saveBtn.classList.toggle('d-none', !editing);
    subBtn.classList.toggle('d-none', !editing);
    new bootstrap.Collapse(collapse, {toggle:false}).show();
  });
});

document.querySelectorAll('.save-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    const li = btn.closest('li');
    const collapse = document.getElementById('task-'+id);
    const form = collapse.querySelector('form');
    const fd = new FormData(form);
    fd.set('title', form.querySelector('[data-field=title]').textContent.trim());
    fd.set('description', form.querySelector('[data-field=description]').textContent.trim());
    await fetch('index.php', {method:'POST', body:fd});
    li.querySelector('.task-title-text').textContent = form.querySelector('[data-field=title]').textContent.trim();
    li.querySelector('.description').innerHTML = form.querySelector('[data-field=description]').textContent.replace(/\n/g,'<br>');
    li.querySelector('.due-date').textContent = form.querySelector('input[name=due_date]').value;
    li.querySelector('.assignee').textContent = form.querySelector('select[name=assigned]').selectedOptions[0].textContent;
    const clientSel = form.querySelector('select[name=client_id]');
    li.querySelector('.client').textContent = clientSel.value ? clientSel.selectedOptions[0].textContent : 'Others';
    li.querySelector('.priority').textContent = form.querySelector('select[name=priority]').value;
    form.classList.add('d-none');
    collapse.querySelector('.description').classList.remove('d-none');
    btn.classList.add('d-none');
    li.querySelector('.add-subtask-toggle').classList.add('d-none');
    showToast('Task saved');
  });
});

document.querySelectorAll('.archive-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'archive_task='+id});
    btn.closest('li').remove();
    showToast('Task archived');
  });
});

document.querySelectorAll('.unarchive-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'unarchive_task='+id});
    btn.closest('li').remove();
    showToast('Task unarchived');
  });
});

document.querySelectorAll('.delete-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    if(!confirm('Delete this task?')) return;
    const id = btn.dataset.id;
    await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'delete_task='+id});
    btn.closest('li').remove();
    showToast('Task deleted');
  });
});

document.querySelectorAll('.recurrence-select').forEach(sel=>{
  sel.addEventListener('change', function(){
    const parent = this.closest('form');
    parent.querySelector('.recurrence-days')?.classList.toggle('d-none', this.value !== 'custom');
    parent.querySelector('.recurrence-interval')?.classList.toggle('d-none', this.value !== 'interval');
    parent.querySelector('.recurrence-unit')?.classList.toggle('d-none', this.value !== 'interval');
  });
});

function nextWorkingDay(date){
  while(date.getDay()==5 || date.getDay()==6){date.setDate(date.getDate()+1);}
  return date;
}

document.querySelectorAll('.quick-date').forEach(sel=>{
  sel.addEventListener('change', ()=>{
    const dateInput = sel.closest('form').querySelector('input[name=due_date]');
    const now = new Date();
    if(sel.value==='tomorrow') now.setDate(now.getDate()+1);
    if(sel.value==='nextweek') now.setDate(now.getDate()+7);
    if(sel.value==='nextmonth') now.setMonth(now.getMonth()+1);
    const d = nextWorkingDay(now);
    dateInput.value = d.toISOString().split('T')[0];
    sel.value='';
  });
});

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));
new bootstrap.Tooltip(document.getElementById('addBtn'));
document.getElementById('saveOrderBtn')?.addEventListener('click', ()=>{
  saveOrder('all-list').then(()=>showToast('Order saved'));
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
