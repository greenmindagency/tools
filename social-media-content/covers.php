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
    <div id="coverSection" style="display:none;" class="mb-3">
      <div id="coverContainer" class="mb-2"></div>
      <ul id="coverList" class="list-group mb-2"></ul>
      <button type="button" class="btn btn-success" id="saveCoverBtn">Save</button>
    </div>
  </div>
</div>
<script>
const clientId = <?=$client_id?>;
let coverLinks = <?= json_encode($covers) ?>;
function frameHtml(src,size){
  const [w,h]=(size||'1080x1080').split('x').map(Number);
  const ratio=(h/w*100).toFixed(2);
  return `<div class="border border-secondary"><div class="position-relative overflow-hidden" style="width:100%;padding-top:${ratio}%"><iframe src="${src}" class="position-absolute top-0 start-0 w-100 h-100" style="border:0;" allowfullscreen></iframe></div></div>`;
}
function toPreview(url){
  const m=url.match(/\/d\/([^/]+)/)||url.match(/[?&]id=([^&]+)/);
  return m?`https://drive.google.com/file/d/${m[1]}/preview`:url;
}
function renderCovers(){
  const section=document.getElementById('coverSection');
  const container=document.getElementById('coverContainer');
  const list=document.getElementById('coverList');
  container.innerHTML='';
  list.innerHTML='';
  if(!coverLinks.length){section.style.display='none';return;}
  section.style.display='';
  if(coverLinks.length>1){
    const slides=coverLinks.map((c,i)=>`
      <div class="carousel-item${i===0?' active':''}">
        ${frameHtml(c.src,c.size)}
      </div>`).join('');
    const indicators=coverLinks.map((_,i)=>`<button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="${i}" class="${i===0?'active':''}" aria-current="${i===0?'true':'false'}" aria-label="Slide ${i+1}" style="width:auto;height:auto;text-indent:0;"><span class=\"badge bg-secondary\">${i+1}</span></button>`).join('');
    container.innerHTML=`
      <div id="coverCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators position-static mb-2">${indicators}</div>
        <div class="carousel-inner">${slides}</div>
      </div>`;
    new bootstrap.Carousel(document.getElementById('coverCarousel'));
  } else {
    container.innerHTML=frameHtml(coverLinks[0].src,coverLinks[0].size);
  }
  coverLinks.forEach((c,i)=>{
    const li=document.createElement('li');
    li.className='list-group-item d-flex justify-content-between align-items-center';
    li.textContent=`${c.type.charAt(0).toUpperCase()+c.type.slice(1)} ${i+1}`;
    const actions=document.createElement('div');
    actions.className='d-flex gap-1';
    const dl=document.createElement('a');
    dl.className='btn btn-sm btn-outline-secondary';
    dl.innerHTML='<i class="bi bi-download"></i>';
    const viewSrc=c.src.replace('/preview','/view');
    dl.href=viewSrc;
    dl.target='_blank';
    dl.rel='noopener';
    actions.appendChild(dl);
    const btn=document.createElement('button');
    btn.className='btn btn-sm btn-outline-danger';
    btn.innerHTML='<i class="bi bi-x"></i>';
    btn.addEventListener('click',()=>{coverLinks.splice(i,1);renderCovers();});
    actions.appendChild(btn);
    li.appendChild(actions);
    list.appendChild(li);
  });
}
window.addEventListener('load',()=>{
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));
  renderCovers();
  document.getElementById('importCoverBtn').addEventListener('click',()=>{
    const url=document.getElementById('coverUrl').value.trim();
    const sel=document.getElementById('coverType');
    const type=sel.value;
    const size=sel.options[sel.selectedIndex].dataset.size;
    if(url){coverLinks.push({src:toPreview(url),type,size});}
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
