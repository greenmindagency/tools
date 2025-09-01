<?php
/* ======================= CONFIG: EDIT THESE ======================= */
const CLIENT_ID     = '154567125513-3r6vh411d14igpsq52jojoq22s489d7v.apps.googleusercontent.com';
const CLIENT_SECRET = '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';
const REDIRECT_URI  = 'https://greenmindagency.com/tools/seo-platform/gsc_test.php';

// Use EXACT property string:
// - URL-prefix: 'https://example.com/'
// - Domain property: 'sc-domain:example.com'
const SITE_PROPERTY = 'https://greenmindagency.com/tools/seo-platform/gsc_test.php';
/* ================================================================= */

// Minimal storage path for tokens (make sure this file is writable by PHP)
const TOKEN_FILE = __DIR__ . '/gsc_token.json';

// ---- Helpers ----
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
    if ($res === false) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) {
        throw new Exception("HTTP $status: $res");
    }
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

function save_token($data) {
    file_put_contents(TOKEN_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function load_token() {
    if (!file_exists(TOKEN_FILE)) return null;
    $tok = json_decode(file_get_contents(TOKEN_FILE), true);
    return $tok ?: null;
}

function is_token_expired($token) {
    if (!isset($token['created']) || !isset($token['expires_in'])) return true;
    // consider 60s safety buffer
    return (time() >= ($token['created'] + $token['expires_in'] - 60));
}

function refresh_access_token($refreshToken) {
    $resp = http_post_form('https://oauth2.googleapis.com/token', [
        'client_id'     => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    // Merge with existing refresh_token (Google doesn’t always resend it)
    $resp['created'] = time();
    return $resp;
}

function get_access_token() {
    $token = load_token();
    if ($token && !is_token_expired($token)) {
        return $token['access_token'];
    }
    if ($token && isset($token['refresh_token'])) {
        $refreshed = refresh_access_token($token['refresh_token']);
        $merged = array_merge($token, $refreshed);
        save_token($merged);
        return $merged['access_token'];
    }
    return null;
}

// ---- OAuth flow ----
if (isset($_GET['logout'])) {
    @unlink(TOKEN_FILE);
    header('Location: ' . strtok(REDIRECT_URI, '?'));
    exit;
}

if (isset($_GET['code'])) {
    // Exchange code for tokens
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

// ---- UI / Data call ----
echo '<!doctype html><meta charset="utf-8"><title>GSC API Test</title>';
echo '<div style="font:14px/1.4 system-ui,Arial,sans-serif;max-width:900px;margin:40px auto">';
echo '<h2>Google Search Console – API Smoke Test</h2>';

$accessToken = get_access_token();
if (!$accessToken) {
    // Not authorized yet → show login link
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'response_type' => 'code',
        'client_id'     => CLIENT_ID,
        'redirect_uri'  => REDIRECT_URI,
        'scope'         => 'https://www.googleapis.com/auth/webmasters.readonly',
        'access_type'   => 'offline',   // to get refresh_token
        'prompt'        => 'consent',   // force refresh_token on first grant
        // optional: 'include_granted_scopes' => 'true',
    ]);
    echo '<p>Not connected. Click below to authorize read-only access to your Search Console property:</p>';
    echo '<p><a href="'.$authUrl.'" style="display:inline-block;padding:10px 14px;background:#1a73e8;color:#fff;text-decoration:none;border-radius:6px">Connect Google (GSC Read-Only)</a></p>';
    echo '<p style="color:#666">Make sure this Google account has access to: <code>'.htmlspecialchars(SITE_PROPERTY).'</code></p>';
    echo '</div>';
    exit;
}

// If we’re here, we have an access token → query last 7 days by date
try {
    // Build request body (last 7 full days)
    $end   = new DateTime('yesterday');  // avoids partial today
    $start = (clone $end)->modify('-6 days');
    $body = [
        'startDate'  => $start->format('Y-m-d'),
        'endDate'    => $end->format('Y-m-d'),
        'dimensions' => ['date'],
        'rowLimit'   => 7
    ];

    $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' .
           rawurlencode(SITE_PROPERTY) . '/searchAnalytics/query';

    // POST with Bearer token
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 30
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) throw new Exception("HTTP $status: $res");

    $data = json_decode($res, true);
    $rows = $data['rows'] ?? [];

    echo '<p>Connected ✅ &nbsp;Property: <code>'.htmlspecialchars(SITE_PROPERTY).'</code> ';
    echo ' &nbsp;Range: '.$start->format('Y-m-d').' → '.$end->format('Y-m-d').'</p>';
    echo '<p><a href="?logout=1" style="font-size:12px">Disconnect / reset tokens</a></p>';

    if (!$rows) {
        echo '<p><strong>No data returned.</strong> Check that the property string matches your GSC property exactly (URL-prefix vs Domain), and that this Google user has access.</p>';
    } else {
        echo '<table border="0" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">';
        echo '<thead><tr style="background:#f5f5f5"><th align="left">Date</th><th align="right">Clicks</th><th align="right">Impressions</th><th align="right">CTR</th><th align="right">Position</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $date = $r['keys'][0] ?? '';
            $clicks = number_format($r['clicks'] ?? 0);
            $impr   = number_format($r['impressions'] ?? 0);
            $ctr    = isset($r['ctr']) ? round($r['ctr']*100, 2).'%' : '—';
            $pos    = isset($r['position']) ? round($r['position'], 2) : '—';
            echo "<tr><td>$date</td><td align='right'>$clicks</td><td align='right'>$impr</td><td align='right'>$ctr</td><td align='right'>$pos</td></tr>";
        }
        echo '</tbody></table>';
    }

    // Bonus: example of queries by page (uncomment to use)
    /*
    $body2 = [
        'startDate'  => $start->format('Y-m-d'),
        'endDate'    => $end->format('Y-m-d'),
        'dimensions' => ['page'],
        'rowLimit'   => 10
    ];
    // ...POST $body2 to same $url to get top pages
    */

} catch (Exception $e) {
    $err = htmlspecialchars($e->getMessage(), ENT_QUOTES);
    echo "<p style='color:#c00'><strong>Error:</strong></p><pre>$err</pre>";
}

echo '</div>';
