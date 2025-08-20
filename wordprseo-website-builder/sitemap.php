<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/file_utils.php';
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

$error = '';
$saved = '';
$generated = '';
$activeTab = $_GET['tab'] ?? 'source';
if (!in_array($activeTab, ['source','sitemap'])) {
    $activeTab = 'source';
}
$instructions = $client['instructions'] ?? '';
$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];

if ($sitemap) {
    $convert = function (&$items) use (&$convert) {
        foreach ($items as &$item) {
            if (($item['type'] ?? '') === 'cat') $item['type'] = 'category';
            if (!empty($item['children'])) $convert($item['children']);
        }
    };
    $convert($sitemap);
}

function buildTree(array $items, array &$seen, int $depth = 0): array {
    $result = [];
    foreach ($items as $item) {
        $title = trim($item['title'] ?? '');
        if ($title === '') continue;
        $key = strtolower($title);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $type = $item['type'] ?? 'page';
        if (!in_array($type, ['page','single','category','tag'])) $type = 'page';
        $node = ['title' => $title, 'type' => $type, 'children' => []];
        if ($depth < 1 && !empty($item['children']) && is_array($item['children'])) {
            $node['children'] = buildTree($item['children'], $seen, $depth + 1);
        }
        $result[] = $node;
    }
    return $result;
}

