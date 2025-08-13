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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_structure'])) {
    $page = $_POST['page'] ?? '';
    $openPage = $page;
    $structure = $_POST['page_structure'] ?? '[]';
    $stmt = $pdo->prepare('INSERT INTO client_structures (client_id, page, structure) VALUES (?,?,?) ON DUPLICATE KEY UPDATE structure=VALUES(structure)');
    $stmt->execute([$client_id, $page, $structure]);
    $pageData[$page] = json_decode($structure, true) ?: [];
    $saved = 'Page structure saved.';
}

$sitemap = $client['sitemap'] ? json_decode($client['sitemap'], true) : [];
function flattenPages(array $items, array &$list) {
    foreach ($items as $it) {
        $list[] = $it['title'];
        if (!empty($it['children'])) flattenPages($it['children'], $list);
    }
}
$pages = [];
flattenPages($sitemap, $pages);
$sectionOptions = ['accordion','articledescription','articleimages','articletitle','articlevideo','articlevideogallery','catconnection','postconnection','tagconnection','contacts','herovideo','imageslider','imagecarousel','imgcarousel','pagecontent1','pagecontent2','pagecontent3','pagecontent4','pagecontent5','pagecontent6','pagecontent7','pagecontent8','pagecontent9','postsrelatedcat','postsrelatedcatslider','postsrelatedwithfilter','slider','tagslist','testimonial','verticaltabs'];

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
          <div id="addSectionContainer-<?= $slug ?>" class="mb-2">
            <button type="button" class="btn btn-secondary btn-sm show-add-section">Add Section</button>
            <div class="input-group mt-2 d-none add-section-form">
              <select class="form-select form-select-sm section-select">
                <?php foreach ($sectionOptions as $opt): ?>
                  <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-success btn-sm add-section-confirm" type="button">Add</button>
            </div>
          </div>
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
