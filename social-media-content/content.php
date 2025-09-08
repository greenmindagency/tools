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
$stmt = $pdo->prepare('SELECT source FROM client_sources WHERE client_id = ?');
$stmt->execute([$client_id]);
$sourceText = $stmt->fetchColumn() ?: '';
$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $client['name']), '-'));
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "content.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Content';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="source.php?<?=$base?>">Source</a></li>
  <li class="nav-item"><a class="nav-link" href="occasions.php?<?=$base?>">Occasions</a></li>
  <li class="nav-item"><a class="nav-link" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link active" href="content.php?<?=$base?>">Content</a></li>
</ul>
<div class="row">
  <div class="col-md-3">
    <label class="form-label">Month</label>
    <select id="month" class="form-select mb-3">
      <?php
      $current = new DateTime('first day of this month');
      for ($i=-3; $i<=9; $i++) {
          $dt = (clone $current)->modify("$i month");
          $val = $dt->format('Y-m');
          $label = $dt->format('F Y');
          $sel = $i===0 ? 'selected' : '';
          $style = $i===0 ? "style=\"background-color:#eee;\"" : '';
          echo "<option value='$val' $sel $style>$label</option>";
      }
      ?>
    </select>
    <div id="postList" class="list-group small"></div>
  </div>
  <div class="col-md-5">
    <div class="d-flex justify-content-end mb-2">
      <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="genBtn">&#9889;</button>
      <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="regenBtn">&#x21bb;</button>
      <button type="button" class="btn btn-sm btn-outline-success me-1" id="saveBtn">&#x1F4BE;</button>
      <button type="button" class="btn btn-sm btn-outline-primary" id="promptBtn">&#x2728;</button>
    </div>
    <div class="mb-2">
      <div><strong>Date:</strong> <span id="postDate"></span></div>
      <div><strong>Title:</strong> <span id="postTitle"></span></div>
    </div>
    <textarea id="contentText" class="form-control" rows="12"></textarea>
  </div>
  <div class="col-md-4">
    <div class="input-group mb-3">
      <input type="text" class="form-control" id="mediaUrl" placeholder="Enter media URL">
      <button class="btn btn-outline-secondary" type="button" id="importImgBtn">Import Image</button>
      <button class="btn btn-outline-secondary" type="button" id="importVidBtn">Import Video</button>
    </div>
    <label class="form-label">Image Size</label>
    <select id="imgSize" class="form-select mb-2">
      <option value="1080x1080">1080x1080</option>
      <option value="1080x1350">1080x1350</option>
      <option value="1920x1080">1920x1080</option>
      <option value="1080x1920">1080x1920</option>
    </select>
    <div id="imgContainer" class="mb-4"></div>
    <label class="form-label">Video Size</label>
    <select id="vidSize" class="form-select mb-2">
      <option value="1920x1080">1920x1080</option>
      <option value="1080x1920">1080x1920</option>
      <option value="1280x720">1280x720</option>
    </select>
    <div id="vidContainer"></div>
  </div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="contentToast" class="toast text-bg-success" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<div class="modal fade" id="promptModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enter prompt for this post</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <textarea id="promptText" class="form-control" rows="3"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="promptSubmit">Generate</button>
      </div>
    </div>
  </div>
