<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Green Mind's Keyword Clustering Tool</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 30px; margin-top: 50px; }
    #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 150px; }
    img.logo { width: 80px; margin-bottom: 20px; }
  </style>
</head>
<body>
<?php
  $active = 'clustering';
  include 'header.php';
?>


<div class="container">
  <div class="text-center mb-4">
    <img src="https://i.ibb.co/MyYRCxGx/Green-Mind-Agency-Logo-square.png" class="logo" alt="Green Mind Logo">
    <h2>Clustering</h2>
  </div>

  <div class="mb-3">
    <label class="form-label">List of Keywords (one per line):</label>
    <textarea id="keywords" class="form-control" rows="8" placeholder="e.g. seo company\ntechnical seo agency\nbest seo expert"></textarea>
  </div>

  <div class="mb-3">
    <label class="form-label">List of Page Names (one per line):</label>
    <small class="form-text text-muted">These represent the actual pages you want to rank or create content for. Each group of related keywords should ideally map to one of these pages. If a keyword doesn't fit any page, it should go in a standalone cluster.</small>
    <textarea id="pages" class="form-control" rows="5" placeholder="e.g. digital marketing\nseo audit\nppc management"></textarea>
  </div>

  <div class="form-check form-switch mb-4">
    <input class="form-check-input" type="checkbox" id="includeCanvas" checked>
    <label class="form-check-label" for="includeCanvas">Include /canvas at top of prompt</label>
  </div>

  <button class="btn btn-primary" onclick="generateClusteringPrompt()">Generate Prompt</button>

  <div class="d-flex align-items-center mt-5">
    <h4 class="mb-0">Generated Prompt:</h4>
    <button class="btn btn-outline-secondary btn-sm ms-3" onclick="copyClusteringPrompt()">Copy</button>
  </div>
  <div id="output" class="form-control" readonly></div>

  <textarea id="clipboardArea" style="position: absolute; left: -9999px; top: -9999px;"></textarea>
</div>

<script>
function generateClusteringPrompt() {
  const pages = document.getElementById('pages').value.trim();
  const keywords = document.getElementById('keywords').value.trim();
  const includeCanvas = document.getElementById('includeCanvas').checked;

  let prompt = "";
  if (includeCanvas) {
    prompt += "/canvas\n\n";
  }

  prompt += "Please cluster those split the same meaning with | no spaces between the |\n";
  prompt += "Please split clusters with a horizontal line\n";
  prompt += "If you are using one of the keywords in the cluster don’t repeat it on another cluster\n";
  prompt += "Try to split as possible the same meaning groups, and cluster only the same meaning, if not keep it in a standalone cluster\n";
  prompt += "Don’t ever add keywords from your side, use only the list below I gave you\n";
  prompt += "Don’t ever make a one cluster for all keywords. I need them split for the same meaning or they can be merged in one cluster to be used in a one page for SEO content creation. Please minimum 2 keywords per cluster, don't make a cluster for 1 keyword\n";
    prompt += "Don’t use alot of keywords in one cluster, try manage the keywords to be used in one page, for example i don't want 15 keywords in one cluster  that's too big to be used in one page, you have to find a way to align the keywords that can be use on which cluster \n";
	prompt += "Group commercial keywords (like company, agency, services) **together only when they are of the same intent**. \n";
  prompt += "Try to keep the clusters not more than 5 keywords. You can exceed this number if the keywords are the same (please focus on this), and don't make it in a table i need it under each other for easy of copy/paste \n\n";
  
  

  prompt += "Please use the below as guidelines for clusters.\nThese are the names of the pages that exist or will be created, and the goal is to assign relevant keyword groups to them.\nIf any keywords are not related to these pages, create new standalone clusters for them.\n\n";

  prompt += "The keywords:\n\n" + keywords + "\n\n";
  prompt += "The pages:\n\n" + pages;

  document.getElementById('output').textContent = prompt;
}

function copyClusteringPrompt() {
  const text = document.getElementById('output').textContent;
  const hiddenTextarea = document.getElementById('clipboardArea');
  hiddenTextarea.value = text;
  hiddenTextarea.select();
document.execCommand("copy");
alert("Prompt copied to clipboard!");
}
</script>
<?php include 'footer.php'; ?>

