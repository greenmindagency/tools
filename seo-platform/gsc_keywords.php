<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');

const CLIENT_ID     = '154567125513-3r6vh411d14igpsq52jojoq22s489d7v.apps.googleusercontent.com';
const CLIENT_SECRET = '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';
const TOKEN_FILE    = __DIR__ . '/gsc_token.json';

function http_post_json($url, $payload, $headers = []) {
    $ch = curl_init($url);
    $default = ['Content-Type: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array_merge($default, $headers),
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 30
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) throw new Exception("HTTP $status: $res");
    return json_decode($res, true);
}
function http_post_form($url, $fields) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_TIMEOUT        => 30
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) throw new Exception("HTTP $status: $res");
    return json_decode($res, true);
}
function save_token($data) { file_put_contents(TOKEN_FILE, json_encode($data, JSON_PRETTY_PRINT)); }
function load_token()      { return file_exists(TOKEN_FILE) ? json_decode(file_get_contents(TOKEN_FILE), true) : null; }
function is_token_expired($t){ return !isset($t['created'],$t['expires_in']) || time() >= ($t['created'] + $t['expires_in'] - 60); }
function refresh_access_token($refreshToken) {
    $resp = http_post_form('https://oauth2.googleapis.com/token', [
        'client_id'     => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    $resp['created'] = time();
    return $resp;
}
function get_access_token() {
    $token = load_token();
    if ($token && !is_token_expired($token)) return $token['access_token'];
    if ($token && isset($token['refresh_token'])) {
        $ref = refresh_access_token($token['refresh_token']);
        $merged = array_merge($token, $ref);
        save_token($merged);
        return $merged['access_token'];
    }
    return null;
}

$clientId = (int)($_REQUEST['client_id'] ?? 0);
$site     = trim($_REQUEST['site'] ?? '');
if (!$clientId || !$site) {
    echo json_encode(['status'=>'error','error'=>'Missing parameters']);
    exit;
}

$accessToken = get_access_token();
if (!$accessToken) {
    echo json_encode(['status'=>'error','error'=>'Not authorized']);
    exit;
}

$endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $start = date('Y-m-d', strtotime('first day of -15 month'));
    $end   = date('Y-m-d', strtotime('last day of previous month'));
    $body = [
        'startDate'  => $start,
        'endDate'    => $end,
        'dimensions' => ['query'],
        'rowLimit'   => 25000,
        'dataState'  => 'all'
    ];
    try {
        $resp = http_post_json($endpoint, $body, ['Authorization: Bearer ' . $accessToken]);
        $rows = $resp['rows'] ?? [];
        usort($rows, fn($a,$b)=>($b['impressions']??0)<=>($a['impressions']??0));
        echo json_encode(['status'=>'ok','rows'=>$rows]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','error'=>$e->getMessage()]);
    }
    exit;
}

// POST -> import keywords & positions
$body = [
    'startDate'  => date('Y-m-d', strtotime('first day of -15 month')),
    'endDate'    => date('Y-m-d', strtotime('last day of previous month')),
    'dimensions' => ['query'],
    'rowLimit'   => 25000,
    'dataState'  => 'all'
];
try {
    $resp = http_post_json($endpoint, $body, ['Authorization: Bearer ' . $accessToken]);
    $rows = $resp['rows'] ?? [];
} catch (Exception $e) {
    echo json_encode(['status'=>'error','error'=>$e->getMessage()]);
    exit;
}
$keywords = [];
foreach ($rows as $r) {
    $kw = strtolower(trim($r['keys'][0] ?? ''));
    if ($kw !== '') $keywords[$kw] = true;
}
$keywords = array_keys($keywords);
$ins = $pdo->prepare('INSERT IGNORE INTO keyword_positions (client_id, keyword) VALUES (?, ?)');
foreach ($keywords as $kw) {
    $ins->execute([$clientId, $kw]);
}
$pdo->query("DELETE kp1 FROM keyword_positions kp1 JOIN keyword_positions kp2 ON kp1.keyword = kp2.keyword AND kp1.id > kp2.id WHERE kp1.client_id = $clientId AND kp2.client_id = $clientId");

$mapStmt = $pdo->prepare('SELECT id, keyword FROM keyword_positions WHERE client_id = ?');
$mapStmt->execute([$clientId]);
$kwMap = [];
while ($r = $mapStmt->fetch(PDO::FETCH_ASSOC)) {
    $kwMap[strtolower(trim($r['keyword']))] = $r['id'];
}

for ($i = 0; $i < 12; $i++) {
    $start = date('Y-m-d', strtotime("first day of -$i month"));
    $end   = date('Y-m-d', strtotime("last day of -$i month"));
    $body = [
        'startDate'  => $start,
        'endDate'    => $end,
        'dimensions' => ['query'],
        'rowLimit'   => 25000,
        'dataState'  => 'all'
    ];
    try {
        $resp = http_post_json($endpoint, $body, ['Authorization: Bearer ' . $accessToken]);
        $rows = $resp['rows'] ?? [];
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','error'=>$e->getMessage()]);
        exit;
    }
    $col = 'm' . ($i + 1);
    $pdo->prepare("UPDATE keyword_positions SET `$col` = NULL, sort_order = NULL WHERE client_id = ?")->execute([$clientId]);
    usort($rows, fn($a,$b)=>($b['impressions']??0)<=>($a['impressions']??0));
    $update = $pdo->prepare("UPDATE keyword_positions SET `$col` = ?, sort_order = ? WHERE id = ?");
    $order = 1;
    foreach ($rows as $row) {
        $kw = strtolower(trim($row['keys'][0] ?? ''));
        if ($kw === '' || !isset($kwMap[$kw])) continue;
        $pos = isset($row['position']) ? round($row['position'], 2) : null;
        $update->execute([$pos, $order, $kwMap[$kw]]);
        $order++;
    }
}

echo json_encode(['status'=>'ok','keywords'=>count($keywords)]);
