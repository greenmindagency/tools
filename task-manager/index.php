<?php
session_start();
date_default_timezone_set('Africa/Cairo');
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
        if ($row['id'] == 1) {
            header('Location: index.php');
        } else {
            header('Location: index.php?user=' . urlencode($row['username']));
        }
        exit;
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
                if ($unit === 'month') {
                    $months = $diff->m + $diff->y*12;
                    return $months % $cnt === 0 && $targetDate->format('d') === $dueDate->format('d');
                }
            } elseif (strpos($recurrence,'custom:')===0) {
                $days = explode(',', substr($recurrence,7));
                $map = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6];
                $dow = (int)$targetDate->format('w');
                return $targetDate >= $dueDate && in_array($dow, array_map(fn($d)=>$map[$d], $days));
            }
            return $targetDate >= $dueDate && $targetDate->format('w') === $dueDate->format('w');
    }
}
function current_log_range() {
    $today = new DateTime();
    $year = (int)$today->format('Y');
    $month = (int)$today->format('m');
    $day = (int)$today->format('d');
    if ($day >= 26) {
        $start = new DateTime("$year-$month-26");
        $end = (clone $start)->modify('+1 month')->modify('-1 day');
    } else {
        $start = (new DateTime("$year-$month-26"))->modify('-1 month');
        $end = new DateTime("$year-$month-25");
    }
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}
function month_logs_html($pdo, $userId) {
    [$start, $end] = current_log_range();
    $stmt = $pdo->prepare('SELECT log_date, clock_in, clock_out FROM work_logs WHERE user_id=? AND log_date BETWEEN ? AND ? ORDER BY log_date');
    $stmt->execute([$userId, $start, $end]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ob_start();
    foreach ($logs as $log) {
        $duration = $log['clock_out'] ? gmdate('H:i:s', strtotime($log['clock_out']) - strtotime($log['clock_in'])) : '';
        ?>
        <tr>
          <td><?= htmlspecialchars(date('Y/m/d', strtotime($log['log_date']))) ?></td>
          <td><?= htmlspecialchars(date('h:i A', strtotime($log['clock_in']))) ?></td>
          <td><?= $log['clock_out'] ? htmlspecialchars(date('h:i A', strtotime($log['clock_out']))) : '' ?></td>
          <td><?= $duration ?></td>
        </tr>
        <?php
    }
    return ob_get_clean();
}
function render_task($t, $users, $clients, $filterUser = null, $userLoadClasses = [], $archivedView = false) {
    global $pdo;
    $today = date('Y-m-d');
    $overdue = $t['due_date'] < $today && $t['status'] !== 'done';
    $isSub = !empty($t['parent_id']);
    $toggleAttr = ' data-bs-toggle="collapse" data-bs-target="#task-'.$t['id'].'"';
    ob_start();
    ?>
    <li class="list-group-item d-flex align-items-start <?= $t['status']==='done'?'opacity-50':'' ?> <?= $overdue?'border border-danger':'' ?>" data-task-id="<?= $t['id'] ?>" data-due-date="<?= htmlspecialchars($t['due_date']) ?>" data-recurrence="<?= htmlspecialchars($t['recurrence']) ?>"<?= $isSub ? ' data-parent-id="'.$t['parent_id'].'" data-parent-title="'.htmlspecialchars($t['parent_title']).'" data-sub-title="'.htmlspecialchars($t['title']).'"' : '' ?>>
      <?php if(!$archivedView): ?>
      <form method="post" class="me-2 complete-form" data-bs-toggle="tooltip" title="Complete">
        <input type="hidden" name="toggle_complete" value="<?= $t['id'] ?>">
        <input type="checkbox" class="complete-checkbox" name="completed" <?= $t['status']==='done'?'checked':'' ?>>
      </form>
      <?php endif; ?>
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between">
          <div class="task-main"<?= $toggleAttr ?>>
            <strong class="client">
              <?php if ($t['client_name']): ?>
                <span class="client-priority <?= strtolower($t['client_priority'] ?? '') ?>"><?= htmlspecialchars($t['client_name']) ?></span>
              <?php else: ?>
                Others
              <?php endif; ?>
            </strong>
            <strong class="task-title-text my-2"><?= htmlspecialchars($isSub ? ($t['parent_title'] . ' - ' . $t['title']) : $t['title']) ?></strong>
            <div class="small text-muted due-date"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($t['due_date']) ?></div>
            <div><small class="fw-bold assignee p-1 text-dark <?= $userLoadClasses[$t['username']] ?? '' ?>"><i class="bi bi-person me-1"></i><?= htmlspecialchars($t['username']) ?></small></div>
          </div>
          <div class="text-end ms-2">
            <div class="mb-1">
              <?php if($archivedView): ?>
              <button type="button" class="btn btn-success btn-sm unarchive-btn" data-id="<?= $t['id'] ?>" title="Unarchive" data-bs-toggle="tooltip"><i class="bi bi-arrow-counterclockwise"></i></button>
              <button type="button" class="btn btn-danger btn-sm delete-btn" data-id="<?= $t['id'] ?>" title="Remove" data-bs-toggle="tooltip"><i class="bi bi-trash"></i></button>
              <?php else: ?>
              <button type="button" class="btn btn-success btn-sm save-btn d-none" data-id="<?= $t['id'] ?>" title="Save" data-bs-toggle="tooltip"><i class="bi bi-check"></i></button>
              <button type="button" class="btn btn-light btn-sm edit-btn" data-id="<?= $t['id'] ?>" title="Edit" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></button>
              <button type="button" class="btn btn-primary btn-sm add-subtask-toggle d-none" data-bs-toggle="collapse" data-bs-target="#subtask-form-<?= $t['id'] ?>" title="Add Subtask"><i class="bi bi-plus-lg"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm share-btn" data-id="<?= $t['id'] ?>" title="Share" data-bs-toggle="tooltip"><i class="bi bi-share"></i></button>
              <button type="button" class="btn btn-secondary btn-sm duplicate-btn" data-id="<?= $t['id'] ?>" title="Duplicate" data-bs-toggle="tooltip"><i class="bi bi-files"></i></button>
              <button type="button" class="btn btn-warning btn-sm archive-btn" data-id="<?= $t['id'] ?>" title="Archive" data-bs-toggle="tooltip"><i class="bi bi-archive"></i></button>
              <button type="button" class="btn btn-danger btn-sm delete-btn" data-id="<?= $t['id'] ?>" title="Remove" data-bs-toggle="tooltip"><i class="bi bi-trash"></i></button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="collapse mt-2" id="task-<?= $t['id'] ?>">
          <div class="description mb-2"><?= nl2br(htmlspecialchars($t['description'])) ?></div>
          <?php if(!$archivedView): ?>
          <form method="post" class="row g-2 mt-2 task-form d-none">
            <input type="hidden" name="update_task" value="<?= $t['id'] ?>">
            <div class="col-12"><div class="form-control editable" data-field="title" contenteditable="true"><?= htmlspecialchars($t['title']) ?></div></div>
            <div class="col-12 mt-2"><div class="form-control editable" data-field="description" contenteditable="true"><?= htmlspecialchars($t['description'] ?? '') ?></div></div>
            <div class="col-md-3"><input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($t['due_date']) ?>"></div>
            <div class="col-md-3">
              <select class="form-select quick-date">
                <option value="">Quick date</option>
                <option value="today">Today</option>
                <option value="tomorrow">Tomorrow</option>
                <option value="nextweek">Next Week</option>
                <option value="nextmonth">Next Month</option>
              </select>
            </div>
            <div class="col-md-3">
              <select name="assigned" class="form-select">
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $t['assigned_to']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if(!$isSub): ?>
            <div class="col-md-3">
              <select name="client_id" class="form-select">
                <option value="">Client</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" data-priority="<?= htmlspecialchars($c['priority']) ?>" <?= $t['client_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="client_id" value="<?= $t['client_id'] ?>">
            <?php endif; ?>
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
          </form>
          <?php endif; ?>
          <?php include __DIR__ . '/comments.php'; ?>
          <?php if(!$isSub): ?>
          <?php if(!$archivedView): ?>
          <hr class="my-3">
          <button class="btn btn-primary btn-sm add-subtask-toggle mt-3 d-none" type="button" data-bs-toggle="collapse" data-bs-target="#subtask-form-<?= $t['id'] ?>">Add Subtask</button>
          <div class="collapse" id="subtask-form-<?= $t['id'] ?>">
            <form method="post" class="row g-2 mt-2 ajax">
              <input type="hidden" name="add_task" value="1">
              <input type="hidden" name="parent_id" value="<?= $t['id'] ?>">
              <input type="hidden" name="client_id" value="<?= $t['client_id'] ?>">
              <div class="col-12"><input type="text" name="title" class="form-control" placeholder="Subtask title" required></div>
              <div class="col-12"><textarea name="description" class="form-control" placeholder="Description"></textarea></div>
              <div class="col-md-3"><input type="date" name="due_date" class="form-control" required></div>
              <div class="col-md-3">
                <select class="form-select quick-date">
                  <option value="">Quick date</option>
                  <option value="today">Today</option>
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
              <div class="col-md-3 mt-2">
                <select name="recurrence" class="form-select recurrence-select">
                  <option value="none" selected>No repeat</option>
                  <option value="everyday">Every Day</option>
                  <option value="working">Working Days</option>
                  <option value="interval">Every N...</option>
                  <option value="custom">Specific Days</option>
                </select>
              </div>
              <div class="col-md-3 mt-2 recurrence-interval d-none">
                <input type="number" min="1" name="interval_count" value="1" class="form-control">
              </div>
              <div class="col-md-3 mt-2 recurrence-unit d-none">
                <select name="interval_unit" class="form-select">
                  <option value="week">week(s)</option>
                  <option value="month">month(s)</option>
                  <option value="year">year(s)</option>
                </select>
              </div>
              <div class="col-md-12 mt-2 recurrence-days d-none">
                <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                <label class="me-2"><input type="checkbox" name="days[]" value="<?= $d ?>"> <?= $d ?></label>
                <?php endforeach; ?>
              </div>
              <div class="col-auto mt-2"><button class="btn btn-primary btn-sm">Add</button></div>
            </form>
          </div>
          <?php endif; ?>
          <?php if(!$filterUser): ?>
          <?php
          $childSql = 'SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id JOIN tasks p ON t.parent_id=p.id WHERE t.parent_id=?';
          $childSql .= $archivedView ? ' AND t.status="archived"' : ' AND t.status!="archived" AND t.status!="done"';
          $params = [$t['id']];
          if ($filterUser) {
            $childSql .= ' AND t.assigned_to=?';
            $params[] = $filterUser;
          }
          $childSql .= ' ORDER BY t.order_index, t.due_date';
          $childStmt = $pdo->prepare($childSql);
          $childStmt->execute($params);
          $children = $childStmt->fetchAll(PDO::FETCH_ASSOC);
          if ($children): ?>
          <ul class="list-group ms-4 mt-3 subtask-list" data-parent="<?= $t['id'] ?>">
            <?php foreach ($children as $ch): ?>
            <?= render_task($ch, $users, $clients, $filterUser, $userLoadClasses, $archivedView); ?>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </li>
    <?php
    return ob_get_clean();
}
function duplicate_task_recursive($pdo, $taskId, $newParentId = null, $depth = 0) {
    $stmt = $pdo->prepare('SELECT title,description,assigned_to,client_id,priority,due_date,recurrence FROM tasks WHERE id=?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) return null;
    $title = rtrim($task['title']) . ' (duplicated)';
    if ($newParentId) {
        $oStmt = $pdo->prepare('SELECT COALESCE(MAX(order_index),0)+1 FROM tasks WHERE parent_id=?');
        $oStmt->execute([$newParentId]);
        $orderIdx = (int)$oStmt->fetchColumn();
    } else {
        $orderIdx = strtotime($task['due_date']);
    }
    $insert = $pdo->prepare('INSERT INTO tasks (title,description,assigned_to,client_id,priority,due_date,recurrence,parent_id,order_index) VALUES (?,?,?,?,?,?,?,?,?)');
    $insert->execute([$title,$task['description'],$task['assigned_to'],$task['client_id'],$task['priority'],$task['due_date'],$task['recurrence'],$newParentId,$orderIdx]);
    $newId = $pdo->lastInsertId();
    if ($depth < 1) {
        $childStmt = $pdo->prepare('SELECT id FROM tasks WHERE parent_id=?');
        $childStmt->execute([$taskId]);
        while ($child = $childStmt->fetch(PDO::FETCH_ASSOC)) {
            duplicate_task_recursive($pdo, $child['id'], $newId, $depth + 1);
        }
    }
    return $newId;
}

function load_meta($pdo) {
    $users = $pdo->query('SELECT id,username FROM users ORDER BY sort_order, username')->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query('SELECT c.id,c.name,c.priority,c.progress_percent,COUNT(t.id) AS task_count FROM clients c JOIN tasks t ON t.client_id=c.id AND t.status!="archived" GROUP BY c.id,c.name,c.priority,c.sort_order,c.progress_percent ORDER BY (c.priority IS NULL), c.sort_order, c.name')->fetchAll(PDO::FETCH_ASSOC);

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

    $workerTaskCounts = [];
    $totalTasks = 0;
    $taskCountsStmt = $pdo->query('SELECT u.username, COUNT(*) AS cnt FROM tasks t JOIN users u ON t.assigned_to=u.id WHERE t.status!="archived" GROUP BY u.username');
    while ($row = $taskCountsStmt->fetch(PDO::FETCH_ASSOC)) {
        $workerTaskCounts[$row['username']] = $row['cnt'];
        $totalTasks += $row['cnt'];
        if (!isset($workerTotals[$row['username']])) $workerTotals[$row['username']] = 0;
    }

    $workerArchivedCounts = [];
    $archivedCountsStmt = $pdo->query('SELECT u.username, COUNT(*) AS cnt FROM tasks t JOIN users u ON t.assigned_to=u.id WHERE t.status="archived" GROUP BY u.username');
    while ($row = $archivedCountsStmt->fetch(PDO::FETCH_ASSOC)) {
        $workerArchivedCounts[$row['username']] = $row['cnt'];
        if (!isset($workerTotals[$row['username']])) $workerTotals[$row['username']] = 0;
        if (!isset($workerTaskCounts[$row['username']])) $workerTaskCounts[$row['username']] = 0;
    }

    $allWorkers = array_unique(array_merge(array_column($users, 'username'), array_keys($workerTotals), array_keys($workerTaskCounts), array_keys($workerArchivedCounts)));
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

    $userLoadClasses = [];
    foreach ($loadPercent as $name => $load) {
        $ratio = $load / 100;
        if ($ratio >= 0.75) $class = 'priority-critical';
        elseif ($ratio >= 0.5) $class = 'priority-high';
        elseif ($ratio >= 0.25) $class = 'priority-intermed';
        else $class = 'priority-low';
        $userLoadClasses[$name] = $class;
    }
    $usersByTasks = array_map(fn($name) => ['username' => $name], array_keys($loadPercent));
    $clients = $pdo->query('SELECT c.id,c.name,c.priority,c.progress_percent,COUNT(t.id) AS task_count FROM clients c JOIN tasks t ON t.client_id=c.id AND t.status!="archived" GROUP BY c.id,c.name,c.priority,c.sort_order,c.progress_percent ORDER BY (c.progress_percent IS NULL), c.progress_percent DESC, c.sort_order, c.name')->fetchAll(PDO::FETCH_ASSOC);
    return [$users, $clients, $userLoadClasses, $usersByTasks];
}



try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clock_in'])) {
        $nowObj = new DateTime('now', new DateTimeZone('Africa/Cairo'));
        $now = $nowObj->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('INSERT INTO work_logs (user_id, log_date, clock_in) VALUES (?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], $nowObj->format('Y-m-d'), $now]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $statusRows = month_logs_html($pdo, $_SESSION['user_id']);
            echo json_encode(['clock_in' => $now, 'status_html' => $statusRows]);
            exit;
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clock_out'])) {
        $now = (new DateTime('now', new DateTimeZone('Africa/Cairo')))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE work_logs SET clock_out=? WHERE user_id=? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
        $stmt->execute([$now, $_SESSION['user_id']]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $statusRows = month_logs_html($pdo, $_SESSION['user_id']);
            echo json_encode(['clock_out' => $now, 'status_html' => $statusRows]);
            exit;
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
        $taskId = (int)$_POST['add_comment'];
        $content = trim($_POST['comment'] ?? '');
        if ($taskId && $content !== '') {
            $stmt = $pdo->prepare('INSERT INTO comments (task_id,user_id,content) VALUES (?,?,?)');
            $stmt->execute([$taskId, $_SESSION['user_id'], $content]);
            $commentId = $pdo->lastInsertId();
            if (!empty($_FILES['files']['name'][0])) {
                $dir = __DIR__ . '/uploads';
                if (!is_dir($dir)) { mkdir($dir, 0777, true); }
                foreach ($_FILES['files']['name'] as $i => $name) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK && $_FILES['files']['size'][$i] <= 5*1024*1024) {
                        $tmp = $_FILES['files']['tmp_name'][$i];
                        $safe = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
                        $dest = $dir . '/' . $safe;
                        if (move_uploaded_file($tmp, $dest)) {
                            $fstmt = $pdo->prepare('INSERT INTO comment_files (comment_id,file_path,original_name) VALUES (?,?,?)');
                            $fstmt->execute([$commentId, $safe, $name]);
                        }
                    }
                }
            }
            $cstmt = $pdo->prepare('SELECT c.id, c.content, u.username FROM comments c JOIN users u ON c.user_id=u.id WHERE c.id=?');
            $cstmt->execute([$commentId]);
            $comment = $cstmt->fetch(PDO::FETCH_ASSOC);
            $fstmt = $pdo->prepare('SELECT file_path, original_name FROM comment_files WHERE comment_id=?');
            $fstmt->execute([$commentId]);
            $comment['files'] = $fstmt->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: application/json');
            echo json_encode($comment);
        }
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_comment'])) {
        $cid = (int)$_POST['edit_comment'];
        $content = trim($_POST['content'] ?? '');
        if ($cid && $content !== '') {
            $stmt = $pdo->prepare('UPDATE comments SET content=? WHERE id=? AND user_id=?');
            $stmt->execute([$content, $cid, $_SESSION['user_id']]);
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'content' => $content, 'username' => $_SESSION['username']]);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
        $cid = (int)$_POST['delete_comment'];
        if ($cid) {
            $fstmt = $pdo->prepare('SELECT file_path FROM comment_files WHERE comment_id=?');
            $fstmt->execute([$cid]);
            $paths = $fstmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($paths as $p) {
                @unlink(__DIR__ . '/uploads/' . $p);
            }
            $pdo->prepare('DELETE FROM comment_files WHERE comment_id=?')->execute([$cid]);
            $pdo->prepare('DELETE FROM comments WHERE id=? AND user_id=?')->execute([$cid, $_SESSION['user_id']]);
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
        refresh_client_priorities($pdo);
        $title = trim($_POST['title'] ?? '');
        $assigned = (int)($_POST['assigned'] ?? 0);
        $due = $_POST['due_date'] ?? date('Y-m-d');
        $priority = $_POST['priority'] ?? 'Normal';
        $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
        if ($clientId) {
            $pstmt = $pdo->prepare('SELECT priority FROM clients WHERE id=?');
            $pstmt->execute([$clientId]);
            $cPrio = $pstmt->fetchColumn();
            if ($cPrio) { $priority = $cPrio; }
        }
        $desc = trim($_POST['description'] ?? '');
        $rec = $_POST['recurrence'] ?? 'none';
        $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($parentId) {
            $pcheck = $pdo->prepare('SELECT parent_id FROM tasks WHERE id=?');
            $pcheck->execute([$parentId]);
            $grand = $pcheck->fetchColumn();
            if ($grand) { $parentId = $grand; }
        }
        if ($rec === 'custom') {
            $days = $_POST['days'] ?? [];
            $rec = 'custom:' . implode(',', array_map('htmlspecialchars',$days));
        } elseif ($rec === 'interval') {
            $cnt = max(1,(int)($_POST['interval_count'] ?? 1));
            $unit = $_POST['interval_unit'] ?? 'week';
            $rec = 'interval:' . $cnt . ':' . $unit;
        }
        if ($title !== '' && $assigned) {
            if ($parentId) {
                $oStmt = $pdo->prepare('SELECT COALESCE(MAX(order_index),0)+1 FROM tasks WHERE parent_id=?');
                $oStmt->execute([$parentId]);
                $orderIdx = (int)$oStmt->fetchColumn();
            } else {
                $orderIdx = strtotime($due);
            }
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
        if ($clientId) {
            $pstmt = $pdo->prepare('SELECT priority FROM clients WHERE id=?');
            $pstmt->execute([$clientId]);
            $cPrio = $pstmt->fetchColumn();
            if ($cPrio) { $priority = $cPrio; }
        }
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
            $stmtCur = $pdo->prepare('SELECT order_index, title FROM tasks WHERE id=?');
            $stmtCur->execute([$id]);
            $curr = $stmtCur->fetch(PDO::FETCH_ASSOC);
            $currIdx = (int)($curr['order_index'] ?? 0);
            $oldTitle = $curr['title'] ?? '';
            $orderIdx = $currIdx > 100000 ? strtotime($due) : $currIdx;
            $stmt = $pdo->prepare('UPDATE tasks SET title=?,description=?,assigned_to=?,client_id=?,priority=?,due_date=?,recurrence=?,order_index=? WHERE id=?');
            $stmt->execute([$title,$desc,$assigned,$clientId,$priority,$due,$rec,$orderIdx,$id]);
            $stmtSub = $pdo->prepare('UPDATE tasks SET client_id=?, priority=? WHERE parent_id=?');
            $stmtSub->execute([$clientId,$priority,$id]);
            if (strpos($oldTitle, ' (duplicated)') !== false && strpos($title, ' (duplicated)') === false) {
                $stmtRename = $pdo->prepare("UPDATE tasks SET title = REPLACE(title, ' (duplicated)', '') WHERE parent_id=?");
                $stmtRename->execute([$id]);
            }
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
        $stmt = $pdo->prepare('UPDATE tasks SET status="archived" WHERE id=? OR parent_id=?');
        $stmt->execute([$id,$id]);
        list($users, $clients, $userLoadClasses, $usersByTasks) = load_meta($pdo);
        $stmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id LEFT JOIN tasks p ON t.parent_id=p.id WHERE t.id=?');
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        echo render_task($task, $users, $clients, null, $userLoadClasses, true);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unarchive_task'])) {
        $id = (int)$_POST['unarchive_task'];
        $stmt = $pdo->prepare('UPDATE tasks SET status="pending" WHERE id=? OR parent_id=?');
        $stmt->execute([$id,$id]);
        list($users, $clients, $userLoadClasses, $usersByTasks) = load_meta($pdo);
        $stmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id LEFT JOIN tasks p ON t.parent_id=p.id WHERE t.id=?');
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        echo render_task($task, $users, $clients, null, $userLoadClasses);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_task'])) {
        $id = (int)$_POST['duplicate_task'];
        $pstmt = $pdo->prepare('SELECT parent_id FROM tasks WHERE id=?');
        $pstmt->execute([$id]);
        $parentId = $pstmt->fetchColumn() ?: null;
        $newId = duplicate_task_recursive($pdo, $id, $parentId);
        list($users, $clients, $userLoadClasses, $usersByTasks) = load_meta($pdo);
        $stmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id LEFT JOIN tasks p ON t.parent_id=p.id WHERE t.id=?');
        $stmt->execute([$newId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        echo render_task($task, $users, $clients, null, $userLoadClasses);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_task'])) {
        $id = (int)$_POST['share_task'];
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
        $url = $base . '?task=' . $id;
        $short = @file_get_contents('https://tinyurl.com/api-create.php?url=' . urlencode($url));
        echo $short ?: $url;
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_subtasks'])) {
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $order = $_POST['order'] ?? '';
        $ids = array_filter(explode(',', $order));
        $stmt = $pdo->prepare('UPDATE tasks SET order_index=? WHERE id=? AND parent_id=?');
        foreach ($ids as $idx => $taskId) {
            $stmt->execute([$idx, (int)$taskId, $parentId]);
        }
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
        $id = (int)$_POST['delete_task'];
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id=?');
        $stmt->execute([$id]);
        exit;
    }

    $filterUserName = $_GET['user'] ?? null;
    $filterClientName = $_GET['client'] ?? null;
    $range = $_GET['range'] ?? 'all';
    $filterUser = $filterClient = null;
    if ($filterUserName) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username=?');
        $stmt->execute([$filterUserName]);
        $filterUser = $stmt->fetchColumn() ?: null;
    }
    if ($filterClientName) {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE name=?');
        $stmt->execute([$filterClientName]);
        $filterClient = $stmt->fetchColumn() ?: null;
    }
    $filterArchived = isset($_GET['archived']);

    list($users, $clients, $userLoadClasses, $usersByTasks) = load_meta($pdo);

    $logStmt = $pdo->prepare('SELECT id, clock_in FROM work_logs WHERE user_id=? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
    $logStmt->execute([$_SESSION['user_id']]);
    $currentLog = $logStmt->fetch(PDO::FETCH_ASSOC);
    $statusRows = month_logs_html($pdo, $_SESSION['user_id']);

    $today = date('Y-m-d');
    $weekEnd = date('Y-m-d', strtotime('+7 day'));
    $overdueCounts = $pdo->query("SELECT assigned_to, COUNT(*) AS cnt FROM tasks WHERE status!='archived' AND status!='done' AND due_date < '$today' GROUP BY assigned_to")->fetchAll(PDO::FETCH_KEY_PAIR);
    $todayCounts = $pdo->query("SELECT assigned_to, COUNT(*) AS cnt FROM tasks WHERE status!='archived' AND due_date = '$today' GROUP BY assigned_to")->fetchAll(PDO::FETCH_KEY_PAIR);
    $weekCountsUser = $pdo->query("SELECT assigned_to, COUNT(*) AS cnt FROM tasks WHERE status!='archived' AND due_date > '$today' AND due_date <= '$weekEnd' GROUP BY assigned_to")->fetchAll(PDO::FETCH_KEY_PAIR);

    $cond = [];
    $params = [];
    if ($filterUser) {
        $cond[] = 't.assigned_to=?';
        $params[] = $filterUser;
    }
    if ($filterClient) { $cond[] = 't.client_id=?'; $params[] = $filterClient; }
    if ($filterArchived) { $cond[] = 't.status="archived"'; } else { $cond[] = 't.status!="archived"'; }
    $where = $cond ? ' AND '.implode(' AND ',$cond) : '';
    if ($filterUser && !$filterArchived) {
        $order = 'ORDER BY (c.progress_percent IS NULL), t.due_date, c.progress_percent DESC';
    } else {
        $order = 'ORDER BY (c.progress_percent IS NULL), c.progress_percent DESC, t.due_date';
    }
    if (!$filterUser && !$filterClient && !$filterArchived) {
        $allStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,c.progress_percent AS client_percent,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id LEFT JOIN tasks p ON t.parent_id=p.id WHERE t.parent_id IS NULL'.$where.' '.$order);
        $allStmt->execute($params);
        $allTasks = $allStmt->fetchAll(PDO::FETCH_ASSOC);
        $completedTasks = array_values(array_filter($allTasks, fn($t) => $t['status'] === 'done'));
        $allTasks = array_values(array_filter($allTasks, fn($t) => $t['status'] !== 'done'));
        $completedSubStmt = $pdo->query('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,c.progress_percent AS client_percent,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id JOIN tasks p ON t.parent_id=p.id WHERE t.parent_id IS NOT NULL AND t.status="done" AND p.status!="archived" '.$order);
        $completedSubs = $completedSubStmt->fetchAll(PDO::FETCH_ASSOC);
        $completedTasks = array_merge($completedTasks, $completedSubs);
        usort($completedTasks, fn($a,$b)=>strcmp($a['due_date'], $b['due_date']));
    } elseif ($filterArchived) {
        $archivedStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,c.progress_percent AS client_percent FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id WHERE t.parent_id IS NULL'.$where.' '.$order);
        $archivedStmt->execute($params);
        $archivedTasks = $archivedStmt->fetchAll(PDO::FETCH_ASSOC);

        $archivedSubStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,c.progress_percent AS client_percent,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id JOIN tasks p ON t.parent_id=p.id WHERE t.parent_id IS NOT NULL AND p.status!="archived"'.$where.' '.$order);
        $archivedSubStmt->execute($params);
        $archivedSubs = $archivedSubStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $parentCond = $filterUser ? '' : 't.parent_id IS NULL AND ';
        $todayStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,c.progress_percent AS client_percent,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id LEFT JOIN tasks p ON t.parent_id=p.id WHERE ' . $parentCond . 't.status!="done" AND t.due_date <= ?'.$where.' '.$order);
        $todayStmt->execute(array_merge([$today],$params));
        $todayTasks = $todayStmt->fetchAll(PDO::FETCH_ASSOC);
        $upcomingStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,c.progress_percent AS client_percent,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id LEFT JOIN tasks p ON t.parent_id=p.id WHERE ' . $parentCond . 't.status!="done" AND t.due_date > ?'.$where.' '.$order);
        $upcomingStmt->execute(array_merge([$today],$params));
        $upcomingTasks = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
        $completedStmt = $pdo->prepare('SELECT t.*,u.username,c.name AS client_name,c.priority AS client_priority,c.progress_percent AS client_percent,p.title AS parent_title FROM tasks t JOIN users u ON t.assigned_to=u.id LEFT JOIN clients c ON t.client_id=c.id LEFT JOIN tasks p ON t.parent_id=p.id WHERE ' . $parentCond . 't.status="done"'.$where.' '.$order);
        $completedStmt->execute($params);
        $completedTasks = $completedStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($range === 'overdue') {
        if (isset($allTasks)) {
            $allTasks = array_values(array_filter($allTasks, fn($t) => $t['due_date'] < $today && $t['status'] !== 'done'));
        } else {
            $todayTasks = array_values(array_filter($todayTasks ?? [], fn($t) => $t['due_date'] < $today && $t['status'] !== 'done'));
            $upcomingTasks = [];
        }
        if (isset($completedTasks)) {
            $completedTasks = array_values(array_filter($completedTasks, fn($t) => $t['due_date'] < $today));
        }
    } elseif ($range === 'today') {
        if (isset($allTasks)) {
            $allTasks = array_values(array_filter($allTasks, fn($t) => $t['due_date'] === $today));
        } else {
            $todayTasks = array_values(array_filter($todayTasks ?? [], fn($t) => $t['due_date'] === $today));
            $upcomingTasks = [];
        }
        if (isset($completedTasks)) {
            $completedTasks = array_values(array_filter($completedTasks, fn($t) => $t['due_date'] === $today));
        }
    } elseif ($range === 'week') {
        if (isset($allTasks)) {
            $allTasks = array_values(array_filter($allTasks, fn($t) => $t['due_date'] >= $today && $t['due_date'] <= $weekEnd));
        } else {
            $todayTasks = array_values(array_filter($todayTasks ?? [], fn($t) => $t['due_date'] >= $today && $t['due_date'] <= $weekEnd));
            $upcomingTasks = array_values(array_filter($upcomingTasks ?? [], fn($t) => $t['due_date'] > $today && $t['due_date'] <= $weekEnd));
        }
        if (isset($completedTasks)) {
            $completedTasks = array_values(array_filter($completedTasks, fn($t) => $t['due_date'] >= $today && $t['due_date'] <= $weekEnd));
        }
    }

    $weekCounts = [];
    $start = new DateTime('today');
    $i = 0;
    while (count($weekCounts) < 5) {
        $d = clone $start; $d->modify("+{$i} day");
        if (in_array($d->format('w'), ['5','6'])) { $i++; continue; }
        $weekCounts[$d->format('Y-m-d')] = 0;
        $i++;
    }
    $allForCounts = $pdo->query('SELECT due_date, recurrence FROM tasks WHERE status!="archived"')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allForCounts as $t) {
        foreach ($weekCounts as $day => $cnt) {
            if (is_due_on($t['due_date'], $t['recurrence'], $day)) {
                $weekCounts[$day]++;
            }
        }
    }

    $targetAchieved = null;
    $csv = @file_get_contents(CLIENT_SHEET_URL);
    if ($csv !== false) {
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));
        if (isset($rows[3][19])) {
            $targetAchieved = rtrim($rows[3][19], '%');
        }
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
$uid = $_SESSION['user_id'] ?? null;
$myOverdue = $overdueCounts[$uid] ?? 0;
$myToday = $todayCounts[$uid] ?? 0;
$myWeek = $weekCountsUser[$uid] ?? 0;
?>
<div class="row">
  <div class="col-md-3">
    <?php $isAllPage = basename($_SERVER['SCRIPT_NAME']) === 'alltasks.php'; ?>
    <ul class="list-group mb-3">
      <li class="list-group-item <?= $filterUserName === ($_SESSION['username'] ?? '') && !$filterClientName && !$filterArchived ? 'active' : '' ?>">
        <a href="index.php?user=<?= urlencode($_SESSION['username'] ?? '') ?>" class="text-decoration-none<?= $filterUserName === ($_SESSION['username'] ?? '') && !$filterClientName && !$filterArchived ? ' text-white' : '' ?>">My Tasks</a>
      </li>
      <li class="list-group-item <?= $isAllPage && !$filterClientName && !$filterUserName && !$filterArchived ? 'active' : '' ?>">
        <a href="alltasks.php" class="text-decoration-none<?= $isAllPage && !$filterClientName && !$filterUserName && !$filterArchived ? ' text-white' : '' ?>">All Tasks</a>
      </li>
      <li class="list-group-item <?= $filterArchived ? 'active' : '' ?>">
        <a href="index.php?archived=1" class="text-decoration-none<?= $filterArchived ? ' text-white' : '' ?>">Archive</a>
      </li>
    </ul>
    <div class="d-none d-md-block">
      <hr>
      <h5>Clients</h5>
      <ul class="list-inline mb-3">
        <?php foreach ($clients as $c): ?>
        <?php $isActive = $filterClientName === $c['name']; ?>
        <?php $pClass = $c['priority'] ? 'priority-'.strtolower($c['priority']) : 'btn-light border-secondary'; ?>
        <li class="list-inline-item mb-2">
          <a href="index.php?client=<?= urlencode($c['name']) ?>" class="btn btn-sm <?= $pClass ?> me-2 <?= $isActive ? 'border-dark border-2' : '' ?>">
            <?= htmlspecialchars($c['name']) ?>
            <span class="badge bg-light text-dark ms-1"><?= $c['task_count'] ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <hr>
      <h5>Team Members</h5>
      <ul class="list-inline mb-3">
        <?php foreach ($usersByTasks as $u): ?>
        <?php $uActive = $filterUserName === $u['username']; $loadClass = $userLoadClasses[$u['username']] ?? 'btn-outline-secondary'; ?>
        <li class="list-inline-item mb-2">
          <a href="index.php?user=<?= urlencode($u['username']) ?>" class="btn btn-sm <?= $loadClass ?> text-dark me-2 <?= $uActive ? 'border-dark border-2' : '' ?>"><i class="bi bi-person me-1"></i><?= htmlspecialchars($u['username']) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <hr>
      <?php if ($targetAchieved !== null): ?>
      <h5>Status</h5>
      <div class="small mb-3">Target Achieved: <?= htmlspecialchars($targetAchieved) ?>%</div>
      <hr>
      <?php endif; ?>
      <h5>Upcoming Week</h5>
      <ul class="list-unstyled small">
        <?php foreach ($weekCounts as $day => $cnt): ?>
        <li><?= date('D', strtotime($day)) ?>: <?= $cnt ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
      <form method="get" class="mb-2">
        <?php if ($filterUserName): ?><input type="hidden" name="user" value="<?= htmlspecialchars($filterUserName) ?>"><?php endif; ?>
        <?php if ($filterClientName): ?><input type="hidden" name="client" value="<?= htmlspecialchars($filterClientName) ?>"><?php endif; ?>
        <?php if ($filterArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
        <div class="btn-group btn-group-sm" role="group">
          <button type="submit" name="range" value="overdue" class="btn btn-outline-secondary<?= $range==='overdue'?' active':'' ?>">Overdue</button>
          <button type="submit" name="range" value="today" class="btn btn-outline-secondary<?= $range==='today'?' active':'' ?>">Today</button>
          <button type="submit" name="range" value="week" class="btn btn-outline-secondary<?= $range==='week'?' active':'' ?>">Week</button>
          <button type="submit" name="range" value="all" class="btn btn-outline-secondary<?= $range==='all'?' active':'' ?>">All</button>
        </div>
      </form>
      <div class="mb-2">
        <span id="date-time" class="me-3 fw-bold"></span>
        <span id="work-timer" class="me-2 fw-bold">Shift: 00:00:00</span>
        <form method="post" class="d-inline ajax" id="clock-form">
          <?php if ($currentLog): ?>
          <button type="submit" name="clock_out" class="btn btn-danger btn-sm">Clock Out</button>
          <?php else: ?>
          <button type="submit" name="clock_in" class="btn btn-success btn-sm">Clock In</button>
          <?php endif; ?>
        </form>
        <button type="button" class="btn btn-secondary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#statusModal">Status</button>
      </div>
    </div>
    <hr class="my-3">
    <div class="d-flex justify-content-between mb-2 align-items-start">
      <button id="addBtn" class="btn btn-success btn-sm" data-bs-toggle="collapse" data-bs-target="#addTask" title="Add Task"><i class="bi bi-plus"></i></button>
      <div>
        <?php if (($_SESSION['user_id'] ?? 0) == 1): ?>
        <a href="admin.php" class="btn btn-secondary btn-sm me-2" data-bs-toggle="tooltip" title="Admin">Admin</a>
        <?php endif; ?>
        <a href="?logout=1" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Logout">Logout</a>
      </div>
    </div>
    <hr>
    <div id="addTask" class="collapse mb-4">
      <form method="post" class="row g-2 ajax">
        <input type="hidden" name="add_task" value="1">
        <div class="col-12"><input type="text" name="title" class="form-control" placeholder="Task title" required></div>
        <div class="col-12"><textarea name="description" class="form-control" placeholder="Description"></textarea></div>
        <div class="col-md-3"><input type="date" name="due_date" class="form-control" required></div>
        <div class="col-md-3">
          <select class="form-select quick-date">
            <option value="">Quick date</option>
            <option value="today">Today</option>
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
            <option value="<?= $c['id'] ?>" data-priority="<?= htmlspecialchars($c['priority']) ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
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
        <div class="col-auto mt-2"><button class="btn btn-success btn-sm">Add Task</button></div>
      </form>
    </div>
    <?php if ($filterArchived): ?>
      <h3 class="mb-4">Archived Tasks</h3>
      <ul class="list-group mb-4" id="archived-list">
        <?php foreach ($archivedTasks as $t): ?>
        <?= render_task($t, $users, $clients, $filterUser, $userLoadClasses, true); ?>
        <?php endforeach; ?>
      </ul>
      <h3 class="mb-4">Archived Sub Tasks</h3>
      <ul class="list-group" id="archived-sub-list">
        <?php foreach ($archivedSubs as $t): ?>
        <?= render_task($t, $users, $clients, $filterUser, $userLoadClasses, true); ?>
        <?php endforeach; ?>
      </ul>
    <?php elseif (!$filterUser && !$filterClient): ?>
      <ul id="all-list" class="list-group mb-4">
        <?php foreach ($allTasks as $t): ?>
        <?= render_task($t, $users, $clients, $filterUser, $userLoadClasses); ?>
        <?php endforeach; ?>
      </ul>
      <h3 class="mb-4">
        <a class="text-decoration-none" data-bs-toggle="collapse" href="#completed-collapse" role="button" aria-expanded="false" aria-controls="completed-collapse">Completed Tasks</a>
      </h3>
      <div id="completed-collapse" class="collapse">
        <ul id="completed-list" class="list-group mb-4">
          <?php foreach ($completedTasks as $t): ?>
          <?= render_task($t, $users, $clients, $filterUser, $userLoadClasses); ?>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <h3 class="mb-4">Today's & Overdue Tasks</h3>
      <ul id="today-list" class="list-group mb-4">
        <?php foreach ($todayTasks as $t): ?>
        <?= render_task($t, $users, $clients, $filterUser, $userLoadClasses); ?>
        <?php endforeach; ?>
      </ul>
      <h3 class="mb-4">Upcoming Tasks</h3>
      <ul id="upcoming-list" class="list-group mb-4">
        <?php foreach ($upcomingTasks as $t): ?>
        <?= render_task($t, $users, $clients, $filterUser, $userLoadClasses); ?>
        <?php endforeach; ?>
      </ul>
      <h3 class="mb-4">
        <a class="text-decoration-none" data-bs-toggle="collapse" href="#completed-collapse" role="button" aria-expanded="false" aria-controls="completed-collapse">Completed Tasks</a>
      </h3>
      <div id="completed-collapse" class="collapse">
        <ul id="completed-list" class="list-group mb-4">
          <?php foreach ($completedTasks as $t): ?>
          <?= render_task($t, $users, $clients, $filterUser, $userLoadClasses); ?>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
</div>
</div>
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Monthly Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <table class="table table-sm">
          <thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Duration</th></tr></thead>
          <tbody id="status-body">
            <?= $statusRows ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
let cairoNow = new Date('<?php echo date('c'); ?>');
const dtEl = document.getElementById('date-time');
if (dtEl) {
  function updateDT(){
    const dateStr = new Intl.DateTimeFormat('en-CA', {timeZone:'Africa/Cairo', year:'numeric', month:'2-digit', day:'2-digit'}).format(cairoNow).replace(/-/g,'/');
    const timeStr = new Intl.DateTimeFormat('en-US', {timeZone:'Africa/Cairo', hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true}).format(cairoNow);
    dtEl.textContent = `Date: ${dateStr} - Time: ${timeStr}`;
    cairoNow.setSeconds(cairoNow.getSeconds()+1);
  }
  updateDT();
  setInterval(updateDT,1000);
}
let workSeconds = <?php echo $currentLog ? (time() - strtotime($currentLog['clock_in'])) : 0; ?>;
const timerEl = document.getElementById('work-timer');
let timerInterval = null;
function updateWorkTimer(){
  if(!timerEl) return;
  const h = String(Math.floor(workSeconds/3600)).padStart(2,'0');
  const m = String(Math.floor((workSeconds%3600)/60)).padStart(2,'0');
  const s = String(workSeconds%60).padStart(2,'0');
  timerEl.textContent = `Shift: ${h}:${m}:${s}`;
  workSeconds++;
}
updateWorkTimer();
<?php if ($currentLog): ?>timerInterval = setInterval(updateWorkTimer,1000);<?php endif; ?>
function nl2br(str){
  return str.replace(/\n/g,'<br>');
}
function escapeHtml(str){
  return str.replace(/[&<>"']/g, function(m){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

document.getElementById('recurrence').addEventListener('change', function(){
  document.getElementById('day-select').classList.toggle('d-none', this.value !== 'custom');
  document.getElementById('interval-count').classList.toggle('d-none', this.value !== 'interval');
  document.getElementById('interval-unit').classList.toggle('d-none', this.value !== 'interval');
});

function renderCommentHTML(c){
  let files = '';
  if(c.files && c.files.length){
    files = '<ul class="list-inline small mb-0 mt-1">'+
      c.files.map(f=>`<li class="list-inline-item"><a href="uploads/${escapeHtml(f.file_path)}" target="_blank">${escapeHtml(f.original_name)}</a></li>`).join('')+
      '</ul>';
  }
  return `<div class="mb-2 comment-item" data-id="${c.id}">`+
    `<span class="comment-text"><strong>${escapeHtml(c.username)}:</strong> ${nl2br(escapeHtml(c.content))}</span>`+
    `<span class="float-end ms-2"><a href="#" class="text-decoration-none text-muted edit-comment" data-id="${c.id}" data-content="${escapeHtml(c.content)}"><i class="bi bi-pencil"></i></a>`+
    ` <a href="#" class="text-decoration-none text-danger ms-2 delete-comment" data-id="${c.id}"><i class="bi bi-trash"></i></a></span>`+
    files+
    `</div>`;
}

function bindCommentActions(scope=document){
  scope.querySelectorAll('.edit-comment').forEach(btn=>{
    btn.addEventListener('click', async e=>{
      e.preventDefault();
      const id = btn.dataset.id;
      const current = btn.dataset.content;
      const content = prompt('Edit comment', current);
      if(content !== null && content.trim() !== ''){
        const fd = new FormData();
        fd.append('edit_comment', id);
        fd.append('content', content.trim());
        const res = await fetch('index.php', {method:'POST', body:fd});
        const data = await res.json();
        if(data.success){
          const item = btn.closest('.comment-item');
          const name = item.querySelector('strong').textContent;
          item.querySelector('.comment-text').innerHTML = '<strong>'+escapeHtml(name)+'</strong> '+nl2br(escapeHtml(data.content));
          btn.dataset.content = data.content;
          showToast('Comment updated');
        }
      }
    });
  });
  scope.querySelectorAll('.delete-comment').forEach(btn=>{
    btn.addEventListener('click', async e=>{
      e.preventDefault();
      if(!confirm('Remove comment?')) return;
      const fd = new FormData();
      fd.append('delete_comment', btn.dataset.id);
      const res = await fetch('index.php', {method:'POST', body:fd});
      const data = await res.json();
      if(data.success){
        btn.closest('.comment-item').remove();
        showToast('Comment removed');
      }
    });
  });
}
bindCommentActions();

document.querySelectorAll('form.ajax').forEach(f=>{
  f.addEventListener('submit', async e=>{
    e.preventDefault();
    const fd = new FormData(f);
    const submitter = e.submitter;
    if (submitter && submitter.name && !fd.has(submitter.name)) {
      fd.append(submitter.name, submitter.value);
    }
    if (fd.has('add_task')) {
      await fetch('index.php', {method:'POST', body:fd});
      f.reset();
      showToast('Task added');
      location.reload();
    } else if (fd.has('add_comment')) {
      const res = await fetch('index.php', {method:'POST', body:fd});
      const data = await res.json();
      const list = f.parentElement.querySelector('.comments-list');
      list.insertAdjacentHTML('beforeend', renderCommentHTML(data));
      bindCommentActions(list.lastElementChild);
      f.reset();
      showToast('Comment added');
    } else if (fd.has('clock_in')) {
      const res = await fetch('index.php', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
      const data = await res.json();
      document.getElementById('status-body').innerHTML = data.status_html;
      workSeconds = 0;
      updateWorkTimer();
      timerInterval = setInterval(updateWorkTimer,1000);
      const btn = f.querySelector('button');
      btn.name = 'clock_out';
      btn.textContent = 'Clock Out';
      btn.classList.remove('btn-success');
      btn.classList.add('btn-danger');
      showToast('Clocked in');
    } else if (fd.has('clock_out')) {
      const res = await fetch('index.php', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
      const data = await res.json();
      document.getElementById('status-body').innerHTML = data.status_html;
      clearInterval(timerInterval);
      workSeconds = 0;
      updateWorkTimer();
      const btn = f.querySelector('button');
      btn.name = 'clock_in';
      btn.textContent = 'Clock In';
      btn.classList.remove('btn-danger');
      btn.classList.add('btn-success');
      showToast('Clocked out');
    } else {
      await fetch('index.php', {method:'POST', body:fd});
    }
  });
});

document.querySelectorAll('.upload-trigger').forEach(icon=>{
  icon.addEventListener('click', ()=>{
    const target = document.getElementById(icon.dataset.target);
    target?.click();
  });
});

function insertSorted(li, list){
  if(!list) return;
  const items = Array.from(list.children);
  const before = items.find(el => el.dataset.dueDate > li.dataset.dueDate);
  list.insertBefore(li, before || null);
}

document.querySelectorAll('.complete-checkbox').forEach(cb=>{
  cb.addEventListener('change', async ()=>{
    const form = cb.closest('form');
    const fd = new FormData(form);
    if(cb.checked) fd.set('completed','1');
    const res = await fetch('index.php', {method:'POST', body:fd});
    const text = (await res.text()).trim();
    const li = form.closest('li');
    const completedList = document.getElementById('completed-list');
    const todayList = document.getElementById('today-list');
    const upcomingList = document.getElementById('upcoming-list');
    const allList = document.getElementById('all-list');
    const parentId = li.dataset.parentId;
    const parentList = parentId ? document.querySelector(`ul.subtask-list[data-parent="${parentId}"]`) : null;
    const todayStr = new Intl.DateTimeFormat('en-CA',{timeZone:'Africa/Cairo'}).format(new Date());
    if(/^\d{4}-\d{2}-\d{2}$/.test(text)){
      li.querySelector('.due-date').innerHTML = '<i class="bi bi-calendar-event me-1"></i>' + text;
      li.dataset.dueDate = text;
      cb.checked = false;
      li.classList.remove('opacity-50');
      if(completedList){
        if(parentList){
          insertSorted(li, parentList);
        } else if(allList){
          insertSorted(li, allList);
        } else {
          const target = text > todayStr ? upcomingList : todayList;
          insertSorted(li, target);
        }
      }
    } else if(text === 'done') {
      li.classList.add('opacity-50');
      if(completedList) insertSorted(li, completedList);
    } else if(text === 'pending') {
      li.classList.remove('opacity-50');
      if(completedList){
        if(parentList){
          insertSorted(li, parentList);
        } else if(allList){
          insertSorted(li, allList);
        } else {
          const target = li.dataset.dueDate > todayStr ? upcomingList : todayList;
          insertSorted(li, target);
        }
      }
    } else {
      li.classList.toggle('opacity-50', cb.checked);
    }
    showToast('Task saved');
  });
});

function collapseSiblings(currentLi, currentCollapse){
  const parentUl = currentLi.parentElement;
  parentUl.querySelectorAll(':scope > li .collapse.show').forEach(el=>{
    if(el !== currentCollapse){
      new bootstrap.Collapse(el, {toggle:false}).hide();
      const li = el.closest('li');
      const form = el.querySelector('.task-form');
      const desc = el.querySelector('.description');
      if(form && desc){
        form.classList.add('d-none');
        desc.classList.remove('d-none');
      }
      li.querySelector('.save-btn')?.classList.add('d-none');
      li.querySelector('.add-subtask-toggle')?.classList.add('d-none');
    }
  });
}

document.querySelectorAll('.collapse[id^="task-"]').forEach(col=>{
  col.addEventListener('show.bs.collapse', ()=>{
    const li = col.closest('li');
    collapseSiblings(li, col);
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
    form.classList.toggle('d-none');
    desc.classList.toggle('d-none');
    const editing = !form.classList.contains('d-none');
    saveBtn.classList.toggle('d-none', !editing);
    subBtn?.classList.toggle('d-none', !editing);
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
    const newTitle = form.querySelector('[data-field=title]').textContent.trim();
    li.dataset.subTitle = newTitle;
    if(li.dataset.parentTitle){
      li.querySelector('.task-title-text').textContent = li.dataset.parentTitle + ' - ' + newTitle;
    } else {
      li.querySelector('.task-title-text').textContent = newTitle;
      const removeDup = !newTitle.includes('(duplicated)');
      li.querySelectorAll('.subtask-list li').forEach(sub=>{
        let subTitle = sub.dataset.subTitle || '';
        if(removeDup){
          subTitle = subTitle.replace(/\s*\(duplicated\)$/,'').trim();
          sub.dataset.subTitle = subTitle;
        }
        sub.dataset.parentTitle = newTitle;
        const editTitle = sub.querySelector('[data-field=title]');
        if(editTitle) editTitle.textContent = subTitle;
        sub.querySelector('.task-title-text').textContent = newTitle + ' - ' + subTitle;
      });
    }
    li.querySelector('.description').innerHTML = form.querySelector('[data-field=description]').textContent.replace(/\n/g,'<br>');
    const newDate = form.querySelector('input[name=due_date]').value;
    li.querySelector('.due-date').innerHTML = '<i class="bi bi-calendar-event me-1"></i>' + newDate;
    li.dataset.dueDate = newDate;
    li.querySelector('.assignee').textContent = form.querySelector('select[name=assigned]').selectedOptions[0].textContent;
    const clientSel = form.querySelector('select[name=client_id]');
    if(clientSel){
      const opt = clientSel.selectedOptions[0];
      const cName = clientSel.value ? opt.textContent : 'Others';
      const cPrio = clientSel.value ? (opt.dataset.priority || '') : '';
      const clientWrapper = li.querySelector('.client');
      let clientSpan = clientWrapper.querySelector('.client-priority');
      if(clientSel.value){
        if(!clientSpan){
          clientSpan = document.createElement('span');
          clientWrapper.textContent = '';
          clientWrapper.appendChild(clientSpan);
        }
        clientSpan.textContent = cName;
        clientSpan.className = 'client-priority ' + cPrio.toLowerCase();
      } else {
        if(clientSpan) clientSpan.remove();
        clientWrapper.textContent = 'Others';
      }
      li.querySelectorAll('.subtask-list li').forEach(sub=>{
        const subClient = sub.querySelector('.client');
        let subSpan = subClient.querySelector('.client-priority');
        if(clientSel.value){
          if(!subSpan){
            subSpan = document.createElement('span');
            subClient.textContent = '';
            subClient.appendChild(subSpan);
          }
          subSpan.textContent = cName;
          subSpan.className = 'client-priority ' + cPrio.toLowerCase();
        } else {
          if(subSpan) subSpan.remove();
          subClient.textContent = 'Others';
        }
        const subInput = sub.querySelector('input[name=client_id]');
        if(subInput) subInput.value = clientSel.value;
      });
      // Priority display removed
    }
    form.classList.add('d-none');
    collapse.querySelector('.description').classList.remove('d-none');
    btn.classList.add('d-none');
    li.querySelector('.add-subtask-toggle')?.classList.add('d-none');
    showToast('Task saved');
    const params = new URLSearchParams(window.location.search);
    const range = params.get('range') || 'all';
    const now = new Date();
    const cairoNow = new Date(now.toLocaleString('en-US', {timeZone:'Africa/Cairo'}));
    const todayStr = cairoNow.toLocaleDateString('en-CA');
    const weekEnd = new Date(cairoNow);
    weekEnd.setDate(weekEnd.getDate() + 7);
    const weekEndStr = weekEnd.toLocaleDateString('en-CA');
    let keep = true;
    if(range === 'overdue') keep = newDate < todayStr;
    else if(range === 'today') keep = newDate === todayStr;
    else if(range === 'week') keep = newDate >= todayStr && newDate <= weekEndStr;
    if(!keep){
      li.remove();
      return;
    }
    const todayList = document.getElementById('today-list');
    const upcomingList = document.getElementById('upcoming-list');
    if (todayList && upcomingList) {
      const targetList = newDate > todayStr ? upcomingList : todayList;
      insertSorted(li, targetList);
    }
  });
});

document.querySelectorAll('.archive-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    const res = await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'archive_task='+id});
    const html = (await res.text()).trim();
    const li = btn.closest('li');
    li.remove();
    const archList = document.getElementById('archived-list');
    const archSubList = document.getElementById('archived-sub-list');
    if((archList || archSubList) && html){
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const newLi = tmp.firstElementChild;
      if(newLi.dataset.parentTitle && archSubList){
        archSubList.prepend(newLi);
      } else if(archList){
        archList.prepend(newLi);
      }
      initTask(newLi);
    }
    showToast('Task archived');
  });
});

document.querySelectorAll('.unarchive-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    const res = await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'unarchive_task='+id});
    const html = (await res.text()).trim();
    const li = btn.closest('li');
    li.remove();
    const activeList = document.getElementById('all-list') || document.getElementById('today-list') || document.getElementById('upcoming-list');
    if(activeList && html){
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const newLi = tmp.firstElementChild;
      activeList.prepend(newLi);
      initTask(newLi);
      const range = new URLSearchParams(window.location.search).get('range') || 'all';
      const newDate = newLi.querySelector('.due-date').textContent.trim();
      const now = new Date();
      const cairoNow = new Date(now.toLocaleString('en-US', {timeZone:'Africa/Cairo'}));
      const todayStr = cairoNow.toLocaleDateString('en-CA');
      const weekEnd = new Date(cairoNow);
      weekEnd.setDate(weekEnd.getDate() + 7);
      const weekEndStr = weekEnd.toLocaleDateString('en-CA');
      let keep = true;
      if(range === 'overdue') keep = newDate < todayStr;
      else if(range === 'today') keep = newDate === todayStr;
      else if(range === 'week') keep = newDate >= todayStr && newDate <= weekEndStr;
      if(!keep){ newLi.remove(); return; }
      const todayList = document.getElementById('today-list');
      const upcomingList = document.getElementById('upcoming-list');
      if(todayList && upcomingList){
        if(newDate > todayStr){
          upcomingList.appendChild(newLi);
        } else {
          todayList.appendChild(newLi);
        }
      }
    }
    showToast('Task unarchived');
  });
});

document.querySelectorAll('.share-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    const res = await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'share_task='+id});
    const url = (await res.text()).trim();
    if(url){
      await navigator.clipboard.writeText(url);
      showToast('Link copied');
    }
  });
});

