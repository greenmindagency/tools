<?php
// Content creation tool with AI output and prompt tabs
$title = "Content Creation";
$presetKeywords = isset($_GET['keywords']) ? trim($_GET['keywords']) : '';
$presetFocus = isset($_GET['focus']) ? trim($_GET['focus']) : '';
$embed = isset($_GET['embed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_doc'])) {
    $title = trim($_POST['title'] ?? 'document');
    $body  = $_POST['body'] ?? '';
    $html  = "<html><head><meta charset=\"UTF-8\"></head><body>".$body."</body></html>";
    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $title) ?: 'document';
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'.doc"');
    echo $html;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    function slugify($text){
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/','-',$text);
        return trim($text,'-');
    }
    function callGemini($prompt){
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $payload = json_encode(['contents'=>[[ 'parts'=>[['text'=>$prompt]] ]]]);
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','X-goog-api-key: '.$apiKey]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error'=>'API request failed: '.$err];
        }
        curl_close($ch);
        $json = json_decode($response, true);
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/^`{3}\w*\n?|`{3}$/m','',$text);
        $start = strpos($text,'{');
        $end = strrpos($text,'}');
        if ($start !== false && $end !== false && $end >= $start) {
            $text = substr($text,$start,$end-$start+1);
        }
        $data = json_decode($text,true);
        if (!$data) return ['error'=>'Failed to parse response'];
        return $data;
    }

    if (isset($_POST['generate_content'])) {
        $prompt = $_POST['prompt'] ?? '';
        $prompt .= "\n\nReturn JSON with keys 'meta_title','meta_description','slug','sections'. 'sections' is an array of objects with keys 'title','subtitle','paragraphs' (array of strings). Each section must include both 'title' and 'subtitle'. Do not include markdown fences.";
        $res = callGemini($prompt);
        if (isset($res['slug'])) $res['slug'] = slugify($res['slug']);
        echo json_encode($res);
    } elseif (isset($_POST['generate_meta'])) {
        $field = $_POST['field'] ?? '';
        $prompt = $_POST['prompt'] ?? '';
        $current = trim($_POST['current'] ?? '');
        $userPrompt = trim($_POST['userPrompt'] ?? '');
        $prompt .= "\n\nRegenerate only the {$field}.";
        if ($current !== '') $prompt .= "\nCurrent {$field}:\n{$current}";
        if ($userPrompt !== '') $prompt .= "\nAdditional instructions:\n{$userPrompt}";
        $prompt .= "\nReturn JSON with key '{$field}'.";
        $res = callGemini($prompt);
        if ($field === 'slug' && isset($res['slug'])) $res['slug'] = slugify($res['slug']);
        echo json_encode($res);
    } elseif (isset($_POST['generate_section'])) {
        $base = $_POST['prompt'] ?? '';
        $title = $_POST['title'] ?? '';
        $subtitle = $_POST['subtitle'] ?? '';
        $content = $_POST['content'] ?? '';
        $context = $_POST['context'] ?? '';
        $userPrompt = trim($_POST['userPrompt'] ?? '');
        $prompt = $base;
        if ($context !== '') $prompt .= "\n\nExisting preceding content:\n" . strip_tags($context);
        $prompt .= "\n\nYou will rewrite only the section titled '{$title}' with subtitle '{$subtitle}'.";
        if ($content !== '') $prompt .= "\nExisting section content:\n{$content}";
        if ($userPrompt !== '') $prompt .= "\nUser instructions:\n{$userPrompt}";
        $prompt .= "\nReturn only JSON for this section with keys 'title','subtitle','paragraphs' (array of strings). Do not include any other content.";
        $res = callGemini($prompt);
        if (isset($res['sections'][0])) $res = $res['sections'][0];
        echo json_encode($res);
    } elseif (isset($_POST['generate_new_section'])) {
        $base = $_POST['prompt'] ?? '';
        $current = $_POST['current'] ?? '';
        $prompt = $base . "\n\nExisting content:\n" . strip_tags($current);
        $prompt .= "\n\nCreate one additional section that continues the flow without repeating previous sections. Return JSON with keys 'title','subtitle','paragraphs' (array of strings). Do not include markdown fences or extra keys.";
        $res = callGemini($prompt);
        echo json_encode($res);
    } elseif (isset($_POST['media_suggestions'])) {
        $html = $_POST['html'] ?? '';
        $prompt = "Analyze the following HTML content and suggest relevant media.\n{$html}\nReturn JSON with keys 'icons','images','videos'. 'icons' should list five Font Awesome icon names without the 'fa-' prefix. 'images' should list five 2-3 word stock photo keywords. 'videos' should list five 2-3 word stock footage keywords. Avoid duplicates and relate suggestions to the content. Return JSON only.";
        $res = callGemini($prompt);
        $out = [
            'icons' => $res['icons'] ?? [],
            'images' => $res['images'] ?? [],
            'videos' => $res['videos'] ?? [],
        ];
        echo json_encode($out);
    } else {
        echo json_encode(['error'=>'Invalid request']);
    }
    exit;
}

