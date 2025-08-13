<?php
$output = '';
$error = '';

function extractText(string $path, string $ext, string &$error): string {
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
            $error = 'Unable to read DOCX file.';
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
            $error = 'Unable to read PPTX file.';
            return '';
        case 'pdf':
            $cmd = 'pdftotext ' . escapeshellarg($path) . ' -';
            $out = [];
            $status = 0;
            exec($cmd, $out, $status);
            if ($status === 0) {
                return implode("\n", $out);
            }
            $error = 'pdftotext not installed or failed to parse PDF.';
            return '';
        case 'doc':
            $error = 'Legacy .doc files are not supported.';
            return '';
        case 'ppt':
            $error = 'Legacy .ppt files are not supported.';
            return '';
        default:
            $error = 'Unsupported file type.';
            return '';
    }
}

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

            $text = extractText($destination, $ext, $error);
            if ($text === '') {
                if (!$error) {
                    $error = 'Could not extract text from file.';
                }
            } else {
                $page = $_POST['page'] ?? 'home';
                $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
                if (!$apiKey) {
                    $error = 'Set the GEMINI_API_KEY environment variable with your Google Gemini key.';
                } else {
                    $prompt = "Write a full {$page} page with Hero, About Us, Services, and Contact sections. Each section should have a title and subtitle. Base the content on the following source text:\n\n" . $text;
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
    <p style="color:red;"> <?= htmlspecialchars($error) ?> </p>
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
