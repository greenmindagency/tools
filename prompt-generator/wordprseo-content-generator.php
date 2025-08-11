<?php
$title = "WordprSEO Content Generator";
include 'header.php';
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 200px; }
</style>
<div class="mb-3">
  <label class="form-label">Website Tree (optional)</label>
  <textarea id="tree" class="form-control" rows="4" placeholder="- Home\n- About\n- Services\n  - ..."></textarea>
</div>
<div class="mb-3">
  <label class="form-label">Template</label>
  <select id="template" class="form-select" onchange="updateSections()">
    <option value="home">Home</option>
    <option value="about">About</option>
    <option value="service-category">Service Category</option>
    <option value="service-tag">Service Tag</option>
    <option value="other-category">Blog/News Category</option>
    <option value="single">Single</option>
    <option value="careers">Careers</option>
    <option value="contact">Contact Us</option>
  </select>
</div>
<div class="mb-3">
  <label class="form-label">Page Name</label>
  <input type="text" id="pageName" class="form-control" placeholder="e.g. Digital Marketing Services">
</div>
<div class="mb-3">
  <label class="form-label">Target Keyword (optional)</label>
  <input type="text" id="keyword" class="form-control" placeholder="e.g. Digital Marketing Agency">
</div>
<div class="mb-3">
  <label class="form-label">Output Language</label>
  <input type="text" id="language" class="form-control" placeholder="e.g. English">
</div>
<div class="mb-3">
  <label class="form-label">Sections (comma separated)</label>
  <textarea id="sections" class="form-control" rows="3" placeholder="slider, tagslist, ..."></textarea>
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
const defaultSections = {
  "home": "slider, tagslist, pagecontent2, pagecontent1, catconnection, slider, pagecontent3, articletitle, articleimages, postconnection, hero-video, articletitle, pagecontent7, postsrelatedcat (infinite), accordion, pagecontent5",
  "about": "slider, articletitle, pagecontent2, pagecontent1, catconnection, pagecontent4, tagconnection, postsrelatedcat, articletitle, articleslideshow, pagecontent5",
  "service-category": "herovideo|pagecontent5, tagslist, articletitle, articlevideogallery, postsrelatedtagslider, pagecontent1, pagecontent3, verticaltabs, pagecontent7, pagecontent4, pagecontent2, pagecontent5, catconnection, articletitle, articleimages, tagconnection, postsrelatedcat, articletitle, articlevideogallery, postconnection, pagecontent5",
  "service-tag": "slider, pagecontent1, tagconnection, articletitle, pagecontent1, pagecontent3, pagecontent1, articletitle, articlevideogallery, accordion, articletitle, imgcarousel, pagecontent5",
  "other-category": "postsrelatedcat (infinite)",
  "single": "pagecontent1, articletitle, articleimages, pagecontent5",
  "careers": "pagecontent5",
  "contact": "contacts, pagecontent6"
};
function updateSections() {
  const template = document.getElementById('template').value;
  document.getElementById('sections').value = defaultSections[template] || '';
}
function generatePrompt() {
  const tree = document.getElementById('tree').value.trim();
  const template = document.getElementById('template').value;
  const pageName = document.getElementById('pageName').value.trim();
  const keyword = document.getElementById('keyword').value.trim();
  const language = document.getElementById('language').value.trim() || 'English';
  const sections = document.getElementById('sections').value.trim();
  let prompt = "";
  if (!tree) {
    prompt += "Create a website tree for a WordprSEO site. List each item and note whether it is a page, category, tag, or single. Existing pages: Home, About, Careers, Contact Us.\n\n";
    prompt += "Return the tree in a bullet list.";
  } else {
    prompt += "/doc Please open a doc for edit\n\n";
    prompt += `We are creating content for a ${template.replace(/-/g,' ')} page called \"${pageName}\" on a WordprSEO website by Green Mind Agency.\n`;
    prompt += `Output language: ${language}.\n`;
    if (keyword) prompt += `Focused keyword: ${keyword}.\n`;
    prompt += `Website tree:\n${tree}\n\n`;
    prompt += `Use the following sections in order: ${sections || defaultSections[template]}. Follow the section guidelines provided.`;
  }
  document.getElementById('output').textContent = prompt;
}
function copyPrompt() {
  const output = document.getElementById('output').textContent;
  const textarea = document.getElementById('clipboardArea');
  textarea.value = output;
  textarea.select();
  document.execCommand('copy');
}
updateSections();
</script>
<?php include 'footer.php'; ?>
