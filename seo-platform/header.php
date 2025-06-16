<?php
$title = $title ?? 'SEO Platform';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { padding: 30px; margin-top: 50px; }
    img.logo { width: 50px; margin-right: 10px; }
    .sticky-controls {
      position: sticky;
      top: 70px;
      z-index: 100;
      background-color: #fff;
      padding: 8px 0;
    }
  </style>
</head>
<body>
<nav class="navbar fixed-top navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="/tools/">Green Mind Tools</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <?php
          $active = basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '';
          echo "<li class='nav-item'><a class='nav-link $active' href='index.php'>Clients</a></li>";
        ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
  <div class="d-flex align-items-center mb-2">
    <img src="https://i.ibb.co/MyYRCxGx/Green-Mind-Agency-Logo-square.png" class="logo me-2" alt="Green Mind Logo">
    <h2 class="mb-0"><?= htmlspecialchars($title) ?></h2>
  </div>
  <nav aria-label="breadcrumb" class="border-bottom container-fluid bg-light p-3 my-4">
    <ol class="breadcrumb mb-0">
      <?php
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (!empty($segments) && $segments[0] === 'tools') {
          array_shift($segments);
        }
        $segments = array_values(array_filter($segments, fn($s) => $s !== 'index.php'));

        echo '<li class="breadcrumb-item"><a href="/tools/">Home</a></li>';
        $link = '/tools';
        foreach ($segments as $index => $seg) {
          $link .= '/' . $seg;
          $name = ucwords(str_replace(['-', '.php'], [' ', ''], $seg));
          if ($index === count($segments) - 1) {
            echo "<li class='breadcrumb-item active' aria-current='page'>$name</li>";
          } else {
            echo "<li class='breadcrumb-item'><a href='$link/'>$name</a></li>";
          }
        }
      ?>
    </ol>
  </nav>
