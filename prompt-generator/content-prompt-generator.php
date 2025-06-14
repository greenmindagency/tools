<?php
$title = "Content Creation";
include 'header.php';
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 200px; }
</style>

<div class="mb-3">
  <label class="form-label">Main Keyword:</label>
  <input type="text" id="keyword" class="form-control" placeholder="e.g. seo agency">
</div>
<div class="mb-3">
  <label class="form-label">Target Country:</label>
  <input type="text" id="country" class="form-control" placeholder="e.g. USA">
</div>
<div class="mb-3">
  <label class="form-label">Tone of Voice:</label>
  <input type="text" id="tone" class="form-control" placeholder="e.g. professional">
</div>
<div class="mb-3">
  <label class="form-label">Existing Content (optional):</label>
  <textarea id="old" class="form-control" rows="5"></textarea>
</div>
<div class="mb-3">
  <label class="form-label">Refinement Level (0-100):</label>
  <input type="number" id="refine" class="form-control" value="50" min="0" max="100">
</div>
<div class="form-check mb-3">
  <input class="form-check-input" type="checkbox" id="faq">
  <label class="form-check-label" for="faq">Add FAQ section</label>
</div>
<button class="btn btn-primary" onclick="generatePrompt()">Generate Prompt</button>

<div class="d-flex align-items-center mt-5">
  <h4 class="mb-0">Generated Prompt:</h4>
  <button class="btn btn-outline-secondary btn-sm ms-3" onclick="copyPrompt()">Copy</button>
</div>
<div id="output" class="form-control" readonly></div>
<textarea id="clipboardArea" style="position: absolute; left: -9999px; top: -9999px;"></textarea>

<script>
function generatePrompt() {
  const keyword = document.getElementById('keyword').value.trim();
  const country = document.getElementById('country').value.trim();
  const tone = document.getElementById('tone').value.trim();
  const refine = document.getElementById('refine').value.trim();
  const old = document.getElementById('old').value.trim();
  const faq = document.getElementById('faq').checked;
  let prompt = `Create a blog post about ${keyword} targeting ${country} with a ${tone} tone.\n`;
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
  alert("Prompt copied to clipboard!");
}
</script>
<?php include 'footer.php'; ?>
