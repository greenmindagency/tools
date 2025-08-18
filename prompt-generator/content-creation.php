<?php
// Content creation tool with AI output and prompt tabs
$title = "Content Creation";
$presetKeywords = isset($_GET['keywords']) ? trim($_GET['keywords']) : '';
$presetFocus = isset($_GET['focus']) ? trim($_GET['focus']) : '';
$embed = isset($_GET['embed']);

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
        $prompt = $_POST['prompt'] ?? '';
        $title = $_POST['title'] ?? '';
        $subtitle = $_POST['subtitle'] ?? '';
        $content = $_POST['content'] ?? '';
        $userPrompt = trim($_POST['userPrompt'] ?? '');
        $prompt .= "\n\nRegenerate the section with title '{$title}' and subtitle '{$subtitle}'.";
        if ($content !== '') $prompt .= "\nCurrent content:\n{$content}";
        if ($userPrompt !== '') $prompt .= "\nAdditional instructions:\n{$userPrompt}";
        $prompt .= "\nReturn JSON with keys 'title','subtitle','paragraphs' (array of strings).";
        $res = callGemini($prompt);
        echo json_encode($res);
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
    <button class="btn btn-success mb-3" onclick="exportDocx()">Export to DOCX</button>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/docx/8.2.1/docx.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script>
