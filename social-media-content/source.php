<?php
require_once __DIR__ . '/session.php';
require 'config.php';
require __DIR__ . '/../wordprseo-website-builder/file_utils.php';

$client_id = $_GET['client_id'] ?? 0;
$isAdmin = $_SESSION['is_admin'] ?? false;
if (!$isAdmin) {
    $allowed = $_SESSION['client_ids'] ?? [];
    if ($allowed) {
        if (!in_array($client_id, $allowed)) {
            header('Location: login.php');
            exit;
        }
        $_SESSION['client_id'] = $client_id;
    } elseif (!isset($_SESSION['client_id']) || $_SESSION['client_id'] != $client_id) {
        header('Location: login.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) die('Client not found');

$stmt = $pdo->prepare('SELECT source FROM client_sources WHERE client_id = ?');
$stmt->execute([$client_id]);
$sourceText = $stmt->fetchColumn() ?: '';

$saved = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = $_POST['source_text'] ?? '';
    if (!empty($_FILES['source']['name'][0])) {
        $files = $_FILES['source'];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        $errors = [];
        for ($i=0; $i<$count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $part = extractUploaded($files['tmp_name'][$i], $files['name'][$i], $errors);
            if ($part !== '') {
                $text .= ($text ? "\n" : '') . $part;
            }
        }
        if ($errors) $error = implode(' ', $errors);
    }
    $pdo->prepare('REPLACE INTO client_sources (client_id, source) VALUES (?,?)')->execute([$client_id, $text]);
    $sourceText = $text;
    $saved = 'Source text saved.';
}

$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $client['name']), '-'));
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "source.php?client_id=$client_id&slug=$slug",
];
$title = $client['name'] . ' Source';
include 'header.php';
$base = "client_id=$client_id&slug=$slug";
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" href="source.php?<?=$base?>">Source</a></li>
  <li class="nav-item"><a class="nav-link" href="calendar.php?<?=$base?>">Calendar</a></li>
  <li class="nav-item"><a class="nav-link" href="posts.php?<?=$base?>">Posts</a></li>
</ul>
<?php if ($saved): ?><div class="alert alert-success"><?= htmlspecialchars($saved) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="mb-4">
  <div class="mb-3">
    <label class="form-label">Upload Source Files</label>
    <input type="file" name="source[]" multiple class="form-control">
  </div>
  <div class="mb-3">
    <label class="form-label">Source Text</label>
    <textarea name="source_text" class="form-control" rows="10"><?= htmlspecialchars($sourceText) ?></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Save</button>
</form>
<?php include 'footer.php'; ?>
