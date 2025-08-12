<?php
$title = 'WordprSEO Website Builder';
include 'header.php';
$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sitemap = $_POST['sitemap'] ?? '';
    $uploads = [];
    if (!empty($_FILES['files'])) {
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        foreach ($_FILES['files']['name'] as $i => $name) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['files']['tmp_name'][$i];
                $dest = $uploadDir . '/' . basename($name);
                if (move_uploaded_file($tmp, $dest)) {
                    $uploads[] = $dest;
                }
            }
        }
    }
    $escaped = array_map('escapeshellarg', $uploads);
    $cmd = 'python3 ' . escapeshellarg(__DIR__ . '/builder.py') . ' ' . implode(' ', $escaped);
    $env = ['SITEMAP' => $sitemap];
    $process = proc_open($cmd, [1=>['pipe','w'], 2=>['pipe','w']], $pipes, __DIR__, $env);
    if (is_resource($process)) {
        $output = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        if ($err) {
            $error = $err;
        } else {
            $result = json_decode($output, true);
        }
    } else {
        $error = 'Failed to run builder script.';
    }
}
?>
<h1>WordprSEO Website Builder</h1>
<form method="post" enctype="multipart/form-data" class="mb-4">
  <div class="mb-3">
    <label class="form-label">Upload Content Files</label>
    <input type="file" name="files[]" multiple class="form-control" accept=".doc,.docx,.pdf,.ppt,.pptx,.txt"/>
  </div>
  <div class="mb-3">
    <label class="form-label">Sitemap</label>
    <textarea name="sitemap" class="form-control" rows="5" placeholder="Home|page\nBlog|cat\nCase Study|single"></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Build</button>
</form>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
<div class="mb-3">
  <h2>Sitemap</h2>
  <pre><?= htmlspecialchars($result['sitemap']) ?></pre>
  <h2>Files</h2>
  <ul>
  <?php foreach ($result['files'] as $file): ?>
    <li>
      <strong><?= htmlspecialchars($file['file']) ?></strong>
      <?php if (isset($file['preview'])): ?>
        <pre><?= htmlspecialchars($file['preview']) ?></pre>
      <?php else: ?>
        <span class="text-danger">Error: <?= htmlspecialchars($file['error']) ?></span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<?php include 'footer.php'; ?>
