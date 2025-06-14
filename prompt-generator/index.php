<?php
// Auto-generate menu and cards from this directory
$descriptionMap = [
    'clustering.html' => 'Group keywords into focused clusters for better planning.',
    'content-prompt-generator.html' => 'Quickly build content prompts optimized for search engines.',
    'keyword-structuring-tool.html' => 'Break keyword groups into useful variations.',
    'longtail-generator.html' => 'Expand your list with relevant longtail phrases.'
];
$files = array_merge(glob('*.php'), glob('*.html'));
$tools = [];
foreach ($files as $file) {
    if (basename($file) === 'index.php') continue;
    $title = ucwords(str_replace('-', ' ', pathinfo($file, PATHINFO_FILENAME)));
    $description = $descriptionMap[$file] ?? 'Tool for ' . strtolower($title);
    $tools[] = ['file' => $file, 'title' => $title, 'description' => $description];
}
usort($tools, fn($a, $b) => strcmp($a['title'], $b['title']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Green Mind Tools</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 30px; margin-top: 50px; }
    img.logo { width: 80px; margin-bottom: 20px; }
  </style>
</head>
<body>
<nav class="navbar fixed-top navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Green Mind</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <?php foreach ($tools as $tool): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= $tool['file'] ?>"><?= $tool['title'] ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
  <div class="text-center mb-4">
    <img src="https://i.ibb.co/MyYRCxGx/Green-Mind-Agency-Logo-square.png" class="logo" alt="Green Mind Logo">
    <h2>Green Mind Tools</h2>
    <p>Select from our collection of utilities.</p>
  </div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
