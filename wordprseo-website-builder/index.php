<?php
$output = '';
$error = '';


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
                for ($i = 1; ; $i++) {
                    $slide = $zip->getFromName("ppt/slides/slide{$i}.xml");
                    if ($slide === false) {
                        break;
                    }
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
        case 'doc':
            $err = 'Legacy .doc files are not supported.';
            return '';
        case 'ppt':
            $err = 'Legacy .ppt files are not supported.';
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
                $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt'];
                if (!in_array($entryExt, $allowed)) {
                    continue;
                }
                $temp = tempnam(sys_get_temp_dir(), 'wpb');
                file_put_contents($temp, $zip->getFromIndex($i));
                $err = '';
                $textPart = extractText($temp, $entryExt, $err);
                unlink($temp);
                if ($err) {
                    $errors[] = $err;
                }
                if ($textPart !== '') {
                    $text .= "\n" . $textPart;
                }
            }
            $zip->close();
            return trim($text);
        }
        $errors[] = 'Unable to open ZIP file.';
        return '';
    }
    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt'];
    if (!in_array($ext, $allowed)) {
        $errors[] = 'Unsupported file type.';
        return '';
    }
    $err = '';
    $text = extractText($tmpPath, $ext, $err);
    if ($err) {
        $errors[] = $err;
    }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['source'])) {
        $error = 'Please upload at least one file.';
    } else {
        $files = $_FILES['source'];
        $texts = [];
        $errors = [];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            $text = extractUploaded($files['tmp_name'][$i], $files['name'][$i], $errors);
            if ($text !== '') {
                $texts[] = $text;
            }
        }
        if (!$texts) {
            $error = $errors ? implode(' ', $errors) : 'Could not extract text from file.';
        } else {
            if ($errors) {
                $error = implode(' ', $errors);
            }
            $page = $_POST['page'] ?? 'home';
            $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
            $prompt = "Write a full {$page} page with Hero, About Us, Services, and Contact sections. Each section should have a title and subtitle. Base the content on the following source text:\n\n" . implode("\n", $texts);
            $payload = json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);
            $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-goog-api-key: ' . $apiKey,
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $response = curl_exec($ch);
            if ($response === false) {
                $error = 'API request failed: ' . curl_error($ch);
            } else {
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $json = json_decode($response, true);
                if ($code >= 400 || isset($json['error'])) {
                    $msg = $json['error']['message'] ?? $response;
                    $error = 'API error: ' . $msg;
                } elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                    $output = $json['candidates'][0]['content']['parts'][0]['text'];
                } else {
                    $error = 'Unexpected API response.';
                }
            }
            curl_close($ch);
        }
    }
}

$title = 'Wordprseo Website Builder';
require __DIR__ . '/../header.php';
?>
<h1>Wordprseo Website Builder</h1>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form id="generatorForm" method="post" enctype="multipart/form-data" class="mb-4">
  <div class="mb-3">
    <label class="form-label">Source files:</label>
    <input type="file" name="source[]" multiple class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Page:</label>
    <select name="page" class="form-select">
      <option value="home">Home Page</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Generate</button>
  <div id="progress" class="progress mt-3 d-none">
    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
  </div>
</form>
<?php if ($output): ?>
  <h2>Generated Content</h2>
  <div class="mt-3"><?= $output ?></div>
<?php endif; ?>
<script>
document.getElementById('generatorForm').addEventListener('submit', function() {
  document.getElementById('progress').classList.remove('d-none');
});
</script>
<?php include __DIR__ . '/../footer.php'; ?>
