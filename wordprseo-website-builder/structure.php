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

the important thing try to don't chnage to mush in the structure of the default website since we spent alot of time to do, and also make the sections that cover the content only don't make things too long and repetative in 1 page, i don't mind to have 3/4 sections per page based on the content i provided in the instructions, i made the sections long in the above example because i have content source i can build from.

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