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
    'url'  => "calendar.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Calendar';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="content.php?<?=$base?>">Content</a></li>
  <li class="nav-item"><a class="nav-link active" href="calendar.php?<?=$base?>">Content Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="posts.php?<?=$base?>">Posts</a></li>
</ul>
<form class="row g-2" onsubmit="return false;">
  <div class="col-md-3">
    <label class="form-label">Month</label>
    <select id="month" class="form-select">
      <?php for($m=1;$m<=12;$m++): $name=date('F', mktime(0,0,0,$m,1)); echo "<option value='$m'>$name</option>"; endfor; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Countries</label>
    <select id="countries" class="form-select" multiple>
      <option value="US">United States</option>
      <option value="EG">Egypt</option>
      <option value="GB">United Kingdom</option>
      <option value="AE">United Arab Emirates</option>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Posts per Week</label>
    <input type="number" id="ppw" class="form-control" value="3" min="0">
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button type="button" id="generate" class="btn btn-primary me-2">Generate</button>
    <button type="button" id="saveCal" class="btn btn-outline-secondary">Save</button>
  </div>
</form>
<div class="row mt-4">
  <div class="col-md-3">
    <div id="postsList" class="list-group"></div>
  </div>
  <div class="col-md-9">
    <div id="calendar"></div>
  </div>
</div>
<script>
const postsList=document.getElementById('postsList');
const calendarEl=document.getElementById('calendar');
async function fetchHolidays(year,country){
  const res=await fetch(`https://date.nager.at/api/v3/PublicHolidays/${year}/${country}`);
  return res.ok?res.json():[];
}
function makeDraggable(el){
  el.addEventListener('dragstart',e=>{e.dataTransfer.setData('text/plain',el.dataset.postId);});
}
document.getElementById('generate').addEventListener('click',async()=>{
  postsList.innerHTML='';
  calendarEl.innerHTML='';
  const month=parseInt(document.getElementById('month').value);
  const year=new Date().getFullYear();
  const countries=Array.from(document.getElementById('countries').selectedOptions).map(o=>o.value);
  const ppw=parseInt(document.getElementById('ppw').value)||0;
  let holidays={};
  for(const c of countries){
    const list=await fetchHolidays(year,c);
    list.forEach(h=>{holidays[h.date]=h.localName;});
  }
  const daysInMonth=new Date(year,month,0).getDate();
  const working=[];
  for(let d=1;d<=daysInMonth;d++){
    const date=new Date(year,month-1,d);
    const dow=date.getDay();
    if(dow<=4){
      const iso=date.toISOString().split('T')[0];
      working.push({date,iso,holiday:holidays[iso]});
    }
  }
  const weeks=Math.ceil(working.length/5);
  const total=ppw*weeks;
  for(let i=1;i<=total;i++){
    const div=document.createElement('div');
    div.className='list-group-item';
    div.textContent='Post '+i;
    div.draggable=true;div.dataset.postId=i;
    makeDraggable(div);
    postsList.appendChild(div);
  }
  const table=document.createElement('table');
  table.className='table table-bordered text-center';
  const head=document.createElement('thead');
  head.innerHTML='<tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th></tr>';
  table.appendChild(head);
  const body=document.createElement('tbody');
  table.appendChild(body);
  let row;
  working.forEach((w,i)=>{
    if(i%5===0){row=document.createElement('tr');body.appendChild(row);} 
    const td=document.createElement('td');
    td.dataset.date=w.iso;
    td.style.minHeight='80px';
    td.addEventListener('dragover',e=>e.preventDefault());
    td.addEventListener('drop',e=>{e.preventDefault();const id=e.dataTransfer.getData('text/plain');const post=postsList.querySelector(`[data-post-id="${id}"]`);if(post){td.textContent=post.textContent;}});
    const lbl=document.createElement('div');lbl.className='small text-muted';lbl.textContent=w.date.getDate();td.appendChild(lbl);
    if(w.holiday){const occ=document.createElement('div');occ.className='text-danger small';occ.textContent=w.holiday;td.appendChild(occ);} 
    row.appendChild(td);
  });
  calendarEl.appendChild(table);
});

document.getElementById('saveCal').addEventListener('click',()=>{
  const data=[];
  calendarEl.querySelectorAll('td').forEach(td=>{data.push({date:td.dataset.date,post:td.textContent.trim()});});
  fetch('save_calendar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(r=>r.json()).then(()=>alert('Saved (demo)'));
});
</script>
<?php include 'footer.php'; ?>
