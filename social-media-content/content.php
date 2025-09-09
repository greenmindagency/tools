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
    <div class="d-flex mb-3">
      <select id="month" class="form-select me-2">
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
      <button type="button" class="btn btn-outline-secondary" id="gridBtn" data-bs-toggle="tooltip" title="Show media grid"><i class="bi bi-grid-3x3-gap"></i></button>
    </div>
    <div id="postList" class="list-group small"></div>
  </div>
  <div class="col-md-5">
    <div class="d-flex justify-content-end mb-2">
      <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="genBtn" data-bs-toggle="tooltip" title="Generate content">&#9889;</button>
      <button type="button" class="btn btn-sm btn-outline-primary" id="promptBtn" data-bs-toggle="tooltip" title="Generate with prompt">&#x2728;</button>
    </div>
    <div class="mb-2">
      <div><strong>Date:</strong> <span id="postDate"></span></div>
      <div><strong>Title:</strong> <span id="postTitle"></span></div>
    </div>
    <div id="creativeSection" class="mb-2">
      <div class="d-flex align-items-center">
        <strong class="me-2">Recommended Creatives:</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="creativeRefresh" data-bs-toggle="tooltip" title="Refresh keyword ideas"><i class="bi bi-arrow-repeat"></i></button>
      </div>
      <div id="creativeList" class="mt-1"></div>
    </div>
    <textarea id="contentText" class="form-control" rows="12"></textarea>
    <button type="button" class="btn btn-success mt-2" id="saveBtn">Save</button>
    <div class="mt-3">
      <h6>Comments</h6>
      <div class="comments-wrapper">
        <div id="commentList" class="mb-2"></div>
        <div class="mb-2 position-relative">
          <textarea id="commentText" class="form-control form-control-sm" style="padding-right:5rem;" placeholder="Comment now"></textarea>
          <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 m-1" id="addCommentBtn">Comment</button>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="input-group mb-3">
      <input type="text" class="form-control" id="mediaUrl" placeholder="Enter media URL">
      <select class="form-select" id="mediaType">
        <option value="image">Image</option>
        <option value="video">Video</option>
      </select>
      <select class="form-select" id="mediaSize"></select>
      <button class="btn btn-outline-secondary" type="button" id="importMediaBtn" data-bs-toggle="tooltip" title="Import media"><i class="bi bi-upload"></i></button>
    </div>
    <div id="imageSection" style="display:none;" class="mb-3">
      <div id="imgContainer" class="mb-2"></div>
      <ul id="imgList" class="list-group"></ul>
    </div>
    <div id="videoSection" style="display:none;" class="mb-3">
      <div id="vidContainer" class="mb-2"></div>
      <ul id="vidList" class="list-group"></ul>
    </div>
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
<div class="modal fade" id="gridModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Media Grid</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row row-cols-3 g-2" id="gridContainer"></div>
      </div>
    </div>
  </div>