document.querySelectorAll('.duplicate-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id;
    const res = await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'duplicate_task='+id});
    const html = (await res.text()).trim();
    if(html){
      const li = btn.closest('li');
      const parentList = li.parentElement;
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const newLi = tmp.firstElementChild;
      parentList.insertBefore(newLi, li.nextSibling);
      initTask(newLi);
      const range = new URLSearchParams(window.location.search).get('range') || 'all';
      const newDate = newLi.querySelector('.due-date').textContent.trim();
      const now = new Date();
      const cairoNow = new Date(now.toLocaleString('en-US', {timeZone:'Africa/Cairo'}));
      const todayStr = cairoNow.toLocaleDateString('en-CA');
      const weekEnd = new Date(cairoNow);
      weekEnd.setDate(weekEnd.getDate() + 7);
      const weekEndStr = weekEnd.toLocaleDateString('en-CA');
      let keep = true;
      if(range === 'overdue') keep = newDate < todayStr;
      else if(range === 'today') keep = newDate === todayStr;
      else if(range === 'week') keep = newDate >= todayStr && newDate <= weekEndStr;
      if(!keep){ newLi.remove(); return; }
      const todayList = document.getElementById('today-list');
      const upcomingList = document.getElementById('upcoming-list');
      if(todayList && upcomingList){
        if(newDate > todayStr){
          upcomingList.appendChild(newLi);
        } else {
          todayList.appendChild(newLi);
        }
      }
    }
    showToast('Task duplicated');
  });
});

