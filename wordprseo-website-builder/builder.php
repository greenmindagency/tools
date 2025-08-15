<?php
session_start();
require __DIR__ . '/config.php';
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
    $pageData[$row['page']] = json_decode($row['content'], true) ?: [];
}
$stmt = $pdo->prepare('SELECT page, structure FROM client_structures WHERE client_id = ?');
$stmt->execute([$client_id]);
$pageStructures = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $arr = json_decode($row['structure'], true) ?: [];
    $pageStructures[$row['page']] = array_values($arr);
}
$error = '';
$saved = '';
$generated = '';
$openPage = '';
$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];

// Placeholder instructions for each supported section.
$sectionInstructions = [
    'accordion' => 'Instructions for accordion',
    'articleimages' => 'Instructions for articleimages',
    'articletitle' => 'Instructions for articletitle',
    'articlevideo' => 'Instructions for articlevideo',
    'articlevideogallery' => 'Instructions for articlevideogallery',
    'catconnection' => 'Instructions for catconnection',
    'postconnection' => 'Instructions for postconnection',
    'tagconnection' => 'Instructions for tagconnection',
    'contacts' => 'Instructions for contacts',
    'herovideo' => 'Instructions for herovideo',
    'imagesslider' => 'Instructions for imagesslider',
    'imgcarousel' => 'Instructions for imgcarousel',
    'pagecontent1' => 'Instructions for pagecontent1',
    'pagecontent2' => 'Instructions for pagecontent2',
    'pagecontent3' => 'Instructions for pagecontent3',
    'pagecontent4' => 'Instructions for pagecontent4',
    'pagecontent5' => 'Instructions for pagecontent5',
    'pagecontent6' => 'Instructions for pagecontent6',
    'pagecontent7' => 'Instructions for pagecontent7',
    'pagecontent8' => 'Instructions for pagecontent8',
    'pagecontent9' => 'Instructions for pagecontent9',
    'postsrelatedcat' => 'Instructions for postsrelatedcat',
    'postsrelatedcatslider' => 'Instructions for postsrelatedcatslider',
    'postsrelatedwithfilter' => 'Instructions for postsrelatedwithfilter',
    'slider' => 'Instructions for slider',
    'tagslist' => 'Instructions for tagslist',
    'testimonial' => 'Instructions for testimonial',
    'verticaltabs' => 'Instructions for verticaltabs',
];

function sectionInstr(array $sections): string {
    global $sectionInstructions;
    $result = [];
    foreach ($sections as $s) {
        $key = strtolower($s);
        if (isset($sectionInstructions[$key])) {
            $result[] = "Section Name: {$s}\n" . $sectionInstructions[$key];
        }
    }
    return implode("\n", $result);
}

function sectionInstr(array $sections, string $instr): string {
    $parts = preg_split('/\nSection Name:\s*/', $instr);
    $map = [];
    for ($i = 1; $i < count($parts); $i++) {
        $block = $parts[$i];
        $lines = explode("\n", $block, 2);
        $name = strtolower(trim($lines[0]));
        $content = $lines[1] ?? '';
        $map[$name] = trim($content);
    }
    $result = [];
    foreach ($sections as $s) {
        $key = strtolower($s);
        if (isset($map[$key])) {
            $result[] = "Section Name: {$s}\n" . $map[$key];
        }
    }
    return implode("\n", $result);
}

