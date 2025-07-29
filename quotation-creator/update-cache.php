<?php
/**
 * Fetches the latest price list from the website and stores it
 * in pricing-cache.json for offline use.
 */

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json');

$localPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/').'/price-list/index.html';
if (is_readable($localPath)) {
    $html = file_get_contents($localPath);
} else {
    $url = 'https://greenmindagency.com/price-list/';
    $html = @file_get_contents($url);
    if (!$html) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to download price list.']);
        exit;
    }
}

$data = parse_html($html);
file_put_contents(__DIR__ . '/pricing-cache.json', json_encode($data, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'data' => $data]);