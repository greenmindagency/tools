<?php
/**
 * Test script to fetch the HTML content of the pricing page.
 * This file should be placed at: https://greenmindagency.com/tools/quotation-creator/test.php
 */

// Option 1: Try local file access (only works if /price-list/ is a static file)
$localPath = $_SERVER['DOCUMENT_ROOT'] . '/price-list/index.html';

if (file_exists($localPath)) {
    echo "<h2>✅ Loaded from local file:</h2>";
    echo nl2br(htmlspecialchars(file_get_contents($localPath)));
    exit;
}

// Option 2: Fallback to fetching over HTTP using cURL
$url = 'https://greenmindagency.com/price-list/';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL check temporarily

$html = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if (!$html) {
    echo "<h2>❌ Failed to fetch over HTTP:</h2><pre>$error</pre>";
} else {
    echo "<h2>✅ Loaded from HTTP request:</h2>";
    echo nl2br(htmlspecialchars($html));
}
?>
