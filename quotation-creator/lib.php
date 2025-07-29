<?php
function parse_html($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $pots = $xpath->query("//*[contains(@class,'potp')]");
    $all = [];
    foreach ($pots as $pot) {
        $h3 = $xpath->query('.//h3[contains(@class,"package")]|.//h3[1]', $pot)->item(0);
        $service = trim($h3 ? $h3->textContent : 'Service');
        $options = [];
        foreach ($xpath->query('.//div[contains(@class,"mb-3")]', $pot) as $box) {
            $usd = $xpath->query('.//p[contains(@class,"h2")]', $box)->item(0);
            $egp = $xpath->query('.//p[contains(@class,"h5")]', $box)->item(0);
            $usdText = $usd ? trim($usd->textContent) : '';
            $egpText = $egp ? trim($egp->textContent) : '';
            $usdVal = (float)preg_replace('/[^0-9.]/','',$usdText);
            $egpVal = (float)preg_replace('/[^0-9.]/','',$egpText);
            $lis = $xpath->query('.//ul/li', $box);
            $details = [];
            foreach ($lis as $li) $details[] = trim($li->textContent);
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
