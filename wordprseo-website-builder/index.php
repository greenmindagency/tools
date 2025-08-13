<?php
$output = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['source']) || $_FILES['source']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a file.';
    } else {
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt'];
        $ext = strtolower(pathinfo($_FILES['source']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $error = 'Unsupported file type.';
        } else {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $destination = $uploadDir . basename($_FILES['source']['name']);
            move_uploaded_file($_FILES['source']['tmp_name'], $destination);

            $page = $_POST['page'] ?? 'home';
            $cmd = escapeshellcmd("python3 generate_content.py " . escapeshellarg($page) . ' ' . escapeshellarg($destination));
            $output = shell_exec($cmd);
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Wordprseo Website Builder</title>
</head>
<body>
<h1>Wordprseo Website Builder</h1>
<?php if ($error): ?>
    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <label>Source file:</label>
    <input type="file" name="source" required>
    <label>Page:</label>
    <select name="page">
        <option value="home">Home Page</option>
    </select>
    <button type="submit">Generate</button>
</form>
<?php if ($output): ?>
    <h2>Generated Content</h2>
    <pre><?= htmlspecialchars($output) ?></pre>
<?php endif; ?>
</body>
</html>
