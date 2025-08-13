<?php
$output = '';
$error = '';

function extractText(string $path, string $ext, string &$error): string {
    switch ($ext) {
        case 'txt':
            return file_get_contents($path) ?: '';
        case 'docx':
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml !== false) {
                    // keep paragraph breaks: turn </w:p> into newlines, then strip tags
                    $xml = preg_replace('/<\/w:p>/', "\n", $xml);
                    $text = strip_tags($xml);
                    $text = preg_replace('/[ \t]+/',' ', $text);
                    return trim($text);
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
                    if ($slide === false) break;
                    $slide = preg_replace('/<\/a:p>/', "\n", $slide);
                    $text .= strip_tags($slide) . "\n";
                }
                $zip->close();
                return trim($text);
            }
            $error = 'Unable to read PPTX file.';
            return '';
        case 'pdf':
            // requires poppler-utils (pdftotext)
            $cmd = 'pdftotext ' . escapeshellarg($path) . ' -';
            $out = [];
            $status = 0;
            exec($cmd, $out, $status);
            if ($status === 0) return implode("\n", $out);
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

function stripCodeFences(string $s): string {
    // If model wraps HTML in ```html ... ```, remove the fences
    if (preg_match('/^```(?:html)?\s*(.*?)\s*```$/s', trim($s), $m)) {
        return $m[1];
    }
    return $s;
}

$htmlPreview = '';
$downloadPath = '';

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
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $destination = $uploadDir . uniqid('src_', true) . '.' . $ext;
            if (!move_uploaded_file($_FILES['source']['tmp_name'], $destination)) {
                $error = 'Failed to store uploaded file.';
            } else {
                $text = extractText($destination, $ext, $error);
                if ($text === '') {
                    if (!$error) $error = 'Could not extract text from file.';
                } else {
                    $page = $_POST['page'] ?? 'home';

                    // 1) Get API key (env var). Optional: fallback to hardcoded (NOT recommended).
                    $apiKey = 'AIzaSyD4GbyZjZjMAvqLJKFruC1_iX07n8u18x0';
                    if (!$apiKey || !preg_match('/^AIza[0-9A-Za-z_\-]{30,}$/', $apiKey)) {
                        $error = 'Set GEMINI_API_KEY environment variable with your Google AI Studio key.';
                    } else {
                        // 2) Build prompt asking for FULL HTML page
                        $prompt = <<<EOT
You are a professional website copywriter and layout generator.
Task: Build a FULL HTML page for the "{$page}" page using ONLY the provided source text for facts.
Requirements:
- Return a COMPLETE, valid HTML document (<html><head>...</head><body>...</body></html>).
- Include: <title> (<=60 chars) + <meta name="description"> (110–140 chars).
- Sections with clear structure:
  - HERO: <section id="hero"> with <h1>Title</h1>, <p class="subtitle">Subtitle (<=160 chars)</p>, and a short paragraph (<=240 chars).
  - WHO WE ARE: <section id="who-we-are"><h2>...</h2><p>...</p></section>
  - SERVICES: <section id="services"> with <h2>, and 2–4 <article> service blocks (each with short <h3> + <p>).
  - CTA: <section id="cta"> with <h2> and a short <p> + a button-like <a>.
- Keep tone: professional, concise, human.
- Language: keep the source language (Arabic if source is Arabic; else English).
- No external CSS/JS; just minimal inline CSS (safe, neutral).
- Do NOT include any code fences.
Source text:
{$text}
EOT;

                        // 3) Call Gemini generateContent
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
                        curl_setopt_array($ch, [
                            CURLOPT_HTTPHEADER => [
                                'Content-Type: application/json',
                                'X-goog-api-key: ' . $apiKey,
                            ],
                            CURLOPT_POST => true,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POSTFIELDS => $payload,
                            CURLOPT_TIMEOUT => 60,
                        ]);
                        $response = curl_exec($ch);
                        if ($response === false) {
                            $error = 'API request failed: ' . curl_error($ch);
                        } else {
                            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $json = json_decode($response, true);
                            if ($code >= 400 || isset($json['error'])) {
                                $msg = $json['error']['message'] ?? $response;
                                $error = 'API error: ' . $msg;
                            } elseif (!empty($json['candidates'][0]['content']['parts'][0]['text'])) {
                                $html = $json['candidates'][0]['content']['parts'][0]['text'];
                                $html = stripCodeFences($html);

                                // 4) Inline preview with iframe + downloadable file
                                $outFile = $uploadDir . uniqid('home_full_', true) . '.html';
                                file_put_contents($outFile, $html);
                                $downloadPath = 'uploads/' . basename($outFile);

                                // Do NOT escape; we want to render full HTML (sandboxed by iframe via srcdoc)
                                $htmlPreview = $html;
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
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Wordprseo Website Builder</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#0f1220;color:#e8eaf6;margin:0;padding:24px}
    .card{background:#161a2b;border:1px solid #28314d;border-radius:14px;padding:20px;margin-bottom:16px}
    input[type=file],select{display:block;margin:8px 0}
    button{background:#5865f2;border:none;color:#fff;padding:10px 14px;border-radius:10px;cursor:pointer}
    iframe{width:100%;height:75vh;border:1px solid #28314d;border-radius:10px;background:#fff}
    a.btn{display:inline-block;margin-top:10px;color:#fff;text-decoration:none;background:#2aa198;padding:8px 12px;border-radius:10px}
    pre{background:#0b0f1d;border:1px solid #28314d;border-radius:10px;padding:12px;white-space:pre-wrap;word-break:break-word}
  </style>
</head>
<body>
  <div class="card">
    <h1>Wordprseo Website Builder (Gemini)</h1>
    <?php if ($error): ?><p style="color:#ff8a8a"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <label>Source file:</label>
      <input type="file" name="source" required>
      <label>Page:</label>
      <select name="page">
        <option value="home">Home Page</option>
      </select>
      <button type="submit">Generate</button>
    </form>
  </div>

  <?php if ($htmlPreview): ?>
    <div class="card">
      <h3>Preview</h3>
      <iframe srcdoc="<?= htmlspecialchars($htmlPreview) ?>"></iframe>
      <?php if ($downloadPath): ?>
        <p><a class="btn" href="<?= htmlspecialchars($downloadPath) ?>" download>Download HTML</a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$htmlPreview && !$error): ?>
    <div class="card">
      <h3>Tip</h3>
      <pre>Set an env var on the server:
export GEMINI_API_KEY="YOUR_API_KEY_FROM_AI_STUDIO"</pre>
    </div>
  <?php endif; ?>
