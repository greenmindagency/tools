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
  <li class="nav-item"><a class="nav-link" href="occasions.php?<?=$base?>">Occasions</a></li>
  <li class="nav-item"><a class="nav-link active" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="posts.php?<?=$base?>">Posts</a></li>
</ul>
<form class="row g-2 align-items-center" onsubmit="return false;">
  <div class="col-md-3">
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
    <label class="form-label">Content Countries</label>
    <div id="countries">
      <div class="input-group">
        <input type="text" name="country[]" class="form-control" placeholder="e.g. Egypt">
        <button class="btn btn-outline-success" type="button" onclick="addCountry(this)">+</button>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <label class="form-label">Posts per Month</label>
    <input type="number" id="ppm" class="form-control" value="8" min="0">
  </div>
  <div class="col-md-3 d-flex justify-content-end">
    <button type="button" id="generate" class="btn btn-primary me-2">Generate</button>
    <button type="button" id="saveCal" class="btn btn-outline-secondary">Save</button>
  </div>
</form>
<div id="progress" class="progress mt-4 d-none"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div></div>
<div id="occWarning" class="alert alert-warning mt-4 d-none">No occasions imported for selected countries and month.</div>
<div id="calendar" class="mt-4"></div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="genToast" class="toast text-bg-success" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<script>
const clientId = <?=$client_id?>;
const sourceText = <?= json_encode($sourceText) ?>;
function addCountry(btn){
  const div=document.createElement('div');
  div.className='input-group mt-2';
  div.innerHTML='<input type="text" name="country[]" class="form-control" placeholder="e.g. Egypt"><button class="btn btn-outline-danger" type="button" onclick="this.parentNode.remove()">-</button>';
  document.getElementById('countries').appendChild(div);
}
function stripEmojis(str){
  return str.replace(/[\u{1F300}-\u{1F6FF}\u{1F900}-\u{1F9FF}\u{2600}-\u{27BF}]/gu,'');
}
let dragSrc=null;
function render(entries,year,month){
  const cal=document.getElementById('calendar');
  cal.innerHTML='';
  const table=document.createElement('table');
  table.className='table table-bordered text-center';
  const head=document.createElement('thead');
  head.innerHTML='<tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr>';
  table.appendChild(head);
  const body=document.createElement('tbody');
  table.appendChild(body);
  let row=document.createElement('tr');
  const firstDow=new Date(year,month-1,1).getDay();
  for(let i=0;i<firstDow;i++){row.appendChild(document.createElement('td'));}
  entries.forEach((e,i)=>{
    const td=document.createElement('td');
    td.dataset.date=e.date;
    td.addEventListener('dragover',ev=>ev.preventDefault());
    td.addEventListener('drop',()=>{
      if(dragSrc!==null && dragSrc!==i){
        const tmp=entries[i].title;
        entries[i].title=entries[dragSrc].title;
        entries[dragSrc].title=tmp;
        render(entries,year,month);
      }
    });
    if(e.holiday){
      td.classList.add('bg-danger-subtle');
    }else if(e.title){
      td.classList.add('bg-success-subtle');
    }
    const [yr,mn,dd]=e.date.split('-').map(Number);
    const lbl=document.createElement('div');
    lbl.className='fw-bold';
    lbl.textContent=`${dd}/${mn}/${yr}`;
    td.appendChild(lbl);
    const title=document.createElement('div');
    title.className='title';
    title.textContent=stripEmojis(e.title||'');
    title.draggable=true;
    title.addEventListener('dragstart',()=>{dragSrc=i;});
    td.appendChild(title);
    row.appendChild(td);
    if(new Date(e.date+'T00:00:00').getDay()===6){
      body.appendChild(row);
      row=document.createElement('tr');
    }
  });
  if(row.children.length){
    while(row.children.length<7){row.appendChild(document.createElement('td'));}
    body.appendChild(row);
  }
  cal.appendChild(table);
  window.currentEntries=entries;
}

async function loadSaved(){
  const monthVal=document.getElementById('month').value;
  const [year,month]=monthVal.split('-').map(Number);
  const res=await fetch(`load_calendar.php?client_id=${clientId}&year=${year}&month=${month}`);
  const saved=await res.json();
  const map={};
  saved.forEach(r=>{map[r.post_date]={title:r.title};});
  const dates=await fetch(`dates.php?year=${year}&month=${month}`).then(r=>r.json());
  const entries=dates.map(d=>({date:d.date,title:map[d.date]?stripEmojis(map[d.date].title):'',holiday:false}));
  render(entries,year,month);
}

document.getElementById('generate').addEventListener('click',async()=>{
  const monthVal=document.getElementById('month').value;
  const [year,month]=monthVal.split('-').map(Number);
  const countries=Array.from(document.querySelectorAll('input[name="country[]"]')).map(i=>i.value.trim()).filter(Boolean);
  const ppm=parseInt(document.getElementById('ppm').value)||0;
  setProgress(10);
  let occData=[];
  try{
    const res=await fetch('get_occasions.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({year,month,countries})});
    occData=await res.json();
  }catch(e){
    occData=[];
  }
  const warn=document.getElementById('occWarning');
  if(!occData.length){
    warn.classList.remove('d-none');
  }else{
    warn.classList.add('d-none');
  }
  const selected={};
  occData.forEach(o=>{selected[o.date]=o.name;});
  setProgress(30);
  const dates=await fetch(`dates.php?year=${year}&month=${month}`).then(r=>r.json());
  const entries=dates.map(d=>({date:d.date,title:selected[d.date]?`Happy ${stripEmojis(selected[d.date])}`:'',holiday:!!selected[d.date]}));
  const holidayCount=Object.keys(selected).length;
  const remaining=Math.max(ppm-holidayCount,0);
  let titles=[];
  if(remaining>0){
    const res=await fetch('generate_titles.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({source:sourceText,count:remaining,month:new Date(year,month-1).toLocaleString('default',{month:'long'}),year,countries})});
    const js=await res.json();
    titles=js.titles||[];
  }
  setProgress(70);
  const workingIdx=entries.map((e,i)=>{const dow=new Date(e.date+'T00:00:00').getDay();return (!e.title && dow<=4)?i:null;}).filter(i=>i!==null);
  const cnt=Math.min(titles.length, workingIdx.length);
  if(cnt>0){
    const step=(workingIdx.length-1)/(cnt-1||1);
    for(let i=0;i<cnt;i++){
      const idx=workingIdx[Math.round(i*step)];
      entries[idx].title=stripEmojis(titles[i]);
    }
  }
  setProgress(100);
  render(entries,year,month);
  showToast('Content generated');
});

document.getElementById('saveCal').addEventListener('click',()=>{
  const data=window.currentEntries||[];
  fetch('save_calendar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId, entries:data})})
    .then(r=>r.json())
    .then(()=>showToast('Calendar saved'))
    .catch(()=>showToast('Save failed'));
});

const progress=document.getElementById('progress');
const bar=progress.querySelector('.progress-bar');
function showToast(msg){
  const tEl=document.getElementById('genToast');
  tEl.querySelector('.toast-body').textContent=msg;
  bootstrap.Toast.getOrCreateInstance(tEl).show();
}
function setProgress(p){
  progress.classList.remove('d-none');
  bar.style.width=p+'%';
  bar.textContent=p+'%';
  if(p>=100)setTimeout(()=>progress.classList.add('d-none'),500);
}

document.getElementById('month').addEventListener('change',loadSaved);
window.addEventListener('load',loadSaved);
</script>
<?php include 'footer.php'; ?>
