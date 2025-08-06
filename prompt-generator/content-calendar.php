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
  <label class="form-label">Start Date:</label>
  <input type="date" id="startDate" class="form-control">
</div>

<div class="mb-3">
  <label class="form-label">End Date:</label>
  <input type="date" id="endDate" class="form-control">
</div>

<div class="mb-3">
  <label class="form-label">Posts per Week:</label>
  <input type="number" id="postsPerWeek" class="form-control" placeholder="e.g. 2">
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

<div class="form-check form-switch mb-3">
  <input class="form-check-input" type="checkbox" id="includeHolidays" checked>
  <label class="form-check-label" for="includeHolidays">Include occasion posts and bank holidays</label>
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
  const start = document.getElementById('startDate').value.trim();
  const end = document.getElementById('endDate').value.trim();
  const postsPerWeek = parseInt(document.getElementById('postsPerWeek').value, 10) || 0;
  const docFlag = document.getElementById('includeDoc').checked;
  const holidaysFlag = document.getElementById('includeHolidays').checked;
  const countries = Array.from(document.querySelectorAll('input[name="country[]"]')).map(c => c.value.trim()).filter(Boolean);
  let prompt = '';
  if (docFlag) prompt += '/doc Please open a doc for edit\n\n';
  prompt += `Generate a social media content calendar for ${company || 'the company'}.`;
  if (start && end) {
    prompt += ` Cover the period from ${start} to ${end}.`;
  } else if (start) {
    prompt += ` Starting from ${start}.`;
  } else if (end) {
    prompt += ` Up to ${end}.`;
  }
  if (website) prompt += ` Website: ${website}.`;
  prompt += ` Write in ${lang || 'English'}.\n\n`;
  if (keywords) {
    prompt += 'Focus on these keywords:\n' + keywords.split('\n').map(k => `- ${k}`).join('\n') + '\n\n';
  }
  if (countries.length) {
    prompt += `Localize the calendar for ${countries.join(', ')}`;
    if (holidaysFlag) {
      prompt += ' and include local holidays and occasions as simple greeting posts on their actual dates.';
    }
    prompt += '\n';
  } else {
    if (holidaysFlag) {
      prompt += 'Include occasion posts and relevant bank holidays as simple greeting posts on their actual dates.\n';
    } else {
      prompt += 'Keep the calendar generic without occasion posts or bank holidays.\n';
    }
  }
  let totalPosts = 0;
  if (start && end && postsPerWeek) {
    const diffTime = new Date(end) - new Date(start);
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)) + 1;
    const weeks = Math.floor(diffDays / 7);
    totalPosts = postsPerWeek * weeks;
  }
  if (totalPosts) {
    prompt += `Provide ${totalPosts} posts, roughly ${postsPerWeek} per week, sorted by date.\n`;
    if (start && end) {
      prompt += `Start with a brief recap covering the period from ${start} to ${end} mentioning the ${totalPosts} total posts.\n`;
    } else {
      prompt += `Start with a brief recap of the period mentioning the ${totalPosts} total posts.\n`;
    }
  } else if (postsPerWeek) {
    prompt += `Provide posts at a rate of ${postsPerWeek} per week, sorted by date.\n`;
    if (start && end) {
      prompt += `Start with a brief recap covering the period from ${start} to ${end} mentioning the total number of posts.\n`;
    } else {
      prompt += 'Start with a brief recap of the period mentioning the total number of posts.\n';
    }
  } else {
    prompt += 'Provide posts sorted by date.\n';
    if (start && end) {
      prompt += `Start with a brief recap covering the period from ${start} to ${end} mentioning the total number of posts.\n`;
    } else {
      prompt += 'Start with a brief recap of the period mentioning the total number of posts.\n';
    }
  }
  prompt += `Each post should be in this format:

**Date:** ...

**Post Title:** ...

**Description:** ...

**Purpose:** ...

Image: https://www.pinterest.com/search/pins/?q=(keyword you used)%20social%20media%20post

Video: https://www.pinterest.com/search/videos/?q=(keyword you used)%20social%20media%20post%20post

Grid: https://www.pinterest.com/search/pins/?q=(keyword you used)%20social%20media%20post%20grid

`;
  prompt += 'Replace (keyword you used) with the post\'s keyword in each link.';
  prompt += 'Do not schedule posts on Fridays or Saturdays. ';
  prompt += 'When occasion posts or bank holidays are included, place them on their actual dates unless the date is a Friday or Saturday. ';
  prompt += 'If an occasion falls on Friday or Saturday, shift its greeting post to the preceding Thursday and do not mention the weekend date. ';
  prompt += 'Ensure regular content uses the provided keywords and aligns with the company\'s brand, keeping occasion posts as simple greetings unrelated to the keywords.';
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

