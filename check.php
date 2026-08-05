<?php
// ============================================================
//  check.php — Diagnostic tool for the messages.php real-time
//  chat issue (receiver not getting messages without refresh).
//
//  Open this in your browser while logged in:
//    https://CARSOKO.page.gd/check.php
//
//  It checks, in order:
//   1. PHP/server environment
//   2. Session & login status
//   3. Whether the NEW messages.php / poll-messages.php files
//      are actually the ones deployed on the server
//   4. A live, server-side test of the poll endpoint — this
//      bypasses the browser entirely, so it tells us whether
//      the problem is server-side (deployment/caching) or
//      client-side (JS not running in the browser).
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once 'connection.php';
Auth::requireLogin('/login.php');

header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Chat Diagnostics</title></head>
<body style="background:#0a0a0b;margin:0;padding:24px">
<pre style="background:#111;color:#ddd;padding:24px;font-family:'Consolas',monospace;font-size:13px;line-height:1.6;border-radius:8px;max-width:1000px;margin:0 auto;overflow-x:auto">
<?php

function ok($label)   { echo "<span style='color:#22c55e'>OK   $label</span>\n"; }
function bad($label)  { echo "<span style='color:#ef4444'>FAIL $label</span>\n"; }

// ============================================================
// 1. ENVIRONMENT
// ============================================================
echo "==================== 1. ENVIRONMENT ====================\n";
echo "PHP version:       " . PHP_VERSION . "\n";
echo "Server software:   " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n";
echo "curl extension:    " . (extension_loaded('curl') ? 'yes' : 'NO - internal self-test in section 5 will be skipped') . "\n";
echo "Request scheme:    " . (($_SERVER['HTTPS'] ?? '') !== '' ? 'https' : 'http') . "\n";
echo "Request host:      " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n";
echo "BASE_URL constant: " . BASE_URL . "\n";
$currentOrigin = (($_SERVER['HTTPS'] ?? '') !== '' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
if (strtolower(rtrim($currentOrigin, '/')) !== strtolower(rtrim(BASE_URL, '/'))) {
    bad("The address you're actually visiting does NOT match BASE_URL exactly.");
    echo "  You are on:  $currentOrigin\n";
    echo "  BASE_URL is: " . BASE_URL . "\n";
    echo "  This matters: the JS on messages.php calls the poll endpoint using BASE_URL.\n";
    echo "  If that doesn't match the address bar exactly (http vs https, www vs non-www),\n";
    echo "  the fetch() call can silently fail or get blocked as cross-origin.\n";
} else {
    ok("Visited address matches BASE_URL.");
}
echo "\n";

// ============================================================
// 2. SESSION / LOGIN
// ============================================================
echo "==================== 2. SESSION / LOGIN ====================\n";
$loggedIn = Auth::check();
if ($loggedIn) { ok("Logged in as user #" . Auth::id()); } else { bad("Not logged in - unexpected since this page requires login."); }
$cookieParams = session_get_cookie_params();
echo "Session cookie params: " . json_encode($cookieParams) . "\n";
if (!empty($cookieParams['secure']) && (($_SERVER['HTTPS'] ?? '') === '')) {
    bad("session.cookie_secure is ON but you're viewing this over plain HTTP.");
    echo "  If that's also true when the chat page runs, the session cookie won't be sent\n";
    echo "  on fetch() calls, and poll-messages.php would silently report 'not logged in'.\n";
} else {
    ok("Cookie secure flag is consistent with how you're accessing the site.");
}
echo "\n";

// ============================================================
// 3. FILE DEPLOYMENT CHECK
// ============================================================
echo "==================== 3. FILE DEPLOYMENT CHECK ====================\n";
$msgFile  = __DIR__ . '/messages.php';
$pollFile = __DIR__ . '/ajax/poll-messages.php';

if (!file_exists($msgFile)) {
    bad("messages.php not found at $msgFile");
} else {
    $mtime = filemtime($msgFile);
    $size  = filesize($msgFile);
    echo "messages.php found - size: {$size} bytes, last modified: " . date('Y-m-d H:i:s', $mtime) . "\n";
    $content = file_get_contents($msgFile);
    $markers = [
        'poll-messages.php'  => 'references the poll endpoint',
        'setInterval(poll'   => 'has the polling timer',
        'pollUrl'            => 'has the pollUrl JS variable',
        'data-poll-url'      => 'has the data-poll-url attribute on the form',
        'msg-content'        => 'has the fixed CSS bubble wrapper',
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' => 'sends no-cache headers on the page itself (critical fix)',
    ];
    foreach ($markers as $needle => $desc) {
        if (strpos($content, $needle) !== false) {
            ok("Found '$needle' - $desc");
        } else {
            bad("MISSING '$needle' - $desc. This file is NOT the updated version!");
        }
    }
}
echo "\n";

if (!file_exists($pollFile)) {
    bad("ajax/poll-messages.php NOT FOUND at $pollFile - this is very likely the whole problem.");
    echo "  Upload the poll-messages.php file into your /ajax/ folder on the server.\n";
} else {
    $mtime = filemtime($pollFile);
    $size  = filesize($pollFile);
    echo "ajax/poll-messages.php found - size: {$size} bytes, last modified: " . date('Y-m-d H:i:s', $mtime) . "\n";
    $content = file_get_contents($pollFile);
    if (strpos($content, 'no-store') !== false) {
        ok("Has no-cache headers.");
    } else {
        bad("Missing no-cache headers - old version of this file.");
    }
}
echo "\n";

// ============================================================
// 4. DATABASE / CONVERSATION DATA
// ============================================================
echo "==================== 4. DATABASE ====================\n";
$convs = [];
if (!DB::isConnected()) {
    bad("Database not connected - check DB credentials in connection.php");
} else {
    ok("Database connected.");
    $myId = Auth::id();
    $convs = DB::select(
        "SELECT id, last_message, last_message_at FROM conversations
         WHERE buyer_id = ? OR seller_id = ? ORDER BY last_message_at DESC LIMIT 5",
        [$myId, $myId]
    );
    echo count($convs) . " conversation(s) found for you:\n";
    foreach ($convs as $c) {
        $count = DB::value("SELECT COUNT(*) FROM messages WHERE conversation_id=?", [$c['id']]);
        echo "  conv #{$c['id']}  - {$count} messages - last: " . e($c['last_message'] ?? '') . "\n";
    }
}
echo "\n";

// ============================================================
// 5. LIVE SELF-TEST OF THE POLL ENDPOINT
// ============================================================
echo "==================== 5. LIVE SELF-TEST ====================\n";
echo "This calls ajax/poll-messages.php exactly like the browser JS does,\n";
echo "but from the server itself - bypassing your browser and any browser\n";
echo "cache entirely. If this section also shows stale/cached data, the\n";
echo "problem is server/host-side. If this section works correctly, the\n";
echo "problem is 100% in the browser/JS, not the server.\n\n";

if (!extension_loaded('curl')) {
    bad("curl extension not available - cannot run this test. Skipping.");
} elseif (empty($convs)) {
    bad("No conversation found to test with - skipping.");
} else {
    $testConvId = $convs[0]['id'];
    $url = rtrim(BASE_URL, '/') . '/ajax/poll-messages.php?conv_id=' . $testConvId . '&after_id=0&_=' . time();
    echo "Testing URL: $url\n\n";

    // Forward this script's own session cookie so the internal request is "logged in" too
    $cookieHeader = 'Cookie: ' . session_name() . '=' . session_id();

    for ($i = 1; $i <= 2; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => [$cookieHeader, 'X-Requested-With: XMLHttpRequest'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        echo "--- Call #$i ---\n";
        if ($err) {
            bad("cURL error: $err");
            echo "  (Some free hosts block a server calling its own domain - if so, this\n";
            echo "  test is inconclusive, but it does NOT mean the browser test would fail too.)\n\n";
            continue;
        }
        echo "HTTP status: $code\n";
        $headers = substr($resp, 0, $headerSize);
        $body    = substr($resp, $headerSize);

        preg_match('/cache-control:.*/i', $headers, $ccMatch);
        echo "Cache-Control header: " . trim($ccMatch[0] ?? '(none found!)') . "\n";
        echo "Raw body: " . trim($body) . "\n\n";

        if (stripos($headers, 'cache-control') === false) {
            bad("No Cache-Control header at all - old poll-messages.php or something is stripping it.");
        }
        sleep(1);
    }
}

echo "\n==================== DONE ====================\n";
echo "Delete this file (check.php) once you're done diagnosing - it exposes internals.\n";
?>
</pre>
</body>
</html>