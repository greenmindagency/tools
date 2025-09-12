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

<div class="row">
  <div class="col-md-5">
    <label class="form-label">Month</label>
    <select id="month" class="form-select mb-3">
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
    <div id="contentList" class="list-group small mb-2"></div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="addContent">Add Content</button>
  </div>
  <div class="col-md-7">
    <div class="d-flex justify-content-end mb-2">
      <button type="button" class="btn btn-sm btn-success me-2" id="saveBtn">Save</button>
      <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="shareBtn" title="Share month"><i class="bi bi-share"></i></button>
      <button type="button" class="btn btn-sm btn-outline-danger" id="deleteBtn" title="Delete entry"><i class="bi bi-trash"></i></button>
    </div>
    <table class="table">
      <tbody>
        <tr><th style="width:150px">Date</th><td><input type="date" id="contentDate" class="form-control"></td></tr>
        <tr><th>Title</th><td><input type="text" id="contentTitle" class="form-control"></td></tr>
        <tr><th>Keywords</th><td><span id="keywordsText"></span></td></tr>
        <tr><th>Cluster</th><td><input type="text" id="contentCluster" class="form-control" list="clusterList"></td></tr>
        <tr><th>Link</th><td><a href="#" target="_blank" id="docLink"></a><button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="updateDoc">Update Doc</button></td></tr>
        <tr><th>Status</th><td>
          <select id="status" class="form-select form-select-sm">
            <option>Work in Progress</option>
            <option>Under Review</option>
            <option>Approved - Unpublished</option>
            <option>Published</option>
            <option>Request Edit</option>
          </select>
        </td></tr>
      </tbody>
    </table>
  </div>
</div>
<datalist id="clusterList">
<?php foreach ($clusters as $c): ?>
    <option value="<?= htmlspecialchars($c) ?>"></option>
  <?php endforeach; ?>
