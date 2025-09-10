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
    $del = $pdo->prepare('DELETE FROM occasions WHERE client_id = ? AND country = ?');
    $ins = $pdo->prepare('INSERT INTO occasions (client_id, country, occasion_date, name) VALUES (?,?,?,?)');
    foreach ($countries as $i => $country) {
        $country = trim($country);
        if ($country === '') continue;
        $del->execute([$client_id, $country]);
        $lines = preg_split("/\r?\n/", $data[$i] ?? '');
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $parts = preg_split("/[\t,]+/", $line, 2);
            if (count($parts) < 2) continue;
            $date = trim($parts[0]);
            $name = trim($parts[1]);
            if ($date && $name) {
                $ins->execute([$client_id, $country, $date, $name]);
            }
        }
    }
    $saved = 'Occasions saved.';
}

$countries = [];
$stmt = $pdo->prepare("SELECT DISTINCT country FROM occasions WHERE client_id = ? ORDER BY country");
$stmt->execute([$client_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $c = $row['country'];
    $occs = $pdo->prepare('SELECT occasion_date,name FROM occasions WHERE client_id = ? AND country = ? ORDER BY occasion_date');
    $occs->execute([$client_id, $c]);
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
  <li class="nav-item"><a class="nav-link" href="covers.php?<?=$base?>">Covers</a></li>
  <li class="nav-item"><a class="nav-link" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="content.php?<?=$base?>">Content</a></li>
</ul>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<form method="post" id="occForm">
  <div id="countriesContainer"></div>
  <button type="button" id="addCountry" class="btn btn-outline-success mt-2">Add Country</button>
  <button type="submit" class="btn btn-primary mt-2">Save</button>
</form>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="copyToast" class="toast text-bg-success" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<script>
const existing = <?= json_encode($countries) ?>;
const container = document.getElementById('countriesContainer');
const clientName = <?= json_encode($client['name']) ?>;

function addCountry(name='', lines=''){
  const div = document.createElement('div');
  div.className = 'card mb-3';
  const rows = lines
    ? lines.split(/\n/).filter(l=>l.trim()).map(line=>{
        const [d,n]=line.split(/\t|,/);
        return `<tr><td contenteditable>${d?d.trim():''}</td><td contenteditable>${n?n.trim():''}</td></tr>`;
      }).join('')
    : '<tr><td contenteditable></td><td contenteditable></td></tr>';
  div.innerHTML = `
    <div class="card-header">
      <div class="input-group">
        <input type="text" name="country[]" class="form-control country-name" placeholder="Country" value="${name}">
        <button type="button" class="btn btn-outline-secondary" onclick="copyPrompt(this)">Copy Prompt</button>
        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.card').remove()">-</button>
      </div>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>Occasion</th></tr></thead>
        <tbody contenteditable="true">${rows}</tbody>
      </table>
      <input type="hidden" name="data[]">
      <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addRow(this)">Add Row</button>
    </div>`;
  container.appendChild(div);
  const tbody = div.querySelector('tbody');
  tbody.addEventListener('paste', handlePaste);
}

function addRow(btn){
  const tbody = btn.closest('.card-body').querySelector('tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = '<td contenteditable></td><td contenteditable></td>';
  tbody.appendChild(tr);
}

function handlePaste(e){
  e.preventDefault();
  const text = e.clipboardData.getData('text/plain');
  const lines = text.split(/\r?\n/).filter(l=>l.trim());
  const tbody = e.target.closest('tbody');
  const empty = tbody.children.length===1 && Array.from(tbody.children[0].children).every(td=>!td.textContent.trim());
  if(empty) tbody.innerHTML='';
  lines.forEach(line=>{
    const [d,n] = line.split(/\t|,/);
    const tr = document.createElement('tr');
    tr.innerHTML = `<td contenteditable>${d?d.trim():''}</td><td contenteditable>${n?n.trim():''}</td>`;
    tbody.appendChild(tr);
  });
}

existing.forEach(c=>addCountry(c.name, c.lines));
if(!existing.length) addCountry();
document.getElementById('addCountry').addEventListener('click',()=>addCountry());

document.getElementById('occForm').addEventListener('submit',()=>{
  container.querySelectorAll('.card').forEach(card=>{
    const rows=[];
    card.querySelectorAll('tbody tr').forEach(tr=>{
      const tds=tr.querySelectorAll('td');
      const d=tds[0].textContent.trim();
      const n=tds[1].textContent.trim();
      if(d && n) rows.push(d+'\t'+n);
    });
    card.querySelector('input[name="data[]"]').value=rows.join('\n');
  });
});

function copyPrompt(btn){
  const country = btn.closest('.input-group').querySelector('.country-name').value.trim();
  if(!country) return;
  const year = new Date().getFullYear();
  const text = `please make a table for occassions days for ${clientName} in ${country} for the ${year} and make the table start with the date and the next column in the occassion name Please use the format for the date like this ${year}-01-01\n\nplease also include relgions muslimes occassions and of course the bank holidays occassions and days related to the country for festival,you have to search online to get the correct date before give it to me,please don't add any emojies, please follow the below alwayes: date | occasion`;
  navigator.clipboard.writeText(text).then(()=>{
    showToast('Prompt copied to clipboard');
  });
}

function showToast(msg){
  const toastEl = document.getElementById('copyToast');
  toastEl.querySelector('.toast-body').textContent = msg;
  new bootstrap.Toast(toastEl).show();
}
</script>
<?php include 'footer.php'; ?>