let basePrompt = '';
const toastContainer = document.querySelector('.toast-container');
function sanitizeHtml(html){
  return String(html || '').replace(/<(?!\/?(p|strong|b|em|i|ul|ol|li|br)\b)[^>]*>/gi, '');
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
function generatePrompt() {
  const type = document.getElementById('pageType').value;
  const keyword = document.getElementById('keyword').value.trim();
  const lang = document.getElementById('outputLanguage').value.trim();
  const website = document.getElementById('website').value.trim();
  const company = document.getElementById('companyName').value.trim();
  const keywords = document.getElementById('keywords').value.trim();
  const auto = document.getElementById('autoSelectKeywords').checked;
  const docFlag = document.getElementById('includeDoc').checked;
  const emojis = document.getElementById('includeEmojis').checked;
  const old = document.getElementById('oldContent').value.trim();
  const refine = document.getElementById('refineLevel').value;
  const faq = document.getElementById('includeFAQ').checked;
  const countries = Array.from(document.querySelectorAll('input[name="country[]"]')).map(c => c.value.trim()).filter(Boolean);
  let prompt = "";
  if (docFlag) prompt += "/doc Please open a doc for edit\n\n";
  prompt += `Write a content for a ${type === "Social Media" ? "social media post" : `${type} page`} in ${lang || 'English'}\n\n`;
  prompt += "Please act as a content writer, a very proficient SEO writer who writes fluently.\n\n";

  if (type === "Social Media") {
    prompt += `Please write a creative and engaging social media post for ${company}, focusing on the keyword: ${keyword}.\n`;
    prompt += "Make the tone appealing, human, and valuable.\nInclude hooks or calls to action and avoid robotic language.\nDo research for top SERP performing social media posts about this topic.\n";
    if (emojis) prompt += "Include relevant emojis naturally in the post.\n";
    prompt += "Add the most relevant hashtags at the end.\n\n";
  } else {
    prompt += `– Focused keyword – ${keyword}\n`;
    prompt += "– Use the above keyword in title, description, slug, keywords, first paragraph, and one time on any heading tag\n";
    prompt += "– Article content: 500 words minimum\n";
    prompt += "– Slug length: 60 characters\n";
    prompt += "– Please provide: Meta Title: 60 characters max including the targeted keyword\n";
    prompt += "– Please provide: Meta Descriptions: 140 characters max including the target keyword, keep it within 110 to 140 characters\n";
    prompt += "– Headings: the hierarchy must be formed well – starting with H3, the subheadings will be H4 please avoid of adding the H1,2,3,4 as a text in the output just make the tag in the code not as an actual text\n";
    prompt += "– Please make the content follow Yoast SEO guidelines\n";
    prompt += "– Please make a quick search for SERP and understand why these pages are ranking and give the best SEO content to compete with them\n";
    prompt += "– please don't bold the keywords that you are using within the content; only bold the text needing emphasis, andplease in the doc don't add any data classes or classes for easy copy paste.\n";
    prompt += "– focused Keyphrase density is important to not to use it too mush YOAST SEO usually recommend to not repeate the focused keywords too much. \n";
    prompt += "– please don't add <hr> between each section no need it i need the content seamlesly typed. \n";
    prompt += "– I need to make a deep research online to give some new ideas or a numbers average related to content if needed i need the content to be like i really spent alot of time humen writing it, content that realy rank and give a better values to SEO \n\n";

    prompt += `Please write a ${type.toLowerCase()} page content for the website ${website} (${company})\n\n`;
  }
  const list = keywords.split('\n').map(k => k.trim()).filter(k => k && k !== keyword);
  if (keywords && auto) {
    prompt += `Please choose the most suitable keywords based on the selected page type (${type}).\n`;
    prompt += "Please select the related keywords from the below list:\n\n" + keywords.split('\n').map(k => `- ${k}`).join('\n') + '\n\n';
    prompt += `Keywords: ${keyword}|${list.join('|')}\n\n`;
  } else if (keywords) {
    prompt += "Please you must use the below keywords, in the headings and within the content:\n\n" + keywords.split('\n').map(k => `- ${k}`).join('\n') + '\n\n';
    prompt += `Keywords: ${keyword}|${list.join('|')}\n\n`;
  }
  if (countries[0]) prompt += `Localize the main content to ${countries[0]}.\n`;
  if (countries.length > 1) {
    prompt += "At the end of the main content, add sections localized for:\n" + countries.slice(1).map(c => `- ${c}`).join('\n') + '\n\n';
  }
  if (old) prompt += `Refine the following old content with intensity '${refine}':\n\n${old}\n\n`;
  prompt += "Please don’t give me an outline, give me the content directly.";
  if (faq) prompt += " Add a FAQ section at the end.";
  basePrompt = prompt;
  document.getElementById('output').textContent = prompt;

  showProgress();
  showToast('Generating content...', 'info');
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ajax: '1', generate_content: '1', prompt: prompt})
  })
  .then(r => r.json())
  .then(renderContent)
  .catch(() => alert('Failed to generate content'))
  .finally(() => { hideProgress(); showToast('Content generated', 'success'); });
}
function renderContent(data){
  if(data.error){ alert(data.error); return; }
  const meta = document.getElementById('metaSection');
  meta.innerHTML = '';
  const mtGroup = document.createElement('div');
  mtGroup.className = 'd-flex mb-2';
  const metaTitle = document.createElement('input');
  metaTitle.type = 'text';
  metaTitle.id = 'metaTitle';
  metaTitle.className = 'form-control';
  metaTitle.placeholder = 'Meta Title';
  metaTitle.value = data.meta_title || '';
  const mtBtn = document.createElement('button');
  mtBtn.type = 'button';
  mtBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
  mtBtn.textContent = '\u21bb';
  mtBtn.setAttribute('data-bs-toggle','tooltip');
  mtBtn.setAttribute('data-bs-title','Regenerate meta title');
  mtBtn.addEventListener('click', () => regenMeta('meta_title'));
  mtGroup.append(metaTitle, mtBtn);
  const mtNote = document.createElement('div');
  mtNote.id = 'metaTitleNote';
  mtNote.className = 'form-text text-danger';

  const mdGroup = document.createElement('div');
  mdGroup.className = 'd-flex mb-2';
  const metaDesc = document.createElement('textarea');
  metaDesc.id = 'metaDescription';
  metaDesc.className = 'form-control';
  metaDesc.placeholder = 'Meta Description';
  metaDesc.value = data.meta_description || '';
  autoResize(metaDesc);
  metaDesc.addEventListener('input', () => autoResize(metaDesc));
  const mdBtn = document.createElement('button');
  mdBtn.type = 'button';
  mdBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
  mdBtn.textContent = '\u21bb';
  mdBtn.setAttribute('data-bs-toggle','tooltip');
  mdBtn.setAttribute('data-bs-title','Regenerate meta description');
  mdBtn.addEventListener('click', () => regenMeta('meta_description'));
  mdGroup.append(metaDesc, mdBtn);
  const mdNote = document.createElement('div');
  mdNote.id = 'metaDescNote';
  mdNote.className = 'form-text text-danger';

  const slugGroup = document.createElement('div');
  slugGroup.className = 'd-flex mb-3';
  const slugInput = document.createElement('input');
  slugInput.type = 'text';
  slugInput.id = 'slug';
  slugInput.className = 'form-control';
  slugInput.placeholder = 'Slug';
  slugInput.value = data.slug || '';
  const slugBtn = document.createElement('button');
  slugBtn.type = 'button';
  slugBtn.className = 'btn btn-sm btn-outline-secondary ms-2';
  slugBtn.textContent = '\u21bb';
  slugBtn.setAttribute('data-bs-toggle','tooltip');
  slugBtn.setAttribute('data-bs-title','Regenerate slug');
  slugBtn.addEventListener('click', () => regenMeta('slug'));
  slugGroup.append(slugInput, slugBtn);
  const hr = document.createElement('hr');
  meta.append(mtGroup, mtNote, mdGroup, mdNote, slugGroup, hr);
  metaTitle.addEventListener('input', checkMeta);
  metaDesc.addEventListener('input', checkMeta);
  checkMeta();

  const container = document.getElementById('sectionsContainer');
  container.innerHTML = '';
  (data.sections || []).forEach((sec, idx) => {
    const wrap = document.createElement('div');
    wrap.className = 'mb-3';
    wrap.dataset.index = idx;

    const header = document.createElement('div');
    header.className = 'd-flex justify-content-between align-items-center mb-2';
    const label = document.createElement('strong');
    label.textContent = 'Section ' + (idx+1);
    header.appendChild(label);
    const btnGroup = document.createElement('div');
    const regen = document.createElement('button');
    regen.type = 'button';
    regen.className = 'btn btn-sm btn-outline-secondary me-2';
    regen.textContent = '\u21bb';
    regen.setAttribute('data-bs-toggle','tooltip');
    regen.setAttribute('data-bs-title','Regenerate section');
    regen.addEventListener('click', () => regenSection(idx));
    const promptBtn = document.createElement('button');
    promptBtn.type = 'button';
    promptBtn.className = 'btn btn-sm btn-outline-primary';
    promptBtn.textContent = '\u2728';
    promptBtn.setAttribute('data-bs-toggle','tooltip');
    promptBtn.setAttribute('data-bs-title','Regenerate with prompt');
    promptBtn.addEventListener('click', () => promptSection(idx));
    btnGroup.append(regen, promptBtn);
    header.appendChild(btnGroup);

    const div = document.createElement('div');
    div.className = 'form-control section-field';
    div.id = 'sec-content-' + idx;
    div.contentEditable = 'true';
    div.style.minHeight = '6em';
    const subVal = sec.subtitle || sec.sub_title || sec.subTitle || sec.subheading || sec.sub_heading || '';
    let html = '';
    if(sec.title) html += `<p><strong>${sec.title}</strong></p>`;
    if(subVal) html += `<p>${subVal}</p>`;
    if(sec.title && sec.title.toLowerCase().includes('faq')){
      const divider = document.createElement('hr');
      container.appendChild(divider);
      if(Array.isArray(sec.faqs)){
        sec.faqs.forEach(f => { html += `<p><strong>${f.question}</strong><br>${f.answer}</p>`; });
      } else {
        for(let i=0;i<(sec.paragraphs||[]).length;i+=2){
          const q = (sec.paragraphs[i]||'').replace(/\*\*(.*?)\*\*/g,'$1');
          const a = sec.paragraphs[i+1]||'';
          html += `<p><strong>${q}</strong><br>${a}</p>`;
        }
      }
    } else {
      html += (sec.paragraphs || []).map(p => `<p>${p.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')}</p>`).join('');
    }
    div.innerHTML = sanitizeHtml(html);

    wrap.append(header, div);
    container.appendChild(wrap);
  });
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
}
function regenMeta(field, p=''){
  const map = {meta_title:'metaTitle', meta_description:'metaDescription', slug:'slug'};
  const current = document.getElementById(map[field]).value;
  const params = new URLSearchParams({ajax:'1', generate_meta:'1', field:field, prompt:basePrompt, current:current});
  if(p) params.append('userPrompt', p);
  showProgress();
  showToast('Regenerating '+field.replace('_',' ')+"...", 'info');
  fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
    .then(r => r.json())
    .then(res => {
      if(res[field] !== undefined) {
        document.getElementById(map[field]).value = res[field];
        if(field === 'meta_description') autoResize(document.getElementById('metaDescription'));
      } else if(res.error) alert(res.error);
      checkMeta();
    })
    .finally(() => { hideProgress(); showToast(field.replace('_',' ')+' updated', 'success'); });
}
function regenSection(i, p=''){
  const div = document.getElementById('sec-content-' + i);
  const html = sanitizeHtml(div.innerHTML);
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  const parts = Array.from(tmp.querySelectorAll('p')).map(p => p.innerHTML.trim()).filter(Boolean);
  const title = parts.shift() || '';
  const subtitle = parts.shift() || '';
  const content = parts.join('\n');
  const params = new URLSearchParams({ajax:'1', generate_section:'1', prompt:basePrompt, title:title, subtitle:subtitle, content:content});
  if(p) params.append('userPrompt', p);
  showProgress();
  showToast('Regenerating section '+(i+1)+'...', 'info');
  fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
    .then(r => r.json())
    .then(res => {
      let html = '';
      if(res.title) html += `<p><strong>${res.title}</strong></p>`;
      const sub = res.subtitle ?? res.sub_title ?? res.subTitle ?? res.subheading ?? res.sub_heading;
      if(sub) html += `<p>${sub}</p>`;
      if(res.faqs){
        html += res.faqs.map(f => `<p><strong>${f.question}</strong><br>${f.answer}</p>`).join('');
      } else if(res.paragraphs) {
        html += res.paragraphs.map(p => `<p>${p.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')}</p>`).join('');
      }
      div.innerHTML = sanitizeHtml(html);
    })
    .finally(() => { hideProgress(); showToast('Section '+(i+1)+' updated', 'success'); });
}
function promptSection(i){
  const p = prompt('Additional instructions?');
  if(p) regenSection(i, p);
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
  const mdNote = document.getElementById('metaDescNote');
  if(mt && mtNote){ mtNote.textContent = mt.value.length > 60 ? 'Title exceeds 60 characters' : ''; }
  if(md && mdNote){ const len = md.value.length; mdNote.textContent = (len < 110 || len > 140) ? 'Description should be 110-140 characters' : ''; }
}