function flattenTitles(array $items, array &$list): void {
    foreach ($items as $it) {
        $title = $it['title'] ?? '';
        if ($title !== '') $list[] = $title;
        if (!empty($it['children'])) flattenTitles($it['children'], $list);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_core'])) {
        $text = $_POST['core_text'] ?? '';
        if (!empty($_FILES['source']['name'][0])) {
            $files = $_FILES['source'];
            $count = is_array($files['name']) ? count($files['name']) : 0;
            $errors = [];
            for ($i=0; $i<$count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $part = extractUploaded($files['tmp_name'][$i], $files['name'][$i], $errors);
                if ($part !== '') {
                    $text .= ($text ? "\n" : '') . $part;
                }
            }
            if ($errors) $error = implode(' ', $errors);
        }
        $pdo->prepare('UPDATE clients SET core_text = ? WHERE id = ?')->execute([$text, $client_id]);
        $client['core_text'] = $text;
        $saved = 'Source text saved.';
        $activeTab = 'source';
    } elseif (isset($_POST['save_sitemap'])) {
        $json = $_POST['sitemap_json'] ?? '[]';
        $newMap = json_decode($json, true) ?: [];
        $oldPages = [];
        flattenTitles($sitemap, $oldPages);
        $newPages = [];
        flattenTitles($newMap, $newPages);
        $removed = array_diff($oldPages, $newPages);
        if ($removed) {
            $placeholders = implode(',', array_fill(0, count($removed), '?'));
            $params = array_merge([$client_id], array_values($removed));
            $pdo->prepare("DELETE FROM client_structures WHERE client_id = ? AND page IN ($placeholders)")->execute($params);
            $pdo->prepare("DELETE FROM client_pages WHERE client_id = ? AND page IN ($placeholders)")->execute($params);
        }
        $pdo->prepare('UPDATE clients SET sitemap = ? WHERE id = ?')->execute([$json, $client_id]);
        $sitemap = $newMap;
        $saved = 'Sitemap saved.';
        $activeTab = 'sitemap';
    } elseif (isset($_POST['regenerate'])) {
        $source = $client['core_text'] ?? '';
        $instr = $instructions;
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $prompt = "Using the following source text:\n{$source}\n\nAnd the instructions:\n{$instr}\nGenerate a website sitemap as a JSON array. Limit the structure to two levels: top-level items with optional children only. Each item must contain 'title', 'type' (page, single, category, or tag) and optional 'children'. Ensure titles are unique. Return only JSON without any explanations.";
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
                $text = trim($json['candidates'][0]['content']['parts'][0]['text']);
                $start = strpos($text, '[');
                $end = strrpos($text, ']');
                if ($start !== false && $end !== false) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                $items = json_decode($text, true);
                if (is_array($items)) {
                    $seen = [];
                    $sitemap = buildTree($items, $seen);
                    $generated = 'Sitemap regenerated. Save to apply changes.';
                } else {
                    $error = 'Failed to parse sitemap JSON.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
        $activeTab = 'sitemap';
    }
}

function renderList(array $items, int $depth = 0) {
    foreach ($items as $item) {
        $title = htmlspecialchars($item['title']);
        $type = htmlspecialchars($item['type'] ?? 'page');
        echo "<li class='list-group-item py-1 bg-light' data-depth='{$depth}'><div class='d-flex align-items-center mb-1 bg-light'>";
        echo "<input type='text' class='form-control form-control-sm item-title-input me-2' value='{$title}'>";
        echo "<select class='form-select form-select-sm item-type me-2'>";
        echo "<option value='page'" . ($type==='page'?" selected":"") . ">Page</option>";
        echo "<option value='single'" . ($type==='single'?" selected":"") . ">Single</option>";
        echo "<option value='category'" . ($type==='category'?" selected":"") . ">Category</option>";
        echo "<option value='tag'" . ($type==='tag'?" selected":"") . ">Tag</option>";
        echo "</select><div class='btn-group btn-group-sm ms-2'>";
        if ($depth < 1) echo "<button type='button' class='btn btn-outline-secondary add-child'>+</button>";
        echo "<button type='button' class='btn btn-outline-danger remove'>x</button></div></div><ul class='list-group ms-3 children'>";
        if (!empty($item['children'])) renderList($item['children'], $depth + 1);
        echo "</ul></li>";
    }
}

$title = 'Wordprseo Content Builder';
require __DIR__ . '/../header.php';
?>
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab==='source'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#sourceTab" type="button" role="tab">Source</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab==='sitemap'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#sitemapTab" type="button" role="tab">Site Map</button>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" href="structure.php?client_id=<?= $client_id ?>">Structure</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" href="builder.php?client_id=<?= $client_id ?>">Content</a>
  </li>
</ul>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($generated): ?><div class="alert alert-info"><?= htmlspecialchars($generated) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="tab-content border border-top-0 p-3">
  <div class="tab-pane fade <?= $activeTab==='source'?'show active':'' ?>" id="sourceTab" role="tabpanel">
    <form method="post" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label">Upload Source Files</label>
        <input type="file" name="source[]" multiple class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label">Core Text</label>
        <textarea name="core_text" class="form-control" rows="10"><?= htmlspecialchars($client['core_text']) ?></textarea>
      </div>
      <button type="submit" name="save_core" class="btn btn-primary">Save</button>
    </form>
  </div>
  <div class="tab-pane fade <?= $activeTab==='sitemap'?'show active':'' ?>" id="sitemapTab" role="tabpanel">
    <form method="post" id="sitemapForm">
      <div class="p-3 rounded mb-3">
        <ul id="sitemapRoot" class="list-group mb-0">
          <?php renderList($sitemap); ?>
        </ul>
      </div>
      <div id="addPageContainer" class="mb-3">
        <button type="button" class="btn btn-secondary" id="showAddPage">Add Page</button>
        <div id="addPageForm" class="input-group mt-2 d-none">
          <input type="text" id="newPage" class="form-control" placeholder="New page name">
          <button class="btn btn-success" type="button" id="addPage">Add</button>
        </div>
      </div>
      <input type="hidden" name="sitemap_json" id="sitemapData">
      <button type="submit" name="save_sitemap" class="btn btn-primary">Save Site Map</button>
      <button type="submit" name="regenerate" class="btn btn-outline-secondary">Regenerate</button>
    </form>
  </div>
</div>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <script>
  function setDepth(li, depth){
    li.dataset.depth = depth;
    const addBtn = li.querySelector(':scope > div .add-child');
    if(addBtn) addBtn.style.display = depth >= 1 ? 'none' : '';
    li.querySelectorAll(':scope > .children > li').forEach(function(child){
      setDepth(child, depth + 1);
    });
  }
  function updateDepths(){
    document.querySelectorAll('#sitemapRoot > li').forEach(function(li){
      setDepth(li,0);
    });
  }
  function initSortables(){
    document.querySelectorAll('#sitemapRoot, #sitemapRoot .children').forEach(function(el){
      const s = Sortable.get(el);
      if (s) s.destroy();
    });
    Sortable.create(document.getElementById('sitemapRoot'),{group:'pages',animation:150,onEnd:updateDepths});
    document.querySelectorAll('#sitemapRoot > li > .children').forEach(function(el){
      Sortable.create(el,{group:'pages',animation:150,onEnd:updateDepths});
    });
  }
  function createItem(name){
    const li=document.createElement('li');
    li.className='list-group-item py-1 bg-light';
    li.innerHTML="<div class='d-flex align-items-center mb-1 bg-light'>"+
      "<input type='text' class='form-control form-control-sm item-title-input me-2'>"+
    "<select class='form-select form-select-sm item-type me-2'>"+
    "<option value='page'>Page</option>"+
    "<option value='single'>Single</option>"+
    "<option value='category'>Category</option>"+
    "<option value='tag'>Tag</option>"+
    "</select><div class='btn-group btn-group-sm ms-2'><button type='button' class='btn btn-outline-secondary add-child'>+</button><button type='button' class='btn btn-outline-danger remove'>x</button></div></div><ul class='list-group ms-3 children'></ul>";
  li.querySelector('.item-title-input').value=name;
  return li;
}
  initSortables();
  updateDepths();
  document.getElementById('showAddPage').addEventListener('click',function(){
    document.getElementById('addPageForm').classList.toggle('d-none');
  });
  document.getElementById('addPage').addEventListener('click',function(){
    const name=document.getElementById('newPage').value.trim();
    if(!name) return;
    document.getElementById('sitemapRoot').appendChild(createItem(name));
    document.getElementById('newPage').value='';
    initSortables();
    updateDepths();
  });
  document.getElementById('sitemapRoot').addEventListener('click',function(e){
    if(e.target.classList.contains('add-child')){
      const ul=e.target.closest('li').querySelector('.children');
      ul.appendChild(createItem('New page'));
      initSortables();
      updateDepths();
    } else if(e.target.classList.contains('remove')){
      e.target.closest('li').remove();
      updateDepths();
    }
  });
document.getElementById('sitemapForm').addEventListener('submit', function () {
  updateDepths();
  document.getElementById('sitemapData').value = JSON.stringify(serialize(document.getElementById('sitemapRoot'), 0));
});
function serialize(ul, depth = 0) {
  const items = [];
  ul.querySelectorAll(':scope > li').forEach(function (li) {
    const title = li.querySelector('.item-title-input').value.trim();
    const type = li.querySelector('.item-type').value;
    let children = [];
    if (depth < 1) {
      children = serialize(li.querySelector('.children'), depth + 1);
    }
    items.push({ title: title, type: type, children: children });
  });
  return items;
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>