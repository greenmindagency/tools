<?php
/**
 * Parse the Green Mind Agency pricing page HTML.
 * Uses CSS class names to locate each service and its packages.
 */
function parse_html($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $pots = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' potp ')]");
    $all = [];
    foreach ($pots as $pot) {
        $h3 = $xpath->query('.//h3[contains(concat(" ", normalize-space(@class), " "), " package ")] | .//h3[1]', $pot)->item(0);
        $service = trim($h3 ? $h3->textContent : 'Service');
        $options = [];
        foreach ($xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " mb-3 ")]', $pot) as $box) {
            $usd = $xpath->query('.//p[contains(concat(" ", normalize-space(@class), " "), " h2 ")]', $box)->item(0);
            $egp = $xpath->query('.//p[contains(concat(" ", normalize-space(@class), " "), " h5 ")]', $box)->item(0);
            $usdText = $usd ? trim($usd->textContent) : '';
            $egpText = $egp ? trim($egp->textContent) : '';
            $usdVal = (float)preg_replace('/[^0-9.]/','',$usdText);
            $egpVal = (float)preg_replace('/[^0-9.]/','',$egpText);
            $lis = $xpath->query('.//ul/li', $box);
            $details = [];
            foreach ($lis as $li) {
                $cls = $li->getAttribute('class');
                if (strpos($cls, 'list-group-item-dark') !== false) continue;
                $details[] = trim($li->textContent);
            }
            $options[] = [
                'service' => $service,
                'usd' => $usdText,
                'egp' => $egpText,
                'usd_val' => $usdVal,
                'egp_val' => $egpVal,
                'details' => $details
            ];
        }
        if ($options) $all[] = ['name' => $service, 'packages' => $options];
    }
    return $all;
}

function fetch_remote_html($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html ?: false;
}
