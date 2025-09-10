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

$stmt = $pdo->prepare('SELECT source FROM client_sources WHERE client_id = ?');
$stmt->execute([$client_id]);
$sourceText = $stmt->fetchColumn() ?: '';

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
  <li class="nav-item"><a class="nav-link" href="source.php?<?=$base?>">Source</a></li>
  <li class="nav-item"><a class="nav-link" href="occasions.php?<?=$base?>">Occasions</a></li>
  <li class="nav-item"><a class="nav-link" href="covers.php?<?=$base?>">Covers</a></li>
  <li class="nav-item"><a class="nav-link active" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="content.php?<?=$base?>">Content</a></li>
</ul>
<form class="row g-2 align-items-center" onsubmit="return false;">
  <div class="col-md-3">
    <label class="form-label">Month</label>
    <select id="month" class="form-select">
      <?php
      $current = new DateTime('first day of this month');
      $selectedMonth = (isset($_GET['year'], $_GET['month']))
        ? sprintf('%04d-%02d', $_GET['year'], $_GET['month'])
        : $current->format('Y-m');
      for ($i=-3; $i<=9; $i++) {
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
  <div class="col-md-4">
    <label class="form-label">Output Languages</label>
    <input type="text" id="langs" class="form-control" placeholder="e.g. English, Arabic">
  </div>
  <div class="col-md-2">
    <label class="form-label">Posts per Month</label>
    <input type="number" id="ppm" class="form-control" value="8" min="0">
  </div>
  <div class="col-md-3 d-flex justify-content-end">
    <button type="button" id="generate" class="btn btn-sm btn-primary me-2">Generate</button>
    <button type="button" id="saveCal" class="btn btn-sm btn-success me-2">Save</button>
    <button type="button" id="shareCal" class="btn btn-sm btn-outline-secondary" title="Share calendar"><i class="bi bi-share"></i></button>
  </div>
</form>
<div id="progress" class="progress mt-4 d-none"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div></div>
<div id="occWarning" class="alert alert-warning mt-4 d-none">No occasions imported for selected month.</div>
<div id="calendar" class="mt-4"></div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="genToast" class="toast text-bg-success" role="alert" aria-live="assertive" aria-atomic="true">
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
        <h5 class="modal-title">Enter prompt for this date</h5>
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
let promptModal;
let promptIdx = null;
window.addEventListener('load', () => {
  promptModal = new bootstrap.Modal(document.getElementById('promptModal'));
  const langVal = localStorage.getItem('sm_langs_'+clientId);
  if(langVal) document.getElementById('langs').value = langVal;
});
document.getElementById('langs').addEventListener('change', ()=>{
  localStorage.setItem('sm_langs_'+clientId, document.getElementById('langs').value.trim());
});
function stripEmojis(str){
  return str.replace(/[\u{1F300}-\u{1F6FF}\u{1F900}-\u{1F9FF}\u{2600}-\u{27BF}]/gu,'');
}
let dragSrc=null;
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
  entries.forEach((e,i)=>{
    const td=document.createElement('td');
    td.dataset.date=e.date;
    td.classList.add('align-top','p-3');
    td.addEventListener('dragover',ev=>ev.preventDefault());
    td.addEventListener('drop',()=>{
      if(dragSrc!==null && dragSrc!==i){
        const from=entries[dragSrc].date;
        const to=entries[i].date;
        dragSrc=null;
        fetch('move_post.php',{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({client_id:clientId,from,to})
        })
        .then(r=>r.json())
        .then(js=>{
          if(js.status==='ok'){
            loadSaved();
            showToast('Post moved');
          }else{
            showToast('Move failed');
          }
        })
        .catch(()=>showToast('Move failed'));
      }
    });
    const [yr,mn,dd]=e.date.split('-').map(Number);
    const lbl=document.createElement('div');
    lbl.className='fw-bold';
    lbl.textContent=`${dd}/${mn}/${yr}`;
    td.appendChild(lbl);
    const btnWrap=document.createElement('div');
    btnWrap.className='d-flex justify-content-center gap-1 my-1';
    const regen=document.createElement('button');
    regen.type='button';
    regen.className='btn btn-sm btn-outline-secondary regen-cell';
    regen.dataset.idx=i;
    regen.innerHTML='\u21bb';
    const save=document.createElement('button');
    save.type='button';
    save.className='btn btn-sm btn-outline-success save-cell';
    save.dataset.idx=i;
    save.innerHTML='\u{1F4BE}';
    const prompt=document.createElement('button');
    prompt.type='button';
    prompt.className='btn btn-sm btn-outline-primary prompt-cell';
    prompt.dataset.idx=i;
    prompt.innerHTML='\u2728';
    btnWrap.append(regen,save,prompt);
    td.appendChild(btnWrap);
    const title=document.createElement('div');
    title.className='title d-inline-block px-1';
    title.textContent=stripEmojis(e.title||'');
    title.draggable=true;
    title.contentEditable=true;
    title.addEventListener('dragstart',()=>{dragSrc=i;});
    title.addEventListener('input',()=>{
      entries[i].title=title.textContent;
      if(entries[i].holiday) return;
      title.classList.toggle('bg-success-subtle', !!entries[i].title);
    });
    if(e.holiday){
      title.classList.add('bg-danger-subtle');
    }else if(e.title){
      title.classList.add('bg-success-subtle');
    }
    td.appendChild(title);
    row.appendChild(td);
    if(new Date(e.date+'T00:00:00').getDay()===6){
      body.appendChild(row);
      row=document.createElement('tr');
    }
  });
  if(row.children.length){
    while(row.children.length<7){row.appendChild(document.createElement('td'));}
    body.appendChild(row);
  }
  cal.appendChild(table);
  window.currentEntries=entries;
  window.currentYear=year;
  window.currentMonth=month;
  document.querySelectorAll('.regen-cell').forEach(btn=>{
    btn.addEventListener('click',()=>regenCell(btn.dataset.idx));
  });
  document.querySelectorAll('.prompt-cell').forEach(btn=>{
    btn.addEventListener('click',()=>{
      promptIdx=btn.dataset.idx;
      promptModal.show();
    });
  });
  document.querySelectorAll('.save-cell').forEach(btn=>{
    btn.addEventListener('click',saveMonth);
  });
}

