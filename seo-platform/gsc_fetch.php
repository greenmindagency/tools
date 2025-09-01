<?php
/* ======================= CONFIG: EDIT THESE ======================= */
const CLIENT_ID     = '154567125513-3r6vh411d14igpsq52jojoq22s489d7v.apps.googleusercontent.com';
const CLIENT_SECRET = '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';
const REDIRECT_URI  = 'https://greenmindagency.com/tools/seo-platform/gsc_fetch.php';

const TOKEN_FILE = __DIR__ . '/gsc_token.json';
require 'config.php';

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
function bearer_get($url, $accessToken) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 30
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) throw new Exception("HTTP $status: $res");
    return json_decode($res, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_id'], $_POST['site'])) {
    $clientId = (int)$_POST['client_id'];
    $site = trim($_POST['site']);
    $stmt = $pdo->prepare("INSERT INTO sc_domains (client_id, domain) VALUES (?, ?) ON DUPLICATE KEY UPDATE domain = VALUES(domain)");
    $stmt->execute([$clientId, $site]);
    header('Location: positions.php?client_id=' . $clientId);
    exit;
}


echo '<!doctype html><meta charset="utf-8"><title>GSC API Test</title>';
echo '<style>body{font:14px/1.45 system-ui,Arial,sans-serif;margin:32px} table{border-collapse:collapse;width:100%;} th,td{padding:8px 10px;border-bottom:1px solid #eee} thead th{background:#f6f7f9;text-align:left} .btn{display:inline-block;padding:8px 12px;border-radius:6px;background:#1a73e8;color:#fff;text-decoration:none} .row{margin:14px 0} .muted{color:#666}</style>';
echo '<h2>Google Search Console – Property Selector & Last 3 Days</h2>';

if (isset($_GET['logout'])) { @unlink(TOKEN_FILE); header('Location: ' . strtok(REDIRECT_URI, '?')); exit; }

if (isset($_GET['code'])) {
    // OAuth code exchange
    try {
        $resp = http_post_form('https://oauth2.googleapis.com/token', [
            'code'          => $_GET['code'],
            'client_id'     => CLIENT_ID,
            'client_secret' => CLIENT_SECRET,
            'redirect_uri'  => REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]);
        $resp['created'] = time();
        save_token($resp);
        header('Location: ' . strtok(REDIRECT_URI, '?'));
        exit;
    } catch (Exception $e) {
        $err = htmlspecialchars($e->getMessage(), ENT_QUOTES);
        die("<h3>Token exchange failed</h3><pre>$err</pre>");
    }
}

$accessToken = get_access_token();
if (!$accessToken) {
    // Show connect button
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'response_type' => 'code',
        'client_id'     => CLIENT_ID,
        'redirect_uri'  => REDIRECT_URI,
        'scope'         => 'https://www.googleapis.com/auth/webmasters.readonly',
        'access_type'   => 'offline',
        'prompt'        => 'consent',
    ]);
    echo '<p class="row">Not connected. Click to authorize read-only access:</p>';
    echo '<p class="row"><a class="btn" href="'.$authUrl.'">Connect Google (GSC Read-Only)</a></p>';
    echo '<p class="muted">Tip: ensure the Google account you choose has access to your Search Console properties.</p>';
    exit;
}

// 1) List all accessible properties
try {
    $sitesResp = bearer_get('https://searchconsole.googleapis.com/webmasters/v3/sites', $accessToken);
    $sites = $sitesResp['siteEntry'] ?? [];
    if (!$sites) {
        echo '<p style="color:#c00"><strong>No GSC properties found for this Google account.</strong></p>';
        echo '<p><a href="?logout=1" class="muted">Disconnect / reset tokens</a></p>';
        exit;
    }
} catch (Exception $e) {
    $err = htmlspecialchars($e->getMessage(), ENT_QUOTES);
    echo "<p style='color:#c00'><strong>Failed to list properties:</strong></p><pre>$err</pre>";
    echo '<p><a href="?logout=1" class="muted">Disconnect / reset tokens</a></p>';
    exit;
}

// Choose property (from ?site=... or default to first)
$selected = isset($_GET['site']) ? $_GET['site'] : ($sites[0]['siteUrl'] ?? '');
$selected = is_string($selected) ? $selected : '';
// ensure selected exists in list; otherwise fallback
$siteUrls = array_map(fn($s)=>$s['siteUrl'] ?? '', $sites);
if (!in_array($selected, $siteUrls, true)) $selected = $sites[0]['siteUrl'] ?? '';