document.querySelectorAll('.delete-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    if(!confirm('Remove this task?')) return;
    const id = btn.dataset.id;
    await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'delete_task='+id});
    btn.closest('li').remove();
    showToast('Task removed');
  });
});

document.querySelectorAll('.subtask-list').forEach(list=>{
  new Sortable(list, {
    animation:150,
    onEnd: async ()=>{
      const order = Array.from(list.children).map(li=>li.dataset.taskId).join(',');
      const params = new URLSearchParams();
      params.set('reorder_subtasks','1');
      params.set('parent_id', list.dataset.parent);
      params.set('order', order);
      await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params.toString()});
    }
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
    const cairoNow = new Date(now.toLocaleString('en-US', { timeZone: 'Africa/Cairo' }));
    let target = new Date(cairoNow);
    if(sel.value === 'today') {
      // target is already today
    } else if(sel.value === 'tomorrow') {
      target.setDate(target.getDate() + 1);
    } else if(sel.value === 'nextweek') {
      const daysUntilSunday = (7 - target.getDay()) % 7 || 7;
      target.setDate(target.getDate() + daysUntilSunday);
    } else if(sel.value === 'nextmonth') {
      target.setMonth(target.getMonth() + 1);
    }
    target = nextWorkingDay(target);
    dateInput.value = target.toLocaleDateString('en-CA', { timeZone: 'Africa/Cairo' });
    sel.value='';
  });
});

