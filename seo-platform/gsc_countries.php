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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $countries = json_decode($_POST['countries'] ?? '[]', true);
    if (!$clientId || !is_array($countries)) {
        echo json_encode(['status'=>'error','error'=>'Invalid parameters']);
        exit;
    }
    $countries = array_values(array_filter(array_map(fn($c)=>strtolower(trim($c)), $countries)));
    $pdo->exec("CREATE TABLE IF NOT EXISTS sc_countries (
        client_id INT,
        country VARCHAR(3),
        PRIMARY KEY (client_id, country)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = $pdo->prepare('SELECT country FROM sc_countries WHERE client_id = ?');
    $stmt->execute([$clientId]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $toAdd = array_diff($countries, $existing);
    $toRemove = array_diff($existing, $countries);
    if ($toAdd) {
        $ins = $pdo->prepare('INSERT IGNORE INTO sc_countries (client_id, country) VALUES (?, ?)');
        foreach ($toAdd as $c) $ins->execute([$clientId, $c]);
    }
    if ($toRemove) {
        $in = implode(',', array_fill(0, count($toRemove), '?'));
        $delC = $pdo->prepare("DELETE FROM sc_countries WHERE client_id = ? AND country IN ($in)");
        $delC->execute(array_merge([$clientId], $toRemove));
        $delK = $pdo->prepare("DELETE FROM keyword_positions WHERE client_id = ? AND country IN ($in)");
        $delK->execute(array_merge([$clientId], $toRemove));
    }
    echo json_encode(['status'=>'ok']);
    exit;
}

$site = trim($_GET['site'] ?? '');
if (!$site) {
    echo json_encode(['status'=>'error','error'=>'Missing site']);
    exit;
}
$accessToken = get_access_token();
if (!$accessToken) {
    echo json_encode(['status'=>'error','error'=>'Not authorized']);
    exit;
}
$start = date('Y-m-d', strtotime('first day of -15 month'));
$end   = date('Y-m-d', strtotime('last day of previous month'));
$body = [
    'startDate'  => $start,
    'endDate'    => $end,
    'dimensions' => ['country'],
    'rowLimit'   => 250,
    'dataState'  => 'all'
];
$endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query';
try {
    $resp = http_post_json($endpoint, $body, ['Authorization: Bearer ' . $accessToken]);
    $rows = $resp['rows'] ?? [];
    $countries = [];
    foreach ($rows as $r) {
        $code = strtolower($r['keys'][0] ?? '');
        if ($code === '') continue;
        $impr = $r['impressions'] ?? 0;
        $countries[] = ['code' => $code, 'impressions' => $impr];
    }
    usort($countries, fn($a,$b)=>($b['impressions']<=>$a['impressions']));
    echo json_encode(['status'=>'ok','countries'=>$countries]);
} catch (Exception $e) {
    echo json_encode(['status'=>'error','error'=>$e->getMessage()]);
}
