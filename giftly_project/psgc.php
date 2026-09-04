<?php
/**
 * Server-side proxy for the free PSGC (Philippine Standard Geographic Code) API,
 * so the address picker has no CORS issues and no API key.
 * Source: https://psgc.gitlab.io/api/   Cached for a day in the temp dir.
 *
 *   psgc.php?type=regions
 *   psgc.php?type=provinces&code=<regionCode>
 *   psgc.php?type=cities&code=<provinceCode>
 *   psgc.php?type=cities-region&code=<regionCode>      (NCR / region-level)
 *   psgc.php?type=barangays&code=<cityMunCode>
 */

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$code = preg_replace('/[^0-9]/', '', (string) ($_GET['code'] ?? ''));

$paths = [
    'regions'        => '/regions/',
    'provinces'      => "/regions/$code/provinces/",
    'cities'         => "/provinces/$code/cities-municipalities/",
    'cities-region'  => "/regions/$code/cities-municipalities/",
    'barangays'      => "/cities-municipalities/$code/barangays/",
];

if (!isset($paths[$type]) || ($type !== 'regions' && $code === '')) {
    echo '[]';
    exit();
}

$path  = $paths[$type];
$cache = sys_get_temp_dir() . '/psgc_' . md5($path) . '.json';

if (is_file($cache) && filemtime($cache) > time() - 86400) {
    echo file_get_contents($cache);
    exit();
}

$ctx = stream_context_create(['http' => [
    'timeout' => 12,
    'header'  => "User-Agent: Giftly/1.0\r\nAccept: application/json\r\n",
    'ignore_errors' => true,
]]);
$raw  = @file_get_contents('https://psgc.gitlab.io/api' . $path, false, $ctx);
$data = json_decode((string) $raw, true);

if (!is_array($data)) {
    // don't cache failures; let the client fall back to manual entry
    echo '[]';
    exit();
}

$out = [];
foreach ($data as $row) {
    if (!empty($row['name'])) {
        $out[] = ['code' => (string) ($row['code'] ?? ''), 'name' => $row['name']];
    }
}
usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));

$json = json_encode($out);
@file_put_contents($cache, $json);
echo $json;
