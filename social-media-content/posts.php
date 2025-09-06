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
  <li class="nav-item"><a class="nav-link" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link active" href="posts.php?<?=$base?>">Posts</a></li>
</ul>
<div id="postsContainer" class="row g-3"></div>
<script>
const clientId=<?=$client_id?>;
const container=document.getElementById('postsContainer');
const data=JSON.parse(localStorage.getItem('smc_calendar_'+clientId)||'[]');
data.forEach(item=>{
  const div=document.createElement('div');
  div.className='col-12';
  div.innerHTML=`<h5>${item.title}</h5><p>${item.date}</p>`;
  container.appendChild(div);
});
</script>
<?php include 'footer.php'; ?>