async function loadSaved(){
  const monthVal=document.getElementById('month').value;
  const [year,month]=monthVal.split('-').map(Number);
  const [saved,dates] = await Promise.all([
    fetch(`load_calendar.php?client_id=${clientId}&year=${year}&month=${month}`).then(r=>r.json()),
    fetch(`dates.php?year=${year}&month=${month}`).then(r=>r.json())
  ]);
  const map={};
  saved.forEach(r=>{map[r.post_date]={title:r.title};});
  const entries=dates.map(d=>({date:d.date,title:map[d.date]?stripEmojis(map[d.date].title):'',holiday:false}));
  render(entries,year,month);
}

  document.getElementById('generate').addEventListener('click',async()=>{
    const monthVal=document.getElementById('month').value;
    const [year,month]=monthVal.split('-').map(Number);
    const langs=getLangs();
  const ppm=parseInt(document.getElementById('ppm').value)||0;
  setProgress(10);
  let occData=[];
  try{
    const res=await fetch('get_occasions.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({year,month,client_id:clientId})
    });
    occData=await res.json();
  }catch(e){
    occData=[];
  }
  const warn=document.getElementById('occWarning');
  if(!occData.length){
    warn.classList.remove('d-none');
  }else{
    warn.classList.add('d-none');
  }
  const selected={};
  occData.forEach(o=>{selected[o.date]=o.name;});
  setProgress(30);
  const dates=await fetch(`dates.php?year=${year}&month=${month}`).then(r=>r.json());
  const entries=dates.map(d=>({date:d.date,title:selected[d.date]?`Happy ${stripEmojis(selected[d.date])}`:'',holiday:!!selected[d.date]}));
  const holidayCount=Object.keys(selected).length;
  const remaining=Math.max(ppm-holidayCount,0);
  let titles=[];
  if(remaining>0){
    const monthName=new Date(year,month-1).toLocaleString('default',{month:'long'});
    const perLang=Math.ceil(remaining/(langs.length||1));
    const usedLangs=langs.length?langs:[''];
    for(const lg of usedLangs){
      const body={source:sourceText,count:perLang,month:monthName,year};
      if(lg) body.languages=[lg];
      const res=await fetch('generate_titles.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
      const js=await res.json();
      if(js.titles) titles=titles.concat(js.titles);
    }
    if(langs.length>1) titles.sort(()=>Math.random()-0.5);
    titles=titles.slice(0,remaining);
  }
  setProgress(70);
  const workingIdx=entries.map((e,i)=>{const dow=new Date(e.date+'T00:00:00').getDay();return (!e.title && dow<=4)?i:null;}).filter(i=>i!==null);
  const cnt=Math.min(titles.length, workingIdx.length);
  if(cnt>0){
    const step=(workingIdx.length-1)/(cnt-1||1);
    for(let i=0;i<cnt;i++){
      const idx=workingIdx[Math.round(i*step)];
      entries[idx].title=stripEmojis(titles[i]);
    }
  }
  setProgress(100);
  render(entries,year,month);
  showToast('Content generated');
});

