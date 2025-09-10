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
      <button type="button" class="btn btn-success w-100" id="saveCoverBtn">Save</button>
    </div>
  </div>
  <div class="col-md-8">
    <h5 id="previewTitle"></h5>
    <div id="previewContainer"></div>
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
    const viewSrc=c.src.replace('/preview','/view');
    dl.href=viewSrc;
    dl.target='_blank';
    dl.rel='noopener';
    dl.addEventListener('click',e=>e.stopPropagation());
    actions.appendChild(dl);
    const btn=document.createElement('button');
    btn.className='btn btn-sm btn-outline-danger';
    btn.innerHTML='<i class="bi bi-x"></i>';
    btn.addEventListener('click',e=>{
      e.stopPropagation();
      coverLinks.splice(i,1);
      if(activeIndex>=coverLinks.length) activeIndex=coverLinks.length-1;
      renderCovers();
      if(coverLinks.length) showPreview(activeIndex);
    });
    actions.appendChild(btn);
    li.appendChild(actions);
    list.appendChild(li);
  });
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
    }
    document.getElementById('coverUrl').value='';
    renderCovers();
  });
  document.getElementById('saveCoverBtn').addEventListener('click',()=>{
    fetch('save_covers.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({client_id:clientId,covers:coverLinks})
    }).then(()=>alert('Saved'));
  });
});
</script>
<?php include 'footer.php'; ?>