</div>
<script>
const clientId = <?=$client_id?>;
const sourceText = <?= json_encode($sourceText) ?>;
const currentUser = <?= json_encode($_SESSION['username'] ?? '') ?>;
const isAdmin = <?= json_encode($isAdmin) ?>;
let currentDate = null;
let currentEntries = [];
let imgLinks = [];
let vidLinks = [];
let comments = [];
const sizeOptions = ['1080x1350','1080x1920','1080x1080'];
let imgSize = '1080x1350';
let vidSize = '1080x1920';
const imgSizes = sizeOptions;
const vidSizes = sizeOptions;
let promptModal;
function loadCreatives(){
  const list=document.getElementById('creativeList');
  list.innerHTML='';
  if(!currentDate) return;
  const entry=currentEntries.find(e=>e.post_date===currentDate);
  const title=entry?entry.title:'';
  fetch('generate_creatives.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({source:sourceText,title})
  }).then(r=>r.json()).then(js=>{
    list.innerHTML='';
    (js.keywords||[]).forEach(k=>{
      const a=document.createElement('a');
      a.href=`https://www.pinterest.com/search/pins/?q=${encodeURIComponent(k)}`;
      a.target='_blank';
      a.className='me-2';
      a.textContent=k;
      list.appendChild(a);
    });
  }).catch(()=>{list.innerHTML='<span class="text-danger">Failed</span>';});
}
function showGrid(){
  const container=document.getElementById('gridContainer');
  container.innerHTML='';
  currentEntries.forEach(e=>{
    let src=null;
    if(e.images){
      try{
        const arr=JSON.parse(e.images||'[]');
        if(arr.length) src=arr[0];
      }catch{}
    }
    if(!src && e.videos){
      try{
        const arr=JSON.parse(e.videos||'[]');
        if(arr.length) src=arr[0];
      }catch{}
    }
    const col=document.createElement('div');
    col.className='col';
    if(src){
      col.innerHTML=frameHtml(src,'1080x1080');
    }else{
      col.innerHTML='<div class="ratio ratio-1x1 bg-secondary border border-secondary"></div>';
    }
    container.appendChild(col);
  });
  bootstrap.Modal.getOrCreateInstance(document.getElementById('gridModal')).show();
}
function showToast(msg){
  const t=document.getElementById('contentToast');
  t.querySelector('.toast-body').textContent=msg;
  bootstrap.Toast.getOrCreateInstance(t).show();
}
function loadPosts(){
  const val=document.getElementById('month').value;
  const [year,month]=val.split('-');
  const prev=currentDate;
  const url=`load_calendar.php?client_id=${clientId}&year=${year}&month=${month}&with_title=1`;
  fetch(url).then(r=>r.json()).then(js=>{
    currentEntries=js;
    const list=document.getElementById('postList');
    list.innerHTML='';
    if(!js.length){
      list.innerHTML='<div class="list-group-item">No posts for this month</div>';
      currentDate=null;
      document.getElementById('contentText').value='';
      document.getElementById('postDate').textContent='';
      document.getElementById('postTitle').textContent='';
      imgLinks=[];
      vidLinks=[];
      updateSizeOptions();
      renderImages();
      renderVideos();
      comments=[];
      renderComments();
      document.getElementById('creativeList').innerHTML='';
      return;
    }
    js.forEach(e=>{
      const btn=document.createElement('button');
      btn.type='button';
      btn.className='list-group-item list-group-item-action';
      btn.innerHTML=`<span class="me-2 px-1 bg-secondary text-white rounded">${e.post_date}</span>${e.title}`;
      btn.addEventListener('click',()=>{
        currentDate=e.post_date;
        document.getElementById('contentText').value=e.content||'';
        document.getElementById('postDate').textContent=e.post_date;
        document.getElementById('postTitle').textContent=e.title;
        imgLinks = e.images ? JSON.parse(e.images || '[]') || [] : [];
        vidLinks = e.videos ? JSON.parse(e.videos || '[]') || [] : [];
        imgSize = e.image_size || sizeOptions[0];
        vidSize = e.video_size || sizeOptions[1];
        comments = e.comments ? JSON.parse(e.comments || '[]') || [] : [];
        updateSizeOptions();
        renderImages();
        renderVideos();
        renderComments();
        loadCreatives();
      });
      list.appendChild(btn);
    });
    const first=js.find(e=>e.post_date===prev)||js[0];
    currentDate=first.post_date;
    document.getElementById('contentText').value=first.content||'';
    document.getElementById('postDate').textContent=first.post_date;
    document.getElementById('postTitle').textContent=first.title;
    imgLinks = first.images ? JSON.parse(first.images || '[]') || [] : [];
    vidLinks = first.videos ? JSON.parse(first.videos || '[]') || [] : [];
    imgSize = first.image_size || sizeOptions[0];
    vidSize = first.video_size || sizeOptions[1];
    comments = first.comments ? JSON.parse(first.comments || '[]') || [] : [];
    updateSizeOptions();
    renderImages();
    renderVideos();
    renderComments();
    loadCreatives();
  });
}
function updateSizeOptions(){
  const type=document.getElementById('mediaType').value;
  const size=document.getElementById('mediaSize');
  size.innerHTML='';
  const sizes=type==='image'?imgSizes:vidSizes;
  const current=type==='image'?imgSize:vidSize;
  sizes.forEach(s=>{
    const opt=document.createElement('option');
    opt.value=s;
    opt.textContent=s;
    if(s===current) opt.selected=true;
    size.appendChild(opt);
  });
}
window.addEventListener('load',()=>{
  promptModal=new bootstrap.Modal(document.getElementById('promptModal'));
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));
  updateSizeOptions();
  loadPosts();
});
document.getElementById('month').addEventListener('change',loadPosts);
document.getElementById('gridBtn').addEventListener('click',showGrid);
document.getElementById('saveBtn').addEventListener('click',()=>{
  if(!currentDate) return;
  const content=document.getElementById('contentText').value;
  fetch('save_content.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({client_id:clientId,date:currentDate,content,images:imgLinks,videos:vidLinks,image_size:imgSize,video_size:vidSize})
  }).then(()=>{showToast('Saved');loadPosts();});
});
function regen(custom=''){
  if(!currentDate) return;
  const entry=currentEntries.find(e=>e.post_date===currentDate);
  const title=entry?entry.title:'';
  showToast('Generating...');
  const body={source:sourceText,title};
  if(custom){
    body.prompt=custom;
    const curr=document.getElementById('contentText').value.trim();
    if(curr) body.content=curr;
  }
  fetch('generate_content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})
    .then(r=>r.json()).then(js=>{
      const t=js.content||'';
      document.getElementById('contentText').value=t;
      showToast('Generated');
    }).catch(()=>showToast('Generation failed'));
}
document.getElementById('genBtn').addEventListener('click',()=>regen());
document.getElementById('promptBtn').addEventListener('click',()=>{promptModal.show();});
document.getElementById('promptSubmit').addEventListener('click',()=>{
  const txt=document.getElementById('promptText').value.trim();
  promptModal.hide();
  document.getElementById('promptText').value='';
  if(txt) regen(txt);
});
document.getElementById('creativeRefresh').addEventListener('click',loadCreatives);
function renderComments(){
  const list=document.getElementById('commentList');
  list.innerHTML='';
  comments.forEach((c,i)=>{
    const div=document.createElement('div');
    div.className='mb-2 comment-item';
    const text=document.createElement('span');
    text.className='comment-text';
    text.innerHTML=`<strong>${c.user}:</strong> ${c.text}`;
    div.appendChild(text);
    if(isAdmin || c.user===currentUser){
      const actions=document.createElement('span');
      actions.className='float-end ms-2';
      const edit=document.createElement('a');
      edit.href='#';
      edit.className='text-decoration-none me-2';
      edit.innerHTML='<i class="bi bi-pencil"></i>';
      edit.addEventListener('click',e=>{e.preventDefault();const t=prompt("Edit comment",c.text);if(t!==null){c.text=t;renderComments();saveComments();}});
      actions.appendChild(edit);
      const del=document.createElement('a');
      del.href='#';
      del.className='text-decoration-none text-danger';
      del.innerHTML='<i class="bi bi-trash"></i>';
      del.addEventListener('click',e=>{e.preventDefault();comments.splice(i,1);renderComments();saveComments();});
      actions.appendChild(del);
      div.appendChild(actions);
    }
    list.appendChild(div);
  });
}
document.getElementById('addCommentBtn').addEventListener('click',()=>{
  const text=document.getElementById('commentText').value.trim();
  if(currentUser && text){
    comments.push({user:currentUser,text});
    document.getElementById('commentText').value='';
    renderComments();
    saveComments();
  }
});
function saveComments(){
  if(!currentDate) return;
  fetch('save_comments.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({client_id:clientId,date:currentDate,comments})
  });
}
function frameHtml(src,size){
  const [w,h]=size.split('x').map(Number);
  const ratio=(h/w*100).toFixed(2);
  return `<div class="position-relative border border-secondary overflow-hidden" style="width:100%;padding-top:${ratio}%"><iframe src="${src}" class="position-absolute top-0 start-0 w-100 h-100" style="border:0;object-fit:cover;" allowfullscreen></iframe></div>`;
}
function renderImages(){
  const section=document.getElementById('imageSection');
  const container=document.getElementById('imgContainer');
  const list=document.getElementById('imgList');
  container.innerHTML='';
  list.innerHTML='';
  if(!imgLinks.length){section.style.display='none';return;}
  section.style.display='';
  if(imgLinks.length>1){
    const slides=imgLinks.map((src,i)=>`
      <div class="carousel-item${i===0?' active':''}">
        ${frameHtml(src,imgSize)}
      </div>`).join('');
    const indicators=imgLinks.map((_,i)=>`<button type="button" data-bs-target="#imgCarousel" data-bs-slide-to="${i}" class="${i===0?'active':''}" aria-current="${i===0?'true':'false'}" aria-label="Slide ${i+1}" style="width:auto;height:auto;text-indent:0;"><span class=\"badge bg-secondary\">${i+1}</span></button>`).join('');
    container.innerHTML=`
      <div id="imgCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators position-static mb-2">${indicators}</div>
        <div class="carousel-inner">${slides}</div>
      </div>`;
    new bootstrap.Carousel(document.getElementById('imgCarousel'));
  } else {
    container.innerHTML=frameHtml(imgLinks[0],imgSize);
  }
  imgLinks.forEach((_,i)=>{
    const li=document.createElement('li');
    li.className='list-group-item d-flex justify-content-between align-items-center';
    li.textContent=`Slide ${i+1}`;
    const btn=document.createElement('button');
    btn.className='btn btn-sm btn-outline-danger';
    btn.textContent='Remove';
    btn.addEventListener('click',()=>{imgLinks.splice(i,1);renderImages();});
    li.appendChild(btn);
    list.appendChild(li);
  });
}
function renderVideos(){
  const section=document.getElementById('videoSection');
  const container=document.getElementById('vidContainer');
  const list=document.getElementById('vidList');
  container.innerHTML='';
  list.innerHTML='';
  if(!vidLinks.length){section.style.display='none';return;}
  section.style.display='';
  if(vidLinks.length>1){
    const slides=vidLinks.map((src,i)=>`
      <div class="carousel-item${i===0?' active':''}">
        ${frameHtml(src,vidSize)}
      </div>`).join('');
    const indicators=vidLinks.map((_,i)=>`<button type="button" data-bs-target="#vidCarousel" data-bs-slide-to="${i}" class="${i===0?'active':''}" aria-current="${i===0?'true':'false'}" aria-label="Slide ${i+1}" style="width:auto;height:auto;text-indent:0;"><span class=\"badge bg-secondary\">${i+1}</span></button>`).join('');
    container.innerHTML=`
      <div id="vidCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators position-static mb-2">${indicators}</div>
        <div class="carousel-inner">${slides}</div>
      </div>`;
    new bootstrap.Carousel(document.getElementById('vidCarousel'));
  } else {
    container.innerHTML=frameHtml(vidLinks[0],vidSize);
  }
  vidLinks.forEach((_,i)=>{
    const li=document.createElement('li');
    li.className='list-group-item d-flex justify-content-between align-items-center';
    li.textContent=`Slide ${i+1}`;
    const btn=document.createElement('button');
    btn.className='btn btn-sm btn-outline-danger';
    btn.textContent='Remove';
    btn.addEventListener('click',()=>{vidLinks.splice(i,1);renderVideos();});
    li.appendChild(btn);
    list.appendChild(li);
  });
}
document.getElementById('mediaType').addEventListener('change',()=>{updateSizeOptions();renderImages();renderVideos();});
document.getElementById('mediaSize').addEventListener('change',e=>{
  if(document.getElementById('mediaType').value==='image'){
    imgSize=e.target.value;renderImages();
  }else{
    vidSize=e.target.value;renderVideos();
  }
});
function toPreview(url){
  const m=url.match(/\/d\/([^/]+)/)||url.match(/[?&]id=([^&]+)/);
  return m?`https://drive.google.com/file/d/${m[1]}/preview`:url;
}
document.getElementById('importMediaBtn').addEventListener('click',()=>{
  const url=document.getElementById('mediaUrl').value.trim();
  const type=document.getElementById('mediaType').value;
  if(url){(type==='image'?imgLinks:vidLinks).push(toPreview(url));}
  document.getElementById('mediaUrl').value='';
  if(type==='image') renderImages(); else renderVideos();
});
</script>
<?php include 'footer.php'; ?>
