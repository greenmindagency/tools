<?php
require_once __DIR__ . '/session.php';
require 'config.php';
$client_id = $_GET['client_id'] ?? 0;
$isAdmin = $_SESSION['is_admin'] ?? false;
if (!$isAdmin) {
    $allowed = $_SESSION['client_ids'] ?? [];
    if ($allowed) {
        if (!in_array($client_id, $allowed)) {
            header('Location: login.php');
            exit;
        }
        $_SESSION['client_id'] = $client_id;
    } elseif (!isset($_SESSION['client_id']) || $_SESSION['client_id'] != $client_id) {
        header('Location: login.php');
        exit;
    }
}
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) die('Client not found');
$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $client['name']), '-'));
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "posts.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Posts';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="source.php?<?=$base?>">Source</a></li>
  <li class="nav-item"><a class="nav-link" href="occasions.php?<?=$base?>">Occasions</a></li>
  <li class="nav-item"><a class="nav-link" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link active" href="posts.php?<?=$base?>">Posts</a></li>
</ul>
<?php
// fetch saved posts from database
$stmt = $pdo->prepare('SELECT post_date,title FROM client_calendar WHERE client_id = ? ORDER BY post_date DESC');
$stmt->execute([$client_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$grouped = [];
foreach ($rows as $row) {
    $key = date('F Y', strtotime($row['post_date']));
    $grouped[$key][] = $row;
}
?>
<?php foreach ($grouped as $month => $items): ?>
  <h4><?=htmlspecialchars($month)?></h4>
  <table class="table table-bordered mb-4">
    <thead><tr><th>Date</th><th>Title</th></tr></thead>
    <tbody>
    <?php foreach ($items as $row): ?>
      <tr>
        <td><?=$row['post_date']?></td>
        <td><?=htmlspecialchars($row['title'])?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endforeach; ?>
<?php include 'footer.php'; ?>
