<?php
/**
 * TEMPORARY diagnostic — visit /supabase_check.php while logged in as admin.
 * Delete this file once storage uploads are confirmed working.
 */
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { http_response_code(403); exit('Login as admin first.'); }
$uid = (int) $_SESSION['user_id'];
$r = $conn->query("SELECT role FROM users WHERE id = $uid");
$u = $r ? $r->fetch_assoc() : null;
if (!$u || $u['role'] !== 'admin') { http_response_code(403); exit('Admin only.'); }

header('Content-Type: text/plain');

$c = supabase_storage_config();
echo "=== Config ===\n";
echo "SUPABASE_URL         : " . ($c['url'] !== '' ? $c['url'] : '(EMPTY)') . "\n";
echo "SUPABASE_SERVICE_KEY : " . ($c['key'] !== '' ? substr($c['key'], 0, 8) . '…(' . strlen($c['key']) . ' chars)' : '(EMPTY)') . "\n";
echo "SUPABASE_BUCKET      : " . $c['bucket'] . "\n";
if (stripos($c['key'], 'sb_publishable_') === 0 || (strlen($c['key']) > 0 && strlen($c['key']) < 60 && stripos($c['key'], 'sb_secret_') !== 0)) {
    echo "  ⚠ this looks like a PUBLISHABLE/anon key — you need the service_role (secret) key\n";
}
echo "curl extension       : " . (function_exists('curl_init') ? 'yes' : 'NO') . "\n";
echo "allow_url_fopen      : " . (ini_get('allow_url_fopen') ? 'on' : 'off') . "\n";
echo "upload_max_filesize  : " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size        : " . ini_get('post_max_size') . "\n\n";

if ($c['url'] === '' || $c['key'] === '') {
    echo "STOP: env vars are not visible to PHP. Re-check names in Render → Environment,\n";
    echo "then Manual Deploy (env changes need a redeploy/restart).\n";
    exit;
}

// 1x1 transparent PNG
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
$object = 'diag_' . time() . '.png';
$endpoint = $c['url'] . '/storage/v1/object/' . $c['bucket'] . '/' . $object;

echo "=== Test upload ===\n";
echo "PUT/POST $endpoint\n\n";

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $png,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $c['key'],
        'apikey: ' . $c['key'],
        'Content-Type: image/png',
        'x-upsert: true',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP status : $code\n";
echo "curl error  : " . ($err ?: '(none)') . "\n";
echo "response    : $resp\n\n";

if ($code >= 200 && $code < 300) {
    $public = $c['url'] . '/storage/v1/object/public/' . $c['bucket'] . '/' . $object;
    echo "SUCCESS. Public URL (open it in a browser — should show a tiny image):\n$public\n\n";
    echo "If that URL 404s, the bucket is PRIVATE. Make it public in Supabase → Storage.\n";
} else {
    echo "FAILED. Common causes by status:\n";
    echo "  400 / 'Bucket not found'  → SUPABASE_BUCKET name doesn't match the bucket\n";
    echo "  401 / 403 / 'Invalid JWT' → wrong key (must be service_role, not anon)\n";
    echo "  404                       → SUPABASE_URL wrong\n";
}