function exportDocx(){
  const {Document, Packer, Paragraph, TextRun, HeadingLevel} = window.docx;
  const children = [];
  const mt = document.getElementById('metaTitle')?.value || '';
  const md = document.getElementById('metaDescription')?.value || '';
  const slug = document.getElementById('slug')?.value || '';
  if(mt) children.push(new Paragraph({text: mt, heading: HeadingLevel.HEADING_1}));
  if(md) children.push(new Paragraph(md));
  if(slug) children.push(new Paragraph('Slug: ' + slug));
  children.push(new Paragraph(''));
  const wraps = document.querySelectorAll('#sectionsContainer .mb-3');
  wraps.forEach(wrap => {
    const html = wrap.querySelector('.section-field').innerHTML;
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const ps = Array.from(tmp.querySelectorAll('p'));
    if(ps[0]) children.push(new Paragraph({text: ps[0].textContent, heading: HeadingLevel.HEADING_1}));
    let start = 1;
    if(ps[1]) { children.push(new Paragraph({text: ps[1].textContent, heading: HeadingLevel.HEADING_2})); start = 2; }
    for(let i=start;i<ps.length;i++){
      const text = ps[i].innerHTML
        .replace(/<strong>(.*?)<\/strong>/gi,'**$1**')
        .replace(/<br\s*\/?>/gi,'\n')
        .replace(/<\/p>\s*<p>/gi,'\n\n')
        .replace(/<[^>]+>/g,'');
      text.split(/\n+/).forEach(p => {
        if(!p.trim()) return;
        const parts = p.split(/\*\*/);
        const runs = parts.map((part,j) => new TextRun({text:part, bold:j%2===1}));
        children.push(new Paragraph({children:runs}));
      });
    }
    children.push(new Paragraph(''));
  });
  const doc = new Document({sections:[{children}]});
  Packer.toBlob(doc).then(blob => window.saveAs(blob, 'content.docx'));
}
</script>
<?php include 'footer.php'; ?>