</datalist>
  <style>
  .status-badge{min-width:120px;font-size:0.75rem;color:#000;}
  .status-wip{background-color:#fff3cd !important;}
  .status-review{background-color:#fff3cd !important;}
  .status-approved{background-color:#d1e7dd !important;}
  .status-published{background-color:#d1e7dd !important;}
  .status-edit{background-color:#f8d7da !important;}
  </style>
<script>
const clientId = <?=$client_id?>;
let entries = [];
let current = null;
function ensureCurrent(){
  if(!current){
    const [y,m]=document.getElementById('month').value.split('-');
    current={post_date:'',title:'',cluster:'',doc_link:'',status:'Work in Progress'};
    entries.push(current);
  }
}
function statusClass(stat){
  switch(stat){
    case 'Work in Progress': return 'status-wip';
    case 'Under Review': return 'status-review';
    case 'Approved - Unpublished': return 'status-approved';
    case 'Published': return 'status-published';
    case 'Request Edit': return 'status-edit';
    default: return '';
  }
}
function applyStatusColor(sel){
  sel.className='form-select form-select-sm '+statusClass(sel.value);
}

function loadSaved(){
  const [year,month] = document.getElementById('month').value.split('-').map(Number);
  fetch(`load_content.php?client_id=${clientId}&year=${year}&month=${month}`)
    .then(r=>r.ok?r.json():Promise.reject())
    .then(js=>{entries=js;renderList();if(entries.length){selectEntry(entries[0]);}else{clearForm();}})
    .catch(()=>{entries=[];renderList();clearForm();showToast('Load failed');});
}

function renderList(){
  const list=document.getElementById('contentList');
  list.innerHTML='';
  entries.sort((a,b)=>a.post_date.localeCompare(b.post_date));
  entries.forEach(e=>{
    const a=document.createElement('a');
    a.href='#';
    a.className='list-group-item list-group-item-action d-flex align-items-center';
    const badge=document.createElement('span');
    badge.className='badge bg-secondary me-2';
    badge.textContent=e.post_date;
    const title=document.createElement('span');
    title.className='flex-grow-1';
    title.textContent=e.title||'';
    const status=document.createElement('span');
    status.className='badge ms-2 status-badge '+statusClass(e.status);
    status.textContent=e.status||'';
    a.appendChild(badge);
    a.appendChild(title);
    a.appendChild(status);
    a.addEventListener('click',()=>selectEntry(e));
    list.appendChild(a);
  });
}

function selectEntry(entry){
  current=entry;
  document.getElementById('contentDate').value=entry.post_date||'';
  document.getElementById('contentTitle').value=entry.title||'';
  document.getElementById('contentCluster').value=entry.cluster||'';
  document.getElementById('docLink').href=entry.doc_link||'#';
  document.getElementById('docLink').textContent=entry.doc_link? 'Doc Link' : '';
  const sel=document.getElementById('status');
  sel.value=entry.status||'Work in Progress';
  applyStatusColor(sel);
  loadKeywords(entry.cluster);
}

function clearForm(){
  current=null;
  document.getElementById('contentDate').value='';
  document.getElementById('contentTitle').value='';
  document.getElementById('contentCluster').value='';
  document.getElementById('docLink').href='#';
  document.getElementById('docLink').textContent='';
  const sel=document.getElementById('status');
  sel.value='Work in Progress';
  applyStatusColor(sel);
  document.getElementById('keywordsText').textContent='';
}

function loadKeywords(cluster){
  const span=document.getElementById('keywordsText');
  span.textContent='';
  if(!cluster) return;
  fetch(`get_cluster_keywords.php?client_id=${clientId}&cluster=${encodeURIComponent(cluster)}`)
    .then(r=>r.json())
    .then(js=>{span.textContent=js.join(' | ');});
}

function showToast(msg){
  const toast=document.createElement('div');
  toast.className='toast align-items-center text-bg-primary border-0 position-fixed bottom-0 end-0 m-3';
  toast.innerHTML=`<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  document.body.appendChild(toast);
  new bootstrap.Toast(toast,{delay:2000}).show();
  toast.addEventListener('hidden.bs.toast',()=>toast.remove());
}

document.getElementById('month').addEventListener('change',loadSaved);
document.getElementById('addContent').addEventListener('click',()=>{
  const [y,m]=document.getElementById('month').value.split('-');
  const newEntry={post_date:`${y}-${m}-01`,title:'',cluster:'',doc_link:'',status:'Work in Progress'};
  entries.push(newEntry);
  renderList();
  selectEntry(newEntry);
});
document.getElementById('contentDate').addEventListener('change',()=>{
  ensureCurrent();
  current.post_date=document.getElementById('contentDate').value;
  renderList();
});
document.getElementById('contentTitle').addEventListener('input',()=>{
  ensureCurrent();
  current.title=document.getElementById('contentTitle').value.trim();
  renderList();
});
document.getElementById('contentCluster').addEventListener('change',()=>{
  ensureCurrent();
  current.cluster=document.getElementById('contentCluster').value.trim();
  loadKeywords(current.cluster);
  renderList();
});
document.getElementById('status').addEventListener('change',e=>{
  ensureCurrent();
  current.status=e.target.value;
  applyStatusColor(e.target);
  renderList();
});
document.getElementById('updateDoc').addEventListener('click',()=>{
  ensureCurrent();
  const link=prompt('Enter Google Doc link', current.doc_link||'');
  if(link!==null){
    current.doc_link=link.trim();
    document.getElementById('docLink').href=current.doc_link||'#';
    document.getElementById('docLink').textContent=current.doc_link?'Doc Link':'';
    renderList();
  }
});
document.getElementById('saveBtn').addEventListener('click',()=>{
  if(current){
    current.post_date=document.getElementById('contentDate').value;
    current.title=document.getElementById('contentTitle').value.trim();
    current.cluster=document.getElementById('contentCluster').value.trim();
    current.status=document.getElementById('status').value;
    current.doc_link=document.getElementById('docLink').href === '#' ? '' : document.getElementById('docLink').href;
  }
  const [year,month]=document.getElementById('month').value.split('-').map(Number);
  fetch('save_content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,year,month,entries})})
    .then(r=>r.ok?r.json():Promise.reject())
    .then(()=>{showToast('Content saved');loadSaved();})
    .catch(()=>showToast('Save failed'));
});
document.getElementById('deleteBtn').addEventListener('click',()=>{
  if(!current) return;
  entries=entries.filter(e=>e!==current);
  const [year,month]=document.getElementById('month').value.split('-').map(Number);
  fetch('save_content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,year,month,entries})})
    .then(r=>r.json())
    .then(()=>{loadSaved();});
});
document.getElementById('shareBtn').addEventListener('click',()=>{
  const [year,month]=document.getElementById('month').value.split('-').map(Number);
  fetch('share_content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,year,month})})
    .then(r=>r.json()).then(js=>{if(js.short_url) navigator.clipboard.writeText(js.short_url);});
});

window.addEventListener('load',loadSaved);
</script>
<?php include 'footer.php'; ?>
