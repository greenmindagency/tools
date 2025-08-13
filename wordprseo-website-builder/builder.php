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
// Default page generation instructions.
$defaultPageInstr = <<<TXT

I need to create an SEO content for the page please start with Meta title (60 characters max don't add the client name), Meta description (from 110 to 140 characters max) 

what i need from you is to follow below sections you have to make pages appeal and have varities of sections, usually make a variations to make things looks nice 

the default template have the below structure that you can follow:

home page:
slider, tagslist, pagecontent2, pagecontent1, catconnection, slider, pagecontent3, articletitle, articleimages, postconnection, hero-video, articletitle, pagecontent7, postsrelatedcat, accordion, pagecontent5


about page:
slider, articletitle, pagecontent2, pagecontent1, catconnection, pagecontent4, tagconnection, postsrelatedcat, articletitle, articleslideshow, pagecontent5

service category which has all services 
hero-video|pagecontent5, tagslist, articletitle, articlevideogallery, postsrelatedtagslider, pagecontent1, pagecontent3, verticaltabs, pagecontent7, pagecontent4, pagecontent2, pagecontent5, catconnection, articletitle, articleimages, tagconnection, postsrelatedcat, articletitle, articlevideogallery, postconnection, pagecontent5

when you see | between 2 tabs means this is a 2 columns of tabs beside each other it can work only in hero video and pagecontent5

for other categories like blog or news it has only 1 section postsrelatedcat and select the infinite checkbox


service tags (sub services) under the service category working as a subservices (tag)
slider, pagecontent1, tagconnection, articletitle, pagecontent1, pagecontent3, pagecontent1, articletitle, articlevideogallery, accordion, articletitle, imgcarousel, pagecontent5


careers page have the pagecontent5 only which is the form

contact us have the contacts section and pagecontent6 for the map

start with the sitemap then step by step we will work on each page content based on below each section requeriments only, please also when you send me a sitemap make what the type if it will be cat, single etc..

the important thing try to don't chnage to mush in the structure of the default website since we spent alot of time to do, and also make the sections that cover the content only don't make things too long and repetative in 1 page, i don't mind to have 3/4 sections per page based on the content i provided in the instructions.



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



Section Name: article description

Layout Structure: Single-column full-width layout.

Heading: One section heading (recommended 2–5 words).

Paragraphs: 1–2 short paragraphs (each 20–35 words) describing the article or topic.

Additional Element: May include a subheading above the paragraphs (optional, 3–6 words).




Section Name: article images

Layout Structure: Single row with multiple columns (4–6 equal-width image blocks).

Images: Each block contains a single logo or image.

Text: No text overlay, images only.

Recommended Size: Consistent image dimensions for uniform alignment.

Section Name: article title

Layout Structure: Single-column full-width layout.

Heading: One main section title (recommended 2–5 words).

Subtitle: One subtitle directly under the title (recommended 5–8 words).



Section Name: article video

Layout Structure: Single-column full-width layout.

Content: Embedded video player.

Heading: Optional heading above the video (2–4 words).

Video Dimensions: Full-width or responsive container.



Section Name: article video gallery

Layout Structure: Two-column layout.

Content: Each column contains an embedded video player.

Number of Videos: 2–4 per section.

Video Dimensions: Equal height and width for consistent display.



Section Name: catconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the category page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 


Section Name: postconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the post  page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 


Section Name: tagconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the tag  page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 



Section Name: contacts

Layout Structure: Two-column layout.

Left Column: Contact information grouped by location, each with an icon for phone and address.

Right Column: Simple form with and submit button, this is loaded from the cf7.

Heading: 2–5 words.

Intro Text: One short paragraph (15–25 words).



Section Name: herovideo

Layout Structure: Full-width background video.

Text Overlay: Large title (4–8 words), short subtitle (6–10 words), and 1–2 call-to-action buttons.



Section Name: image slider

Layout Structure: Single-row image carousel.

Images: 4–6 logos or images per view.

Navigation: Left/right arrows.



Section Name: image carousel

Layout Structure: Full-width rotating image gallery.

Images: 1 large image visible at a time.

Navigation: Dots or arrows for switching.



Section Name: pagecontent1

Layout Structure: Single-column text block.

Heading: 4–6 words.

Paragraphs: 2–3 paragraphs (each 25–35 words).

Optional List: Bulleted list (3–5 items, 4–7 words each).



Section Name: pagecontent2

Layout Structure: Three equal-width columns.

Each Column: Icon (awesome font icon), heading (2–4 words), and short text (10–20 words).




Section Name: pagecontent3


Layout Structure: Full-width background color with centered statistics.

Stats: 3–5 numerical highlights, each with an icon (awesome font icon), number, and label (2–4 words).




Section Name: pagecontent4

it can work for the team members or images left with content beside it 


strating with title 5 words and subtitle from 15 to 20 words

Layout Structure: Two-column layout, loop content.

Left Column: Image.

Right Column: related to each image has 1–2 short paragraphs (20–35 words each).

at the end a content ending the section can have a bullet list.



Section Name: pagecontent5

Layout Structure: 1-column

a content form section for ending pages can be transfered to a title/ subtitle and a call to action 1 or 2 buttons


Section Name: pagecontent5

Layout Structure: 1-column

a content form section for ending pages can be transfered to a title/ subtitle and a call to action 1 or 2 buttons



Section Name: pagecontent6

Layout Structure: 1-column

a map location at the end above it title (5/6 words) and description 15 to 20 words.


Section Name: pagecontent7

Layout Structure: 2-columns

a section present % of success 

title (5/6 words) and content under it 15 to 20 words.

at the right a title and % this title showing a service or important figuer with % beside it


Section Name: pagecontent8

Layout Structure: Two-column layout.

Left Column: Full-height background image.

Right Column: Section title (4–7 words), subtitle (6–10 words), and accordion list.

Accordion Panels: 3 panels with headings (3–5 words each).

Content Style: When expanded, each panel shows a short paragraph (20–35 words).

Additional Text Block: Short paragraph (20–30 words) below accordion to highlight overall career mission.

Call-to-Action: One button (2–4 words) below text block.


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





Section Name: postsrelatedcat

Layout Structure: Single-column full-width layout.

Heading: Large main heading (2–4 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: Three-column grid, posts related from the category i'll select, no need to do anything, just mention if it should be,infinite loading, or have a button at the end linked to the category.

Number of Cards: 3 visible per row.



Section Name: postsrelatedcatslider

Layout Structure: 2-column width layout.

Heading: Large main heading (2–4 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: 1 grid carousel, posts related from the category i'll select, no need to do anything.

Number of Cards: 1 visible per row.



Section Name: postsrelatedwithfilter

Layout Structure: Single-column full-width layout.

Heading: Large main heading (3–5 words).

Subtitle: Short descriptive line (8–12 words).

Filters: Tag buttons above grid, please tell me the tags i should select that will filter the posts


Section Name: slider

Layout Structure: Full-width image slider.

Each Slide:

Background image.

Title (4–6 words).

Subtitle (6–10 words). 

from 3 to 5 slides make sure to suggest image keyword for search on images stocks websites



Section Name: tagslist

Layout Structure: Two-row, two-column grid.

Heading: Large main heading (3–5 words).

Subtitle: Short descriptive line (6–10 words).

Each Service Item: will be selected tags from admin, just mention the tags.




Section Name: testimonial

Layout Structure: Single-column full-width layout.

Heading: Large main heading (5–8 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: a testemenials number i'll select and it will show up form the single pages.



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
        $pageInstr = $defaultPageInstr;
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nInstructions:\n{$instructions}\n{$pageInstr}\nWrite HTML-formatted content for the {$page} page only. Include a main <h3> heading and paragraphs using <p> tags. Return HTML only.";
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
                // API may return HTML entities; decode to render actual HTML tags
                $pageData[$page] = trim(html_entity_decode($json['candidates'][0]['content']['parts'][0]['text']));
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
        $hasContent = trim($content) !== '';
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
              <div class="mb-2<?= $hasContent ? '' : ' d-none' ?>">
                <div class="border p-2" id="display-<?= $slug ?>" contenteditable="true" style="white-space: pre-wrap;">
                  <?= trim($content) ?>
                </div>
              </div>
              <button type="submit" name="generate_page" class="btn btn-secondary btn-sm me-2">Generate</button>
              <button type="submit" name="save_page" class="btn btn-primary btn-sm<?= $hasContent ? '' : ' d-none' ?>">Save</button>
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
