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
    $pageStructures[$row['page']] = json_decode($row['structure'], true) ?: [];
}
$error = '';
$saved = '';
$generated = '';
$openPage = '';
$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];
// Default page generation instructions.
$defaultPageInstr = <<<TXT

I need to create an SEO content for the page please start with Meta title (60 characters max don't add the client name), Meta description (from 110 to 140 characters max) 

please follow below guidelines on the length of each section and Structure

--- sections description and design:

Section Name: accordion

Layout Structure: Single-column full-width layout.

Title: One main section title at the top (recommended 3–6 words).

Subtitle: One subtitle directly under the main title (recommended 4–8 words).

Content Display: Accordion component with three collapsible panels.

Panels: Each panel has a clickable heading bar with a background color and title text (recommended 2–4 words).

Content Style: When a panel is expanded, it displays a short paragraph (recommended 25–40 words) or bullet list (3–5 bullet points, 4–7 words each) beneath the heading.

Number of Panels: Three total, can be up to 5.

Interaction: Only one panel is expanded at a time (likely configured via accordion behavior).

---

Section Name: article description

Layout Structure: Single-column full-width layout.

Heading: One section heading (recommended 2–5 words).

Paragraphs: 1–2 short paragraphs (each 20–35 words) describing the article or topic.

Additional Element: May include a subheading above the paragraphs (optional, 3–6 words).


---

Section Name: article images

Layout Structure: Single row with multiple columns (4–6 equal-width image blocks).

Images: Each block contains a single logo or image.

Text: No text overlay, images only.

Recommended Size: Consistent image dimensions for uniform alignment.

Section Name: article title

Layout Structure: Single-column full-width layout.

Heading: One main section title (recommended 2–5 words).

Subtitle: One subtitle directly under the title (recommended 5–8 words).

---

Section Name: article video

Layout Structure: Single-column full-width layout.

Content: Embedded video player.

Heading: Optional heading above the video (2–4 words).

Video Dimensions: Full-width or responsive container.

---

Section Name: article video gallery

Layout Structure: Two-column layout.

Content: Each column contains an embedded video player.

Number of Videos: 2–4 per section.

Video Dimensions: Equal height and width for consistent display.

---

Section Name: catconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the category page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 

---

Section Name: postconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the post  page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 

---

Section Name: tagconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the tag  page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 

---

Section Name: contacts

Layout Structure: Two-column layout.

Left Column: Contact information grouped by location, each with an icon for phone and address.

Right Column: Simple form with and submit button, this is loaded from the cf7.

Heading: 2–5 words.

Intro Text: One short paragraph (15–25 words).

---

Section Name: herovideo

Layout Structure: Full-width background video.

Text Overlay: Large title (4–8 words), short subtitle (6–10 words), and 1–2 call-to-action buttons.

Please suggest a video stocks photoage links from yoututbe that can be a background video.

---

Section Name: image slider

Layout Structure: Single-row image carousel.

Images: 4–6 logos or images per view.

Navigation: Left/right arrows.

---

Section Name: image carousel

Layout Structure: Full-width rotating image gallery.

Images: 1 large image visible at a time.

Navigation: Dots or arrows for switching.

---

Section Name: pagecontent1

Layout Structure: Single-column text block.

Heading: 4–6 words.

Paragraphs: 2–3 paragraphs (each 25–35 words).

Optional List: Bulleted list (3–5 items, 4–7 words each).

---

Section Name: pagecontent2

Layout Structure: Three equal-width columns.

Each Column: Icon (awesome font icon), number , and (2–3 words) reflecting the icon.

this section is show statstics number achivements, etc..

---

Section Name: pagecontent3


Layout Structure: Full-width background color with centered statistics.

Stats: 3–5 numerical highlights, each with an icon (awesome font icon), number, and label (2–4 words).

---



Section Name: pagecontent4

it can work for the team members or images left with content beside it 


strating with title 5 words and subtitle from 15 to 20 words

Layout Structure: Two-column layout, loop content.

Left Column: Image.

Right Column: related to each image has 1–2 short paragraphs (20–35 words each).

at the end a content ending the section can have a bullet list.

---


Section Name: pagecontent5

Layout Structure: 1-column

a content form section for ending pages can be transfered to a title/ subtitle and a call to action 1 or 2 buttons


---

Section Name: pagecontent6

Layout Structure: 1-column

a map location at the end above it title (5/6 words) and description 15 to 20 words.

---


Section Name: pagecontent7

Layout Structure: 2-columns

a section present % of success 

title (5/6 words) and content under it 15 to 20 words.

at the right a title and % this title showing a service or important figuer with % beside it, please make sure to have 3 items with 3 of % and titles

---


Section Name: pagecontent8

Layout Structure: Two-column layout.

Left Column: Full-height background image.

Right Column: Section title (4–7 words), subtitle (6–10 words), and accordion list.

Accordion Panels: 3 panels with headings (3–5 words each).

Content Style: When expanded, each panel shows a short paragraph (20–35 words).

Additional Text Block: Short paragraph (20–30 words) below accordion to highlight overall career mission.

Call-to-Action: One button (2–4 words) below text block.


---


Section Name: pagecontent9

Layout Structure: Single-column full-width layout with heading and subtitle centered at the top.

Heading: Large main heading (5–8 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: Three-column grid.

Each Column:

Image on top.

Title (2–4 words) overlay or below image.

Short paragraph (10–20 words).

Call-to-action link or button (2–4 words).

Number of Cards: 3 visible per row.

a hidden content will show up under the Short paragraph the lenght is 



---


Section Name: postsrelatedcat

Layout Structure: Single-column full-width layout.

Heading: Large main heading (2–4 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: Three-column grid, posts related from the category i'll select, no need to do anything, just mention if it should be,infinite loading, or have a button at the end linked to the category.

Number of Cards: 3 visible per row.

---


Section Name: postsrelatedcatslider

Layout Structure: 2-column width layout.

Heading: Large main heading (2–4 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: 1 grid carousel, posts related from the category i'll select, no need to do anything.

Number of Cards: 1 visible per row.

---


Section Name: postsrelatedwithfilter

Layout Structure: Single-column full-width layout.

Heading: Large main heading (3–5 words).

Subtitle: Short descriptive line (8–12 words).

Filters: Tag buttons above grid, please tell me the tags i should select that will filter the posts

---


Section Name: slider

Layout Structure: Full-width image slider.

Each Slide:

Background image.

Title (4–6 words).

Subtitle (6–10 words). 

from 3 to 5 slides make sure to suggest image keyword for search on images stocks websites

---


Section Name: tagslist

Layout Structure: Two-row, two-column grid.

Heading: Large main heading (3–5 words).

Subtitle: Short descriptive line (6–10 words).

Each Service Item: will be selected tags from admin, just mention the tags.


---


Section Name: testimonial

Layout Structure: Single-column full-width layout.

Heading: Large main heading (5–8 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: a testemenials number i'll select and it will show up form the single pages.

---


Section Name: verticaltabs

Layout Structure: Two-column layout.

Left Column: Vertical list of 3–6 tab buttons (3–6 words each).

Right Column:

Image on top make sure to suggest image keyword for search on images stocks websites.

Title (3–5 words).

1–2 short paragraphs (20–30 words each).


TXT;

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
        $pageInstr = $defaultPageInstr;
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $sectionList = implode(', ', $sections);
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nSections: {$sectionList}\n\nInstructions:\n{$pageInstr}\nGenerate JSON with keys: meta_title (<=60 chars), meta_description (110-140 chars), and sections (object mapping section name to HTML content using only <h3>, <h4>, and <p> tags). Return JSON only.";
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
                $res = json_decode($text, true);
                if ($res) {
                    $pageData[$page] = [
                        'meta_title' => $res['meta_title'] ?? '',
                        'meta_description' => $res['meta_description'] ?? '',
                        'sections' => $res['sections'] ?? []
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
  return html.replace(/<(?!\/?(h3|h4|p)\b)[^>]*>/gi, '');
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
  const sections = pageStructures[page] || [];
  const secData = data.sections || {};
  sections.forEach(sec => {
    const label = document.createElement('label');
    label.className = 'form-label';
    label.textContent = sec;
    const div = document.createElement('div');
    div.className = 'form-control mb-3 section-field';
    div.contentEditable = 'true';
    div.dataset.section = sec;
    div.style.minHeight = '6em';
    div.innerHTML = sanitizeHtml(secData[sec] || '');
    container.append(label, div);
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

function submitAction(page, action){
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
