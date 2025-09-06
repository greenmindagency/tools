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
    'url'  => "content.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Content';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" href="content.php?<?=$base?>">Content</a></li>
  <li class="nav-item"><a class="nav-link" href="calendar.php?<?=$base?>">Content Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="posts.php?<?=$base?>">Posts</a></li>
</ul>
<div class="row">
  <div class="col-md-4">
    <ul id="contentList" class="list-group"></ul>
  </div>
  <div class="col-md-8">
    <form id="contentForm">
      <div class="mb-3">
        <label class="form-label">Post Content</label>
        <textarea class="form-control" id="contentText" rows="10"></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Google Drive Media Link</label>
        <input type="url" class="form-control" id="mediaLink" placeholder="https://drive.google.com/...">
      </div>
      <button type="button" class="btn btn-primary" id="saveContent">Save</button>
    </form>
    <div class="mt-4">
      <h6>Comments</h6>
      <div id="comments" class="border rounded p-2" style="min-height:80px;"></div>
      <div class="input-group mt-2">
        <input type="text" id="commentText" class="form-control" placeholder="Add a comment...">
        <button class="btn btn-outline-secondary" type="button" id="addComment">Add</button>
      </div>
    </div>
  </div>
</div>
<script>
const clientId = <?=$client_id?>;
let data = JSON.parse(localStorage.getItem('smc_content_'+clientId) || '{}');
let currentKey = null;
const listEl = document.getElementById('contentList');
const textEl = document.getElementById('contentText');
const mediaEl = document.getElementById('mediaLink');
const commentsEl = document.getElementById('comments');
function renderList(){
  listEl.innerHTML='';
  Object.keys(data).forEach(key=>{
    const li=document.createElement('li');
    li.className='list-group-item';
    li.textContent=key;
    li.addEventListener('click',()=>load(key));
    listEl.appendChild(li);
  });
}
function load(key){
  currentKey = key;
  const obj=data[key]||{text:'',media:'',comments:[]};
  textEl.value=obj.text;
  mediaEl.value=obj.media;
  renderComments(obj.comments);
}
function renderComments(arr){
  commentsEl.innerHTML='';
  arr.forEach(c=>{
    const div=document.createElement('div');
    div.textContent=c;
    commentsEl.appendChild(div);
  });
}
document.getElementById('saveContent').addEventListener('click',()=>{
  if(!currentKey){
    const name=prompt('Enter post title');
    if(!name) return;
    currentKey=name;
  }
  const comments = Array.from(commentsEl.children).map(d=>d.textContent);
  data[currentKey]={text:textEl.value, media:mediaEl.value, comments};
  localStorage.setItem('smc_content_'+clientId, JSON.stringify(data));
  renderList();
});
document.getElementById('addComment').addEventListener('click',()=>{
  const txt=document.getElementById('commentText').value.trim();
  if(!txt) return;
  const div=document.createElement('div');
  div.textContent=txt;
  commentsEl.appendChild(div);
  document.getElementById('commentText').value='';
});
renderList();
const firstKey=Object.keys(data)[0];
if(firstKey) load(firstKey);
</script>
<?php include 'footer.php'; ?>