function initTask(li){
  li.querySelectorAll('form.ajax').forEach(f=>{
    f.addEventListener('submit', async e=>{
      e.preventDefault();
      const fd = new FormData(f);
      await fetch('index.php', {method:'POST', body:fd});
      if(fd.has('add_task')){
        f.reset();
        showToast('Task added');
        location.reload();
      }
    });
  });

  li.querySelectorAll('.complete-checkbox').forEach(cb=>{
    cb.addEventListener('change', async ()=>{
      const form = cb.closest('form');
      const fd = new FormData(form);
      if(cb.checked) fd.set('completed','1');
      const res = await fetch('index.php', {method:'POST', body:fd});
      const text = (await res.text()).trim();
      const liEl = form.closest('li');
      const completedList = document.getElementById('completed-list');
      const todayList = document.getElementById('today-list');
      const upcomingList = document.getElementById('upcoming-list');
      const allList = document.getElementById('all-list');
      const parentId = liEl.dataset.parentId;
      const parentList = parentId ? document.querySelector(`ul.subtask-list[data-parent="${parentId}"]`) : null;
      const todayStr = new Intl.DateTimeFormat('en-CA',{timeZone:'Africa/Cairo'}).format(new Date());
      if(/^\\d{4}-\\d{2}-\\d{2}$/.test(text)){
        liEl.querySelector('.due-date').innerHTML = '<i class="bi bi-calendar-event me-1"></i>' + text;
        liEl.dataset.dueDate = text;
        cb.checked = false;
        liEl.classList.remove('opacity-50');
        if(completedList){
          if(parentList){
            insertSorted(liEl, parentList);
          } else if(allList){
            insertSorted(liEl, allList);
          } else {
            const target = text > todayStr ? upcomingList : todayList;
            insertSorted(liEl, target);
          }
        }
      } else if(text === 'done'){
        liEl.classList.add('opacity-50');
        if(completedList) insertSorted(liEl, completedList);
      } else if(text === 'pending'){
        liEl.classList.remove('opacity-50');
        if(completedList){
          if(parentList){
            insertSorted(liEl, parentList);
          } else if(allList){
            insertSorted(liEl, allList);
          } else {
            const target = liEl.dataset.dueDate > todayStr ? upcomingList : todayList;
            insertSorted(liEl, target);
          }
        }
      } else {
        liEl.classList.toggle('opacity-50', cb.checked);
      }
      showToast('Task saved');
    });
  });

  li.querySelectorAll('.collapse[id^="task-"]').forEach(col=>{
    col.addEventListener('show.bs.collapse', ()=>{
      const liEl = col.closest('li');
      collapseSiblings(liEl, col);
    });
  });

  li.querySelectorAll('.edit-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.dataset.id;
      const liEl = btn.closest('li');
      const collapse = document.getElementById('task-'+id);
      const form = collapse.querySelector('form');
      const desc = collapse.querySelector('.description');
      const saveBtn = liEl.querySelector('.save-btn');
      const subBtn = liEl.querySelector('.add-subtask-toggle');
      form.classList.toggle('d-none');
      desc.classList.toggle('d-none');
      const editing = !form.classList.contains('d-none');
      saveBtn.classList.toggle('d-none', !editing);
      subBtn?.classList.toggle('d-none', !editing);
      new bootstrap.Collapse(collapse, {toggle:false}).show();
    });
  });

  li.querySelectorAll('.save-btn').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.dataset.id;
      const liEl = btn.closest('li');
      const collapse = document.getElementById('task-'+id);
      const form = collapse.querySelector('form');
      const fd = new FormData(form);
      fd.set('title', form.querySelector('[data-field=title]').textContent.trim());
      fd.set('description', form.querySelector('[data-field=description]').textContent.trim());
      await fetch('index.php', {method:'POST', body:fd});
      const newTitle = form.querySelector('[data-field=title]').textContent.trim();
      liEl.dataset.subTitle = newTitle;
      if(liEl.dataset.parentTitle){
        liEl.querySelector('.task-title-text').textContent = liEl.dataset.parentTitle + ' - ' + newTitle;
      } else {
        liEl.querySelector('.task-title-text').textContent = newTitle;
        const removeDup = !newTitle.includes('(duplicated)');
        liEl.querySelectorAll('.subtask-list li').forEach(sub=>{
          let subTitle = sub.dataset.subTitle || '';
          if(removeDup){
            subTitle = subTitle.replace(/\s*\(duplicated\)$/,'').trim();
            sub.dataset.subTitle = subTitle;
          }
          sub.dataset.parentTitle = newTitle;
          const editTitle = sub.querySelector('[data-field=title]');
          if(editTitle) editTitle.textContent = subTitle;
          sub.querySelector('.task-title-text').textContent = newTitle + ' - ' + subTitle;
        });
      }
      liEl.querySelector('.description').innerHTML = form.querySelector('[data-field=description]').textContent.replace(/\\n/g,'<br>');
      const newDate = form.querySelector('input[name=due_date]').value;
      liEl.querySelector('.due-date').innerHTML = '<i class="bi bi-calendar-event me-1"></i>' + newDate;
      liEl.dataset.dueDate = newDate;
      liEl.querySelector('.assignee').textContent = form.querySelector('select[name=assigned]').selectedOptions[0].textContent;
      const clientSel = form.querySelector('select[name=client_id]');
      if(clientSel){
        const opt = clientSel.selectedOptions[0];
        const cName = clientSel.value ? opt.textContent : 'Others';
        const cPrio = clientSel.value ? (opt.dataset.priority || '') : '';
        const clientWrapper = liEl.querySelector('.client');
        let clientSpan = clientWrapper.querySelector('.client-priority');
        if(clientSel.value){
          if(!clientSpan){
            clientSpan = document.createElement('span');
            clientWrapper.textContent = '';
            clientWrapper.appendChild(clientSpan);
          }
          clientSpan.textContent = cName;
          clientSpan.className = 'client-priority ' + cPrio.toLowerCase();
        } else {
          if(clientSpan) clientSpan.remove();
          clientWrapper.textContent = 'Others';
        }
        liEl.querySelectorAll('.subtask-list li').forEach(sub=>{
          const subClient = sub.querySelector('.client');
          let subSpan = subClient.querySelector('.client-priority');
          if(clientSel.value){
            if(!subSpan){
              subSpan = document.createElement('span');
              subClient.textContent = '';
              subClient.appendChild(subSpan);
            }
            subSpan.textContent = cName;
            subSpan.className = 'client-priority ' + cPrio.toLowerCase();
          } else {
            if(subSpan) subSpan.remove();
            subClient.textContent = 'Others';
          }
          const subInput = sub.querySelector('input[name=client_id]');
          if(subInput) subInput.value = clientSel.value;
        });
      }
      form.classList.add('d-none');
      collapse.querySelector('.description').classList.remove('d-none');
      btn.classList.add('d-none');
      liEl.querySelector('.add-subtask-toggle')?.classList.add('d-none');
      showToast('Task saved');
      const params = new URLSearchParams(window.location.search);
      const range = params.get('range') || 'all';
      const now = new Date();
      const cairoNow = new Date(now.toLocaleString('en-US', {timeZone:'Africa/Cairo'}));
      const todayStr = cairoNow.toLocaleDateString('en-CA');
      const weekEnd = new Date(cairoNow);
      weekEnd.setDate(weekEnd.getDate() + 7);
      const weekEndStr = weekEnd.toLocaleDateString('en-CA');
      let keep = true;
      if(range === 'overdue') keep = newDate < todayStr;
      else if(range === 'today') keep = newDate === todayStr;
      else if(range === 'week') keep = newDate >= todayStr && newDate <= weekEndStr;
      if(!keep){ liEl.remove(); return; }
      const todayList = document.getElementById('today-list');
      const upcomingList = document.getElementById('upcoming-list');
      if(todayList && upcomingList){
        const targetList = newDate > todayStr ? upcomingList : todayList;
        insertSorted(liEl, targetList);
      }
    });
  });

  li.querySelectorAll('.archive-btn').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.dataset.id;
      const res = await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'archive_task='+id});
      const html = (await res.text()).trim();
      const liEl = btn.closest('li');
      liEl.remove();
      const archList = document.getElementById('archived-list');
      const archSubList = document.getElementById('archived-sub-list');
      if((archList || archSubList) && html){
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const newLi = tmp.firstElementChild;
        if(newLi.dataset.parentTitle && archSubList){
          archSubList.prepend(newLi);
        } else if(archList){
          archList.prepend(newLi);
        }
        initTask(newLi);
      }
      showToast('Task archived');
    });
  });

  li.querySelectorAll('.unarchive-btn').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.dataset.id;
      const res = await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'unarchive_task='+id});
      const html = (await res.text()).trim();
      const liEl = btn.closest('li');
      liEl.remove();
      const activeList = document.getElementById('all-list') || document.getElementById('today-list') || document.getElementById('upcoming-list');
      if(activeList && html){
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const newLi = tmp.firstElementChild;
        activeList.prepend(newLi);
        initTask(newLi);
        const range = new URLSearchParams(window.location.search).get('range') || 'all';
        const newDate = newLi.querySelector('.due-date').textContent.trim();
        const now = new Date();
        const cairoNow = new Date(now.toLocaleString('en-US', {timeZone:'Africa/Cairo'}));
        const todayStr = cairoNow.toLocaleDateString('en-CA');
        const weekEnd = new Date(cairoNow);
        weekEnd.setDate(weekEnd.getDate() + 7);
        const weekEndStr = weekEnd.toLocaleDateString('en-CA');
        let keep = true;
        if(range === 'overdue') keep = newDate < todayStr;
        else if(range === 'today') keep = newDate === todayStr;
        else if(range === 'week') keep = newDate >= todayStr && newDate <= weekEndStr;
        if(!keep){ newLi.remove(); return; }
        const todayList = document.getElementById('today-list');
        const upcomingList = document.getElementById('upcoming-list');
        if(todayList && upcomingList){
          if(newDate > todayStr){
            upcomingList.appendChild(newLi);
          } else {
            todayList.appendChild(newLi);
          }
        }
      }
      showToast('Task unarchived');
    });
  });

  li.querySelectorAll('.share-btn').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.dataset.id;
      const res = await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'share_task='+id});
      const url = (await res.text()).trim();
      if(url){
        await navigator.clipboard.writeText(url);
        showToast('Link copied');
      }
    });
  });

  li.querySelectorAll('.duplicate-btn').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.dataset.id;
      const res = await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'duplicate_task='+id});
      const html = (await res.text()).trim();
      if(html){
        const liEl = btn.closest('li');
        const parentList = liEl.parentElement;
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const newLi = tmp.firstElementChild;
        parentList.insertBefore(newLi, liEl.nextSibling);
        initTask(newLi);
        const range = new URLSearchParams(window.location.search).get('range') || 'all';
        const newDate = newLi.querySelector('.due-date').textContent.trim();
        const now = new Date();
        const cairoNow = new Date(now.toLocaleString('en-US', {timeZone:'Africa/Cairo'}));
        const todayStr = cairoNow.toLocaleDateString('en-CA');
        const weekEnd = new Date(cairoNow);
        weekEnd.setDate(weekEnd.getDate() + 7);
        const weekEndStr = weekEnd.toLocaleDateString('en-CA');
        let keep = true;
        if(range === 'overdue') keep = newDate < todayStr;
        else if(range === 'today') keep = newDate === todayStr;
        else if(range === 'week') keep = newDate >= todayStr && newDate <= weekEndStr;
        if(!keep){ newLi.remove(); return; }
        const todayList = document.getElementById('today-list');
        const upcomingList = document.getElementById('upcoming-list');
        if(todayList && upcomingList){
          if(newDate > todayStr){
            upcomingList.appendChild(newLi);
          } else {
            todayList.appendChild(newLi);
          }
        }
      }
      showToast('Task duplicated');
    });
  });

  li.querySelectorAll('.delete-btn').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      if(!confirm('Remove this task?')) return;
      const id = btn.dataset.id;
      await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'delete_task='+id});
      btn.closest('li').remove();
      showToast('Task removed');
    });
  });

  li.querySelectorAll('.subtask-list').forEach(list=>{
    new Sortable(list, {
      animation:150,
      onEnd: async ()=>{
        const order = Array.from(list.children).map(li=>li.dataset.taskId).join(',');
        const params = new URLSearchParams();
        params.set('reorder_subtasks','1');
        params.set('parent_id', list.dataset.parent);
        params.set('order', order);
        await fetch('index.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params.toString()});
      }
    });
  });

  li.querySelectorAll('.recurrence-select').forEach(sel=>{
    sel.addEventListener('change', function(){
      const parent = this.closest('form');
      parent.querySelector('.recurrence-days')?.classList.toggle('d-none', this.value !== 'custom');
      parent.querySelector('.recurrence-interval')?.classList.toggle('d-none', this.value !== 'interval');
      parent.querySelector('.recurrence-unit')?.classList.toggle('d-none', this.value !== 'interval');
    });
  });

  li.querySelectorAll('.quick-date').forEach(sel=>{
    sel.addEventListener('change', ()=>{
      const dateInput = sel.closest('form').querySelector('input[name=due_date]');
      const now = new Date();
      const cairoNow = new Date(now.toLocaleString('en-US', { timeZone: 'Africa/Cairo' }));
      let target = new Date(cairoNow);
      if(sel.value === 'today') {
        // target is already today
      } else if(sel.value === 'tomorrow') {
        target.setDate(target.getDate() + 1);
      } else if(sel.value === 'nextweek') {
        const daysUntilSunday = (7 - target.getDay()) % 7 || 7;
        target.setDate(target.getDate() + daysUntilSunday);
      } else if(sel.value === 'nextmonth') {
        target.setMonth(target.getMonth() + 1);
      }
      target = nextWorkingDay(target);
      dateInput.value = target.toLocaleDateString('en-CA', { timeZone: 'Africa/Cairo' });
      sel.value='';
    });
  });

  li.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  li.querySelectorAll('.add-subtask-toggle').forEach(el => new bootstrap.Tooltip(el));
}

