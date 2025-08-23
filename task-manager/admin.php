<?php
session_start();
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] !== 1) {
    header('Location: index.php');
    exit;
}

function is_due_on($due, $recurrence, $target) {
    if ($recurrence === 'none') return $due === $target;
    if ($target < $due) return false;
    $dueDate = new DateTime($due);
    $targetDate = new DateTime($target);
    switch ($recurrence) {
        case 'everyday':
            return $targetDate >= $dueDate;
        case 'working':
            return $targetDate >= $dueDate && !in_array($targetDate->format('w'), ['5','6']);
        case 'weekly':
            return $targetDate >= $dueDate && $targetDate->format('w') === $dueDate->format('w');
        default:
            if (strpos($recurrence,'interval:')===0) {
                [, $cnt, $unit] = explode(':',$recurrence);
                $diff = $dueDate->diff($targetDate);
                if ($unit === 'day') return $diff->days % $cnt === 0;
                if ($unit === 'week') return ($diff->days/7) % $cnt === 0 && $targetDate->format('w') === $dueDate->format('w');
                if ($unit === 'month') { $months = $diff->m + $diff->y*12; return $months % $cnt === 0 && $targetDate->format('d') === $dueDate->format('d'); }
            } elseif (strpos($recurrence,'custom:')===0) {
                $days = explode(',', substr($recurrence,7));
                $map = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6];
                $dow = (int)$targetDate->format('w');
                return $targetDate >= $dueDate && in_array($dow, array_map(fn($d)=>$map[$d], $days));
            }
            return $targetDate >= $dueDate && $targetDate->format('w') === $dueDate->format('w');
    }
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
        } elseif (isset($_POST['update_task'])) {
            $id = (int)$_POST['update_task'];
            $due = $_POST['due_date'] ?? '';
            $assigned = (int)($_POST['assigned_to'] ?? 0) ?: null;
            $rec = $_POST['recurrence'] ?? 'none';
            if ($rec === 'custom') {
                $days = $_POST['days'] ?? [];
                $rec = 'custom:' . implode(',', array_map('htmlspecialchars',$days));
            } elseif ($rec === 'interval') {
                $cnt = max(1,(int)($_POST['interval_count'] ?? 1));
                $unit = $_POST['interval_unit'] ?? 'week';
                $rec = 'interval:' . $cnt . ':' . $unit;
            }
            if ($due !== '' && $id) {
                $stmt = $pdo->prepare('UPDATE tasks SET due_date=?, assigned_to=?, recurrence=? WHERE id=?');
                $stmt->execute([$due, $assigned, $rec, $id]);
            }
        }
    }

    $users = $pdo->query('SELECT id, username FROM users ORDER BY sort_order, username')->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query('SELECT c.id, c.name, c.priority, c.progress_percent, COALESCE(SUM(t.status != "archived"),0) AS active_count, COALESCE(SUM(t.status = "archived"),0) AS archived_count, COUNT(DISTINCT CASE WHEN t.status != "archived" THEN t.assigned_to END) AS member_count FROM clients c LEFT JOIN tasks t ON t.client_id=c.id GROUP BY c.id,c.name,c.priority,c.sort_order,c.progress_percent ORDER BY (c.priority IS NULL), c.sort_order, c.name')->fetchAll(PDO::FETCH_ASSOC);

    $clientWorkers = [];
    $workerMap = $pdo->query('SELECT t.client_id, u.username FROM tasks t JOIN users u ON t.assigned_to=u.id WHERE t.status!="archived" GROUP BY t.client_id,u.username')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($workerMap as $row) {
        $clientWorkers[$row['client_id']][] = $row['username'];
    }

    $clientMaxTasks = 0;
    $clientMaxMembers = 0;
    foreach ($clients as $c) {
        if ($c['active_count'] > $clientMaxTasks) $clientMaxTasks = $c['active_count'];
        if ($c['member_count'] > $clientMaxMembers) $clientMaxMembers = $c['member_count'];
    }
    $clientMaxTasks = max($clientMaxTasks, 1);
    $clientMaxMembers = max($clientMaxMembers, 1);

    $workerTotals = [];
    $clientStmt = $pdo->query('SELECT id, progress_percent FROM clients WHERE progress_percent IS NOT NULL');
    $uStmt = $pdo->prepare('SELECT u.username, COUNT(*) AS cnt FROM tasks t JOIN users u ON t.assigned_to=u.id WHERE t.client_id=? GROUP BY u.username');
    while ($c = $clientStmt->fetch(PDO::FETCH_ASSOC)) {
        $uStmt->execute([$c['id']]);
        $rows = $uStmt->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($rows, 'cnt'));
        if ($total === 0) continue;
        foreach ($rows as $r) {
            $share = $c['progress_percent'] * ($r['cnt'] / $total);
            $workerTotals[$r['username']] = ($workerTotals[$r['username']] ?? 0) + $share;
        }
    }

    $taskCountsStmt = $pdo->query('SELECT u.username, COUNT(*) AS cnt FROM tasks t JOIN users u ON t.assigned_to=u.id WHERE t.status!="archived" GROUP BY u.username');
    $workerTaskCounts = [];
    $totalTasks = 0;
    while ($row = $taskCountsStmt->fetch(PDO::FETCH_ASSOC)) {
        $workerTaskCounts[$row['username']] = $row['cnt'];
        $totalTasks += $row['cnt'];
        if (!isset($workerTotals[$row['username']])) {
            $workerTotals[$row['username']] = 0;
        }
    }

    $archivedCountsStmt = $pdo->query('SELECT u.username, COUNT(*) AS cnt FROM tasks t JOIN users u ON t.assigned_to=u.id WHERE t.status="archived" GROUP BY u.username');
    $workerArchivedCounts = [];
    while ($row = $archivedCountsStmt->fetch(PDO::FETCH_ASSOC)) {
        $workerArchivedCounts[$row['username']] = $row['cnt'];
        if (!isset($workerTotals[$row['username']])) {
            $workerTotals[$row['username']] = 0;
        }
        if (!isset($workerTaskCounts[$row['username']])) {
            $workerTaskCounts[$row['username']] = 0;
        }
    }

    $allWorkers = array_unique(array_merge(array_keys($workerTotals), array_keys($workerTaskCounts), array_keys($workerArchivedCounts)));
    foreach ($allWorkers as $name) {
        if (!isset($workerTotals[$name])) $workerTotals[$name] = 0;
        if (!isset($workerTaskCounts[$name])) $workerTaskCounts[$name] = 0;
        if (!isset($workerArchivedCounts[$name])) $workerArchivedCounts[$name] = 0;
    }

    $tasksPercent = [];
    foreach ($workerTaskCounts as $name => $cnt) {
        $tasksPercent[$name] = $totalTasks ? ($cnt / $totalTasks * 100) : 0;
    }
    foreach ($allWorkers as $name) {
        if (!isset($tasksPercent[$name])) $tasksPercent[$name] = 0;
    }
    $loadScores = [];
    foreach ($allWorkers as $name) {
        $ach = $workerTotals[$name];
        $taskPct = $tasksPercent[$name];
        $loadScores[$name] = ($ach + $taskPct) / 2;
    }
    $loadSum = array_sum($loadScores);
    $loadPercent = [];
    foreach ($loadScores as $name => $score) {
        $loadPercent[$name] = $loadSum ? ($score / $loadSum * 100) : 0;
    }
    arsort($loadPercent);

    $tasks = $pdo->query('SELECT t.id,t.title,t.due_date,t.recurrence,t.assigned_to,u.username,pt.title AS parent_title,c.name AS client_name,c.priority AS client_priority FROM tasks t LEFT JOIN users u ON t.assigned_to=u.id LEFT JOIN tasks pt ON t.parent_id=pt.id LEFT JOIN clients c ON t.client_id=c.id WHERE t.status!="archived"')->fetchAll(PDO::FETCH_ASSOC);

    $weekCounts = [];
    $start = new DateTime('today');
    $i = 0;
    while (count($weekCounts) < 5) {
        $d = clone $start; $d->modify("+{$i} day");
        if (in_array($d->format('w'), ['5','6'])) { $i++; continue; }
        $weekCounts[$d->format('Y-m-d')] = 0;
        $i++;
    }
    foreach ($tasks as $t) {
        foreach ($weekCounts as $day => $cnt) {
            if (is_due_on($t['due_date'], $t['recurrence'], $day)) {
                $weekCounts[$day]++;
            }
        }
    }
    $maxWeekCount = max($weekCounts) ?: 1;

    $tasksByDay = [];
    $startTasks = new DateTime('today');
    $i = 0;
    while (count($tasksByDay) < 5) {
        $d = clone $startTasks; $d->modify("+{$i} day");
        if (in_array($d->format('w'), ['5','6'])) { $i++; continue; }
        $key = $d->format('Y-m-d');
        $tasksByDay[$key] = ['label'=>$d->format('l'),'tasks'=>[]];
        $i++;
    }
    foreach ($tasks as $t) {
        foreach ($tasksByDay as $day => &$info) {
            if (is_due_on($t['due_date'],$t['recurrence'],$day)) {
                $info['tasks'][] = $t;
            }
        }
    }
    unset($info);
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