if ($embed) {
  $hideNav = $hideBreadcrumb = true;
}
include 'header.php';
if ($embed) {
  echo '<style>body{margin-top:0;padding:15px;}</style>';
}
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding:20px; border-radius:5px; min-height:200px; }
  .drag-handle { cursor: move; }
</style>

<div class="row">
  <div class="col-md-4">
    <div class="mb-3">
      <label class="form-label">Write a content for:</label>
      <select id="pageType" class="form-select">
        <option value="Website">Website</option>
        <option value="Service">Service</option>
        <option value="Blog">Blog</option>
        <option value="Product">Product</option>
        <option value="Social Media">Social Media</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Content Output Language:</label>
      <input type="text" id="outputLanguage" class="form-control" placeholder="e.g. English, Egyptian Arabic">
    </div>

    <div class="mb-3">
      <label class="form-label">Focused Keyword:</label>
      <input type="text" id="keyword" class="form-control" placeholder="e.g. SEO Content Writing Service" value="<?php echo htmlspecialchars($presetFocus); ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Website URL:</label>
      <input type="text" id="website" class="form-control" placeholder="e.g. https://yourwebsite.com">
    </div>

    <div class="mb-3">
      <label class="form-label">Company Name:</label>
      <input type="text" id="companyName" class="form-control" placeholder="e.g. Green Mind Agency">
    </div>

    <div class="mb-2">
      <label class="form-label">Keywords (one per line):</label>
      <textarea id="keywords" class="form-control" rows="5" placeholder="e.g. content marketing strategy\nseo blog writing"><?php echo htmlspecialchars($presetKeywords); ?></textarea>
      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="autoSelectKeywords">
        <label class="form-check-label" for="autoSelectKeywords">
          Let the prompt decide the most relevant keywords based on the page type
        </label>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Localize to Countries:</label>
      <div id="countries">
        <div class="input-group mb-2">
          <input type="text" name="country[]" class="form-control" placeholder="e.g. Egypt">
          <button class="btn btn-outline-success" type="button" onclick="addCountry(this)">+</button>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Old Content to Refine (optional):</label>
      <textarea id="oldContent" class="form-control" rows="6" placeholder="Paste the old content here..."></textarea>
    </div>

    <div class="mb-4">
      <label class="form-label">Refinement Intensity:</label>
      <select id="refineLevel" class="form-select">
        <option value="None">None (only add keywords, no structure change)</option>
        <option value="Low">Low (minimal edits, preserve structure)</option>
        <option value="Mid">Mid (moderate changes)</option>
        <option value="High" selected>High (full rewrite allowed)</option>
      </select>
    </div>

    <div class="form-check form-switch mb-2">
      <input class="form-check-input" type="checkbox" id="includeFAQ">
      <label class="form-check-label" for="includeFAQ">Include FAQ section</label>
    </div>

    <div class="form-check form-switch mb-2">
      <input class="form-check-input" type="checkbox" id="includeDoc" checked>
      <label class="form-check-label" for="includeDoc">Use /doc at top of prompt</label>
    </div>

    <div class="form-check form-switch mb-4">
      <input class="form-check-input" type="checkbox" id="includeEmojis">
      <label class="form-check-label" for="includeEmojis">Include Emojis in Social Media Posts</label>
    </div>

    <div>
      <button class="btn btn-primary" onclick="generatePrompt()">Generate</button>
    </div>
  </div>

  <div class="col-md-8">
    <button class="btn btn-success mb-3" onclick="exportDoc()">Export to DOC</button>
    <button class="btn btn-outline-primary mb-3 ms-2" onclick="saveAll()">Save All</button>
    <div id="genProgress" class="progress mb-2 d-none">
      <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
    </div>
    <ul class="nav nav-tabs" id="outTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#contentTab" type="button" role="tab">Generated Content</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#promptTab" type="button" role="tab">Prompt</button>
      </li>
    </ul>
    <div class="tab-content border border-top-0 p-3">
      <div class="tab-pane fade show active" id="contentTab" role="tabpanel">
        <div id="metaSection"></div>
        <div id="sectionsContainer"></div>
        <div class="mb-3">
          <button class="btn btn-sm btn-outline-success" onclick="addSection()">+</button>
        </div>
        <hr>
        <div id="mediaSuggestions" class="small float-start"></div>
      </div>
      <div class="tab-pane fade" id="promptTab" role="tabpanel">
        <div class="d-flex align-items-center mb-2">
          <button class="btn btn-outline-secondary btn-sm" onclick="copyPrompt()">Copy</button>
        </div>
        <div id="output" class="form-control" readonly></div>
      </div>
    </div>