window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  document.querySelectorAll('.add-subtask-toggle').forEach(el => new bootstrap.Tooltip(el));
  new bootstrap.Tooltip(document.getElementById('addBtn'));
  const tid = new URLSearchParams(window.location.search).get('task');
  if(tid){
    const li = document.querySelector(`[data-task-id="${tid}"]`);
    if(li){ li.classList.add('task-focus-shadow'); }
    const collapse = document.getElementById('task-'+tid);
    if(li && collapse){
      const scrollToTask = () => {
        const header = document.querySelector('.navbar');
        const offset = header ? header.offsetHeight + 10 : 0;
        const top = li.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({top, behavior:'smooth'});
      };
      const openTask = () => {
        collapse.addEventListener('shown.bs.collapse', scrollToTask, {once:true});
        new bootstrap.Collapse(collapse, {toggle:true});
      };
      const parentCollapse = li.closest('.collapse');
      if(parentCollapse && parentCollapse !== collapse && !parentCollapse.classList.contains('show')){
        parentCollapse.addEventListener('shown.bs.collapse', openTask, {once:true});
        new bootstrap.Collapse(parentCollapse, {toggle:true});
      } else {
        openTask();
      }
    }
  }
<?php if(isset($_GET['saved'])): ?>
  showToast('Order saved');
<?php endif; ?>
});
</script>
<?php include __DIR__ . '/footer.php'; ?>