<ul class="nav nav-tabs" id="adminTabs" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#clients" type="button" role="tab">Clients</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#team" type="button" role="tab">Team Members</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#status" type="button" role="tab">Status</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Tasks</button></li>
</ul>
<div class="tab-content pt-3">
  <div class="tab-pane fade show active" id="clients" role="tabpanel">
    <form method="post" class="row g-2 mb-3">
      <input type="hidden" name="add_client" value="1">
      <div class="col-md-6">
        <textarea name="client_names" class="form-control" rows="4" placeholder="One client per line" required></textarea>
      </div>
      <div class="col-md-2"><button class="btn btn-success w-100">Add</button></div>
    </form>
    <ul class="list-group mb-2" id="client-list">
      <?php foreach ($clients as $c):
            $workers = $clientWorkers[$c['id']] ?? [];
            $memberCount = count($workers);
            $share = $memberCount ? (($c['progress_percent'] ?? 0) / $memberCount) : 0;
      ?>
      <li class="list-group-item" data-id="<?= $c['id'] ?>">
        <form method="post" class="row g-2 align-items-center">
          <div class="col-md-3"><input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>"></div>
          <div class="col-md-1">
            <span class="client-priority <?= strtolower($c['priority'] ?? '') ?>"><?= $c['priority'] ? htmlspecialchars($c['priority']) : '&nbsp;' ?></span>
          </div>
          <div class="col-md-1">
            <?php if ($c['progress_percent'] !== null): ?>
              <span><?= number_format($c['progress_percent'], 2) ?>%</span>
            <?php endif; ?>
          </div>
          <div class="col-md-3 small text-muted">
            <?php
              $splits = [];
              foreach ($workers as $w) {
                  $splits[] = htmlspecialchars($w) . ': ' . number_format($share, 2) . '%';
              }
              echo implode(' - ', $splits);
            ?>
          </div>
          <div class="col-md-1"><button class="btn btn-primary w-100 btn-sm" name="save_client" value="<?= $c['id'] ?>">Save</button></div>
          <?php $archived = ($c['active_count'] == 0 && $c['archived_count'] > 0); ?>
          <div class="col-md-2">
            <?php if ($archived): ?>
            <button class="btn btn-warning w-100 btn-sm" name="unarchive_client" value="<?= $c['id'] ?>" onclick="return confirm('Unarchive all tasks for this client?')">Unarchive</button>
            <?php else: ?>
            <button class="btn btn-warning w-100 btn-sm" name="archive_client" value="<?= $c['id'] ?>" onclick="return confirm('Archive all tasks for this client?')">Archive</button>
            <?php endif; ?>
          </div>
          <div class="col-md-1"><button class="btn btn-danger w-100 btn-sm" name="delete_client" value="<?= $c['id'] ?>" onclick="return confirm('Delete client?')">Delete</button></div>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <button id="saveClientOrder" class="btn btn-success btn-sm mb-3">Save Order</button>
    <form method="post" class="d-inline">
      <input type="hidden" name="import_priorities" value="1">
      <button class="btn btn-info btn-sm ms-2">Import Priorities &amp; Sorting</button>
    </form>
  </div>
  <div class="tab-pane fade" id="team" role="tabpanel">
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
    <button id="saveUserOrder" class="btn btn-success btn-sm">Save Order</button>
  </div>
  <div class="tab-pane fade" id="status" role="tabpanel">
    <div class="row">
      <div class="col-md-6">
        <table class="table table-borderless w-auto">
          <thead><tr><th>Team Member</th><th>Achieved %</th><th>Tasks</th><th>Archived</th><th>Load %</th></tr></thead>
          <tbody>
            <?php foreach ($loadPercent as $name => $load):
                $pct = $workerTotals[$name] ?? 0;
                $ratio = $pct / 100;
                if ($ratio >= 0.75) $class = 'priority-critical';
                elseif ($ratio >= 0.5) $class = 'priority-high';
                elseif ($ratio >= 0.25) $class = 'priority-intermed';
                else $class = 'priority-low';
                $count = $workerTaskCounts[$name] ?? 0;
                $archived = $workerArchivedCounts[$name] ?? 0;
            ?>
            <tr class="<?= $class ?>"><td><?= htmlspecialchars($name) ?></td><td><?= number_format($pct, 2) ?>%</td><td><?= $count ?></td><td><?= $archived ?></td><td><?= number_format($load, 2) ?>%</td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="col-md-6">
        <table class="table table-borderless w-auto">
          <tbody>
            <?php foreach ($weekCounts as $day => $count):
                $label = date('D', strtotime($day));
                $width = ($count / $maxWeekCount) * 100;
            ?>
            <tr><td><?= $label ?></td><td class="w-100"><div class="progress" style="height:20px;"><div class="progress-bar" style="width:<?= (int)$width ?>%"><?= $count ?></div></div></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="row mt-4">
      <div class="col">
        <div class="mb-2">
          <span class="badge bg-info">Tasks</span>
          <span class="badge bg-success">Progress %</span>
          <span class="badge bg-warning text-dark">Team Members</span>
        </div>
        <table class="table table-borderless w-auto">
          <tbody>
            <?php foreach ($clients as $c):
                $count = (int)$c['active_count'];
                $pct = (float)($c['progress_percent'] ?? 0);
                $members = (int)$c['member_count'];
                $countWidth = ($count / $clientMaxTasks) * 100;
                $pctWidth = $pct;
                $memberWidth = ($members / $clientMaxMembers) * 100;
            ?>
            <tr>
              <td><?= htmlspecialchars($c['name']) ?></td>
              <td class="w-100">
                <div class="progress mb-1" style="height:20px;">
                  <div class="progress-bar bg-info" style="width:<?= (int)$countWidth ?>%"><?= $count ?></div>
                </div>
                <div class="progress mb-1" style="height:20px;">
                  <div class="progress-bar bg-success" style="width:<?= (int)$pctWidth ?>%"><?= number_format($pct, 2) ?>%</div>
                </div>
                <div class="progress" style="height:20px;">
                  <div class="progress-bar bg-warning text-dark" style="width:<?= (int)$memberWidth ?>%"><?= $members ?></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="tab-pane fade" id="overview" role="tabpanel">
    <div class="accordion" id="tasksByDay">
      <?php $dayIndex=0; foreach ($tasksByDay as $day => $info): $count=count($info['tasks']); ?>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#day-<?= $dayIndex ?>">
            <?= $info['label'] ?> (<?= $count ?>)
          </button>
        </h2>
        <div id="day-<?= $dayIndex ?>" class="accordion-collapse collapse" data-bs-parent="#tasksByDay">
          <div class="accordion-body p-0">
            <ul class="list-group list-group-flush">
              <?php foreach ($info['tasks'] as $t):
                    $title = $t['parent_title'] ? $t['parent_title'].' - '.$t['title'] : $t['title'];
                    $clientName = $t['client_name'] ?? 'Others';
                    $clientClass = strtolower($t['client_priority'] ?? '');
              ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><span class="client-priority <?= $clientClass ?>"><?= htmlspecialchars($clientName) ?></span> <?= htmlspecialchars($title) ?></span>
                <span class="ms-2"><?= htmlspecialchars($t['username']) ?> <button type="button" class="btn btn-sm btn-primary edit-task-btn ms-2" data-id="<?= $t['id'] ?>" data-due="<?= $t['due_date'] ?>" data-rec="<?= htmlspecialchars($t['recurrence']) ?>" data-user="<?= $t['assigned_to'] ?>">Edit</button></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
      <?php $dayIndex++; endforeach; ?>
    </div>
  </div>
