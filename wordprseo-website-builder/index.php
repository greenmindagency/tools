<?php
$title = 'WordprSEO Website Builder';
include 'header.php';
$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client = $_POST['client'] ?? '';
    $language = $_POST['language'] ?? '';
    $content = $_POST['content'] ?? '';
    $sitemap = $_POST['sitemap'] ?? '';
    $cmd = 'python3 ' . escapeshellarg(__DIR__ . '/builder.py');
    $env = [
        'CLIENT' => $client,
        'LANGUAGE' => $language,
        'CONTENT' => $content,
        'SITEMAP' => $sitemap
    ];
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
    <label class="form-label">Client Name</label>
    <input type="text" name="client" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Content Language</label>
    <input type="text" name="language" class="form-control" placeholder="e.g., English">
  </div>
  <div class="mb-3">
    <label class="form-label">Client Content</label>
    <textarea name="content" class="form-control" rows="10" placeholder="Paste full website content or any related content here"></textarea>
  </div>
  <details class="mb-3">
    <summary class="form-label">Existing Sitemap (optional)</summary>
    <textarea name="sitemap" class="form-control mt-2" rows="5" placeholder="Home\nAbout\nBlog"></textarea>
  </details>
  <button type="submit" class="btn btn-primary">Build</button>
</form>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result && !empty($result['sitemap'])): ?>
<div class="mb-3">
  <h2>Sitemap</h2>
  <ol id="sitemapList" class="list-unstyled">
  <?php foreach ($result['sitemap'] as $item): ?>
    <li>
      <div class="d-flex justify-content-between align-items-center border p-2 mb-1">
        <span><?= htmlspecialchars($item['title']) ?></span>
        <span class="badge bg-secondary"><?= htmlspecialchars($item['type']) ?></span>
      </div>
      <ol></ol>
    </li>
  <?php endforeach; ?>
  </ol>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mjs.nestedSortable/2.1.0/jquery.mjs.nestedSortable.min.js"></script>
<script>
$(function() {
  $('#sitemapList').nestedSortable({
    handle: 'div',
    items: 'li',
    toleranceElement: '> div',
    maxLevels: 2
  });
});
</script>
<?php endif; ?>
<?php include 'footer.php'; ?>
