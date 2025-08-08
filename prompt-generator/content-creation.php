<?php
$title = "Content Creation";
$presetKeywords = isset($_GET['keywords']) ? trim($_GET['keywords']) : '';
include 'header.php';
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 200px; }
</style>

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
  <input type="text" id="keyword" class="form-control" placeholder="e.g. SEO Content Writing Service">
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
  <button class="btn btn-primary" onclick="generatePrompt()">Generate Prompt</button>
</div>

<div class="d-flex align-items-center mt-5">
  <h3 class="mb-0">Generated Prompt:</h3>
  <button class="btn btn-outline-secondary btn-sm ms-3" onclick="copyPrompt()">Copy</button>
</div>
<div id="output" class="form-control" readonly></div>
<textarea id="clipboardArea" style="position: absolute; left: -9999px; top: -9999px;"></textarea>

<script>
function addCountry(button) {
  const container = document.getElementById('countries');
  const group = button.closest('.input-group');
  const clone = group.cloneNode(true);
  clone.querySelector('input').value = '';
  clone.querySelector('button').textContent = '-';
  clone.querySelector('button').classList.replace('btn-outline-success', 'btn-outline-danger');
  clone.querySelector('button').setAttribute('onclick', 'removeCountry(this)');
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
    prompt += "– Headings: the hierarchy must be formed well – starting with H3, the subheadings will be H4\n";
    prompt += "– Please make the content follow Yoast SEO guidelines\n";
    prompt += "– Please make a quick search for SERP and understand why these pages are ranking and give the best SEO content to compete with them\n";
    prompt += "– please don't bold the keywords that you are using within the content; only bold the text needing emphasis, and please in the doc don't add any data classes or classes for easy copy paste.\n";
    prompt += "– focused Keyphrase density is important to not to use it too mush YOAST SEO usually recommend to not repeate the focused keywords too much. \n";
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
  document.getElementById('output').textContent = prompt;
}
function copyPrompt() {
  const text = document.getElementById('output').textContent;
  const ta = document.getElementById('clipboardArea');
  ta.value = text;
  ta.select();
  document.execCommand("copy");
  showToast('Prompt copied to clipboard!');
}
</script>
<?php include 'footer.php'; ?>
