<?php
/**
 * Saved recipients + occasion reminders ("My Relations").
 *
 * `recipients` is a per-user address book of people to gift; each recipient
 * can have multiple `recipient_occasions` (birthday, anniversary, or a custom
 * one-off like "Dala Ceremony"). Occasions recur yearly by month/day.
 */

if (!function_exists('recip_ensure_schema')) {

    function recip_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['recip_schema_ok_v2'])) {
            return;
        }

        $conn->query("CREATE TABLE IF NOT EXISTS recipients (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name VARCHAR(150) NOT NULL,
            relationship VARCHAR(50),
            phone VARCHAR(20),
            email VARCHAR(150),
            house_no VARCHAR(100),
            street VARCHAR(255),
            city_line VARCHAR(255),
            zip VARCHAR(20),
            notes VARCHAR(500),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_recipients_user_id ON recipients(user_id)");
        $conn->query("ALTER TABLE recipients ADD COLUMN IF NOT EXISTS photo VARCHAR(500)");

        $conn->query("CREATE TABLE IF NOT EXISTS recipient_occasions (
            id SERIAL PRIMARY KEY,
            recipient_id INTEGER NOT NULL REFERENCES recipients(id) ON DELETE CASCADE,
            occasion_type VARCHAR(20) NOT NULL DEFAULT 'other',
            label VARCHAR(100),
            occasion_date DATE NOT NULL,
            last_notified_year INTEGER,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_recipient_occasions_recipient_id ON recipient_occasions(recipient_id)");

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['recip_schema_ok_v2'] = true;
        }
    }

    /** Preset relationship options shown in the recipient form. */
    function recip_relationships() {
        return ['Mom', 'Dad', 'Spouse', 'Partner', 'Sibling', 'Child', 'Grandparent', 'Friend', 'Colleague', 'Other'];
    }

    /** Occasion type -> [label, icon]. 'other' uses the recipient-supplied label. */
    function recip_occasion_types() {
        return [
            'birthday'    => ['label' => 'Birthday',    'icon' => '🎂'],
            'anniversary' => ['label' => 'Anniversary', 'icon' => '💍'],
            'other'       => ['label' => 'Occasion',    'icon' => '🎉'],
        ];
    }

    /** Stable gradient per recipient, picked by id so it doesn't change on refresh. */
    function recip_avatar_gradient($id) {
        $palette = [
            'linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%)',
            'linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)',
            'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)',
            'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
            'linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%)',
            'linear-gradient(135deg, #f6d365 0%, #fda085 100%)',
            'linear-gradient(135deg, #c3aed6 0%, #f5c6de 100%)',
        ];
        return $palette[((int) $id) % count($palette)];
    }

    /** Stable {bg, fg} pill color for a relationship label — known ones get a fixed color, anything custom is hashed onto the palette so it's still consistent. */
    function recip_relationship_color($relationship) {
        $known = [
            'Mom'         => ['#ffe1ec', '#d6336c'],
            'Dad'         => ['#dbeafe', '#1d4ed8'],
            'Spouse'      => ['#ffe8cc', '#c2410c'],
            'Partner'     => ['#ffe8cc', '#c2410c'],
            'Sibling'     => ['#e0f2fe', '#0369a1'],
            'Child'       => ['#fef9c3', '#a16207'],
            'Grandparent' => ['#ede9fe', '#6d28d9'],
            'Friend'      => ['#dcfce7', '#15803d'],
            'Colleague'   => ['#f1f5f9', '#475569'],
        ];
        if (isset($known[$relationship])) return $known[$relationship];
        $palette = array_values($known);
        $idx = crc32((string) $relationship) % count($palette);
        return $palette[$idx];
    }

    /** Display label for one occasion row (custom label if type=other). */
    function recip_occasion_label($row) {
        $types = recip_occasion_types();
        $type = $row['occasion_type'] ?? 'other';
        if ($type === 'other' && !empty($row['label'])) return $row['label'];
        return $types[$type]['label'] ?? ucfirst($type);
    }

    function recip_occasion_icon($type) {
        $types = recip_occasion_types();
        return $types[$type]['icon'] ?? '🎉';
    }

    /**
     * Next calendar occurrence (today or later) of a recurring MM-DD date.
     * Returns a DateTime at midnight, or null if $date_str is unparsable.
     */
    function recip_next_occurrence($date_str) {
        $d = DateTime::createFromFormat('Y-m-d', (string) $date_str);
        if (!$d) return null;
        $month = (int) $d->format('n');
        $day   = (int) $d->format('j');

        $today = new DateTime('today');
        $year  = (int) $today->format('Y');

        for ($tryYear = $year; $tryYear <= $year + 1; $tryYear++) {
            $useDay = ($month === 2 && $day === 29 && !checkdate(2, 29, $tryYear)) ? 28 : $day;
            $cand = DateTime::createFromFormat('Y-n-j', "$tryYear-$month-$useDay");
            $cand->setTime(0, 0, 0);
            if ($cand >= $today) return $cand;
        }
        return $cand; // fallback (shouldn't normally hit)
    }

    /** Days from today until the next occurrence (0 = today). Null if unparsable. */
    function recip_days_until($date_str) {
        $next = recip_next_occurrence($date_str);
        if (!$next) return null;
        $today = new DateTime('today');
        return (int) $today->diff($next)->days;
    }

    /** One recipient the user owns, or null. */
    function recip_get($conn, $recipient_id, $user_id) {
        $recipient_id = (int) $recipient_id;
        $user_id = (int) $user_id;
        $r = $conn->query("SELECT * FROM recipients WHERE id = $recipient_id AND user_id = $user_id");
        return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    }

    /** All recipients for a user, each with an 'occasions' array (soonest first). */
    function recip_list_for_user($conn, $user_id) {
        $user_id = (int) $user_id;
        $recipients = [];
        $order = [];
        $res = $conn->query("SELECT * FROM recipients WHERE user_id = $user_id ORDER BY id DESC");
        while ($res && $row = $res->fetch_assoc()) {
            $row['occasions'] = [];
            $recipients[$row['id']] = $row;
            $order[] = $row['id'];
        }
        if ($order) {
            $ids = implode(',', array_map('intval', $order));
            $ores = $conn->query("SELECT * FROM recipient_occasions WHERE recipient_id IN ($ids)");
            $byRecipient = [];
            while ($ores && $orow = $ores->fetch_assoc()) {
                $orow['days_until'] = recip_days_until($orow['occasion_date']);
                $byRecipient[$orow['recipient_id']][] = $orow;
            }
            foreach ($byRecipient as $rid => $occasions) {
                usort($occasions, function ($a, $b) { return $a['days_until'] <=> $b['days_until']; });
                $recipients[$rid]['occasions'] = $occasions;
            }
        }
        return array_values($recipients);
    }

    /** Every upcoming occasion for a user's recipients, soonest first. */
    function recip_upcoming_for_user($conn, $user_id, $limit = 20) {
        $user_id = (int) $user_id;
        $rows = [];
        $res = $conn->query("SELECT ro.*, r.name AS recipient_name, r.relationship, r.photo, r.id AS recipient_id
                             FROM recipient_occasions ro
                             JOIN recipients r ON r.id = ro.recipient_id
                             WHERE r.user_id = $user_id");
        while ($res && $row = $res->fetch_assoc()) {
            $days = recip_days_until($row['occasion_date']);
            if ($days === null) continue;
            $row['days_until'] = $days;
            $rows[] = $row;
        }
        usort($rows, function ($a, $b) { return $a['days_until'] <=> $b['days_until']; });
        return array_slice($rows, 0, $limit);
    }

    /** Create a recipient. Returns the new id. */
    function recip_create($conn, $user_id, $data) {
        $user_id = (int) $user_id;
        $name         = $conn->real_escape_string(mb_substr(trim($data['name'] ?? ''), 0, 150));
        $relationship = $conn->real_escape_string(mb_substr(trim($data['relationship'] ?? ''), 0, 50));
        $phone        = $conn->real_escape_string(mb_substr(trim($data['phone'] ?? ''), 0, 20));
        $email        = $conn->real_escape_string(mb_substr(trim($data['email'] ?? ''), 0, 150));
        $house_no     = $conn->real_escape_string(mb_substr(trim($data['house_no'] ?? ''), 0, 100));
        $street       = $conn->real_escape_string(mb_substr(trim($data['street'] ?? ''), 0, 255));
        $city_line    = $conn->real_escape_string(mb_substr(trim($data['city_line'] ?? ''), 0, 255));
        $zip          = $conn->real_escape_string(mb_substr(trim($data['zip'] ?? ''), 0, 20));
        $notes        = $conn->real_escape_string(mb_substr(trim($data['notes'] ?? ''), 0, 500));
        $photo        = trim($data['photo'] ?? '');
        $photo_sql    = $photo !== '' ? "'" . $conn->real_escape_string(mb_substr($photo, 0, 500)) . "'" : 'NULL';

        $conn->query("INSERT INTO recipients (user_id, name, relationship, phone, email, house_no, street, city_line, zip, notes, photo)
                      VALUES ($user_id, '$name', '$relationship', '$phone', '$email', '$house_no', '$street', '$city_line', '$zip', '$notes', $photo_sql)");
        return (int) $conn->insert_id;
    }

    /** Update a recipient's core fields (ownership-checked). */
    function recip_update($conn, $recipient_id, $user_id, $data) {
        $recipient_id = (int) $recipient_id;
        $user_id = (int) $user_id;
        $name         = $conn->real_escape_string(mb_substr(trim($data['name'] ?? ''), 0, 150));
        $relationship = $conn->real_escape_string(mb_substr(trim($data['relationship'] ?? ''), 0, 50));
        $phone        = $conn->real_escape_string(mb_substr(trim($data['phone'] ?? ''), 0, 20));
        $email        = $conn->real_escape_string(mb_substr(trim($data['email'] ?? ''), 0, 150));
        $house_no     = $conn->real_escape_string(mb_substr(trim($data['house_no'] ?? ''), 0, 100));
        $street       = $conn->real_escape_string(mb_substr(trim($data['street'] ?? ''), 0, 255));
        $city_line    = $conn->real_escape_string(mb_substr(trim($data['city_line'] ?? ''), 0, 255));
        $zip          = $conn->real_escape_string(mb_substr(trim($data['zip'] ?? ''), 0, 20));
        $notes        = $conn->real_escape_string(mb_substr(trim($data['notes'] ?? ''), 0, 500));
        $photo        = trim($data['photo'] ?? '');
        $photo_sql    = $photo !== '' ? "'" . $conn->real_escape_string(mb_substr($photo, 0, 500)) . "'" : 'NULL';

        $conn->query("UPDATE recipients SET
                        name = '$name', relationship = '$relationship', phone = '$phone', email = '$email',
                        house_no = '$house_no', street = '$street', city_line = '$city_line', zip = '$zip', notes = '$notes',
                        photo = $photo_sql
                      WHERE id = $recipient_id AND user_id = $user_id");
        return $conn->affected_rows > 0;
    }

    /** Delete a recipient (and its occasions, via FK cascade). Ownership-checked. */
    function recip_delete($conn, $recipient_id, $user_id) {
        $recipient_id = (int) $recipient_id;
        $user_id = (int) $user_id;
        $r = recip_get($conn, $recipient_id, $user_id);
        if ($r && !empty($r['photo']) && function_exists('supabase_delete_image')) {
            supabase_delete_image($r['photo']);
        }
        $conn->query("DELETE FROM recipients WHERE id = $recipient_id AND user_id = $user_id");
        return $conn->affected_rows > 0;
    }

    /** Add an occasion to a recipient the user owns. Returns the new id, or 0. */
    function recip_occasion_add($conn, $recipient_id, $user_id, $data) {
        $r = recip_get($conn, $recipient_id, $user_id);
        if (!$r) return 0;

        $type = in_array($data['occasion_type'] ?? '', ['birthday', 'anniversary', 'other'], true) ? $data['occasion_type'] : 'other';
        $label = $conn->real_escape_string(mb_substr(trim($data['label'] ?? ''), 0, 100));
        $date = trim($data['occasion_date'] ?? '');
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d) return 0;
        $date_esc = $conn->real_escape_string($date);

        $conn->query("INSERT INTO recipient_occasions (recipient_id, occasion_type, label, occasion_date)
                      VALUES (" . (int) $recipient_id . ", '$type', '$label', '$date_esc')");
        return (int) $conn->insert_id;
    }

    /** Delete one occasion, ownership-checked via its parent recipient. */
    function recip_occasion_delete($conn, $occasion_id, $user_id) {
        $occasion_id = (int) $occasion_id;
        $user_id = (int) $user_id;
        $conn->query("DELETE FROM recipient_occasions ro
                      USING recipients r
                      WHERE ro.recipient_id = r.id AND ro.id = $occasion_id AND r.user_id = $user_id");
        return $conn->affected_rows > 0;
    }

    /**
     * Email the owner of $occasion_row (must include recipient_name, recipient_id,
     * user email/name via the join in recip_send_due_reminders) that an occasion
     * is coming up. No-op if mail isn't configured.
     */
    function recip_send_reminder_email($to_email, $owner_name, $recipient_name, $recipient_id, $occasion_row, $days) {
        if (!function_exists('mail_send') || !mail_configured()) return false;

        $label = recip_occasion_label($occasion_row);
        $icon  = recip_occasion_icon($occasion_row['occasion_type'] ?? 'other');
        $when  = ($days === 0) ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days");

        $giftUrl = rtrim(app_base_url_safe(), '/') . '/gift_start.php?recipient_id=' . (int) $recipient_id
                 . '&occasion_id=' . (int) $occasion_row['id'];

        $heading = "$icon " . htmlspecialchars($recipient_name) . "'s $label is $when!";
        $inner = '<p style="color:#555;font-size:14px;line-height:1.6;">Don\'t miss it — '
               . '<strong>' . htmlspecialchars($recipient_name) . '</strong>\'s ' . htmlspecialchars(strtolower($label))
               . ' is <strong>' . htmlspecialchars($when) . '</strong>.</p>'
               . '<p style="text-align:center;margin:22px 0;">'
               . '<a href="' . htmlspecialchars($giftUrl) . '" style="display:inline-block;padding:13px 30px;border-radius:50px;'
               . 'background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%);color:#fff;text-decoration:none;font-weight:600;">'
               . 'Send a gift now 🎁</a></p>'
               . '<p style="color:#999;font-size:12.5px;">Manage your saved people anytime under '
               . '<a href="' . htmlspecialchars(rtrim(app_base_url_safe(), '/')) . '/profile.php?tab=relations" style="color:#ff8ba7;">My Relations</a>.</p>';

        return mail_send($to_email, "$icon " . $recipient_name . "'s $label is $when", mail_wrap($heading, $inner));
    }

    /**
     * Global sweep: email owners whose recipients have an occasion within
     * $days_before days, once per occurrence (tracked by last_notified_year).
     * Meant to be called opportunistically (like pay_sweep_stale()), not via
     * a real cron — safe to call repeatedly, cheap when nothing is due.
     */
    function recip_send_due_reminders($conn, $days_before = 5) {
        if (!function_exists('mail_configured') || !mail_configured()) return;

        $res = $conn->query("SELECT ro.*, r.name AS recipient_name, r.id AS recipient_id,
                                    u.email AS to_email, u.name AS owner_name
                             FROM recipient_occasions ro
                             JOIN recipients r ON r.id = ro.recipient_id
                             JOIN users u ON u.id = r.user_id
                             LIMIT 500");
        $sent = 0;
        while ($res && $row = $res->fetch_assoc()) {
            if ($sent >= 50) break; // safety cap per sweep
            $next = recip_next_occurrence($row['occasion_date']);
            if (!$next) continue;
            $today = new DateTime('today');
            $days = (int) $today->diff($next)->days;
            if ($days > $days_before) continue;

            $targetYear = (int) $next->format('Y');
            $lastYear = $row['last_notified_year'] !== null ? (int) $row['last_notified_year'] : null;
            if ($lastYear === $targetYear) continue; // already notified for this occurrence

            if (empty($row['to_email'])) continue;

            $ok = recip_send_reminder_email($row['to_email'], $row['owner_name'], $row['recipient_name'], $row['recipient_id'], $row, $days);
            if ($ok) {
                $sent++;
                $conn->query("UPDATE recipient_occasions SET last_notified_year = $targetYear WHERE id = " . (int) $row['id']);
            }
        }
    }
}