</div>
</div>
<textarea id="clipboardArea" style="position: absolute; left: -9999px; top: -9999px;"></textarea>
<div class="toast-container position-fixed top-0 end-0 p-3"></div>
<div class="modal fade" id="promptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enter Prompt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <textarea id="promptInput" class="form-control" rows="4" placeholder="Enter prompt"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="promptSubmit">Generate</button>
      </div>
    </div>
  </div>
</div>
<script>
let basePrompt = '';
const toastContainer = document.querySelector('.toast-container');
let promptModal, promptInput, promptResolve;
document.addEventListener('DOMContentLoaded', function(){
  promptModal = new bootstrap.Modal(document.getElementById('promptModal'));
  promptInput = document.getElementById('promptInput');
  document.getElementById('promptSubmit').addEventListener('click', function(){
    promptModal.hide();
    if(promptResolve) promptResolve(promptInput.value.trim());
  });
  loadSaved();
});
function sanitizeHtml(html){
  return String(html || '').replace(/<(?!\/?(h3|h4|p|strong|b|em|i|ul|ol|li|br)\b)[^>]*>/gi, '');
}
function autoResize(el){
  el.style.height = 'auto';
  el.style.height = el.scrollHeight + 'px';
}
function showProgress(){
  document.getElementById('genProgress').classList.remove('d-none');
}
function hideProgress(){
  document.getElementById('genProgress').classList.add('d-none');
}
function showToast(msg, type){
  const toast = document.createElement('div');
  const cls = type || 'secondary';
  toast.className = 'toast align-items-center text-bg-' + cls + ' border-0';
  toast.role = 'alert';
  toast.innerHTML = '<div class="d-flex"><div class="toast-body">'+msg+'</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  toastContainer.appendChild(toast);
  const t = new bootstrap.Toast(toast);
  t.show();
  toast.addEventListener('hidden.bs.toast', () => toast.remove());
}
function askPrompt(title){
  return new Promise(resolve => {
    promptResolve = resolve;
    document.querySelector('#promptModal .modal-title').textContent = title || 'Enter Prompt';
    promptInput.value = '';
    promptModal.show();
  });
}
function addCountry(button) {
  const container = document.getElementById('countries');
  const group = button.closest('.input-group');
  const clone = group.cloneNode(true);
  clone.querySelector('input').value = '';
  const btn = clone.querySelector('button');
  btn.textContent = '-';
  btn.classList.replace('btn-outline-success', 'btn-outline-danger');
  btn.setAttribute('onclick', 'removeCountry(this)');
  container.appendChild(clone);
}
function removeCountry(button) {
  button.closest('.input-group').remove();
}
function initTooltips(){
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
}
function addSection(html='', i, skipSave=false){
  const container = document.getElementById('sectionsContainer');
  const idx = typeof i === 'number' ? i : container.querySelectorAll('.mb-3').length;
  const wrap = document.createElement('div');
  wrap.className = 'mb-3';
  wrap.dataset.index = idx;
  wrap.addEventListener('dragover', handleDragOver);
  wrap.addEventListener('drop', handleDrop);
  wrap.addEventListener('dragend', handleDragEnd);
  const header = document.createElement('div');
  header.className = 'd-flex justify-content-between align-items-center mb-2';
  const label = document.createElement('strong');
  label.textContent = 'Section ' + (idx+1);
  label.classList.add('drag-handle');
  label.draggable = true;
  label.addEventListener('dragstart', e => handleDragStart.call(wrap, e));
  header.appendChild(label);
  const btnGroup = document.createElement('div');
  const save = document.createElement('button');
  save.type = 'button';
  save.className = 'btn btn-sm btn-outline-success me-2';
  save.textContent = '\ud83d\udcbe';
  save.setAttribute('data-bs-toggle','tooltip');
  save.setAttribute('data-bs-title','Save section');
  save.addEventListener('click', () => saveSection(wrap.dataset.index));
  const regen = document.createElement('button');
  regen.type = 'button';
  regen.className = 'btn btn-sm btn-outline-secondary me-2';
  regen.textContent = '\u21bb';
  regen.setAttribute('data-bs-toggle','tooltip');
  regen.setAttribute('data-bs-title','Regenerate section');
  regen.addEventListener('click', () => regenSection(wrap.dataset.index));
  const promptBtn = document.createElement('button');
  promptBtn.type = 'button';
  promptBtn.className = 'btn btn-sm btn-outline-primary me-2';
  promptBtn.textContent = '\u2728';
  promptBtn.setAttribute('data-bs-toggle','tooltip');
  promptBtn.setAttribute('data-bs-title','Regenerate with prompt');
  promptBtn.addEventListener('click', () => promptSection(wrap.dataset.index));
  const remove = document.createElement('button');
  remove.type = 'button';
  remove.className = 'btn btn-sm btn-outline-danger';
  remove.textContent = '-';
  remove.setAttribute('data-bs-toggle','tooltip');
  remove.setAttribute('data-bs-title','Remove section');
  remove.addEventListener('click', () => removeSection(wrap.dataset.index));
  btnGroup.append(save, regen, promptBtn, remove);
  header.appendChild(btnGroup);
  const div = document.createElement('div');
  div.className = 'form-control section-field';
  div.id = 'sec-content-' + idx;
  div.contentEditable = 'true';
  div.style.minHeight = '6em';
  div.innerHTML = html || '<h3></h3><h4></h4><p></p>';
  wrap.append(header, div);
  container.appendChild(wrap);
  initTooltips();
  if(!skipSave) saveAll(true);
}
function removeSection(i){
  const wrap = document.querySelector(`#sectionsContainer .mb-3[data-index="${i}"]`);
  if(!wrap) return;
  wrap.remove();
  localStorage.removeItem('content_section_'+i);
  renumberSections();
  saveAll(true);
  updateMediaSuggestions();
}
function renumberSections(){
  document.querySelectorAll('#sectionsContainer .mb-3').forEach((w, idx) => {
    w.dataset.index = idx;
    w.querySelector('strong').textContent = 'Section ' + (idx+1);
    w.querySelector('.section-field').id = 'sec-content-' + idx;
  });
}

