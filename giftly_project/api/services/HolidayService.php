<?php
// api/services/HolidayService.php
// Public-holiday dates from the Nager.Date API (https://date.nager.at).
// Fetched server-side and cached on disk, so the app never depends on the
// third party at request time and a slow/down upstream can't break Home.

require_once 'config/database.php';

class HolidayService {
    private const COUNTRY = 'PH';
    private const CACHE_TTL = 604800; // 7 days — holiday dates barely change

    // GET holidays — this year's and next year's public holidays, so a
    // December visit still sees January's.
    public function get() {
        $thisYear = (int) gmdate('Y');
        $holidays = [];
        foreach ([$thisYear, $thisYear + 1] as $year) {
            foreach ($this->forYear($year) as $h) {
                $holidays[] = $h;
            }
        }
        sendSuccess(['country' => self::COUNTRY, 'holidays' => $holidays]);
    }

    private function forYear($year) {
        $cache = sys_get_temp_dir() . '/giftly_holidays_' . self::COUNTRY . "_$year.json";

        if (is_file($cache) && (time() - filemtime($cache)) < self::CACHE_TTL) {
            $cached = json_decode((string) @file_get_contents($cache), true);
            if (is_array($cached) && $cached) return $cached;
        }

        $fetched = $this->fetchYear($year);
        if ($fetched) {
            @file_put_contents($cache, json_encode($fetched));
            return $fetched;
        }

        // Upstream unreachable — a stale copy beats showing nothing.
        if (is_file($cache)) {
            $stale = json_decode((string) @file_get_contents($cache), true);
            if (is_array($stale)) return $stale;
        }
        return [];
    }

    // Returns [['date' => 'YYYY-MM-DD', 'name' => ..., 'local_name' => ...], ...]
    // or null on any failure.
    private function fetchYear($year) {
        $url = 'https://date.nager.at/api/v3/PublicHolidays/' . (int) $year . '/' . self::COUNTRY;
        $resp = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $resp = ($body !== false && $code === 200) ? $body : null;
        } else {
            $ctx = stream_context_create(['http' => [
                'timeout' => 6,
                'header'  => "Accept: application/json\r\n",
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            $resp = $body === false ? null : $body;
        }

        $data = json_decode((string) $resp, true);
        if (!is_array($data) || !$data) return null;

        $out = [];
        foreach ($data as $h) {
            if (empty($h['date']) || empty($h['name'])) continue;
            $out[] = [
                'date'       => $h['date'],
                'name'       => $h['name'],
                'local_name' => $h['localName'] ?? $h['name'],
            ];
        }
        return $out ?: null;
    }
}
