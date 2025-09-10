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
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) die('Client not found');

$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $client['name']), '-'));
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "covers.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Covers';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="source.php?<?=$base?>">Source</a></li>
  <li class="nav-item"><a class="nav-link" href="occasions.php?<?=$base?>">Occasions</a></li>
  <li class="nav-item"><a class="nav-link active" href="covers.php?<?=$base?>">Covers</a></li>
  <li class="nav-item"><a class="nav-link" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="content.php?<?=$base?>">Content</a></li>
</ul>
<div class="row">
  <div class="col-md-4">
    <div class="input-group mb-3">
      <select id="coverType" class="form-select">
        <option value="facebook" data-size="1230x468">Facebook Cover</option>
        <option value="linkedin" data-size="1128x191">LinkedIn Cover</option>
        <option value="highlights" data-size="1128x191">Instagram Highlights</option>
      </select>
      <button class="btn btn-outline-secondary" type="button" id="importBtn">Import</button>
    </div>
    <div class="mb-3">
      <label class="form-label">Facebook Cover</label>
      <input type="text" id="fbUrl" class="form-control mb-2" readonly>
      <label class="form-label">LinkedIn Cover</label>
      <input type="text" id="liUrl" class="form-control mb-2" readonly>
      <label class="form-label">Instagram Highlights</label>
      <input type="text" id="igUrl" class="form-control" readonly>
    </div>
  </div>
  <div class="col-md-8">
    <div id="preview" class="border bg-light d-flex align-items-center justify-content-center" style="height:300px;">
      Creative Here
    </div>
  </div>
</div>
<script>
document.getElementById('importBtn').addEventListener('click', () => {
  const sel = document.getElementById('coverType');
  const opt = sel.options[sel.selectedIndex];
  const size = opt.dataset.size;
  const [w,h] = size.split('x');
  const url = `https://placehold.co/${w}x${h}`;
  if (sel.value === 'facebook') {
    document.getElementById('fbUrl').value = url;
  } else if (sel.value === 'linkedin') {
    document.getElementById('liUrl').value = url;
  } else {
    document.getElementById('igUrl').value = url;
  }
  document.getElementById('preview').innerHTML = `<img src="${url}" class="img-fluid" alt="${opt.text}">`;
});
</script>
<?php include 'footer.php'; ?>