let dragSrc = null;
function handleDragStart(e){
  dragSrc = this;
  this.classList.add('opacity-50');
}
function handleDragOver(e){
  e.preventDefault();
  const target = this;
  if(!dragSrc || dragSrc === target) return;
  const container = document.getElementById('sectionsContainer');
  const rect = target.getBoundingClientRect();
  const next = (e.clientY - rect.top) > rect.height/2;
  container.insertBefore(dragSrc, next ? target.nextSibling : target);
}
function handleDrop(e){
  e.preventDefault();
}
function handleDragEnd(){
  this.classList.remove('opacity-50');
  renumberSections();
  saveAll(true);
  updateMediaSuggestions();
}
function loadSaved(){
  const metaExists = ['meta_title','meta_description','slug'].some(f => localStorage.getItem('content_'+f));
  const sectionKeys = Object.keys(localStorage)
    .filter(k => k.startsWith('content_section_'))
    .sort((a,b) => parseInt(a.split('_').pop()) - parseInt(b.split('_').pop()));
  if(!metaExists && !sectionKeys.length) return;
  const sections = sectionKeys.map(k => localStorage.getItem(k) || '');
  renderContent({sections:[]}, true);
  sections.forEach((html, idx) => addSection(html, idx, true));
  saveAll(true);
  updateMediaSuggestions();
}
function generatePrompt() {
  // clear cached meta and sections before generating new content
  ['meta_title','meta_description','slug'].forEach(f => localStorage.removeItem('content_'+f));
  Object.keys(localStorage).forEach(k => { if(k.startsWith('content_section_')) localStorage.removeItem(k); });
  if(!skipMedia) updateMediaSuggestions(true);
}
function regenMeta(field, p=''){
  const map = {meta_title:'metaTitle', meta_description:'metaDescription', slug:'slug'};
  const el = document.getElementById(map[field]);
  const current = el ? el.textContent.trim() : '';
  const params = new URLSearchParams({ajax:'1', generate_meta:'1', field:field, prompt:basePrompt, current:current});
  if(p) params.append('userPrompt', p);
  showProgress();
  showToast('Regenerating '+field.replace('_',' ')+"...", 'info');
  fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
    .then(r => r.json())
    .then(res => {
      if(res[field] !== undefined) {
        el.textContent = res[field];
        if(field === 'meta_description') autoResize(el);
      } else if(res.error) alert(res.error);
      checkMeta();
    })
    .finally(() => { hideProgress(); showToast(field.replace('_',' ')+' updated', 'success'); updateMediaSuggestions(); });
}
async function promptMeta(field){
  const label = field.replace('_',' ');
  const p = await askPrompt('Regenerate '+label);
  if(p) regenMeta(field, p);
}
function regenSection(i, p=''){
  const div = document.getElementById('sec-content-' + i);
  const html = sanitizeHtml(div.innerHTML);
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  const title = tmp.querySelector('h3')?.textContent.trim() || '';
  const subtitle = tmp.querySelector('h4')?.textContent.trim() || '';
  const content = Array.from(tmp.querySelectorAll('p')).map(p => p.textContent.trim()).filter(Boolean).join('\n');
  const context = Array.from(document.querySelectorAll('#sectionsContainer .mb-3'))
    .slice(0, i)
    .map(w => w.querySelector('.section-field').innerHTML)
    .join('');
  const params = new URLSearchParams({ajax:'1', prompt:basePrompt});
  if(title || content){
    params.append('generate_section','1');
    params.append('title', title);
    params.append('subtitle', subtitle);
    params.append('content', content);
    params.append('context', context);
  } else {
    params.append('generate_new_section','1');
    params.append('current', context);
  }
  if(p) params.append('userPrompt', p);
  showProgress();
  showToast('Regenerating section '+(i+1)+'...', 'info');
  fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
    .then(r => r.json())
    .then(res => {
      if(res.sections) res = res.sections[0] || {};
      let html = '';
      if(res.title) html += `<h3>${res.title}</h3>`;
      const sub = res.subtitle ?? res.sub_title ?? res.subTitle ?? res.subheading ?? res.sub_heading;
      if(sub) html += `<h4>${sub}</h4>`;
      if(res.faqs){
        html += res.faqs.map(f => `<h4>${f.question}</h4><p>${f.answer}</p>`).join('');
      } else if(res.paragraphs) {
        html += res.paragraphs.map(p => `<p>${p.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')}</p>`).join('');
      }
      div.innerHTML = sanitizeHtml(html);
      saveSection(i, true);
      updateMediaSuggestions();
    })
    .finally(() => { hideProgress(); showToast('Section '+(i+1)+' updated', 'success'); });
}
async function promptSection(i){
  const p = await askPrompt('Regenerate section '+(i+1));
  if(p) regenSection(i, p);
}
function saveMeta(field, silent=false){
  const map = {meta_title:'metaTitle', meta_description:'metaDescription', slug:'slug'};
  const el = document.getElementById(map[field]);
  if(el){
    localStorage.setItem('content_'+field, el.textContent.trim());
    if(!silent) {
      showToast(field.replace('_',' ')+' saved', 'success');
      updateMediaSuggestions();
    }
  }
}
function saveSection(i, silent=false){
  const div = document.getElementById('sec-content-' + i);
  if(div){
    localStorage.setItem('content_section_'+i, div.innerHTML);
    if(!silent) {
      showToast('Section '+(i+1)+' saved', 'success');
      updateMediaSuggestions();
    }
  }
}
function saveAll(silent=false){
  ['meta_title','meta_description','slug'].forEach(f => saveMeta(f, true));
  Object.keys(localStorage).forEach(k => { if(k.startsWith('content_section_')) localStorage.removeItem(k); });
  document.querySelectorAll('#sectionsContainer .mb-3').forEach((w, idx) => {
    w.dataset.index = idx;
    saveSection(idx, true);
  });
  if(!silent) {
    showToast('All content saved', 'success');
    updateMediaSuggestions();
  }
}
function copyPrompt() {
  const text = document.getElementById('output').textContent;
  const ta = document.getElementById('clipboardArea');
  ta.value = text;
  ta.select();
  document.execCommand("copy");
  showToast('Prompt copied to clipboard!');
}
function checkMeta(){
  const mt = document.getElementById('metaTitle');
  const md = document.getElementById('metaDescription');
  const mtNote = document.getElementById('metaTitleNote');
  const mdNote = document.getElementById('metaDescriptionNote');
  if(mt && mtNote){ mtNote.textContent = mt.textContent.trim().length > 60 ? 'Title exceeds 60 characters' : ''; }
  if(md && mdNote){ const len = md.textContent.trim().length; mdNote.textContent = (len < 110 || len > 140) ? 'Description should be 110-140 characters' : ''; }
}

