<?php
// Auto-generate menu and cards from this directory
$descriptionMap = [
    'clustering.php' => 'Group keywords into focused clusters for better planning.',
    'content-prompt-generator.php' => 'Quickly build content prompts optimized for search engines.',
    'keyword-structuring-tool.php' => 'Break keyword groups into useful variations.',
    'longtail-generator.php' => 'Expand your list with relevant longtail phrases.'
];
$files = glob('*.php');
$exclude = ['index.php', 'header.php', 'footer.php'];
$tools = [];
foreach ($files as $file) {
    if (in_array(basename($file), $exclude)) continue;
    $title = ucwords(str_replace('-', ' ', pathinfo($file, PATHINFO_FILENAME)));
    $description = $descriptionMap[$file] ?? 'Tool for ' . strtolower($title);
    $tools[] = ['file' => $file, 'title' => $title, 'description' => $description];
}
usort($tools, fn($a, $b) => strcmp($a['title'], $b['title']));
$title = 'Green Mind Prompt Generators';
include 'header.php';
?>
<p>Select from our collection of utilities.</p>
<div class="row row-cols-1 row-cols-md-2 g-4">
    <?php foreach ($tools as $tool): ?>
    <div class="col">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">
            <a href="<?= $tool['file'] ?>" class="stretched-link text-decoration-none"><?= $tool['title'] ?></a>
          </h5>
          <p class="card-text"><?= $tool['description'] ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'footer.php'; ?>
