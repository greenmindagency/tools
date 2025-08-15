<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/file_utils.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$client_id = (int)($_GET['client_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    header('Location: index.php');
    exit;
}

$error = '';
$saved = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_core'])) {
    $text = $_POST['core_text'] ?? '';
    if (!empty($_FILES['source']['name'][0])) {
        $files = $_FILES['source'];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        $errors = [];
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $part = extractUploaded($files['tmp_name'][$i], $files['name'][$i], $errors);
            if ($part !== '') {
                $text .= ($text ? "\n" : '') . $part;
            }
        }
        if ($errors) $error = implode(' ', $errors);
    }
    $pdo->prepare('UPDATE clients SET core_text = ? WHERE id = ?')->execute([$text, $client_id]);
    $client['core_text'] = $text;
    $saved = 'Source text saved.';
}

$title = 'Wordprseo Content Builder';
require __DIR__ . '/../header.php';
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link active" href="#">Source</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="sitemap.php?client_id=<?= $client_id ?>">Site Map</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="structure.php?client_id=<?= $client_id ?>">Structure</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="content.php?client_id=<?= $client_id ?>">Content</a>
  </li>
</ul>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="mb-3">
  <div class="mb-3">
    <label class="form-label">Upload Source Files</label>
    <input type="file" name="source[]" multiple class="form-control">
  </div>
  <div class="mb-3">
    <label class="form-label">Core Text</label>
    <textarea name="core_text" class="form-control" rows="10"><?= htmlspecialchars($client['core_text']) ?></textarea>
  </div>
  <button type="submit" name="save_core" class="btn btn-primary">Save</button>
</form>
<?php include __DIR__ . '/../footer.php'; ?>

