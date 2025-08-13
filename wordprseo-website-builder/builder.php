<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/file_utils.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
// ensure table for page contents exists
$pdo->exec("CREATE TABLE IF NOT EXISTS client_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    page VARCHAR(255) NOT NULL,
    content LONGTEXT,
    UNIQUE KEY client_page (client_id, page)
)");

$client_id = (int)($_GET['client_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    header('Location: index.php');
    exit;
}
$stmt = $pdo->prepare('SELECT page, content FROM client_pages WHERE client_id = ?');
$stmt->execute([$client_id]);
$pageData = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pageData[$row['page']] = $row['content'];
}
$error = '';
$saved = '';
$generated = '';
$activeTab = 'source';
$openPage = '';
$instructions = $client['instructions'] ?? '';
$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];
// Page-specific generation instructions.
$pageInstructions = [
    // 'Home' => 'INSTRUCTIONS HERE',
];
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
        $pdo->prepare('UPDATE clients SET sitemap = ? WHERE id = ?')->execute([$json, $client_id]);
        $sitemap = json_decode($json, true);
        $saved = 'Sitemap saved.';
        $activeTab = 'sitemap';
    } elseif (isset($_POST['regenerate'])) {
        $num = (int)($_POST['num_pages'] ?? 4);
        $instr = $instructions;
        $source = $client['core_text'] ?? '';
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
        $activeTab = 'sitemap';
    } elseif (isset($_POST['generate_page'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $pageInstr = $pageInstructions[$page] ?? '';
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nInstructions:\n{$instructions}\n{$pageInstr}\nWrite content for the {$page} page.";
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
                $pageData[$page] = $json['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
        $activeTab = 'content';
    } elseif (isset($_POST['save_page'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $content = $_POST['page_content'] ?? '';
        $stmt = $pdo->prepare('INSERT INTO client_pages (client_id, page, content) VALUES (?,?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
        $stmt->execute([$client_id, $page, $content]);
        $pageData[$page] = $content;
        $saved = 'Page content saved.';
        $activeTab = 'content';
    }
}

function renderList(array $items) {
    foreach ($items as $item) {
        $title = htmlspecialchars($item['title']);
        $type = htmlspecialchars($item['type'] ?? 'page');
        echo "<li class='list-group-item py-1 bg-light'><div class='d-flex align-items-center mb-1 bg-light'>";
        echo "<input type='text' class='form-control form-control-sm item-title-input me-2' value='{$title}'>";
        echo "<select class='form-select form-select-sm item-type me-2'>";
        foreach (['page'=>'Page','single'=>'Single','category'=>'Category','tag'=>'Tag'] as $val => $label) {
            $sel = $type === $val ? 'selected' : '';
            echo "<option value='{$val}' {$sel}>{$label}</option>";
        }
        echo "</select><span><button type='button' class='btn btn-sm btn-link add-child'>+</button><button type='button' class='btn btn-sm btn-link text-danger remove'>x</button></span></div><ul class='list-group ms-3 children'>";
        if (!empty($item['children'])) {
            renderList($item['children']);
        }
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

$title = 'Wordprseo Website Builder';
require __DIR__ . '/../header.php';
?>
<h1>Wordprseo Website Builder</h1>
<p><a href="index.php">&laquo; Back to clients</a></p>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($generated): ?><div class="alert alert-info"><?= htmlspecialchars($generated) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<ul class="nav nav-tabs" id="builderTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab==='source'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#sourceTab" type="button" role="tab">Source</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab==='sitemap'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#sitemapTab" type="button" role="tab">Site Map</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab==='content'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#contentTab" type="button" role="tab">Content</button>
  </li>
</ul>
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
  </div>
  <div class="tab-pane fade <?= $activeTab==='content'?'show active':'' ?>" id="contentTab" role="tabpanel">
    <?php
    function flattenPages(array $items, array &$list) {
        foreach ($items as $item) {
            $list[] = $item['title'];
            if (!empty($item['children'])) flattenPages($item['children'], $list);
        }
    }
    $pages = [];
    flattenPages($sitemap, $pages);
    ?>
    <div class="accordion" id="contentAccordion">
    <?php foreach ($pages as $p) {
        $esc = htmlspecialchars($p);
        $content = $pageData[$p] ?? '';
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-', $p));
        $show = ($openPage === $p) ? ' show' : '';
        $collapsed = ($openPage === $p) ? '' : ' collapsed';
    ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="heading-<?= $slug ?>">
          <button class="accordion-button<?= $collapsed ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $slug ?>" aria-expanded="<?= $openPage === $p ? 'true' : 'false' ?>" aria-controls="collapse-<?= $slug ?>">
            <?= $esc ?>
          </button>
        </h2>
        <div id="collapse-<?= $slug ?>" class="accordion-collapse collapse<?= $show ?>" aria-labelledby="heading-<?= $slug ?>" data-bs-parent="#contentAccordion">
          <div class="accordion-body">
            <form method="post" class="page-form" id="form-<?= $slug ?>">
              <input type="hidden" name="page" value="<?= $esc ?>">
              <input type="hidden" name="page_content" id="input-<?= $slug ?>">
              <div class="mb-2">
                <div class="border p-2" id="display-<?= $slug ?>" contenteditable="true"><?= $content ?></div>
              </div>
              <button type="submit" name="generate_page" class="btn btn-secondary btn-sm me-2">Generate</button>
              <button type="submit" name="save_page" class="btn btn-primary btn-sm">Save</button>
            </form>
          </div>
        </div>
      </div>
    <?php } ?>
    </div>
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
  li.className='list-group-item py-1 bg-light';
  li.innerHTML="<div class='d-flex align-items-center mb-1 bg-light'>"+
    "<input type='text' class='form-control form-control-sm item-title-input me-2'>"+
    "<select class='form-select form-select-sm item-type me-2'>"+
      "<option value='page'>Page</option>"+
      "<option value='single'>Single</option>"+
      "<option value='category'>Category</option>"+
      "<option value='tag'>Tag</option>"+
    "</select><span><button type='button' class='btn btn-sm btn-link add-child'>+</button><button type='button' class='btn btn-sm btn-link text-danger remove'>x</button></span></div><ul class='list-group ms-3 children'></ul>";
  li.querySelector('.item-title-input').value=name;
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
document.getElementById('showAddPage').addEventListener('click',function(){
  document.getElementById('addPageForm').classList.toggle('d-none');
  document.getElementById('newPage').focus();
});
document.getElementById('addPage').addEventListener('click',function(){
  const name=document.getElementById('newPage').value.trim();
  if(name){
    root.appendChild(createItem(name));
    document.getElementById('newPage').value='';
    document.getElementById('addPageForm').classList.add('d-none');
    initSortables();
  }
});
document.getElementById('sitemapForm').addEventListener('submit',function(){
  function serialize(ul){
    return Array.from(ul.children).map(li=>({
      title: li.querySelector('.item-title-input').value.trim(),
      type: li.querySelector('.item-type').value,
      children: serialize(li.querySelector('.children'))
    }));
  }
  document.getElementById('sitemapData').value=JSON.stringify(serialize(root));
});

document.querySelectorAll('.page-form').forEach(function(f){
  f.addEventListener('submit', function(){
    const id = this.id.replace('form-','');
    const display = document.getElementById('display-'+id);
    document.getElementById('input-'+id).value = display.innerHTML;
  });
});
</script>
<?php include __DIR__ . '/../footer.php'; ?>
