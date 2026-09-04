<?php
/**
 * Server-side proxy for the free PSGC (Philippine Standard Geographic Code) API.
 * No API key, no CORS problems. Responses cached for a day in the temp dir.
 *
 *   psgc.php?type=regions
 *   psgc.php?type=provinces&code=<regionCode>
 *   psgc.php?type=cities&code=<provinceCode>
 *   psgc.php?type=cities-region&code=<regionCode>      (NCR / region-level)
 *   psgc.php?type=barangays&code=<cityMunCode>
 *   add &debug=1 to see the upstream status / error
 */

header('Content-Type: application/json');
header('Cache-Control: public, max-age=86400');

$type  = $_GET['type'] ?? '';
$code  = preg_replace('/[^0-9]/', '', (string) ($_GET['code'] ?? ''));
$debug = isset($_GET['debug']);

$paths = [
    'regions'       => '/regions/',
    'provinces'     => "/regions/$code/provinces/",
    'cities'        => "/provinces/$code/cities-municipalities/",
    'cities-region' => "/regions/$code/cities-municipalities/",
    'barangays'     => "/cities-municipalities/$code/barangays/",
];

if (!isset($paths[$type]) || ($type !== 'regions' && $code === '')) {
    echo $debug ? json_encode(['error' => 'bad params', 'list' => []]) : '[]';
    exit();
}

$path  = $paths[$type];
$cache = sys_get_temp_dir() . '/psgc_' . md5($path) . '.json';

if (!$debug && is_file($cache) && filemtime($cache) > time() - 86400) {
    $cached = file_get_contents($cache);
    if ($cached !== false && $cached !== '' && $cached !== '[]') {
        echo $cached;
        exit();
    }
}

// PSGC mirrors, tried in order.
$hosts = ['https://psgc.gitlab.io/api', 'https://psgc.cloud/api'];

$raw = null;
$status = 0;
$err = '';

foreach ($hosts as $host) {
    $url = $host . $path;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: Giftly/1.0'],
        ]);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'timeout'       => 15,
            'header'        => "Accept: application/json\r\nUser-Agent: Giftly/1.0\r\n",
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        $err = $raw === false ? 'stream fetch failed' : '';
    }

    $data = json_decode((string) $raw, true);
    if (is_array($data)) {
        $out = [];
        foreach ($data as $row) {
            if (!empty($row['name'])) {
                $out[] = ['code' => (string) ($row['code'] ?? ''), 'name' => $row['name']];
            }
        }
        usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));

        $json = json_encode($out);
        if (!empty($out)) {
            @file_put_contents($cache, $json);
        }
        echo $debug ? json_encode(['host' => $host, 'status' => $status, 'count' => count($out), 'list' => $out]) : $json;
        exit();
    }
}

error_log("PSGC proxy failed for $path (status $status): $err");
echo $debug
    ? json_encode(['error' => $err ?: 'no valid response', 'status' => $status, 'list' => []])
    : '[]';
