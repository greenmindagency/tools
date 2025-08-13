<?php
session_start();
require __DIR__ . '/config.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    core_text LONGTEXT,
    instructions TEXT,
    sitemap TEXT
)");

$defaultInstr = file_get_contents(__DIR__ . '/INSTRUCTIONS.txt');

function extractText(string $path, string $ext, string &$err): string {
    switch ($ext) {
        case 'txt':
            return file_get_contents($path);
        case 'docx':
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml !== false) {
                    return trim(strip_tags($xml));
                }
            }
            $err = 'Unable to read DOCX file.';
            return '';
        case 'pptx':
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $text = '';
                for ($i = 1;; $i++) {
                    $slide = $zip->getFromName("ppt/slides/slide{$i}.xml");
                    if ($slide === false) break;
                    $text .= strip_tags($slide) . "\n";
                }
                $zip->close();
                return trim($text);
            }
            $err = 'Unable to read PPTX file.';
            return '';
        case 'pdf':
            $cmd = 'pdftotext ' . escapeshellarg($path) . ' -';
            $out = [];
            $status = 0;
            exec($cmd, $out, $status);
            if ($status === 0) {
                return implode("\n", $out);
            }
            $err = 'pdftotext not installed or failed to parse PDF.';
            return '';
        default:
            $err = 'Unsupported file type.';
            return '';
    }
}

function extractUploaded(string $tmpPath, string $name, array &$errors): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) === true) {
            $text = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                $entryExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                $allowed = ['pdf','docx','pptx','txt'];
                if (!in_array($entryExt, $allowed)) continue;
                $temp = tempnam(sys_get_temp_dir(), 'wpb');
                file_put_contents($temp, $zip->getFromIndex($i));
                $err = '';
                $textPart = extractText($temp, $entryExt, $err);
                unlink($temp);
                if ($err) $errors[] = $err;
                if ($textPart !== '') $text .= "\n".$textPart;
            }
            $zip->close();
            return trim($text);
        }
        $errors[] = 'Unable to open ZIP file.';
        return '';
    }
    $allowed = ['pdf','docx','pptx','txt'];
    if (!in_array($ext, $allowed)) {
        $errors[] = 'Unsupported file type.';
        return '';
    }
    $err='';
    $text = extractText($tmpPath, $ext, $err);
    if ($err) $errors[] = $err;
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['client_name'])) {
        $name = trim($_POST['client_name']);
        if ($name !== '' && isset($_FILES['source'])) {
            $files = $_FILES['source'];
            $texts = [];
            $errors = [];
            $count = is_array($files['name']) ? count($files['name']) : 0;
            for ($i=0;$i<$count;$i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $text = extractUploaded($files['tmp_name'][$i], $files['name'][$i], $errors);
                if ($text !== '') $texts[] = $text;
            }
            $core = implode("\n", $texts);
            $stmt = $pdo->prepare("INSERT INTO clients (name, core_text, instructions, sitemap) VALUES (?,?,?,?)");
            $stmt->execute([$name, $core, $defaultInstr, '']);
        }
        header('Location: index.php');
        exit;
    }
    if (isset($_POST['delete_client'])) {
        $id = (int)$_POST['delete_client'];
        $pdo->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
        header('Location: index.php');
        exit;
    }
}

$title = 'Wordprseo Builder Clients';
require __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="mb-0">Select a Client</h5>
  <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
</div>
<ul class="list-group mb-4">
<?php
$stmt = $pdo->query('SELECT * FROM clients ORDER BY name ASC');
foreach ($stmt as $client) {
    $id = $client['id'];
    $name = htmlspecialchars($client['name']);
    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
    echo "<a class='me-auto' href='builder.php?client_id=$id'>$name</a>";
    echo "<form method='POST' class='d-inline ms-1' onsubmit=\"return confirm('Delete this client?');\">";
    echo "<input type='hidden' name='delete_client' value='$id'>";
    echo "<button class='btn btn-sm btn-outline-danger'>Remove</button>";
    echo "</form></li>";
}
?>
</ul>
<h5>Add New Client</h5>
<form method="post" enctype="multipart/form-data" class="mb-5">
  <div class="mb-3">
    <label class="form-label">Client Name</label>
    <input type="text" name="client_name" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Source files</label>
    <input type="file" name="source[]" multiple class="form-control" required>
  </div>
  <button type="submit" class="btn btn-primary">Add Client</button>
</form>
<?php include __DIR__ . '/../footer.php'; ?>
