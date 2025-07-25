<?php
$generator_files = glob(__DIR__ . '/prompt-generator/*.php');
$generators = [];
$converter_files = glob(__DIR__ . '/converter/*.php');
$converters = [];
foreach ($generator_files as $file) {
    $base = basename($file);
    if (in_array($base, ['index.php','header.php','footer.php'])) continue;
    $title = ucwords(str_replace('-', ' ', pathinfo($base, PATHINFO_FILENAME)));
    $generators[$base] = $title;
}
foreach ($converter_files as $file) {
    $base = basename($file);
    if (in_array($base, ['index.php','header.php','footer.php'])) continue;
    $title = ucwords(str_replace('-', ' ', pathinfo($base, PATHINFO_FILENAME)));
    $converters[$base] = $title;
}
$current = $_SERVER['REQUEST_URI'] ?? '';
if (session_status() === PHP_SESSION_NONE) session_start();
$loggedIn = isset($_SESSION['is_admin']) || isset($_SESSION['client_id']) || !empty($_SESSION['client_ids']);
?>
<nav class="navbar fixed-top navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="/tools/">Green Mind Tools</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <?php $active = strpos($current, '/prompt-generator/') !== false ? 'active' : ''; ?>
          <a class="nav-link dropdown-toggle <?= $active ?>" href="#" id="generatorsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Generators
          </a>
          <ul class="dropdown-menu" aria-labelledby="generatorsDropdown">
            <?php foreach ($generators as $file => $title): ?>
              <li><a class="dropdown-item" href="/tools/prompt-generator/<?= $file ?>"><?= $title ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
        <li class="nav-item dropdown">
          <?php $active = strpos($current, '/converter/') !== false ? 'active' : ''; ?>
          <a class="nav-link dropdown-toggle <?= $active ?>" href="#" id="convertersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Converters
          </a>
          <ul class="dropdown-menu" aria-labelledby="convertersDropdown">
            <?php foreach ($converters as $file => $title): ?>
              <li><a class="dropdown-item" href="/tools/converter/<?= $file ?>"><?= $title ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
        <li class="nav-item">
          <?php $active = strpos($current, '/ad-units-creator/') !== false ? 'active' : ''; ?>
          <a class="nav-link <?= $active ?>" href="/tools/ad-units-creator/">Ad Units Creator</a>
        </li>
        <li class="nav-item">
          <?php $active = strpos($current, '/seo-platform/') !== false ? 'active' : ''; ?>
          <a class="nav-link <?= $active ?>" href="/tools/seo-platform/login.php">SEO Platform</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="https://github.com/greenmindagency/tools/tree/main/code-snippets" target="_blank" rel="noopener">Code Snippets</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
