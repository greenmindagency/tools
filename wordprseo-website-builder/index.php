<?php
$title = 'WordprSEO Website Builder';
include 'header.php';
$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sitemap = $_POST['sitemap'] ?? '';
    $cmd = 'python3 ' . escapeshellarg(__DIR__ . '/builder.py');
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
<form method="post" class="mb-4">
  <div class="mb-3">
    <label class="form-label">Sitemap</label>
    <textarea name="sitemap" class="form-control" rows="10" placeholder="Home\nBlog\nCase Study"></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Build</button>
</form>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
<div class="mb-3">
  <h2>Sitemap</h2>
  <ul>
  <?php foreach ($result['sitemap'] as $item): ?>
    <li><?= htmlspecialchars($item) ?></li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<?php include 'footer.php'; ?>
