<?php
$title = "Keywords Breaker";
include 'header.php';
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 150px; }
</style>

<div class="mb-3">
  <label class="form-label">Enter Keyword Groups (one group per line):</label>
  <textarea id="groups" class="form-control" rows="6" placeholder="e.g. seo company | technical seo agency"></textarea>
</div>

<button class="btn btn-primary" onclick="generateKeywordsPrompt()">Generate Prompt</button>

<div class="d-flex align-items-center mt-5">
  <h4 class="mb-0">Generated Prompt:</h4>
  <button class="btn btn-outline-secondary btn-sm ms-3" onclick="copyKeywordsPrompt()">Copy</button>
</div>
<div id="output" class="form-control" readonly></div>
<textarea id="clipboardArea" style="position: absolute; left: -9999px; top: -9999px;"></textarea>

<script>
function generateKeywordsPrompt() {
  const lines = document.getElementById('groups').value.trim().split(/\n+/).filter(Boolean);
  let prompt = "";
  lines.forEach(line => {
    const words = line.split(/\s*\|\s*/).filter(Boolean);
    words.forEach(w => { prompt += w + "\n"; });
  });
  document.getElementById('output').textContent = prompt;
}

function copyKeywordsPrompt() {
  const text = document.getElementById('output').textContent;
  const hiddenTextarea = document.getElementById('clipboardArea');
  hiddenTextarea.value = text;
  hiddenTextarea.select();
  document.execCommand("copy");
  alert("Prompt copied to clipboard!");
}
</script>
<?php include 'footer.php'; ?>
