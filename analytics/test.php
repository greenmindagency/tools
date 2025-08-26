<?php
// ===== Config (env first; falls back to config.php) =====
// Expect standard env var names to be defined in the runtime environment.
// e.g. META_APP_ID, META_APP_SECRET, META_REDIRECT_URI
$appId     = getenv('META_APP_ID');
$appSecret = getenv('META_APP_SECRET');
$redirect  = getenv('META_REDIRECT_URI');

if (!$appId || !$appSecret || !$redirect) {
  $cfg = @include __DIR__ . '/config.php';
  if ($cfg) {
    $appId     = $appId     ?: $cfg['app_id'];
    $appSecret = $appSecret ?: $cfg['app_secret'];
    $redirect  = $redirect  ?: $cfg['redirect_uri'];
  }
}

if (!$appId || !$appSecret || !$redirect) {
  http_response_code(500);
  die('Missing app config. Set META_APP_ID / META_APP_SECRET / META_REDIRECT_URI or use config.php.');
}

$scopes = [
  'public_profile',
  'pages_show_list',
  'pages_read_engagement',
  'read_insights',
  'instagram_basic',
  'instagram_manage_insights',
  // add 'ads_read' if you’ll query ads later
];

session_start();

// --- helpers ---
function http_get_json($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 30,
  ]);
  $resp = curl_exec($ch);
  if ($resp === false) {
    $err = curl_error($ch);
    curl_close($ch);
    die('HTTP GET error: ' . htmlspecialchars($err));
  }
  curl_close($ch);
  $json = json_decode($resp, true);
  return $json ?: ['raw' => $resp];
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Step 1: if no "code", start OAuth ---
if (!isset($_GET['code'])) {
  $state = bin2hex(random_bytes(16));
  $_SESSION['oauth_state'] = $state;

  $authUrl = 'https://www.facebook.com/v23.0/dialog/oauth?' . http_build_query([
    'client_id'     => $GLOBALS['appId'],
    'redirect_uri'  => $GLOBALS['redirect'],
    'state'         => $state,
    'response_type' => 'code',
    'scope'         => implode(',', $GLOBALS['scopes']),
  ]);
  header('Location: ' . $authUrl);
  exit;
}

// --- Step 2: validate state ---
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
  http_response_code(400);
  die('Invalid state.');
}

// --- Step 3: exchange code -> short-lived user token ---
$tokenResp = http_get_json('https://graph.facebook.com/v23.0/oauth/access_token?' . http_build_query([
  'client_id'     => $appId,
  'redirect_uri'  => $redirect,
  'client_secret' => $appSecret,
  'code'          => $_GET['code'],
]));
if (empty($tokenResp['access_token'])) {
  echo "<pre>Token exchange failed:\n" . h(json_encode($tokenResp, JSON_PRETTY_PRINT)) . "</pre>";
  exit;
}
$userTokenShort = $tokenResp['access_token'];

// --- Step 4: exchange for long-lived user token (~60 days) ---
$longResp = http_get_json('https://graph.facebook.com/v23.0/oauth/access_token?' . http_build_query([
  'grant_type'        => 'fb_exchange_token',
  'client_id'         => $appId,
  'client_secret'     => $appSecret,
  'fb_exchange_token' => $userTokenShort,
]));
$userToken = $longResp['access_token'] ?? $userTokenShort;

// --- Step 5: list pages you manage (and get Page tokens) ---
$pagesResp = http_get_json('https://graph.facebook.com/v23.0/me/accounts?' . http_build_query([
  'access_token' => $userToken,
  'fields'       => 'name,id,access_token',
]));

echo "<h1>Meta Test</h1>";
echo "<p><strong>User token:</strong> obtained (" . (isset($longResp['access_token']) ? 'long-lived' : 'short-lived') . ").</p>";

if (empty($pagesResp['data'])) {
  echo "<p>No pages found or missing permissions. Response:</p><pre>" . h(json_encode($pagesResp, JSON_PRETTY_PRINT)) . "</pre>";
  exit;
}

echo "<h2>Your Facebook Pages</h2>";
echo "<table border='1' cellpadding='5'><tr><th>Page Name</th><th>Page ID</th><th>IG Username</th><th>IG ID</th></tr>";
foreach ($pagesResp['data'] as $p) {
  $pName = h($p['name'] ?? '');
  $pId   = h($p['id'] ?? '');
  $igU = $igId = '';

  if (!empty($p['access_token'])) {
    $pageToken = $p['access_token'];
    // fetch connected IG business account (if any)
    $ig = http_get_json("https://graph.facebook.com/v23.0/{$p['id']}?" . http_build_query([
      'fields'       => 'instagram_business_account{id,username}',
      'access_token' => $pageToken,
    ]));

    if (!empty($ig['instagram_business_account']['id'])) {
      $igId = h($ig['instagram_business_account']['id']);
      $igU  = h($ig['instagram_business_account']['username'] ?? '');
    }
  }

  echo "<tr><td>{$pName}</td><td>{$pId}</td><td>{$igU}</td><td>{$igId}</td></tr>";
}
echo "</table>";

echo "<hr><p>If you see permission errors (#200/#190), ensure you requested: pages_show_list, pages_read_engagement, read_insights, instagram_basic, instagram_manage_insights (and that IG is Business & linked to the Page).</p>";
