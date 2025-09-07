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

$saved = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $countries = $_POST['country'] ?? [];
    $data = $_POST['data'] ?? [];
    $del = $pdo->prepare('DELETE FROM occasions WHERE country = ?');
    $ins = $pdo->prepare('INSERT INTO occasions (country, occasion_date, name) VALUES (?,?,?)');
    foreach ($countries as $i => $country) {
        $country = trim($country);
        if ($country === '') continue;
        $del->execute([$country]);
        $lines = preg_split("/\r?\n/", $data[$i] ?? '');
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $parts = preg_split("/[\t,]+/", $line, 2);
            if (count($parts) < 2) continue;
            $date = trim($parts[0]);
            $name = trim($parts[1]);
            if ($date && $name) {
                $ins->execute([$country, $date, $name]);
            }
        }
    }
    $saved = 'Occasions saved.';
}

$countries = [];
$stmt = $pdo->query("SELECT DISTINCT country FROM occasions ORDER BY country");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $c = $row['country'];
    $occs = $pdo->prepare('SELECT occasion_date,name FROM occasions WHERE country = ? ORDER BY occasion_date');
    $occs->execute([$c]);
    $lines = [];
    foreach ($occs as $o) {
        $lines[] = $o['occasion_date']."\t".$o['name'];
    }
    $countries[] = ['name'=>$c,'lines'=>implode("\n", $lines)];
}

$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) die('Client not found');

$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/','-',$client['name']),'-'));
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "occasions.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Occasions';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="source.php?<?=$base?>">Source</a></li>
  <li class="nav-item"><a class="nav-link active" href="occasions.php?<?=$base?>">Occasions</a></li>
  <li class="nav-item"><a class="nav-link" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="posts.php?<?=$base?>">Posts</a></li>
</ul>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<form method="post" id="occForm">
  <div id="countriesContainer"></div>
  <button type="button" id="addCountry" class="btn btn-outline-success mt-2">Add Country</button>
  <button type="submit" class="btn btn-primary mt-2">Save</button>
</form>
<script>
const existing = <?= json_encode($countries) ?>;
const container = document.getElementById('countriesContainer');
function addCountry(name='', lines=''){
  const div = document.createElement('div');
  div.className = 'card mb-3';
  div.innerHTML = `
    <div class="card-header">
      <div class="input-group">
        <input type="text" name="country[]" class="form-control country-name" placeholder="Country" value="${name}">
        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.card').remove()">-</button>
      </div>
    </div>
    <div class="card-body">
      <textarea name="data[]" class="form-control paste" rows="5">${lines}</textarea>
      <table class="table table-sm preview mt-2"></table>
    </div>`;
  container.appendChild(div);
  div.querySelector('.paste').addEventListener('input', e=>updateTable(e.target));
  updateTable(div.querySelector('.paste'));
}
function updateTable(ta){
  const tbl = ta.parentElement.querySelector('.preview');
  tbl.innerHTML='';
  const lines = ta.value.split(/\n/).filter(l=>l.trim());
  for(const line of lines){
    const row = tbl.insertRow();
    const [d,n] = line.split(/\t|,/);
    row.insertCell().textContent = d?.trim()||'';
    row.insertCell().textContent = n?.trim()||'';
  }
}
existing.forEach(c=>addCountry(c.name, c.lines));
if(!existing.length) addCountry();
document.getElementById('addCountry').addEventListener('click',()=>addCountry());
</script>
<?php include 'footer.php'; ?>
