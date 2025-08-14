<?php
session_start();
require __DIR__ . '/config.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// ensure table for storing page structures exists
$pdo->exec("CREATE TABLE IF NOT EXISTS client_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    page VARCHAR(255) NOT NULL,
    structure LONGTEXT,
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

// load saved structures
$stmt = $pdo->prepare('SELECT page, structure FROM client_structures WHERE client_id = ?');
$stmt->execute([$client_id]);
$pageData = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pageData[$row['page']] = json_decode($row['structure'], true) ?: [];
}

$saved = '';
$openPage = '';
$error = '';
$generated = '';

$sectionOptions = ['accordion','articledescription','articleimages','articletitle','articlevideo','articlevideogallery','catconnection','postconnection','tagconnection','contacts','herovideo','imageslider','imagecarousel','imgcarousel','pagecontent1','pagecontent2','pagecontent3','pagecontent4','pagecontent5','pagecontent6','pagecontent7','pagecontent8','pagecontent9','postsrelatedcat','postsrelatedcatslider','postsrelatedwithfilter','slider','tagslist','testimonial','verticaltabs'];

$defaultPageInstr = <<<TXT

I need to create an wordprseo structure for the page please

what i need from you is to follow below sections you have to make pages appeal and have varities of sections, usually make a variations to make things looks nice 

you can chosse from below:

accordion,articledescription,articleimages,articletitle,articlevideo,articlevideogallery,catconnection,postconnection,tagconnection,contacts,herovideo,imageslider,imagecarousel,imgcarousel,pagecontent1,pagecontent2,pagecontent3,pagecontent4,pagecontent5,pagecontent6,pagecontent7,pagecontent8,pagecontent9,postsrelatedcat,postsrelatedcatslider,postsrelatedwithfilter,slider,tagslist,testimonial,verticaltabs

the default template have the below structure just for your info (don't copy paste):

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

$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_structure'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
        $optionsStr = implode(', ', $sectionOptions);
        $prompt = "Using the following source text:\n{$client['core_text']}\n\nInstructions:\n{$defaultPageInstr}\nProvide a comma-separated list of section identifiers for the {$page} page using only these options: {$optionsStr}. Return only the list.";
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
                $items = array_filter(array_map(function($s) use ($sectionOptions) {
                    $slug = strtolower(preg_replace('/[^a-z0-9]/','', $s));
                    return in_array($slug, $sectionOptions) ? $slug : null;
                }, preg_split('/[,\n]/', $text)));
                if ($items) {
                    $pageData[$page] = array_values($items);
                    $generated = 'Structure generated. Review before saving.';
                } else {
                    $error = 'Failed to parse sections.';
                }
            } else {
                $error = 'Unexpected API response.';
            }
        }
        curl_close($ch);
    } elseif (isset($_POST['save_structure'])) {
        $page = $_POST['page'] ?? '';
        $openPage = $page;
        $structure = $_POST['page_structure'] ?? '[]';
        $stmt = $pdo->prepare('INSERT INTO client_structures (client_id, page, structure) VALUES (?,?,?) ON DUPLICATE KEY UPDATE structure=VALUES(structure)');
        $stmt->execute([$client_id, $page, $structure]);
        $pageData[$page] = json_decode($structure, true) ?: [];
        $saved = 'Page structure saved.';
    }
}

function flattenPages(array $items, array &$list) {
    foreach ($items as $it) {
        $list[] = $it['title'];
        if (!empty($it['children'])) flattenPages($it['children'], $list);
    }
}
$pages = [];
flattenPages($sitemap, $pages);

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
    <a class="nav-link active" href="#">Structure</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="builder.php?client_id=<?= $client_id ?>">Content</a>
  </li>
</ul>

<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($generated): ?><div class="alert alert-info"><?= htmlspecialchars($generated) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="accordion" id="structureAccordion">
<?php foreach ($pages as $p):
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-', $p));
    $sections = $pageData[$p] ?? [];
    $show = ($openPage === $p) ? ' show' : '';
    $expanded = ($openPage === $p) ? 'true' : 'false';
?>
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading-<?= $slug ?>">
      <button class="accordion-button<?= $show ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $slug ?>" aria-expanded="<?= $expanded ?>" aria-controls="collapse-<?= $slug ?>">
        <?= htmlspecialchars($p) ?>
      </button>
    </h2>
    <div id="collapse-<?= $slug ?>" class="accordion-collapse collapse<?= $show ?>" aria-labelledby="heading-<?= $slug ?>" data-bs-parent="#structureAccordion">
      <div class="accordion-body">
        <form method="post" class="page-form" id="form-<?= $slug ?>">
          <input type="hidden" name="page" value="<?= htmlspecialchars($p) ?>">
          <input type="hidden" name="page_structure" id="input-<?= $slug ?>">
          <ul class="list-group mb-2 section-list" id="list-<?= $slug ?>">
            <?php foreach ($sections as $sec): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span><?= htmlspecialchars($sec) ?></span>
              <button type="button" class="btn btn-sm btn-link text-danger remove-section">x</button>
            </li>
            <?php endforeach; ?>
          </ul>
          <div id="addSectionContainer-<?= $slug ?>" class="mb-2 d-flex align-items-center gap-2">
            <button type="button" class="btn btn-secondary btn-sm show-add-section">Add Section</button>
            <div class="input-group input-group-sm d-none add-section-form" style="width:auto;">
              <select class="form-select form-select-sm section-select">
                <?php foreach ($sectionOptions as $opt): ?>
                  <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-success btn-sm add-section-confirm" type="button">Add</button>
            </div>
          </div>
          <button type="submit" name="generate_structure" class="btn btn-secondary btn-sm me-2">Generate</button>
          <button type="submit" name="save_structure" class="btn btn-primary btn-sm">Save</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.querySelectorAll('.section-list').forEach(function(el){
  Sortable.create(el,{animation:150});
});
document.querySelectorAll('.show-add-section').forEach(function(btn){
  btn.addEventListener('click',function(){
    const form = this.nextElementSibling;
    form.classList.toggle('d-none');
  });
});
document.querySelectorAll('.add-section-confirm').forEach(function(btn){
  btn.addEventListener('click',function(){
    const select = this.previousElementSibling;
    const val = select.value;
    if(!val) return;
    const ul = this.closest('.accordion-body').querySelector('.section-list');
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center';
    li.innerHTML = "<span>"+val+"</span><button type='button' class='btn btn-sm btn-link text-danger remove-section'>x</button>";
    ul.appendChild(li);
  });
});
document.querySelectorAll('.section-list').forEach(function(ul){
  ul.addEventListener('click',function(e){
    if(e.target.classList.contains('remove-section')){
      e.target.closest('li').remove();
    }
  });
});
document.querySelectorAll('.page-form').forEach(function(f){
  f.addEventListener('submit',function(){
    const id = this.id.replace('form-','');
    const ul = document.getElementById('list-'+id);
    const arr = Array.from(ul.querySelectorAll('li span')).map(s=>s.innerText.trim()).filter(Boolean);
    document.getElementById('input-'+id).value = JSON.stringify(arr);
  });
});
</script>
<?php include __DIR__ . '/../footer.php'; ?>