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
  <li class="nav-item"><a class="nav-link" href="covers.php?<?=$base?>">Covers</a></li>
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
      <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="shareBtn" data-bs-toggle="tooltip" title="Copy share link"><i class="bi bi-share"></i></button>
      <button type="button" class="btn btn-sm btn-outline-success ms-1" id="approveBtn" data-bs-toggle="tooltip" title="Mark as approved"><i class="bi bi-check2"></i> Approve</button>
    </div>
    <div id="metaInfo" class="mb-4">
      <div class="mb-2"><strong>Date:</strong> <span id="postDate"></span></div>
      <div class="mb-2"><strong>Title:</strong> <span id="postTitle"></span></div>
      <div id="creativeSection" class="mb-2 d-flex align-items-center flex-wrap">
        <strong class="me-2">Creatives:</strong>
        <div id="creativeList" class="d-flex flex-wrap"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="refreshCreatives" data-bs-toggle="tooltip" title="Refresh creative keywords"><i class="bi bi-arrow-repeat"></i></button>
      </div>
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
      <select class="form-select" id="mediaSize"></select>
      <button class="btn btn-outline-secondary" type="button" id="importMediaBtn" data-bs-toggle="tooltip" title="Import media"><i class="bi bi-upload"></i></button>
    </div>
    <div id="mediaSection" style="display:none;" class="mb-3">
      <div id="mediaContainer" class="mb-2"></div>
      <ul id="mediaList" class="list-group"></ul>
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
        <div class="row g-2" id="gridContainer" data-masonry='{"percentPosition": true }'></div>
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
let mediaItems = [];
let comments = [];
let approved = false;
const mediaSizes = ['1080x1080','1080x1350','1080x1920'];
let mediaSizeVal = '1080x1920';
let promptModal;
function loadCreatives(force=false){
  const list=document.getElementById('creativeList');
  list.innerHTML='';
  if(!currentDate) return;
  const entry=currentEntries.find(e=>e.post_date===currentDate);
  if(entry && !force){
    let stored=[];
    if(entry.creative_keywords){
      try{stored=JSON.parse(entry.creative_keywords)||[];}catch{}
    }
    if(stored.length){
      stored.forEach(k=>{
        const a=document.createElement('a');
        a.href=`https://www.pinterest.com/search/pins/?q=${encodeURIComponent(k)}`;
        a.target='_blank';
        a.className='me-2';
        a.textContent=k;
        list.appendChild(a);
      });
      return;
    }
  }
  const title=entry?entry.title:'';
  fetch('generate_creatives.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({source:sourceText,title})
  }).then(r=>r.json()).then(js=>{
    const keys=js.keywords||[];
    keys.forEach(k=>{
      const a=document.createElement('a');
      a.href=`https://www.pinterest.com/search/pins/?q=${encodeURIComponent(k)}`;
      a.target='_blank';
      a.className='me-2';
      a.textContent=k;
      list.appendChild(a);
    });
    if(entry){
      entry.creative_keywords=JSON.stringify(keys);
      fetch('save_creatives.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({client_id:clientId,date:currentDate,keywords:keys})
      });
    }
  }).catch(()=>{list.innerHTML='<span class="text-danger">Failed</span>';});
}

