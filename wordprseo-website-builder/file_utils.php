<?php
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
?>
