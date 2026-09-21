<?php
// api/services/HolidayService.php
// Public-holiday dates from the Nager.Date API (https://date.nager.at).
// The fetch + disk cache live in ../../holidays_lib.php (shared with the
// website homepage), so the app never depends on the third party at
// request time and a slow/down upstream can't break Home.

require_once 'config/database.php';
require_once __DIR__ . '/../../holidays_lib.php';

class HolidayService {
    // GET holidays — this year's and next year's public holidays.
    public function get() {
        sendSuccess(['country' => HOLIDAYS_COUNTRY, 'holidays' => holidays_all()]);
    }
}
