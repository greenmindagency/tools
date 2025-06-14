<?php
// Auto-generate menu and cards from directories
$descriptionMap = [
    'prompt-generator' => 'Collection of SEO prompt generation tools.'
];
$dirs = array_filter(glob('*', GLOB_ONLYDIR));
$tools = [];
foreach ($dirs as $dir) {
    $title = ucwords(str_replace('-', ' ', $dir));
    $description = $descriptionMap[$dir] ?? 'Tools for ' . strtolower($title);
    $tools[] = ['dir' => $dir, 'title' => $title, 'description' => $description];
}
usort($tools, fn($a, $b) => strcmp($a['title'], $b['title']));
$title = 'Green Mind Tools';
include 'header.php';
?>
<p>Select from our collection of utilities.</p>
<div class="row row-cols-1 row-cols-md-2 g-4">
    <?php foreach ($tools as $tool): ?>
    <div class="col">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">
            <a href="<?= $tool['dir'] ?>/" class="stretched-link text-decoration-none"><?= $tool['title'] ?></a>
          </h5>
          <p class="card-text"><?= $tool['description'] ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
</div>
<?php include 'footer.php'; ?>