function updateMediaSuggestions(force=false){
  const box = document.getElementById('mediaSuggestions');
  if(!box) return;
  function render(media){
    media.icons = (media.icons || []).map(i => i.replace(/^fa[-\s]?/,''));
    box.innerHTML = '';
    function addGroup(label, items){
      if(!items || !items.length) return;
      const p = document.createElement('p');
      p.className = 'mb-1';
      p.append(label + ': ');
      items.forEach((item, idx) => {
        const span = document.createElement('span');
        span.className = 'text-primary';
        span.style.cursor = 'pointer';
        span.textContent = item;
        span.addEventListener('click', () => {
          navigator.clipboard.writeText(item).then(() => {
            showToast('Copied to clipboard', 'info');
          });
        });
        p.appendChild(span);
        if(idx < items.length - 1) p.append(', ');
      });
      box.appendChild(p);
    }
    addGroup('Recommended icons', media.icons);
    addGroup('Recommended images', media.images);
    addGroup('Recommended videos', media.videos);
    const reloadP = document.createElement('p');
    const reloadA = document.createElement('a');
    reloadA.href = '#';
    reloadA.textContent = 'Reload';
    reloadA.addEventListener('click', e => { e.preventDefault(); updateMediaSuggestions(true); });
    reloadP.appendChild(reloadA);
    box.appendChild(reloadP);
  }
  if(!force){
    const saved = localStorage.getItem('content_media');
    if(saved){
      try{ render(JSON.parse(saved)); return; }catch(e){ localStorage.removeItem('content_media'); }
    }
  }
  const meta = document.getElementById('metaSection').innerHTML;
  const sections = document.getElementById('sectionsContainer').innerHTML;
  const params = new URLSearchParams({ajax:'1', media_suggestions:'1', html: meta + sections});
  fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
    .then(r => r.json())
    .then(media => {
      localStorage.setItem('content_media', JSON.stringify(media));
      render(media);
    })
    .catch(() => { box.innerHTML = ''; });
}