if ($sitemap) {
    $convert = function (&$items) use (&$convert) {
        foreach ($items as &$item) {
            if (($item['type'] ?? '') === 'cat') $item['type'] = 'category';
            if (!empty($item['children'])) $convert($item['children']);
        }
    };
    $convert($sitemap);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_page'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $sections = $pageStructures[$page] ?? [];
        $sectionInstr = sectionInstr($sections);
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $sectionList = implode(', ', $sections);
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nSections: {$sectionList}\n\nInstructions:\n{$sectionInstr}\nGenerate JSON with keys: meta_title (<=60 chars), meta_description (110-140 chars), and sections (object mapping section name to HTML content using only <h3>, <h4>, and <p> tags). Provide non-empty content for every listed section. If unsure, add a brief placeholder paragraph. Return JSON only.";
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
                $text = $json['candidates'][0]['content']['parts'][0]['text'];
                $text = preg_replace('/^```\w*\n?|```$/m', '', $text);
                $start = strpos($text, '{');
                $end = strrpos($text, '}');
                if ($start !== false && $end !== false && $end >= $start) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                $res = json_decode($text, true);
                if ($res) {
                    $sectionContent = $res['sections'] ?? [];
                    foreach ($sections as $sec) {
                        $content = $sectionContent[$sec] ?? '';
                        if (is_array($content)) {
                            $content = $content['content'] ?? '';
                        }
                        if (!is_string($content) || !trim(strip_tags($content))) {
                            $content = '<p>Content pending...</p>';
                        }
                        $sectionContent[$sec] = $content;
                    }
                    $pageData[$page] = [
                        'meta_title' => $res['meta_title'] ?? '',
                        'meta_description' => $res['meta_description'] ?? '',
                        'sections' => $sectionContent
                    ];
                    $generated = 'Content generated. Review before saving.';
                } else {
                    $error = 'Failed to parse generated content.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
    } elseif (isset($_POST['generate_section'])) {
        $page = $_POST['page'] ?? '';
        $section = $_POST['section'] ?? '';
        $openPage = $page;
        $sectionInstr = sectionInstr([$section]);
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nSection: {$section}\n\nInstructions:\n{$sectionInstr}\nGenerate JSON with key 'content' containing HTML for the section using only <h3>, <h4>, and <p> tags. Provide non-empty content. Return JSON only.";
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
                $text = $json['candidates'][0]['content']['parts'][0]['text'];
                $text = preg_replace('/^```\w*\n?|```$/m', '', $text);
                $start = strpos($text, '{');
                $end = strrpos($text, '}');
                if ($start !== false && $end !== false && $end >= $start) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                $res = json_decode($text, true);
                if ($res && !empty($res['content'])) {
                    $content = $res['content'];
                    if (is_array($content)) {
                        $content = $content['content'] ?? '';
                    }
                    if (!isset($pageData[$page])) $pageData[$page] = ['meta_title' => '', 'meta_description' => '', 'sections' => []];
                    $pageData[$page]['sections'][$section] = $content;
                    $generated = 'Section regenerated.';
                } else {
                    $error = 'Failed to parse generated section.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
    } elseif (isset($_POST['save_page'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $content = $_POST['page_content'] ?? '';
        $stmt = $pdo->prepare('INSERT INTO client_pages (client_id, page, content) VALUES (?,?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
        $stmt->execute([$client_id, $page, $content]);
        $pageData[$page] = json_decode($content, true) ?: [];
        $saved = 'Page content saved.';
    }
}


$title = 'Wordprseo Content Builder';
require __DIR__ . '/../header.php';
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link" href="sitemap.php?client_id=<?= $client_id ?>&tab=source">Source</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="sitemap.php?client_id=<?= $client_id ?>&tab=sitemap">Site Map</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="structure.php?client_id=<?= $client_id ?>">Structure</a>
  </li>
  <li class="nav-item">
    <a class="nav-link active" href="#">Content</a>
  </li>
</ul>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($generated): ?><div class="alert alert-info"><?= htmlspecialchars($generated) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php
function flattenPages(array $items, array &$list, int $level = 0) {
    foreach ($items as $it) {
        $list[] = [
            'title' => $it['title'],
            'type' => $it['type'] ?? 'page',
            'level' => $level
        ];
        if (!empty($it['children'])) flattenPages($it['children'], $list, $level + 1);
    }
}
$pages = [];
flattenPages($sitemap, $pages);
?>
<div class="row">
  <div class="col-md-4">
    <ul class="list-group position-sticky" style="top: 70px;">
      <?php foreach ($pages as $p): ?>
      <?php $paddingClass = $p['level'] > 0 ? 'ps-4' : 'ps-2'; ?>
      <li class="list-group-item d-flex justify-content-between align-items-center <?= $paddingClass ?> page-item<?= ($openPage === $p['title']) ? ' active' : '' ?>" data-page="<?= htmlspecialchars($p['title']) ?>">
        <span>
          <?= htmlspecialchars($p['title']) ?>
          <span class="badge bg-secondary ms-1"><?= htmlspecialchars($p['type']) ?></span>
        </span>
        <span class="btn-group btn-group-sm d-none">
          <button type="button" class="btn btn-secondary generate-btn">Generate</button>
          <button type="button" class="btn btn-success save-btn">Save</button>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="col-md-8">
    <div id="contentContainer" class="mb-3"></div>
  </div>
</div>

<form id="actionForm" method="post" class="d-none"></form>

<script>
var pageData = <?= json_encode($pageData) ?>;
var pageStructures = <?= json_encode($pageStructures) ?>;
var currentPage = <?= $openPage ? json_encode($openPage) : 'null' ?>;

function sanitizeHtml(html){
  return String(html || '').replace(/<(?!\/?(h3|h4|p)\b)[^>]*>/gi, '');
}

function loadPage(page){
  currentPage = page;
  document.querySelectorAll('.page-item').forEach(li => {
    const isActive = li.dataset.page === page;
    li.classList.toggle('active', isActive);
    const btns = li.querySelector('.btn-group');
    if (btns) btns.classList.toggle('d-none', !isActive);
  });
  const container = document.getElementById('contentContainer');
  container.innerHTML = '';
  const data = pageData[page] || {};
  const metaTitle = document.createElement('input');
  metaTitle.type = 'text';
  metaTitle.id = 'metaTitle';
  metaTitle.maxLength = 60;
  metaTitle.className = 'form-control mb-2';
  metaTitle.placeholder = 'Meta Title (max 60 chars)';
  metaTitle.value = data.meta_title || '';
  const metaDesc = document.createElement('textarea');
  metaDesc.id = 'metaDescription';
  metaDesc.maxLength = 140;
  metaDesc.rows = 3;
  metaDesc.className = 'form-control mb-3';
  metaDesc.placeholder = 'Meta Description (110-140 chars)';
  metaDesc.value = data.meta_description || '';
  container.append(metaTitle, metaDesc);
  let sections = pageStructures[page] || [];
  if (!Array.isArray(sections)) {
    sections = Object.values(sections);
  }
  const secData = data.sections || {};
  sections.forEach(sec => {
    const wrap = document.createElement('div');
    wrap.className = 'd-flex justify-content-between align-items-center';
    const label = document.createElement('label');
    label.className = 'form-label mb-0';
    label.textContent = sec;
    const regen = document.createElement('button');
    regen.type = 'button';
    regen.className = 'btn btn-sm btn-outline-secondary regen-section';
    regen.dataset.section = sec;
    regen.innerHTML = '\u21bb';
    wrap.append(label, regen);
    const div = document.createElement('div');
    div.className = 'form-control mb-3 section-field';
    div.contentEditable = 'true';
    div.dataset.section = sec;
    div.style.minHeight = '6em';
    div.innerHTML = sanitizeHtml(secData[sec] || '');
    container.append(wrap, div);
  });
  container.querySelectorAll('.regen-section').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      submitAction(currentPage, 'generate_section', this.dataset.section);
    });
  });
}

