<?php
/**
 * Fetches the latest price list from the website and stores it
 * in pricing-cache.json for offline use.
 */

require_once __DIR__ . '/lib.php';

$url = 'https://greenmindagency.com/price-list/';
$html = @file_get_contents($url);
if (!$html) {
    fwrite(STDERR, "Failed to download price list.\n");
    exit(1);
}

$data = parse_html($html);
file_put_contents(__DIR__ . '/pricing-cache.json', json_encode($data, JSON_PRETTY_PRINT));

echo "Cache updated successfully.\n";
