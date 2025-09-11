<?php
require_once __DIR__ . '/session.php';
require 'config.php';
require_once __DIR__ . '/lib/cache.php';
require_once __DIR__ . '/lib/positions_util.php';
header('Content-Type: application/json');

const CLIENT_ID     = '154567125513-3r6vh411d14igpsq52jojoq22s489d7v.apps.googleusercontent.com';
const CLIENT_SECRET = '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';
const TOKEN_FILE = __DIR__ . '/gsc_token.json';

function http_post_json($url, $payload, $headers = []) {
    $ch = curl_init($url);
    $defaultHeaders = ['Content-Type: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
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
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_TIMEOUT => 30
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

$clientId = (int)($_POST['client_id'] ?? 0);
$site     = trim($_POST['site'] ?? '');
$country  = trim($_POST['country'] ?? '');

if (!$clientId || !$site) {
    echo json_encode(['status'=>'error','error'=>'Missing parameters']);
    exit;
}

rotate_position_months($pdo, $clientId, $country);

$accessToken = get_access_token();
if (!$accessToken) {
    echo json_encode(['status'=>'error','error'=>'Not authorized']);
    exit;
}

$endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query';

try {
    $mapStmt = $pdo->prepare('SELECT id, keyword FROM keyword_positions WHERE client_id = ? AND country = ?');
    $mapStmt->execute([$clientId, $country]);
    $kwMap = [];
    while ($r = $mapStmt->fetch(PDO::FETCH_ASSOC)) {
        $kwMap[strtolower(trim($r['keyword']))] = $r['id'];
    }
    // determine which months to fetch: empty columns plus latest months
    $months = [];
    $hasEmpty = false;
    for ($i = 0; $i < 13; $i++) {
        $col = 'm' . ($i + 1);
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM keyword_positions WHERE client_id = ? AND country = ? AND `$col` IS NOT NULL");
        $cntStmt->execute([$clientId, $country]);
        if ($cntStmt->fetchColumn() == 0) {
            $hasEmpty = true;
            $start = date('Y-m-d', strtotime("first day of -$i month"));
            $end   = date('Y-m-d', strtotime("last day of -$i month"));
            $months[$i] = ['start' => $start, 'end' => $end, 'index' => $i];
        }
    }
    // always refresh current month
    $months[0] = [
        'start' => date('Y-m-d', strtotime('first day of this month')),
        'end'   => date('Y-m-d', strtotime('last day of this month')),
        'index' => 0
    ];
    // if no empty columns, also refresh previous month
    if (!$hasEmpty) {
        $months[1] = [
            'start' => date('Y-m-d', strtotime('first day of -1 month')),
            'end'   => date('Y-m-d', strtotime('last day of -1 month')),
            'index' => 1
        ];
    }
    ksort($months);
    $months = array_values($months);

    $total = 0;
    foreach ($months as $m) {
        $start = $m['start'] ?? '';
        $end   = $m['end'] ?? '';
        $idx   = (int)($m['index'] ?? 0);
        if (!$start || !$end) continue;

        $body = [
            'startDate'  => $start,
            'endDate'    => $end,
            'dimensions' => ['query'],
            'rowLimit'   => 25000,
            'dataState'  => 'all'
        ];
        if ($country) {
            $body['dimensionFilterGroups'] = [[
                'filters' => [
                    [
                        'dimension' => 'country',
                        'operator'  => 'equals',
                        'expression'=> strtolower($country)
                    ]
                ]
            ]];
        }

        $cacheKey = hash('sha256', $site . '|' . $country . '|' . $start . '|' . $end);
        $resp = cache_get($pdo, $clientId, $cacheKey);
        if (!$resp) {
            $resp = http_post_json($endpoint, $body, ['Authorization: Bearer ' . $accessToken]);
            cache_set($pdo, $clientId, $cacheKey, $resp);
        }
        $rows = $resp['rows'] ?? [];

        $col = 'm' . ($idx + 1);
        $pdo->prepare("UPDATE keyword_positions SET `$col` = NULL, sort_order = NULL WHERE client_id = ? AND country = ?")
            ->execute([$clientId, $country]);

        usort($rows, fn($a,$b) => ($b['impressions'] ?? 0) <=> ($a['impressions'] ?? 0));
        $update = $pdo->prepare("UPDATE keyword_positions SET `$col` = ?, sort_order = ? WHERE id = ?");
        $order = 1;
        foreach ($rows as $row) {
            $kw = strtolower(trim($row['keys'][0] ?? ''));
            if ($kw === '' || !isset($kwMap[$kw])) continue;
            $pos = isset($row['position']) ? round($row['position'], 2) : null;
            $update->execute([$pos, $order, $kwMap[$kw]]);
            $order++;
        }
        $total += $order - 1;
    }

    echo json_encode(['status'=>'ok','updated'=>$total]);
} catch (Exception $e) {
    echo json_encode(['status'=>'error','error'=>$e->getMessage()]);
}
