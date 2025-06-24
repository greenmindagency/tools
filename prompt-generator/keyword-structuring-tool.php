<?php
$title = "Keywords Breaker";
include 'header.php';
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 150px; }
</style>

<div class="mb-3">
  <label class="form-label">Paste Grouped Keywords:</label>
  <textarea id="keywords" class="form-control" rows="6" placeholder="Group 1: seo tools, blog seo, rank checker
Group 2: content writing, article writing, blog writing, brand voice, tone guide"></textarea>
</div>

<div class="form-check form-switch mb-4">
  <input class="form-check-input" type="checkbox" id="includeDoc" checked>
  <label class="form-check-label" for="includeDoc">Include /doc at top of prompt</label>
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
  const keywords = document.getElementById('keywords').value.trim();
  const includeDoc = document.getElementById('includeDoc').checked;

  let prompt = "";
  if (includeDoc) {
    prompt += "/doc\n\n";
  }

  prompt += "I have a list of the below keywords and their group.\n";
  prompt += "I need you to break the number of the keywords under each group by adding long tail keywords for keywords of more than 5.\n";
  prompt += "You can see now some groups have 7 and 8 keywords.\n";
  prompt += "I need you to break them by adding a long tail for some selected keywords under each group to have its own group.\n\n";
  prompt += "Please give me only a list of long tails without titles and don’t repeat the list I gave you under each other.\n";
  prompt += "Each group should have 2 keywords minimum.\n";
  prompt += "if there keywords has already longtails and working don't add longtails for them (please focus) only single words that doesn't have a logn tail you can only add.\n";
  prompt += "Keep always each keyword at the start and longtails after.\n";
  prompt += "Please also try to avoid using conjunctive words like (with, of, in, ..) or you can use it occasionally.\n";
  prompt += "Try to find the most/hieghest keywords based on search volume coming from google keyword planner as possible.\n\n";
  prompt += "I need also 1 longtail maximum for each keyword, all in lowercase and in bullet points.\n\n";

  prompt += "Keywords List:\n\n" + keywords;

  document.getElementById('output').textContent = prompt;
}

function copyPrompt() {
  const text = document.getElementById('output').textContent;
  const hiddenTextarea = document.getElementById('clipboardArea');
  hiddenTextarea.value = text;
  hiddenTextarea.select();
  document.execCommand("copy");
  alert("Prompt copied to clipboard!");
}
</script>
<?php include 'footer.php'; ?>
