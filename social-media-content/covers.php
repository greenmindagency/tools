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

$stmt = $pdo->prepare('SELECT covers FROM client_covers WHERE client_id = ?');
$stmt->execute([$client_id]);
$coverData = $stmt->fetchColumn();
$covers = $coverData ? json_decode($coverData, true) : [];

$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $client['name']), '-'));
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "covers.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Covers';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<style>
#coverList .list-group-item.active{
  background-color:#e9ecef;
  border-color:#dee2e6;
  color:inherit;
}
</style>
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
      <input type="text" class="form-control" id="coverUrl" placeholder="Enter media URL">
      <select id="coverType" class="form-select">
        <option value="facebook" data-size="1230x468">Facebook Cover</option>
        <option value="linkedin" data-size="1128x191">LinkedIn Cover</option>
        <option value="highlights" data-size="1128x191">Instagram Highlights</option>
      </select>
      <button class="btn btn-outline-secondary" type="button" id="importCoverBtn" data-bs-toggle="tooltip" title="Import cover"><i class="bi bi-upload"></i></button>
    </div>
    <div id="coverSection" style="display:none;">
      <ul id="coverList" class="list-group mb-2"></ul>
      <div class="d-flex">
        <button type="button" class="btn btn-sm btn-success me-2" id="saveCoverBtn" data-bs-toggle="tooltip" title="Save covers">Save</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="shareCoverBtn" data-bs-toggle="tooltip" title="Share covers"><i class="bi bi-share"></i></button>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <h5 id="previewTitle"></h5>
    <div id="previewContainer"></div>
  </div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="coverToast" class="toast text-bg-success" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<script>
const clientId = <?=$client_id?>;
let coverLinks = <?= json_encode($covers) ?>;
let activeIndex = 0;
function frameHtml(src,size){
  const [w,h]=(size||'1080x1080').split('x').map(Number);
  const ratio=(h/w*100).toFixed(2);
  return `<div class="border border-secondary"><div class="position-relative overflow-hidden" style="width:100%;padding-top:${ratio}%"><iframe src="${src}" class="position-absolute top-0 start-0 w-100 h-100" style="border:0;" allowfullscreen></iframe></div></div>`;
}
function toPreview(url){
  const m=url.match(/\/d\/([^/]+)/)||url.match(/[?&]id=([^&]+)/);
  return m?`https://drive.google.com/file/d/${m[1]}/preview`:url;
}
function getLabel(type){
  const opt=document.querySelector(`#coverType option[value='${type}']`);
  return opt?opt.textContent:`${type.charAt(0).toUpperCase()+type.slice(1)} Cover`;
}
function normalizeCovers(){
  const counts={};
  coverLinks=coverLinks.map(c=>{
    counts[c.type]=(counts[c.type]||0)+1;
    return {
      src:c.src,
      type:c.type,
      size:c.size,
      label:c.label||getLabel(c.type),
      option:c.option||counts[c.type]
    };
  });
}
function showPreview(i){
  if(i<0||i>=coverLinks.length)return;
  activeIndex=i;
  const c=coverLinks[i];
  document.getElementById('previewTitle').textContent=`${c.label} Option ${c.option}`;
  document.getElementById('previewContainer').innerHTML=frameHtml(c.src,c.size);
  document.querySelectorAll('#coverList .list-group-item').forEach((li,idx)=>{
    li.classList.toggle('active',idx===i);
  });
}
function renderCovers(){
  const section=document.getElementById('coverSection');
  const list=document.getElementById('coverList');
  list.innerHTML='';
  if(!coverLinks.length){
    section.style.display='none';
    document.getElementById('previewTitle').textContent='';
    document.getElementById('previewContainer').innerHTML='';
    return;
  }
  section.style.display='';
  coverLinks.forEach((c,i)=>{
    const li=document.createElement('li');
    li.className='list-group-item d-flex justify-content-between align-items-center list-group-item-action';
    li.style.cursor='pointer';
    li.addEventListener('click',()=>showPreview(i));
    li.appendChild(document.createTextNode(`${c.label} Option ${c.option}`));
    const actions=document.createElement('div');
    actions.className='d-flex gap-1';
    const dl=document.createElement('a');
    dl.className='btn btn-sm btn-outline-secondary';
    dl.innerHTML='<i class="bi bi-download"></i>';
    dl.title='Download';
    dl.setAttribute('data-bs-toggle','tooltip');
    const viewSrc=c.src.replace('/preview','/view');
    dl.href=viewSrc;
    dl.target='_blank';
    dl.rel='noopener';
    dl.addEventListener('click',e=>e.stopPropagation());
    actions.appendChild(dl);
    const btn=document.createElement('button');
    btn.className='btn btn-sm btn-outline-danger';
    btn.innerHTML='<i class="bi bi-x"></i>';
    btn.title='Remove';
    btn.setAttribute('data-bs-toggle','tooltip');
    btn.addEventListener('click',e=>{
      e.stopPropagation();
      coverLinks.splice(i,1);
      if(activeIndex>=coverLinks.length) activeIndex=coverLinks.length-1;
      renderCovers();
      if(coverLinks.length) showPreview(activeIndex);
      showToast('Cover removed');
    });
    actions.appendChild(btn);
    li.appendChild(actions);
    list.appendChild(li);
  });
  list.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));
  showPreview(activeIndex);
}
window.addEventListener('load',()=>{
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));
  normalizeCovers();
  renderCovers();
  document.getElementById('importCoverBtn').addEventListener('click',()=>{
    const url=document.getElementById('coverUrl').value.trim();
    const sel=document.getElementById('coverType');
    const type=sel.value;
    const size=sel.options[sel.selectedIndex].dataset.size;
    const label=sel.options[sel.selectedIndex].text;
    if(url){
      const option=coverLinks.filter(c=>c.type===type).length+1;
      coverLinks.push({src:toPreview(url),type,size,label,option});
      activeIndex=coverLinks.length-1;
      showToast('Cover imported');
    }
    document.getElementById('coverUrl').value='';
    renderCovers();
  });
  document.getElementById('saveCoverBtn').addEventListener('click',()=>{
    fetch('save_covers.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({client_id:clientId,covers:coverLinks})
    }).then(()=>showToast('Saved')).catch(()=>showToast('Save failed'));
  });
  document.getElementById('shareCoverBtn').addEventListener('click',async()=>{
    try{
      const res=await fetch('share_cover.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId})});
      const js=await res.json();
      if(js.short_url){
        await navigator.clipboard.writeText(js.short_url);
        showToast('Share link copied');
      }else{
        showToast('Share failed');
      }
    }catch(e){showToast('Share failed');}
  });
});
function showToast(msg){
  const tEl=document.getElementById('coverToast');
  tEl.querySelector('.toast-body').textContent=msg;
  bootstrap.Toast.getOrCreateInstance(tEl).show();
}
</script>
<?php include 'footer.php'; ?>
