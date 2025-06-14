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

<div class="form-check form-switch mb-4">
  <input class="form-check-input" type="checkbox" id="includeCanvas" checked>
  <label class="form-check-label" for="includeCanvas">Include /canvas at top of prompt</label>
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
  const input = document.getElementById('keywords').value.trim();
  const list = input.split('\n').map(k => k.toLowerCase().trim()).filter(Boolean);
  const count = document.getElementById('longtailCount').value;
  const includeCanvas = document.getElementById('includeCanvas').checked;

  let prompt = "";
  if (includeCanvas) {
    prompt += "/canvas\n\n";
  }

  prompt += "I have a list of the below keywords. I need you to search and find long tail keywords for each one.\n";
  prompt += `Please add up to ${count} longtail keyword(s) max for each.\n`;
  prompt += "You have to start with the main keyword – it cannot be in the middle or end.\n";
  prompt += "Don't use prepositions like for / to / the etc.\n";
  prompt += "Try to make the long tails meaningful.\n";
  prompt += "Avoid dates and locations.\n";
  prompt += "Output only the longtails in bullet points, not a table please, listed one under the other.\n";
  prompt += "Do not repeat the original keyword.\n";
  prompt += "Try to find the most/hieghest keywords based on search volume coming from google keyword planner as possible.\n\n";

  prompt += "Keywords:\n\n";
  list.forEach(k => prompt += `- ${k}\n`);

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
