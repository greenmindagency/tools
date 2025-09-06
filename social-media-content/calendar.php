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

$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) die('Client not found');

$stmt = $pdo->prepare('SELECT source FROM client_sources WHERE client_id = ?');
$stmt->execute([$client_id]);
$sourceText = $stmt->fetchColumn() ?: '';

$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $client['name']), '-'));
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "calendar.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Calendar';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="source.php?<?=$base?>">Source</a></li>
  <li class="nav-item"><a class="nav-link active" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="posts.php?<?=$base?>">Posts</a></li>
</ul>
<form class="row g-2" onsubmit="return false;">
  <div class="col-md-4">
    <label class="form-label">Month</label>
    <select id="month" class="form-select">
      <?php
      $current = new DateTime('first day of this month');
      for ($i=-3; $i<=9; $i++) {
          $dt = (clone $current)->modify("$i month");
          $val = $dt->format('Y-m');
          $label = $dt->format('F Y');
          $sel = $i===0 ? 'selected' : '';
          echo "<option value='$val' $sel>$label</option>";
      }
      ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Countries</label>
    <div id="countries">
      <div class="input-group mb-2">
        <input type="text" name="country[]" class="form-control" placeholder="e.g. Egypt">
        <button class="btn btn-outline-success" type="button" onclick="addCountry(this)">+</button>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <label class="form-label">Posts per Month</label>
    <input type="number" id="ppm" class="form-control" value="8" min="0">
  </div>
  <div class="col-md-2 d-flex align-items-end">
    <button type="button" id="generate" class="btn btn-primary me-2">Generate</button>
    <button type="button" id="saveCal" class="btn btn-outline-secondary">Save</button>
  </div>
</form>
<div id="calendar" class="mt-4"></div>
<script>
const clientId = <?=$client_id?>;
const sourceText = <?= json_encode($sourceText) ?>;
function addCountry(btn){
  const div=document.createElement('div');
  div.className='input-group mb-2';
  div.innerHTML='<input type="text" name="country[]" class="form-control" placeholder="e.g. Egypt"><button class="btn btn-outline-danger" type="button" onclick="this.parentNode.remove()">-</button>';
  document.getElementById('countries').appendChild(div);
}
async function getCode(name){
  const res=await fetch('https://restcountries.com/v3.1/name/'+encodeURIComponent(name)+'?fields=cca2');
  if(res.ok){
    const js=await res.json();
    return js[0]?.cca2||'';
  }
  return '';
}
async function fetchHolidays(year,code){
  const res=await fetch(`https://date.nager.at/api/v3/PublicHolidays/${year}/${code}`);
  return res.ok?res.json():[];
}
function render(entries,year,month){
  const cal=document.getElementById('calendar');
  cal.innerHTML='';
  const table=document.createElement('table');
  table.className='table table-bordered text-center';
  const head=document.createElement('thead');
  head.innerHTML='<tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th></tr>';
  table.appendChild(head);
  const body=document.createElement('tbody');
  table.appendChild(body);
  let row;
  entries.forEach((e,i)=>{
    if(i%5===0){row=document.createElement('tr');body.appendChild(row);}
    const td=document.createElement('td');
    td.dataset.date=e.date;
    const d=new Date(e.date);
    const lbl=document.createElement('div');
    lbl.className='fw-bold';
    lbl.textContent=`${d.getDate()}/${month}/${year}`;
    td.appendChild(lbl);
    const title=document.createElement('div');
    title.textContent=e.title||'';
    td.appendChild(title);
    row.appendChild(td);
  });
  cal.appendChild(table);
  window.currentEntries=entries;
}

document.getElementById('generate').addEventListener('click',async()=>{
  const monthVal=document.getElementById('month').value;
  const [year,month]=monthVal.split('-').map(Number);
  const countries=Array.from(document.querySelectorAll('input[name="country[]"]')).map(i=>i.value.trim()).filter(Boolean);
  const ppm=parseInt(document.getElementById('ppm').value)||0;
  const codes=[];
  for(const c of countries){const code=await getCode(c);if(code)codes.push(code);}
  const holidays={};
  for(const code of codes){
    const list=await fetchHolidays(year,code);
    list.forEach(h=>{holidays[h.date]=h.localName;});
  }
  const daysInMonth=new Date(year,month,0).getDate();
  const entries=[];
  for(let d=1;d<=daysInMonth;d++){
    const date=new Date(year,month-1,d);
    const dow=date.getDay();
    if(dow<=4){
      const iso=date.toISOString().split('T')[0];
      const title=holidays[iso]?`Happy ${holidays[iso]}`:'';
      entries.push({date:iso,title});
    }
  }
  const holidayCount=entries.filter(e=>e.title).length;
  const remaining=Math.max(ppm-holidayCount,0);
  let titles=[];
  if(remaining>0){
    const res=await fetch('generate_titles.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({source:sourceText,count:remaining,month:new Date(year,month-1).toLocaleString('default',{month:'long'}),year,countries})});
    const js=await res.json();
    titles=js.titles||[];
  }
  let idx=0;
  for(const e of entries){
    if(!e.title && idx<titles.length){e.title=titles[idx++];}
  }
  render(entries,year,month);
});

document.getElementById('saveCal').addEventListener('click',()=>{
  const data=window.currentEntries||[];
  fetch('save_calendar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(r=>r.json()).then(()=>alert('Saved (demo)'));
  localStorage.setItem('smc_calendar_'+clientId, JSON.stringify(data));
});
</script>
<?php include 'footer.php'; ?>