</div>
<div class="mt-3"><a href="index.php" class="btn btn-dark btn-sm">Back to Tasks</a></div>

<div class="modal fade" id="editTaskModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Task</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="update_task" id="editTaskId">
        <div class="mb-3"><label for="editDueDate" class="form-label">Due Date</label><input type="date" class="form-control" name="due_date" id="editDueDate" required></div>
        <div class="mb-3"><label class="form-label" for="editAssigned">Worker</label>
          <select name="assigned_to" id="editAssigned" class="form-select">
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3"><label class="form-label" for="editRecurrence">Recurrence</label>
          <select name="recurrence" id="editRecurrence" class="form-select">
            <option value="none">No repeat</option>
            <option value="everyday">Every Day</option>
            <option value="working">Working Days</option>
            <option value="interval">Every N...</option>
            <option value="custom">Specific Days</option>
          </select>
        </div>
        <div class="row g-2 mb-3 recurrence-interval d-none">
          <div class="col"><input type="number" min="1" name="interval_count" id="editIntervalCount" class="form-control" value="1"></div>
          <div class="col">
            <select name="interval_unit" id="editIntervalUnit" class="form-select">
              <option value="week">week(s)</option>
              <option value="month">month(s)</option>
              <option value="year">year(s)</option>
            </select>
          </div>
        </div>
        <div class="mb-3 recurrence-days d-none">
          <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
          <label class="me-2"><input type="checkbox" name="days[]" value="<?= $d ?>"> <?= $d ?></label>
          <?php endforeach; ?>
        </div>
        <div class="mb-3">
          <select id="editQuickDate" class="form-select">
            <option value="">Quick date</option>
            <option value="tomorrow">Tomorrow</option>
            <option value="nextweek">Next Week</option>
            <option value="nextmonth">Next Month</option>
          </select>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