</div>
<script>
const clientId = <?=$client_id?>;
const sourceText = <?= json_encode($sourceText) ?>;
let currentDate = null;
let currentEntries = [];
let imgLinks = [];
let vidLinks = [];
let promptModal;
function showToast(msg){
  const t=document.getElementById('contentToast');
  t.querySelector('.toast-body').textContent=msg;
  bootstrap.Toast.getOrCreateInstance(t).show();
}
function loadPosts(){
  const val=document.getElementById('month').value;
  const [year,month]=val.split('-');
  fetch(`load_calendar.php?client_id=${clientId}&year=${year}&month=${month}`).then(r=>r.json()).then(js=>{
    js=js.filter(e=>e.title && e.title.trim());
    currentEntries=js;
    const list=document.getElementById('postList');
    list.innerHTML='';
    js.forEach(e=>{
      const btn=document.createElement('button');
      btn.type='button';
      btn.className='list-group-item list-group-item-action';
      btn.innerHTML=`<span class="me-2 px-1 bg-secondary text-white rounded">${e.post_date}</span>${e.title}`;
      btn.addEventListener('click',()=>{
        currentDate=e.post_date;
        document.getElementById('contentText').value=e.title;
        document.getElementById('postDate').textContent=e.post_date;
        document.getElementById('postTitle').textContent=e.title;
      });
      list.appendChild(btn);
    });
    if(js[0]){
      currentDate=js[0].post_date;
      document.getElementById('contentText').value=js[0].title;
      document.getElementById('postDate').textContent=js[0].post_date;
      document.getElementById('postTitle').textContent=js[0].title;
    }
  });
}
window.addEventListener('load',()=>{promptModal=new bootstrap.Modal(document.getElementById('promptModal'));loadPosts();});
document.getElementById('month').addEventListener('change',loadPosts);
document.getElementById('saveBtn').addEventListener('click',()=>{
  if(!currentDate) return;
  const title=document.getElementById('contentText').value;
  fetch('save_calendar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,year:currentDate.slice(0,4),month:currentDate.slice(5,7),entries:[{date:currentDate,title}]})}).then(()=>{showToast('Saved');loadPosts();});
});
function regen(custom=''){
  if(!currentDate) return;
  const used=currentEntries.filter(e=>e.post_date!==currentDate).map(e=>e.title.trim().toLowerCase()).filter(Boolean);
  showToast('Generating...');
  const [year,month]=currentDate.split('-').slice(0,2).map(Number);
  const body={source:sourceText,count:1,month:new Date(year,month-1).toLocaleString('default',{month:'long'}),year,existing:used};
  if(custom) body.prompt=custom;
  fetch('generate_titles.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(r=>r.json()).then(js=>{
    const t=js.titles && js.titles[0]?js.titles[0]:'';
    document.getElementById('contentText').value=t;
    document.getElementById('postTitle').textContent=t;
    showToast('Generated');
  }).catch(()=>showToast('Generation failed'));
}
document.getElementById('genBtn').addEventListener('click',()=>regen());
document.getElementById('regenBtn').addEventListener('click',()=>regen());
document.getElementById('promptBtn').addEventListener('click',()=>{promptModal.show();});
document.getElementById('promptSubmit').addEventListener('click',()=>{
  const txt=document.getElementById('promptText').value.trim();
  promptModal.hide();
  document.getElementById('promptText').value='';
  if(txt) regen(txt);
});
document.getElementById('contentText').addEventListener('input',e=>{
  document.getElementById('postTitle').textContent=e.target.value;
});
function renderImages(){
  const [w,h]=document.getElementById('imgSize').value.split('x');
  const container=document.getElementById('imgContainer');
  container.innerHTML='';
  if(imgLinks.length>1){
    const carousel=document.createElement('div');
    carousel.id='imgCarousel';
    carousel.className='carousel slide';
    carousel.setAttribute('data-bs-ride','carousel');
    carousel.setAttribute('data-bs-interval','5000');
    const inner=document.createElement('div');
    inner.className='carousel-inner';
    imgLinks.forEach((src,i)=>{
      const item=document.createElement('div');
      item.className='carousel-item'+(i===0?' active':'');
      const frame=document.createElement('iframe');
      frame.src=src;
      frame.width=w;frame.height=h;
      frame.style.width=w+'px';
      frame.style.height=h+'px';
      frame.style.maxWidth='100%';
      frame.style.border='0';
      frame.allowFullscreen=true;
      item.appendChild(frame);
      inner.appendChild(item);
    });
    carousel.appendChild(inner);
    container.appendChild(carousel);
    new bootstrap.Carousel(carousel);
  } else if(imgLinks.length===1){
    const frame=document.createElement('iframe');
    frame.src=imgLinks[0];
    frame.width=w;frame.height=h;
    frame.style.width=w+'px';
    frame.style.height=h+'px';
    frame.style.maxWidth='100%';
    frame.style.border='0';
    frame.allowFullscreen=true;
    container.appendChild(frame);
  }
}
function renderVideos(){
  const [w,h]=document.getElementById('vidSize').value.split('x');
  const container=document.getElementById('vidContainer');
  container.innerHTML='';
  if(vidLinks.length>1){
    const carousel=document.createElement('div');
    carousel.id='vidCarousel';
    carousel.className='carousel slide';
    carousel.setAttribute('data-bs-ride','carousel');
    carousel.setAttribute('data-bs-interval','5000');
    const inner=document.createElement('div');
    inner.className='carousel-inner';
    vidLinks.forEach((src,i)=>{
      const item=document.createElement('div');
      item.className='carousel-item'+(i===0?' active':'');
      const frame=document.createElement('iframe');
      frame.src=src;
      frame.width=w;frame.height=h;
      frame.style.width=w+'px';
      frame.style.height=h+'px';
      frame.style.maxWidth='100%';
      frame.style.border='0';
      frame.allowFullscreen=true;
      item.appendChild(frame);
      inner.appendChild(item);
    });
    carousel.appendChild(inner);
    container.appendChild(carousel);
    new bootstrap.Carousel(carousel);
  } else if(vidLinks.length===1){
    const frame=document.createElement('iframe');
    frame.src=vidLinks[0];
    frame.width=w;frame.height=h;
    frame.style.width=w+'px';
    frame.style.height=h+'px';
    frame.style.maxWidth='100%';
    frame.style.border='0';
    frame.allowFullscreen=true;
    container.appendChild(frame);
  }
}
document.getElementById('imgSize').addEventListener('change',renderImages);
document.getElementById('vidSize').addEventListener('change',renderVideos);
document.getElementById('importImgBtn').addEventListener('click',()=>{
  const url=document.getElementById('mediaUrl').value.trim();
  if(url){imgLinks.push(url);renderImages();}
});
document.getElementById('importVidBtn').addEventListener('click',()=>{
  const url=document.getElementById('mediaUrl').value.trim();
  if(url){vidLinks.push(url);renderVideos();}
});
</script>
<?php include 'footer.php'; ?>
