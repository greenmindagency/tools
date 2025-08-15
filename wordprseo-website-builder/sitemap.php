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

$error = '';
$saved = '';
$generated = '';
$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];
$instructions = $client['instructions'] ?? '';

if ($sitemap) {
    $convert = function (&$items) use (&$convert) {
        foreach ($items as &$item) {
            if (($item['type'] ?? '') === 'cat') $item['type'] = 'category';
            if (!empty($item['children'])) $convert($item['children']);
        }
    };
    $convert($sitemap);
}

function buildTree(array $items, int &$count, int $max, array &$seen): array {
    $result = [];
    foreach ($items as $item) {
        if ($count >= $max) break;
        $title = trim($item['title'] ?? '');
        if ($title === '') continue;
        $key = strtolower($title);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $type = $item['type'] ?? 'page';
        if (!in_array($type, ['page','single','category','tag'])) $type = 'page';
        $node = ['title' => $title, 'type' => $type, 'children' => []];
        $count++;
        if (!empty($item['children']) && is_array($item['children'])) {
            $node['children'] = buildTree($item['children'], $count, $max, $seen);
        }
        $result[] = $node;
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_sitemap'])) {
        $json = $_POST['sitemap_json'] ?? '[]';
        $pdo->prepare('UPDATE clients SET sitemap = ? WHERE id = ?')->execute([$json, $client_id]);
        $sitemap = json_decode($json, true);
        $saved = 'Sitemap saved.';
    } elseif (isset($_POST['regenerate'])) {
        $num = (int)($_POST['num_pages'] ?? 4);
        $source = $client['core_text'] ?? '';
        $instr = $instructions;
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $prompt = "Using the following source text:\n{$source}\n\nAnd the instructions:\n{$instr}\nGenerate a website sitemap as a JSON array. Each item must contain 'title', 'type' (page, single, category, or tag) and optional 'children'. Ensure titles are unique and the total number of items including subpages does not exceed {$num}. Return only JSON without any explanations.";
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
                    $count = 0;
                    $sitemap = buildTree($items, $count, $num, $seen);
                    $generated = 'Sitemap regenerated. Save to apply changes.';
                } else {
                    $error = 'Failed to parse sitemap JSON.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
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
        if ($depth < 1) {
            echo "<button type='button' class='btn btn-outline-secondary add-child'>+</button>";
        }
        echo "<button type='button' class='btn btn-outline-danger remove'>x</button></div></div><ul class='list-group ms-3 children'>";
        if ($depth < 1 && !empty($item['children'])) renderList($item['children'], $depth + 1);
        echo "</ul></li>";
    }
}

function countPages(array $items): int {
    $c = 0;
    foreach ($items as $item) {
        $c++;
        if (!empty($item['children'])) $c += countPages($item['children']);
    }
    return $c;
}

$title = 'Wordprseo Content Builder';
require __DIR__ . '/../header.php';
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link" href="source.php?client_id=<?= $client_id ?>">Source</a>
  </li>
  <li class="nav-item">
    <a class="nav-link active" href="#">Site Map</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="structure.php?client_id=<?= $client_id ?>">Structure</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="content.php?client_id=<?= $client_id ?>">Content</a>
  </li>
</ul>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($generated): ?><div class="alert alert-info"><?= htmlspecialchars($generated) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" id="sitemapForm" class="border p-3">
  <div class="mb-3">
    <label class="form-label">Number of pages</label>
    <input type="number" name="num_pages" class="form-control" value="<?= countPages($sitemap) ?: 4 ?>">
  </div>
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
  function setDepth(li, depth){
    li.dataset.depth = depth;
    const btnGroup = li.querySelector('.btn-group');
    let addBtn = btnGroup.querySelector('.add-child');
    if(depth >= 1){
      if(addBtn) addBtn.remove();
    } else {
      if(!addBtn){
        addBtn = document.createElement('button');
        addBtn.type='button';
        addBtn.className='btn btn-outline-secondary add-child';
        addBtn.textContent='+';
        btnGroup.insertBefore(addBtn, btnGroup.firstChild);
      }
    }
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
    document.querySelectorAll('#sitemapRoot, #sitemapRoot > li > .children').forEach(function(el){
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
document.getElementById('sitemapForm').addEventListener('submit',function(){
  updateDepths();
  document.getElementById('sitemapData').value=JSON.stringify(serialize(document.getElementById('sitemapRoot')));
});
function serialize(ul, depth=0){
  const items=[];
  ul.querySelectorAll(':scope > li').forEach(function(li){
    const title=li.querySelector('.item-title-input').value.trim();
    const type=li.querySelector('.item-type').value;
    let children=[];
    if(depth < 1){
      children=serialize(li.querySelector('.children'), depth+1);
    }
    items.push({title:title,type:type,children:children});
  });
  return items;
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>

