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

$sectionOptions = ['accordion','articleimages','articletitle','articlevideo','articlevideogallery','catconnection','postconnection','tagconnection','contacts','herovideo','imagesslider','imgcarousel','pagecontent1','pagecontent2','pagecontent3','pagecontent4','pagecontent5','pagecontent6','pagecontent7','pagecontent8','pagecontent9','postsrelatedcat','postsrelatedcatslider','postsrelatedwithfilter','slider','tagslist','testimonial','verticaltabs'];

$defaultPageInstr = <<<TXT

I need to create an wordprseo structure for the page please

what i need from you is to follow below sections you have to make pages appeal and have varities of sections, usually make a variations to make things looks nice 

you can chosse from below:

accordion,articleimages,articletitle,articlevideo,articlevideogallery,catconnection,postconnection,tagconnection,contacts,herovideo,imagesslider,imgcarousel,pagecontent1,pagecontent2,pagecontent3,pagecontent4,pagecontent5,pagecontent6,pagecontent7,pagecontent8,pagecontent9,postsrelatedcat,postsrelatedcatslider,postsrelatedwithfilter,slider,tagslist,testimonial,verticaltabs

Some instructions:

- If i have tags in the site map you can include if i haven't you should elemnate adding tags
- articletitle can be added only for sections didn't have titles and it will be added before these sections (articleimages, articlevideogallery,imagesslider,imgcarousel) please focue on this it's really important
- postsrelatedcat,postsrelatedcatslider,postsrelatedwithfilter can only be added if i have single pages under categories if not you have not to include them

the default template have the below structure just for your info (don't copy paste):

home page:
slider, tagslist, pagecontent2, pagecontent1, catconnection, pagecontent3, articletitle, articleimages, hero-video, articletitle, pagecontent7, postsrelatedcat, accordion, pagecontent5


about page:
slider, articletitle, pagecontent2, pagecontent1, catconnection, pagecontent4, articletitle, articleslideshow, pagecontent5

service category which has all services 
hero-video, tagslist, articletitle, articlevideogallery, postsrelatedtagslider, pagecontent3, verticaltabs, pagecontent7, pagecontent2, pagecontent5

for other categories like blog or news it has only 1 section postsrelatedcat and select the infinite checkbox


service tags (sub services) under the service category working as a subservices (tag)
slider, pagecontent1, tagconnection, pagecontent1, pagecontent3, articletitle, accordion, pagecontent5

careers page have the pagecontent5 only which is the form

contact us:  have the contacts section and pagecontent6 for the map

for any forms included pages like book appointment or book now or something like this it should be pagecontent5 which has the forms and a 2 or 3 sections below it.


the important thing try to be creative in the structure of page don't copy paste from default template try to produce something creative. i don't mind to have 3/4 sections per page based on the content i provided in the instructions, i made the sections long in the above example because i have content source i can build from.


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

<div class="row">
  <div class="col-md-5">
    <ul class="list-group position-sticky" style="top: 70px;">
      <?php foreach ($pages as $p): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center ps-2 page-item<?= ($openPage === $p['title']) ? ' active' : '' ?>" data-page="<?= htmlspecialchars($p['title']) ?>" style="padding-left: <?= $p['level'] * 15 ?>px;">
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

  <div class="col-md-7">
    <div id="sectionsContainer" class="mb-3"></div>
  </div>
</div>

<form id="actionForm" method="post" class="d-none"></form>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
var pageData = <?= json_encode($pageData) ?>;
var sectionOptions = <?= json_encode($sectionOptions) ?>;
var imgBase = 'https://wordprseo.greenmindagency.com/wp-content/themes/wordprseo/acf-images/';
var currentPage = <?= $openPage ? json_encode($openPage) : 'null' ?>;

function createSectionElement(sec){
  const div = document.createElement('div');
  div.className = 'section-item position-relative';
  const img = document.createElement('img');
  img.src = imgBase + sec + '.jpg';
  img.alt = sec;
  img.className = 'img-fluid';
  const del = document.createElement('button');
  del.type = 'button';
  del.className = 'btn btn-sm btn-danger position-absolute top-0 end-0 remove-section';
  del.textContent = 'x';
  del.addEventListener('click', () => div.remove());
  const add = document.createElement('button');
  add.type = 'button';
  add.className = 'btn btn-sm btn-success position-absolute top-0 start-0 add-before';
  add.textContent = '+';
  add.addEventListener('click', () => showAddMenu(div));
  div.append(img, add, del);
  return div;
}

function showAddMenu(refDiv){
  const prev = refDiv.previousElementSibling;
  if (prev && prev.classList.contains('add-menu')) {
    prev.remove();
    return;
  }
  document.querySelectorAll('.add-menu').forEach(m => m.remove());
  const menu = document.createElement('div');
  menu.className = 'add-menu mb-2 p-2 border bg-white position-relative';
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'btn btn-sm btn-danger position-absolute top-0 end-0';
  close.textContent = '\u00d7';
  close.addEventListener('click', () => menu.remove());
  const grid = document.createElement('div');
  grid.className = 'row row-cols-4 g-1 mt-2';
  sectionOptions.forEach(opt => {
    const col = document.createElement('div');
    col.className = 'col border';
    const img = document.createElement('img');
    img.src = imgBase + opt + '.jpg';
    img.alt = opt;
    img.className = 'img-fluid';
    img.style.cursor = 'pointer';
    img.addEventListener('click', () => {
      menu.replaceWith(createSectionElement(opt));
    });
    col.appendChild(img);
    grid.appendChild(col);
  });
  menu.append(close, grid);
  refDiv.parentNode.insertBefore(menu, refDiv);
}

function loadPage(page){
  currentPage = page;
  document.querySelectorAll('.page-item').forEach(li => {
    const isActive = li.dataset.page === page;
    li.classList.toggle('active', isActive);
    const btns = li.querySelector('.btn-group');
    if (btns) btns.classList.toggle('d-none', !isActive);
  });
  const container = document.getElementById('sectionsContainer');
  container.innerHTML = '';
  (pageData[page] || []).forEach(sec => container.appendChild(createSectionElement(sec)));
  if (!container.dataset.sortable) {
    Sortable.create(container, {animation:150});
    container.dataset.sortable = '1';
  }
}

document.querySelectorAll('.page-item').forEach(li => {
  li.addEventListener('click', function(e){
    if (e.target.classList.contains('generate-btn') || e.target.classList.contains('save-btn')) return;
    loadPage(this.dataset.page);
  });
  li.querySelector('.generate-btn').addEventListener('click', function(e){
    e.stopPropagation();
    submitAction(li.dataset.page, 'generate_structure');
  });
  li.querySelector('.save-btn').addEventListener('click', function(e){
    e.stopPropagation();
    submitAction(li.dataset.page, 'save_structure');
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
  const container = document.getElementById('sectionsContainer');
  const arr = Array.from(container.querySelectorAll('.section-item img')).map(img => img.alt);
  const structInput = document.createElement('input');
  structInput.type = 'hidden';
  structInput.name = 'page_structure';
  structInput.value = JSON.stringify(arr);
  form.appendChild(structInput);
  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = action;
  actionInput.value = '1';
  form.appendChild(actionInput);
  form.submit();
}

if (currentPage === null && Object.keys(pageData).length) {
  currentPage = Object.keys(pageData)[0];
}
if (currentPage) {
  loadPage(currentPage);
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>
