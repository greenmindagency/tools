<?php
$title = $title ?? 'Green Mind Tools';
$hideNav = $hideNav ?? false;
$hideBreadcrumb = $hideBreadcrumb ?? false;
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
    .priority { font-weight: bold; }
    .priority.low { color: #198754 !important; }
    .priority.normal { color: #ffc107 !important; }
    .priority.high { color: #dc3545 !important; }
    .client-priority { padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
    .client-priority.critical,
    .client-priority.high { background-color: #832108; color: #fff; }
    .client-priority.intermed,
    .client-priority.low { background-color: #e8b839; color: #000; }
    .editable { min-height: 38px; white-space: pre-wrap; }
    .task-main[data-bs-target] { cursor: pointer; }
  </style>
</head>
<body>
<?php if (!$hideNav) include __DIR__ . '/../nav.php'; ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
  <div id="gm-toast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" style="background-color:#d1e7dd;color:#0f5132;">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
<div class="container">
  <?php if (!$hideBreadcrumb): ?>
  <nav aria-label="breadcrumb" class="border-bottom container-fluid bg-light p-3 my-4">
    <ol class="breadcrumb mb-0">
      <?php
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (!empty($segments) && $segments[0] === 'tools') {
          array_shift($segments);
        }
        $segments = array_values(array_filter($segments, function ($s) {
          return $s !== 'index.php';
        }));

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
  <?php endif; ?>