document.getElementById('saveCal').addEventListener('click',saveMonth);
document.getElementById('shareCal').addEventListener('click',async()=>{
  const monthVal=document.getElementById('month').value;
  const [year,month]=monthVal.split('-').map(Number);
  try{
    const res=await fetch('share_calendar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,year,month})});
    const js=await res.json();
    if(js.short_url){
      await navigator.clipboard.writeText(js.short_url);
      showToast('Share link copied');
    }else{
      showToast('Share failed');
    }
  }catch(e){
    showToast('Share failed');
  }
});

const progress=document.getElementById('progress');
const bar=progress.querySelector('.progress-bar');
function showToast(msg){
  const tEl=document.getElementById('genToast');
  tEl.querySelector('.toast-body').textContent=msg;
  bootstrap.Toast.getOrCreateInstance(tEl).show();
}
function setProgress(p){
  progress.classList.remove('d-none');
  bar.style.width=p+'%';
  bar.textContent=p+'%';
  if(p>=100)setTimeout(()=>progress.classList.add('d-none'),500);
}

document.getElementById('month').addEventListener('change',loadSaved);
window.addEventListener('load',()=>{
  const params=new URLSearchParams(location.search);
  const y=params.get('year');
  const m=params.get('month');
  if(y&&m){
    const val=`${y}-${String(m).padStart(2,'0')}`;
    const sel=document.getElementById('month');
    if([...sel.options].some(o=>o.value===val)) sel.value=val;
  }
  loadSaved();
});

document.getElementById('promptSubmit').addEventListener('click',()=>{
  const txt=document.getElementById('promptText').value.trim();
  if(txt) regenCell(promptIdx,txt);
  document.getElementById('promptText').value='';
  promptModal.hide();
});

function getLangs(){
  return document.getElementById('langs').value.split(',').map(s=>s.trim()).filter(Boolean);
}

async function regenCell(idx, custom=''){
  const monthVal=document.getElementById('month').value;
  const [year,month]=monthVal.split('-').map(Number);
  const langs=getLangs();
  const lang=langs.length?langs[Math.floor(Math.random()*langs.length)]:'';
  const used=window.currentEntries.map((e,i)=>i!==idx?e.title.trim().toLowerCase():'').filter(Boolean);
  showToast('Generating content...');
  setProgress(10);
  const body={source:sourceText,count:1,month:new Date(year,month-1).toLocaleString('default',{month:'long'}),year,existing:used};
  if(lang) body.languages=[lang];
  if(custom) body.prompt=custom;
  try{
    const res=await fetch('generate_titles.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const js=await res.json();
    const t=js.titles && js.titles[0] ? stripEmojis(js.titles[0]) : '';
    if(used.includes(t.toLowerCase())){
      showToast('Duplicate title generated');
    }else{
      window.currentEntries[idx].title=t;
      render(window.currentEntries,year,month);
      showToast('Post idea generated');
    }
  }catch(e){
    showToast('Generation failed');
  }
  setProgress(100);
}

function saveMonth(){
  const data=window.currentEntries||[];
  const year=window.currentYear;
  const month=window.currentMonth;
  fetch('save_calendar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,year,month,entries:data})})
    .then(r=>r.json())
    .then(()=>showToast('Calendar saved'))
    .catch(()=>showToast('Save failed'));
}
</script>
<?php include 'footer.php'; ?>