function exportDoc(){
  const mt = document.getElementById('metaTitle')?.textContent.trim() || '';
  const md = document.getElementById('metaDescription')?.textContent.trim() || '';
  const slug = document.getElementById('slug')?.textContent.trim() || '';
  const focus = document.getElementById('keyword')?.value.trim() || '';
  const kwLines = document.getElementById('keywords')?.value.split('\n').map(k=>k.trim()).filter(Boolean) || [];
  const kw = [focus, ...kwLines].filter(Boolean).join('|');
  const sections = Array.from(document.querySelectorAll('#sectionsContainer .mb-3'))
    .map(w => w.querySelector('.section-field').innerHTML.trim())
    .filter(Boolean)
    .join('');
  let body = '';
  if(mt) body += `<p><strong>Meta Title:</strong> ${mt}</p>`;
  if(md) body += `<p><strong>Meta Description:</strong> ${md}</p>`;
  if(slug) body += `<p><strong>Slug:</strong> ${slug}</p>`;
  if(kw) body += `<p><strong>Keywords Used:</strong> ${kw}</p>`;
  body += '<hr />';
  body += sections;
  const title = mt || 'content';
  const params = new URLSearchParams({export_doc:'1', title:title, body:body});
  fetch('', {method:'POST', body:params})
    .then(r => { if(!r.ok) throw new Error('Server error'); return r.blob(); })
    .then(blob => {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = (title.replace(/[^A-Za-z0-9_-]+/g,'_') || 'document') + '.doc';
      document.body.appendChild(a);
      a.click();
      setTimeout(()=>{ URL.revokeObjectURL(url); a.remove(); }, 1000);
    })
    .catch(e => alert('Failed to export DOC: ' + (e && e.message ? e.message : e)));
}
</script>
<?php include 'footer.php'; ?>