// UI: selector
echo '<form method="get" class="row">';
echo '<label for="site">Property:&nbsp;</label>';
echo '<select name="site" id="site" onchange="this.form.submit()">';
foreach ($sites as $s) {
    $url = $s['siteUrl'] ?? '';
    $perm = $s['permissionLevel'] ?? '';
    $sel = $url === $selected ? ' selected' : '';
    echo '<option value="'.htmlspecialchars($url).'"'.$sel.'>'.htmlspecialchars($url).'  ['.htmlspecialchars($perm).']</option>';
}
echo '</select> ';
echo '<noscript><button class="btn" type="submit">Load</button></noscript>';
echo ' &nbsp; <a class="muted" href="?logout=1">Disconnect / reset tokens</a>';
echo '</form>';
if (isset($_GET['client_id'])) {
    $cid = (int)$_GET['client_id'];
    echo '<form method="post" class="row" style="margin-top:10px">';
    echo '<input type="hidden" name="client_id" value="'.$cid.'">';
    echo '<input type="hidden" name="site" value="'.htmlspecialchars($selected).'">';
    echo '<button class="btn" type="submit">Use this property</button>';
    echo '</form>';
}

// Preflight: verify selected exists
if (!$selected) {
    echo '<p style="color:#c00"><strong>No property selected.</strong></p>';
    exit;
}

// 2) Build date range: last 3 FULL days with available data
// Search Console reporting is typically delayed by ~2 days, so end at 3 days ago
$end   = new DateTime('3 days ago');
$start = (clone $end)->modify('-2 days');

// Endpoint
$endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode($selected) . '/searchAnalytics/query';

// 3) Query: by date (last 3 days)
$bodyByDate = [
    'startDate'  => $start->format('Y-m-d'),
    'endDate'    => $end->format('Y-m-d'),
    'dimensions' => ['date'],
    'rowLimit'   => 3,
    // Include fresh data that may not yet be marked as final
    'dataState'  => 'all'
];

// 4) Query: Top 10 queries by impressions (same window)
$bodyTopQueries = [
    'startDate'  => $start->format('Y-m-d'),
    'endDate'    => $end->format('Y-m-d'),
    'dimensions' => ['query'],
    'rowLimit'   => 10,
    // Include fresh data that may not yet be marked as final
    'dataState'  => 'all',
    'orderBy'    => [
        ['fieldName' => 'impressions', 'descending' => true]
    ]
];

try {
    // by date
    $byDate = http_post_json($endpoint, $bodyByDate, [
        'Authorization: Bearer ' . $accessToken
    ]);
    $rowsDate = $byDate['rows'] ?? [];

    // top queries
    $byQuery = http_post_json($endpoint, $bodyTopQueries, [
        'Authorization: Bearer ' . $accessToken
    ]);
    $rowsQuery = $byQuery['rows'] ?? [];

    echo '<p class="row">Connected ✅ &nbsp;Property: <code>' . htmlspecialchars($selected) . '</code> ';
    echo ' &nbsp;Range: ' . $start->format('Y-m-d') . ' → ' . $end->format('Y-m-d') . '</p>';

    // Table 1: last 3 days
    echo '<h3>Last 3 Days (by date)</h3>';
    if (!$rowsDate) {
        echo '<p class="muted">No data for this range.</p>';
    } else {
        echo '<table><thead><tr><th>Date</th><th style="text-align:right">Clicks</th><th style="text-align:right">Impressions</th><th style="text-align:right">CTR</th><th style="text-align:right">Position</th></tr></thead><tbody>';
        foreach ($rowsDate as $r) {
            $date = $r['keys'][0] ?? '';
            $clicks = number_format($r['clicks'] ?? 0);
            $impr   = number_format($r['impressions'] ?? 0);
            $ctr    = isset($r['ctr']) ? round($r['ctr']*100, 2).'%' : '—';
            $pos    = isset($r['position']) ? round($r['position'], 2) : '—';
            echo "<tr><td>".htmlspecialchars($date)."</td><td style='text-align:right'>$clicks</td><td style='text-align:right'>$impr</td><td style='text-align:right'>$ctr</td><td style='text-align:right'>$pos</td></tr>";
        }
        echo '</tbody></table>';
    }

    // Table 2: top queries by impressions
    echo '<h3 style="margin-top:24px">Top 10 Queries (by impressions)</h3>';
    if (!$rowsQuery) {
        echo '<p class="muted">No query data for this range.</p>';
    } else {
        echo '<table><thead><tr><th>#</th><th>Query</th><th style="text-align:right">Impressions</th><th style="text-align:right">Clicks</th><th style="text-align:right">CTR</th><th style="text-align:right">Position</th></tr></thead><tbody>';
        $i = 1;
        foreach ($rowsQuery as $r) {
            $q      = $r['keys'][0] ?? '(not set)';
            $impr   = number_format($r['impressions'] ?? 0);
            $clicks = number_format($r['clicks'] ?? 0);
            $ctr    = isset($r['ctr']) ? round($r['ctr']*100, 2).'%' : '—';
            $pos    = isset($r['position']) ? round($r['position'], 2) : '—';
            echo "<tr><td>$i</td><td>".htmlspecialchars($q)."</td><td style='text-align:right'>$impr</td><td style='text-align:right'>$clicks</td><td style='text-align:right'>$ctr</td><td style='text-align:right'>$pos</td></tr>";
            $i++;
        }
        echo '</tbody></table>';
    }

} catch (Exception $e) {
    $err = htmlspecialchars($e->getMessage(), ENT_QUOTES);
    echo "<p style='color:#c00'><strong>Error:</strong></p><pre>$err</pre>";
}