document.querySelectorAll('.page-item').forEach(li => {
  li.addEventListener('click', function(e){
    if (e.target.classList.contains('generate-btn') || e.target.classList.contains('save-btn')) return;
    loadPage(this.dataset.page);
  });
  li.querySelector('.generate-btn').addEventListener('click', function(e){
    e.stopPropagation();
    submitAction(li.dataset.page, 'generate_page');
  });
  li.querySelector('.save-btn').addEventListener('click', function(e){
    e.stopPropagation();
    submitAction(li.dataset.page, 'save_page');
  });
});

function submitAction(page, action, section){
  const form = document.getElementById('actionForm');
  form.innerHTML = '';
  const pageInput = document.createElement('input');
  pageInput.type = 'hidden';
  pageInput.name = 'page';
  pageInput.value = page;
  form.appendChild(pageInput);
  if (action === 'save_page') {
    const obj = {
      meta_title: document.getElementById('metaTitle').value,
      meta_description: document.getElementById('metaDescription').value,
      sections: {}
    };
    document.querySelectorAll('.section-field').forEach(div => {
      obj.sections[div.dataset.section] = sanitizeHtml(div.innerHTML);
    });
    const contentInput = document.createElement('input');
    contentInput.type = 'hidden';
    contentInput.name = 'page_content';
    contentInput.value = JSON.stringify(obj);
    form.appendChild(contentInput);
  } else if (action === 'generate_section') {
    const secInput = document.createElement('input');
    secInput.type = 'hidden';
    secInput.name = 'section';
    secInput.value = section;
    form.appendChild(secInput);
  }
  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = action;
  actionInput.value = '1';
  form.appendChild(actionInput);
  form.submit();
}

if (currentPage === null) {
  const keys = Object.keys(pageData);
  currentPage = keys.length ? keys[0] : Object.keys(pageStructures)[0];
}
if (currentPage) {
  loadPage(currentPage);
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>