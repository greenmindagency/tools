<?php
$title = $title ?? 'Green Mind Tools';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 30px; margin-top: 50px; }
    img.logo { width: 80px; margin-bottom: 20px; }
  </style>
</head>
<body>
<nav class="navbar fixed-top navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Green Mind Tools</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <?php
        $dirs = array_filter(glob('*', GLOB_ONLYDIR));
        foreach ($dirs as $dir) {
          $navTitle = ucwords(str_replace('-', ' ', $dir));
          $active = strpos($_SERVER['REQUEST_URI'], $dir) !== false ? 'active' : '';
          echo "<li class='nav-item'><a class='nav-link $active' href='$dir/'>$navTitle</a></li>";
        }
        ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
  <div class="text-center mb-4">
    <img src="https://i.ibb.co/MyYRCxGx/Green-Mind-Agency-Logo-square.png" class="logo" alt="Green Mind Logo">
    <h2><?= htmlspecialchars($title) ?></h2>
  </div>
