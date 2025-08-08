<?php
$title = $title ?? 'Green Mind Tools';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 30px; margin-top: 50px; }
    img.logo { width: 50px; margin-right: 10px; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../nav.php'; ?>
<div class="container">
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
          $name = str_replace('Seo', 'SEO', $name);
          if ($index === count($segments) - 1) {
            echo "<li class='breadcrumb-item active' aria-current='page'>$name</li>";
          } else {
            echo "<li class='breadcrumb-item'><a href='$link/'>$name</a></li>";
          }
        }
      ?>
    </ol>
  </nav>