new Sortable(document.getElementById('user-list'), {animation:150});
new Sortable(document.getElementById('client-list'), {animation:150});
document.addEventListener('DOMContentLoaded', () => {
  const tabKey = 'admin-active-tab';
  const stored = localStorage.getItem(tabKey);
  if (stored) {
    const trigger = document.querySelector(`#adminTabs button[data-bs-target="${stored}"]`);
    if (trigger) new bootstrap.Tab(trigger).show();
  }
  document.querySelectorAll('#adminTabs button[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', e => {
      localStorage.setItem(tabKey, e.target.getAttribute('data-bs-target'));
    });
  });
});
document.getElementById('saveUserOrder').addEventListener('click', ()=>{
  const order = Array.from(document.querySelectorAll('#user-list li')).map((el,idx)=>el.dataset.id+':'+idx).join(',');
  fetch('admin.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'reorder_users=1&order='+order}).then(()=>location.reload());
});
document.getElementById('saveClientOrder').addEventListener('click', ()=>{
  const order = Array.from(document.querySelectorAll('#client-list li')).map((el,idx)=>el.dataset.id+':'+idx).join(',');
  fetch('admin.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'reorder_clients=1&order='+order}).then(()=>location.reload());
});
const modalEl = document.getElementById('editTaskModal');
const recSelect = document.getElementById('editRecurrence');
const intervalRow = modalEl.querySelector('.recurrence-interval');
const daysRow = modalEl.querySelector('.recurrence-days');
function updateRecurrenceFields(){
  const val = recSelect.value;
  intervalRow.classList.toggle('d-none', val !== 'interval');
  daysRow.classList.toggle('d-none', val !== 'custom');
}
recSelect.addEventListener('change', updateRecurrenceFields);
document.getElementById('editQuickDate').addEventListener('change', function(){
  const today = new Date();
  let d = new Date(today);
  if(this.value==='tomorrow'){ d.setDate(today.getDate()+1); }
  else if(this.value==='nextweek'){ d.setDate(today.getDate()+7); }
  else if(this.value==='nextmonth'){ d.setMonth(today.getMonth()+1); }
  document.getElementById('editDueDate').value = d.toISOString().slice(0,10);
  this.value='';
});
document.querySelectorAll('.edit-task-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.getElementById('editTaskId').value = btn.dataset.id;
    document.getElementById('editDueDate').value = btn.dataset.due;
    document.getElementById('editAssigned').value = btn.dataset.user;
    const rec = btn.dataset.rec || 'none';
    recSelect.value = 'none';
    document.getElementById('editIntervalCount').value = 1;
    document.getElementById('editIntervalUnit').value = 'week';
    daysRow.querySelectorAll('input').forEach(cb=>cb.checked=false);
    if(rec.startsWith('interval:')){
      const parts = rec.split(':');
      recSelect.value = 'interval';
      document.getElementById('editIntervalCount').value = parts[1];
      document.getElementById('editIntervalUnit').value = parts[2];
    } else if(rec.startsWith('custom:')){
      recSelect.value = 'custom';
      rec.substring(7).split(',').forEach(day=>{
        const cb = daysRow.querySelector(`input[value="${day}"]`);
        if(cb) cb.checked = true;
      });
    } else if(rec !== 'none') {
      recSelect.value = rec;
    }
    updateRecurrenceFields();
    new bootstrap.Modal(modalEl).show();
  });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
