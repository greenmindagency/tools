<?php
session_start();
require __DIR__ . '/config.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$client_id = (int)($_GET['client_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    header('Location: index.php');
    exit;
}
$output = '';
$error = '';
$saved = '';
$instructions = $client['instructions'] ?? '';
$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_instructions'])) {
        $instructions = $_POST['instructions'] ?? '';
        $pdo->prepare('UPDATE clients SET instructions = ? WHERE id = ?')->execute([$instructions, $client_id]);
        $saved = 'Instructions saved.';
    } elseif (isset($_POST['save_sitemap'])) {
        $json = $_POST['sitemap_json'] ?? '[]';
        $pdo->prepare('UPDATE clients SET sitemap = ? WHERE id = ?')->execute([$json, $client_id]);
        $sitemap = json_decode($json, true);
        $saved = 'Sitemap saved.';
    } elseif (isset($_POST['regenerate'])) {
        $num = (int)($_POST['num_pages'] ?? 4);
        $instr = $_POST['instructions'] ?? $instructions;
        $source = $client['core_text'] ?? '';
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $prompt = "Using the following source text:\n{$source}\n\nAnd the instructions:\n{$instr}\nGenerate only a website sitemap with at most {$num} pages and one optional level of subpages. Output each entry on a separate line as 'Page' or 'Parent > Child'. Do not include numbering or any other commentary.";
        $payload = json_encode([
            'contents' => [[ 'parts' => [['text' => $prompt]] ]]
        ]);
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-goog-api-key: ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = 'API request failed: ' . curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($response, true);
            if ($code >= 400 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? $response;
                $error = 'API error: ' . $msg;
            } elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $lines = preg_split('/\r?\n/', $json['candidates'][0]['content']['parts'][0]['text']);
                $tree = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $line = preg_replace('/^[\d\-\.\)]+\s*/', '', $line);
                    if (strpos($line, '>') !== false) {
                        list($parent, $child) = array_map('trim', explode('>', $line, 2));
                        $found = false;
                        foreach ($tree as &$node) {
                            if (strcasecmp($node['title'], $parent) === 0) {
                                $node['children'][] = ['title' => $child, 'children' => []];
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $tree[] = ['title' => $parent, 'children' => [['title' => $child, 'children' => []]]];
                        }
                    } else {
                        $tree[] = ['title' => $line, 'children' => []];
                    }
                    if (count($tree) >= $num) break;
                }
                $sitemap = $tree;
                $pdo->prepare('UPDATE clients SET sitemap = ? WHERE id = ?')->execute([json_encode($sitemap), $client_id]);
                $saved = 'Sitemap regenerated.';
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
    } elseif (isset($_POST['generate_content'])) {
        $page = $_POST['page'] ?? '';
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $prompt = "Write a full {$page} page with Hero, About Us, Services, and Contact sections. Each section should have a title and subtitle. Base the content on the following source text:\n\n" . $client['core_text'];
        $payload = json_encode([
            'contents' => [[ 'parts' => [['text' => $prompt]] ]]
        ]);
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-goog-api-key: ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = 'API request failed: ' . curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($response, true);
            if ($code >= 400 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? $response;
                $error = 'API error: ' . $msg;
            } elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $output = $json['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
    }
}

function renderList(array $items) {
    foreach ($items as $item) {
        $title = htmlspecialchars($item['title']);
        echo "<li class='list-group-item'><div class='d-flex justify-content-between'><span class='item-title'>{$title}</span><span><button type='button' class='btn btn-sm btn-link add-child'>+</button><button type='button' class='btn btn-sm btn-link text-danger remove'>x</button></span></div><ul class='list-group ms-3 children'>";
        if (!empty($item['children'])) {
            renderList($item['children']);
        }
        echo "</ul></li>";
    }
}

$title = 'Wordprseo Website Builder';
require __DIR__ . '/../header.php';
?>
<h1>Wordprseo Website Builder</h1>
<p><a href="index.php">&laquo; Back to clients</a></p>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="mb-3">
  <label class="form-label">Instructions</label>
  <textarea name="instructions" class="form-control" rows="6"><?= htmlspecialchars($instructions) ?></textarea>
  <div class="mt-2">
    <button type="submit" name="save_instructions" class="btn btn-secondary">Save Instructions</button>
  </div>