document.getElementById('refreshCreatives').addEventListener('click',()=>loadCreatives(true));
function showGrid(){
  const container=document.getElementById('gridContainer');
  container.innerHTML='';
  currentEntries.forEach(e=>{
    let src=null,size=null;
    if(e.images){
      try{
        const arr=JSON.parse(e.images||'[]');
        if(arr.length){ src=arr[0]; size=e.image_size; }
      }catch{}
    }
    if(!src && e.videos){
      try{
        const arr=JSON.parse(e.videos||'[]');
        if(arr.length){ src=arr[0]; size=e.video_size; }
      }catch{}
    }
    const col=document.createElement('div');
    col.className='col-sm-6 col-lg-4 mb-2';
    if(src){
      col.innerHTML=gridFrameHtml(src,size);
    }else{
      col.innerHTML='<div class="ratio ratio-4x5"><div class="border border-secondary bg-secondary w-100 h-100"></div></div>';
    }
    container.appendChild(col);
  });
  const modalEl=document.getElementById('gridModal');
  modalEl.addEventListener('shown.bs.modal',()=>{
    new Masonry(container,{percentPosition:true});
  },{once:true});
  bootstrap.Modal.getOrCreateInstance(modalEl).show();
}
function showToast(msg){
  const t=document.getElementById('contentToast');
  t.querySelector('.toast-body').textContent=msg;
  bootstrap.Toast.getOrCreateInstance(t).show();
}
function selectPost(e){
  currentDate=e.post_date;
  document.getElementById('contentText').value=e.content||'';
  document.getElementById('postDate').textContent=e.post_date;
  document.getElementById('postTitle').textContent=e.title;
  const imgs = e.images ? JSON.parse(e.images || '[]') || [] : [];
  const vids = e.videos ? JSON.parse(e.videos || '[]') || [] : [];
  mediaItems = [
    ...imgs.map(src=>({type:'image',src})),
    ...vids.map(src=>({type:'video',src}))
  ];
  mediaSizeVal = e.image_size || e.video_size || mediaSizes[0];
  comments = e.comments ? JSON.parse(e.comments || '[]') || [] : [];
  approved = e.approved == 1;
  updateSizeOptions();
  renderMedia();
  renderComments();
  updateApproveBtn();
  loadCreatives();
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
      mediaItems=[];
      updateSizeOptions();
      renderMedia();
      comments=[];
      renderComments();
      approved=false;
      updateApproveBtn();
      document.getElementById('creativeList').innerHTML='';
      return;
    }
    js.forEach(e=>{
      const a=document.createElement('a');
      a.className='list-group-item list-group-item-action';
      a.innerHTML=`<span class="me-2 px-1 bg-secondary text-white rounded">${e.post_date}</span>${e.title}`;
      const u=new URL(window.location);
      u.searchParams.set('date',e.post_date);
      a.href=u.pathname+'?'+u.searchParams.toString();
      a.addEventListener('click',ev=>{
        ev.preventDefault();
        selectPost(e);
        history.replaceState(null,'',a.href);
      });
      list.appendChild(a);
    });
    const paramDate=new URLSearchParams(window.location.search).get('date');
    const first=js.find(e=>e.post_date===paramDate)||js.find(e=>e.post_date===prev)||js[0];
    selectPost(first);
    const u=new URL(window.location);
    u.searchParams.set('date',first.post_date);
    history.replaceState(null,'',u.pathname+'?'+u.searchParams.toString());
  });
}
function updateSizeOptions(){
  const size=document.getElementById('mediaSize');
  size.innerHTML='';
  mediaSizes.forEach(s=>{
    const opt=document.createElement('option');
    opt.value=s;
    opt.textContent=s;
    if(s===mediaSizeVal) opt.selected=true;
    size.appendChild(opt);
  });
}
window.addEventListener('load',()=>{
  promptModal=new bootstrap.Modal(document.getElementById('promptModal'));
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));
  updateSizeOptions();
  const paramDate=new URLSearchParams(window.location.search).get('date');
  if(paramDate){
    const [y,m]=paramDate.split('-');
    const sel=document.getElementById('month');
    const val=`${y}-${m}`;
    if(sel.querySelector(`option[value="${val}"]`)) sel.value=val;
  }
  loadPosts();
});
document.getElementById('month').addEventListener('change',loadPosts);
document.getElementById('gridBtn').addEventListener('click',showGrid);
document.getElementById('saveBtn').addEventListener('click',()=>{
  if(!currentDate) return;
  const content=document.getElementById('contentText').value;
  const images=mediaItems.filter(m=>m.type==='image').map(m=>m.src);
  const videos=mediaItems.filter(m=>m.type==='video').map(m=>m.src);
  fetch('save_content.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({client_id:clientId,date:currentDate,content,images,videos,image_size:mediaSizeVal,video_size:mediaSizeVal})
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
document.getElementById('shareBtn').addEventListener('click',()=>{
  if(!currentDate) return;
  fetch('share_post.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({client_id:clientId,date:currentDate})
  }).then(r=>r.json()).then(js=>{
    if(js.short_url){
      navigator.clipboard.writeText(js.short_url).catch(()=>{});
      showToast('Copied: '+js.short_url);
    }else{
      showToast('Failed to get link');
    }
  }).catch(()=>showToast('Failed to get link'));
});

function updateApproveBtn(){
  const btn=document.getElementById('approveBtn');
  const tip = bootstrap.Tooltip.getOrCreateInstance(btn);
  if(approved){
    btn.className='btn btn-sm btn-success ms-1';
    btn.innerHTML='<i class="bi bi-check2"></i> Approved';
    btn.disabled=false;
    btn.setAttribute('data-bs-original-title','Click to unapprove');
    tip.setContent({'.tooltip-inner':'Click to unapprove'});
  }else{
    btn.className='btn btn-sm btn-outline-success ms-1';
    btn.innerHTML='<i class="bi bi-check2"></i> Approve';
    btn.disabled=false;
    btn.setAttribute('data-bs-original-title','Mark as approved');
    tip.setContent({'.tooltip-inner':'Mark as approved'});
  }
}
document.getElementById('approveBtn').addEventListener('click',()=>{
  if(!currentDate) return;
  const btn=document.getElementById('approveBtn');
  const newVal = approved ? 0 : 1;
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Updating...';
  setTimeout(()=>{
    fetch('approve_post.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({client_id:clientId,date:currentDate,approved:newVal})
    }).then(()=>{
      approved=newVal;
      const entry=currentEntries.find(e=>e.post_date===currentDate);
      if(entry) entry.approved=newVal;
      updateApproveBtn();
    }).catch(()=>{updateApproveBtn();});
  },1000);
});
document.getElementById('promptSubmit').addEventListener('click',()=>{
  const txt=document.getElementById('promptText').value.trim();
  promptModal.hide();
  document.getElementById('promptText').value='';
  if(txt) regen(txt);
});
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
  const [w,h]=(size||'1080x1080').split('x').map(Number);
  const ratio=(h/w*100).toFixed(2);
  return `<div class="border border-secondary"><div class="position-relative overflow-hidden" style="width:100%;padding-top:${ratio}%"><iframe src="${src}" class="position-absolute top-0 start-0 w-100 h-100" style="border:0;" allowfullscreen></iframe></div></div>`;
}
function gridFrameHtml(src,size){
  let ratioClass = 'ratio-4x5';
  if(size==='1080x1920') ratioClass = 'ratio-9x16';
  else if(size==='1080x1080') ratioClass = 'ratio-1x1';
  return `<div class="ratio ${ratioClass}"><iframe src="${src}" allowfullscreen></iframe></div>`;
}
function renderMedia(){
  const section=document.getElementById('mediaSection');
  const container=document.getElementById('mediaContainer');
  const list=document.getElementById('mediaList');
  container.innerHTML='';
  list.innerHTML='';
  if(!mediaItems.length){section.style.display='none';return;}
  section.style.display='';
  if(mediaItems.length>1){
    const slides=mediaItems.map((m,i)=>`
      <div class="carousel-item${i===0?' active':''}">
        ${frameHtml(m.src,mediaSizeVal)}
      </div>`).join('');
    const indicators=mediaItems.map((_,i)=>`<button type="button" data-bs-target="#mediaCarousel" data-bs-slide-to="${i}" class="${i===0?'active':''}" aria-current="${i===0?'true':'false'}" aria-label="Slide ${i+1}" style="width:auto;height:auto;text-indent:0;"><span class=\"badge bg-secondary\">${i+1}</span></button>`).join('');
    container.innerHTML=`
      <div id="mediaCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators position-static mb-2">${indicators}</div>
        <div class="carousel-inner">${slides}</div>
      </div>`;
    new bootstrap.Carousel(document.getElementById('mediaCarousel'));
  } else {
    container.innerHTML=frameHtml(mediaItems[0].src,mediaSizeVal);
  }
  mediaItems.forEach((m,i)=>{
    const li=document.createElement('li');
    li.className='list-group-item d-flex justify-content-between align-items-center';
    li.textContent=`Slide ${i+1}`;
    const actions=document.createElement('div');
    actions.className='d-flex gap-1';
    const dl=document.createElement('a');
    dl.className='btn btn-sm btn-outline-secondary';
    dl.innerHTML='<i class="bi bi-download"></i>';
    const viewSrc=m.src.replace('/preview','/view');
    dl.href=viewSrc;
    dl.target='_blank';
    dl.rel='noopener';
    actions.appendChild(dl);
    const btn=document.createElement('button');
    btn.className='btn btn-sm btn-outline-danger';
    btn.innerHTML='<i class="bi bi-x"></i>';
    btn.addEventListener('click',()=>{mediaItems.splice(i,1);renderMedia();});
    actions.appendChild(btn);
    li.appendChild(actions);
    list.appendChild(li);
  });
}
document.getElementById('mediaSize').addEventListener('change',e=>{mediaSizeVal=e.target.value;renderMedia();});
function toPreview(url){
  const m=url.match(/\/d\/([^/]+)/)||url.match(/[?&]id=([^&]+)/);
  return m?`https://drive.google.com/file/d/${m[1]}/preview`:url;
}
document.getElementById('importMediaBtn').addEventListener('click',()=>{
  const url=document.getElementById('mediaUrl').value.trim();
  const size=document.getElementById('mediaSize').value;
  if(!url) return;
  mediaSizeVal=size;
  const src=toPreview(url);
  const isVideo=/\.(mp4|mov|avi|webm|mkv)$/i.test(url) || size==='1080x1920';
  mediaItems.push({src,type:isVideo?'video':'image'});
  document.getElementById('mediaUrl').value='';
  renderMedia();
});
</script>
<?php include 'footer.php'; ?>