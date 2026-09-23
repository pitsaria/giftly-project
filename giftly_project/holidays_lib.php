<?php
/**
 * "Coming up" occasions — the Nager.Date public-holiday API (fetched
 * server-side and cached on disk), plus gifting observances Nager doesn't
 * list, plus a logged-in user's saved birthdays/anniversaries.
 *
 * Used by the website homepage (index.php, via occasions_upcoming()) and the
 * mobile API (api/services/HolidayService.php serves the raw holiday list).
 */

if (!function_exists('holidays_all')) {

    define('HOLIDAYS_COUNTRY', 'PH');
    define('HOLIDAYS_CACHE_TTL', 604800);      // 7 days — holiday dates barely change
    define('HOLIDAYS_RETRY_AFTER', 300);       // after a failed fetch, don't retry for 5 min
    define('OCCASION_PREP_DAYS', 3);           // boxes need at least 3 days to prepare
    define('OCCASION_PERSONAL_WINDOW_DAYS', 45);

    /** This year's and next year's public holidays, so a December visit still sees January's. */
    function holidays_all() {
        $thisYear = (int) gmdate('Y');
        $all = [];
        foreach ([$thisYear, $thisYear + 1] as $year) {
            foreach (holidays_for_year($year) as $h) $all[] = $h;
        }
        return $all;
    }

    /** [['date' => 'YYYY-MM-DD', 'name' => ..., 'local_name' => ...], ...] — cached, [] if unavailable. */
    function holidays_for_year($year) {
        $year = (int) $year;
        $base = sys_get_temp_dir() . '/giftly_holidays_' . HOLIDAYS_COUNTRY . "_$year";
        $cache = "$base.json";
        $failMarker = "$base.fail";

        if (is_file($cache) && (time() - filemtime($cache)) < HOLIDAYS_CACHE_TTL) {
            $cached = json_decode((string) @file_get_contents($cache), true);
            if (is_array($cached) && $cached) return $cached;
        }

        // Upstream recently failed — don't make every page view wait on it again.
        $recentlyFailed = is_file($failMarker) && (time() - filemtime($failMarker)) < HOLIDAYS_RETRY_AFTER;
        if (!$recentlyFailed) {
            $fetched = holidays_fetch_year($year);
            if ($fetched) {
                @file_put_contents($cache, json_encode($fetched));
                @unlink($failMarker);
                return $fetched;
            }
            @touch($failMarker);
        }

        // A stale copy beats showing nothing.
        if (is_file($cache)) {
            $stale = json_decode((string) @file_get_contents($cache), true);
            if (is_array($stale)) return $stale;
        }
        return [];
    }

    /** One Nager.Date request. Returns the normalized list, or null on any failure. */
    function holidays_fetch_year($year) {
        $url = 'https://date.nager.at/api/v3/PublicHolidays/' . (int) $year . '/' . HOLIDAYS_COUNTRY;
        $resp = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $resp = ($body !== false && $code === 200) ? $body : null;
        } else {
            $ctx = stream_context_create(['http' => [
                'timeout' => 4,
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

    /** Nager.Date holidays (keyed by lowercase English name) people actually give gifts for. */
    function occasions_gift_holidays() {
        return [
            'christmas day'    => ['🎄', "The season's biggest gift day"],
            "new year's day"   => ['🎆', 'Kick off the year with something sweet'],
            'chinese new year' => ['🧧', 'Red envelopes & lucky treats'],
        ];
    }

    function occasions_countdown($days) {
        if ($days === 0) return 'Today';
        if ($days === 1) return 'Tomorrow';
        return "in $days days";
    }

    /**
     * The next few gift-worthy dates, soonest first. Each item:
     * key, name, emoji, tagline, date (DateTime), days_away, order_by
     * (DateTime|null once it's too late to prepare), personal, href.
     * Pass $conn + $user_id to include that user's saved birthdays.
     */
    function occasions_upcoming($conn = null, $user_id = 0, $limit = 5) {
        $today = new DateTime('today');
        $items = [];

        $add = function ($key, $name, $emoji, $tagline, DateTime $date, $personal, $href) use (&$items, $today) {
            $days = (int) $today->diff($date)->format('%r%a');
            if ($days < 0) return;
            $orderBy = (clone $date)->modify('-' . OCCASION_PREP_DAYS . ' days');
            $items[] = [
                'key'       => $key,
                'name'      => $name,
                'emoji'     => $emoji,
                'tagline'   => $tagline,
                'date'      => $date,
                'days_away' => $days,
                'order_by'  => ($orderBy >= $today) ? $orderBy : null,
                'personal'  => $personal,
                'href'      => $href,
            ];
        };

        // Official dates from the Nager.Date API.
        $gift = occasions_gift_holidays();
        foreach (holidays_all() as $h) {
            $meta = $gift[strtolower($h['name'])] ?? null;
            if (!$meta) continue;
            $date = DateTime::createFromFormat('!Y-m-d', $h['date']);
            if (!$date) continue;
            $add($h['name'] . '-' . $h['date'], $h['name'], $meta[0], $meta[1], $date, false, 'shop.php');
        }

        // Gifting occasions that aren't public holidays, so Nager doesn't list them.
        $thisYear = (int) $today->format('Y');
        foreach ([$thisYear, $thisYear + 1] as $year) {
            $add("valentines-$year", "Valentine's Day", '💝', 'Make their heart skip a beat',
                new DateTime("$year-02-14"), false, 'shop.php');
            $add("mothers-day-$year", "Mother's Day", '🌷', 'Spoil the woman who spoils you',
                (new DateTime("second sunday of may $year"))->setTime(0, 0, 0), false, 'shop.php');
            $add("fathers-day-$year", "Father's Day", '👔', 'Something Dad will actually use',
                (new DateTime("third sunday of june $year"))->setTime(0, 0, 0), false, 'shop.php');
        }

        // The logged-in user's own saved dates, once they're close enough to act on.
        if ($conn && (int) $user_id > 0 && function_exists('recip_upcoming_for_user')) {
            foreach (recip_upcoming_for_user($conn, (int) $user_id, 20) as $row) {
                if ((int) $row['days_until'] > OCCASION_PERSONAL_WINDOW_DAYS) continue;
                $date = (clone $today)->modify('+' . (int) $row['days_until'] . ' days');
                $add('personal-' . $row['id'],
                    $row['recipient_name'] . "'s " . strtolower(recip_occasion_label($row)),
                    recip_occasion_icon($row['occasion_type']),
                    $row['relationship'],
                    $date, true, 'profile.php?tab=relations');
            }
        }

        usort($items, function ($a, $b) { return $a['date'] <=> $b['date']; });
        return array_slice($items, 0, $limit);
    }
}