</form>
<ul class="nav nav-tabs" id="builderTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sitemapTab" type="button" role="tab">Site Map</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#contentTab" type="button" role="tab">Content</button>
  </li>
</ul>
<div class="tab-content border border-top-0 p-3">
  <div class="tab-pane fade show active" id="sitemapTab" role="tabpanel">
    <form method="post" id="sitemapForm">
      <div class="mb-3">
        <label class="form-label">Number of pages</label>
        <input type="number" name="num_pages" class="form-control" value="<?= count($sitemap) ?: 4 ?>">
      </div>
      <ul id="sitemapRoot" class="list-group mb-3">
        <?php renderList($sitemap); ?>
      </ul>
      <div class="input-group mb-3">
        <input type="text" id="newPage" class="form-control" placeholder="New page name">
        <button class="btn btn-secondary" type="button" id="addPage">Add Page</button>
      </div>
      <input type="hidden" name="sitemap_json" id="sitemapData">
      <button type="submit" name="save_sitemap" class="btn btn-primary">Save Site Map</button>
      <button type="submit" name="regenerate" class="btn btn-outline-secondary">Regenerate</button>
    </form>
  </div>
  <div class="tab-pane fade" id="contentTab" role="tabpanel">
    <form method="post" id="generatorForm" class="mt-3">
      <div class="mb-3">
        <label class="form-label">Page:</label>
        <select name="page" class="form-select">
<?php
function flattenPages(array $items, array &$list) {
    foreach ($items as $item) {
        $list[] = $item['title'];
        if (!empty($item['children'])) flattenPages($item['children'], $list);
    }
}
$pages = [];
flattenPages($sitemap, $pages);
foreach ($pages as $p) {
    $esc = htmlspecialchars($p);
    echo "<option value='{$esc}'>{$esc}</option>";
}
?>
        </select>
      </div>
      <button type="submit" name="generate_content" class="btn btn-primary">Generate</button>
      <div id="progress" class="progress mt-3 d-none">
        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
      </div>
    </form>
    <?php if ($output): ?>
      <h2 class="mt-3">Generated Content</h2>
      <div class="mt-3"><?= $output ?></div>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function initSortables() {
  document.querySelectorAll('#sitemapRoot, #sitemapRoot .children').forEach(function(el){
    Sortable.create(el, { group: 'pages', animation:150 });
  });
}
function createItem(name){
  const li=document.createElement('li');
  li.className='list-group-item';
  li.innerHTML="<div class='d-flex justify-content-between'><span class='item-title'></span><span><button type='button' class='btn btn-sm btn-link add-child'>+</button><button type='button' class='btn btn-sm btn-link text-danger remove'>x</button></span></div><ul class='list-group ms-3 children'></ul>";
  li.querySelector('.item-title').textContent=name;
  return li;
}
const root=document.getElementById('sitemapRoot');
initSortables();
root.addEventListener('click',function(e){
  if(e.target.classList.contains('add-child')){
    const ul=e.target.closest('li').querySelector('.children');
    const name=prompt('Subpage name?');
    if(name){
      ul.appendChild(createItem(name));
      initSortables();
    }
  } else if(e.target.classList.contains('remove')){
    e.target.closest('li').remove();
  }
});
document.getElementById('addPage').addEventListener('click',function(){
  const name=document.getElementById('newPage').value.trim();
  if(name){
    root.appendChild(createItem(name));
    document.getElementById('newPage').value='';
    initSortables();
  }
});
document.getElementById('sitemapForm').addEventListener('submit',function(){
  function serialize(ul){
    return Array.from(ul.children).map(li=>({
      title: li.querySelector('.item-title').textContent.trim(),
      children: serialize(li.querySelector('.children'))
    }));
  }
  document.getElementById('sitemapData').value=JSON.stringify(serialize(root));
});
const generatorForm=document.getElementById('generatorForm');
if(generatorForm){
  generatorForm.addEventListener('submit',function(){
    document.getElementById('progress').classList.remove('d-none');
  });
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>
