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

$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $client['name']), '-'));
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "content.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Content Plan';

$clusterStmt = $pdo->prepare("SELECT DISTINCT cluster_name FROM keywords WHERE client_id = ? AND cluster_name <> '' ORDER BY cluster_name");
$clusterStmt->execute([$client_id]);
$clusters = $clusterStmt->fetchAll(PDO::FETCH_COLUMN);

include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="dashboard.php?<?=$base?>">Keywords</a></li>
  <li class="nav-item"><a class="nav-link" href="clusters.php?<?=$base?>">Clusters</a></li>
  <li class="nav-item"><a class="nav-link active" href="content.php?<?=$base?>">Content</a></li>
  <li class="nav-item"><a class="nav-link" href="positions.php?<?=$base?>">Keyword Position</a></li>
</ul>
<form class="row g-2 align-items-center mb-3" onsubmit="return false;">
  <div class="col-md-3">
    <label class="form-label">Month</label>
    <select id="month" class="form-select">
      <?php
        $current = new DateTime('first day of this month');
        $selectedMonth = (isset($_GET['year'], $_GET['month'])) ? sprintf('%04d-%02d', $_GET['year'], $_GET['month']) : $current->format('Y-m');
        for ($i=-5; $i<=6; $i++) {
            $dt = (clone $current)->modify("$i month");
            $val = $dt->format('Y-m');
            $label = $dt->format('F Y');
            $sel = ($val === $selectedMonth) ? 'selected' : '';
            $style = $sel ? "style=\"background-color:#eee;\"" : '';
            echo "<option value='$val' $sel $style>$label</option>";
        }
      ?>
    </select>
  </div>
  <div class="col-md-9 d-flex justify-content-end align-items-end">
    <button type="button" id="saveCal" class="btn btn-sm btn-success me-2">Save</button>
    <button type="button" id="shareCal" class="btn btn-sm btn-outline-secondary" title="Share calendar"><i class="bi bi-share"></i></button>
  </div>
</form>
<div id="calendar" class="mt-4"></div>
<div class="modal fade" id="contentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Content Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" id="contentTitle" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Cluster</label>
          <input type="text" id="contentCluster" class="form-control" list="clusterList">
          <datalist id="clusterList">
            <?php foreach ($clusters as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="mb-3">
          <label class="form-label">Doc Link</label>
          <input type="url" id="contentLink" class="form-control" placeholder="https://docs.google.com/...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="modalSave">Save</button>
      </div>
    </div>
  </div>
</div>
<script>
const clientId = <?=$client_id?>;
let entries = [];
let editDate = null;
let contentModal;
function loadSaved(){
  const [year,month]=document.getElementById('month').value.split('-').map(Number);
  fetch(`load_content.php?client_id=${clientId}&year=${year}&month=${month}`)
    .then(r=>r.json())
    .then(js=>{entries=js;render(entries,year,month);});
}
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
  const days=new Date(year,month,0).getDate();
  for(let d=1; d<=days; d++){
    const dateStr=`${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const td=document.createElement('td');
    td.className='align-top p-2';
    td.dataset.date=dateStr;
    td.addEventListener('dragover',ev=>ev.preventDefault());
    td.addEventListener('drop',ev=>{
      const from=ev.dataTransfer.getData('text/plain');
      if(from && from!==dateStr){
        fetch('move_content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,from,to:dateStr})})
          .then(()=>loadSaved());
      }
    });
    const lbl=document.createElement('div');
    lbl.className='fw-bold mb-1';
    lbl.textContent=d;
    td.appendChild(lbl);
    const entry=entries.find(e=>e.post_date===dateStr);
    if(entry){
      const title=document.createElement('div');
      title.className='title d-block p-1 border rounded bg-light text-start';
      title.textContent=entry.title||'';
      title.draggable=true;
      title.addEventListener('dragstart',ev=>{ev.dataTransfer.setData('text/plain', entry.post_date);});
      title.addEventListener('click',()=>{
        editDate=entry.post_date;
        document.getElementById('contentTitle').value=entry.title||'';
        document.getElementById('contentCluster').value=entry.cluster||'';
        document.getElementById('contentLink').value=entry.doc_link||'';
        contentModal.show();
      });
      td.appendChild(title);
    }else{
      td.addEventListener('click',()=>{
        editDate=dateStr;
        document.getElementById('contentTitle').value='';
        document.getElementById('contentCluster').value='';
        document.getElementById('contentLink').value='';
        contentModal.show();
      });
    }
    row.appendChild(td);
    if((firstDow+d)%7===0 || d===days){body.appendChild(row);row=document.createElement('tr');}
  }
  cal.appendChild(table);
}

document.getElementById('month').addEventListener('change',loadSaved);
document.getElementById('saveCal').addEventListener('click',()=>{
  const [year,month]=document.getElementById('month').value.split('-').map(Number);
  fetch('save_content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,year,month,entries})})
    .then(()=>alert('Saved'));
});
document.getElementById('shareCal').addEventListener('click',()=>{
  const [year,month]=document.getElementById('month').value.split('-').map(Number);
  fetch('share_content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,year,month})})
    .then(r=>r.json()).then(js=>{if(js.short_url) navigator.clipboard.writeText(js.short_url);});
});
document.getElementById('modalSave').addEventListener('click',()=>{
  const title=document.getElementById('contentTitle').value.trim();
  const cluster=document.getElementById('contentCluster').value.trim();
  const link=document.getElementById('contentLink').value.trim();
  const existing=entries.find(e=>e.post_date===editDate);
  if(existing){
    existing.title=title;
    existing.cluster=cluster;
    existing.doc_link=link;
  }else{
    entries.push({post_date:editDate,title,cluster,doc_link:link});
  }
  contentModal.hide();
  const [year,month]=document.getElementById('month').value.split('-').map(Number);
  render(entries,year,month);
});

window.addEventListener('load',()=>{contentModal=new bootstrap.Modal(document.getElementById('contentModal'));loadSaved();});
</script>
<?php include 'footer.php'; ?>
