<?php
$title = "Add Longtails";
include 'header.php';
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 150px; }
</style>

<div class="mb-3">
  <label class="form-label">Enter Keywords (one per line):</label>
  <textarea id="keywords" class="form-control" rows="6" placeholder="e.g. seo tools\nemail marketing\ncontent writing"></textarea>
</div>

<div class="mb-3">
  <label class="form-label">Number of Longtails per Keyword:</label>
  <select id="longtailCount" class="form-select">
    <option value="1">1</option>
    <option value="2">2</option>
    <option value="3">3</option>
    <option value="4">4</option>
    <option value="5">5</option>
  </select>
</div>

<button class="btn btn-primary" onclick="generateLongtailPrompt()">Generate Prompt</button>

<div class="d-flex align-items-center mt-5">
  <h4 class="mb-0">Generated Prompt:</h4>
  <button class="btn btn-outline-secondary btn-sm ms-3" onclick="copyLongtailPrompt()">Copy</button>
</div>
<div id="output" class="form-control" readonly></div>
<textarea id="clipboardArea" style="position: absolute; left: -9999px; top: -9999px;"></textarea>

<script>
function generateLongtailPrompt() {
  const keywords = document.getElementById('keywords').value.trim().split(/\n+/).filter(Boolean);
  const count = parseInt(document.getElementById('longtailCount').value, 10);

  let prompt = "";
  keywords.forEach(kw => {
    for (let i = 1; i <= count; i++) {
      prompt += kw + " longtail" + i + "\n";
    }
  });
  document.getElementById('output').textContent = prompt;
}

function copyLongtailPrompt() {
  const text = document.getElementById('output').textContent;
  const hiddenTextarea = document.getElementById('clipboardArea');
  hiddenTextarea.value = text;
  hiddenTextarea.select();
  document.execCommand("copy");
  alert("Prompt copied to clipboard!");
}
</script>
<?php include 'footer.php'; ?>
