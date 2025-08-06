<?php
$title = "Content Calendar";
include 'header.php';
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 200px; }
</style>

<div class="mb-3">
  <label class="form-label">Content Output Language:</label>
  <input type="text" id="outputLanguage" class="form-control" placeholder="e.g. English">
</div>

<div class="mb-3">
  <label class="form-label">Keywords (one per line):</label>
  <textarea id="keywords" class="form-control" rows="5" placeholder="e.g. world-class education\nmodern facilities"></textarea>
</div>

<div class="mb-3">
  <label class="form-label">Client Website URL:</label>
  <input type="text" id="website" class="form-control" placeholder="e.g. https://example.com">
</div>

<div class="mb-3">
  <label class="form-label">Company Name:</label>
  <input type="text" id="companyName" class="form-control" placeholder="e.g. IES Regal Cairo">
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

<div class="form-check form-switch mb-4">
  <input class="form-check-input" type="checkbox" id="includeDoc" checked>
  <label class="form-check-label" for="includeDoc">Use /doc at top of prompt</label>
</div>

<button class="btn btn-primary" onclick="generatePrompt()">Generate Prompt</button>

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
  const lang = document.getElementById('outputLanguage').value.trim();
  const keywords = document.getElementById('keywords').value.trim();
  const website = document.getElementById('website').value.trim();
  const company = document.getElementById('companyName').value.trim();
  const docFlag = document.getElementById('includeDoc').checked;
  const countries = Array.from(document.querySelectorAll('input[name="country[]"]')).map(c => c.value.trim()).filter(Boolean);
  let prompt = '';
  if (docFlag) prompt += '/doc Please open a doc for edit\n\n';
  prompt += `Generate a social media content calendar for ${company || 'the company'}.`;
  if (website) prompt += ` Website: ${website}.`;
  prompt += ` Write in ${lang || 'English'}.\n\n`;
  if (keywords) {
    prompt += 'Focus on these keywords:\n' + keywords.split('\n').map(k => `- ${k}`).join('\n') + '\n\n';
  }
  if (countries.length) {
    prompt += `Localize the calendar for ${countries.join(', ')} and include local holidays and occasions for these countries.\n`;
  } else {
    prompt += 'Keep the calendar generic without country-specific occasions.\n';
  }
  prompt += 'Provide at least 3 posts with the following structure:\n';
  prompt += '1. Post Title: ...\n   Description: ...\n   Purpose: ...\n';
  prompt += '2. Post Title: ...\n   Description: ...\n   Purpose: ...\n';
  prompt += '3. Post Title: ...\n   Description: ...\n   Purpose: ...\n\n';
  prompt += 'Ensure the content uses the provided keywords and aligns with the company\'s brand.';
  document.getElementById('output').textContent = prompt;
}
function copyPrompt() {
  const text = document.getElementById('output').textContent;
  const ta = document.getElementById('clipboardArea');
  ta.value = text;
  ta.select();
  document.execCommand('copy');
  showToast('Prompt copied to clipboard!');
}
</script>
<?php include 'footer.php'; ?>

