<?php
$user_id = (int) $_SESSION['user_id'];
include_once 'recipients_lib.php';
recip_ensure_schema($conn);

/** Combine the barangay/city/province inputs into the single stored city_line, same shape as checkout's "city" field. */
function rl_compose_city_line($post) {
    $barangay = trim($post['barangay'] ?? '');
    $city     = trim($post['city_town'] ?? '');
    $province = trim($post['province'] ?? '');
    $parts = [];
    if ($barangay !== '') $parts[] = preg_match('/^(brgy|barangay|bgy)\b/i', $barangay) ? $barangay : 'Brgy. ' . $barangay;
    if ($city !== '') $parts[] = $city;
    if ($province !== '') $parts[] = $province;
    return implode(', ', $parts);
}

function rl_redirect($flash = null) {
    if ($flash) $_SESSION['relations_flash'] = $flash;
    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=relations">';
    exit();
}

/** Upload a new photo if one was chosen. Returns [url_or_null, error_or_null]. */
function rl_handle_photo_upload() {
    if (empty($_FILES['photo']['name'])) return [null, null];
    $url = supabase_upload_image($_FILES['photo']);
    if ($url === null) return [null, "Couldn't upload the photo (try a JPG or PNG under a few MB) — everything else was saved."];
    return [$url, null];
}

// --- ADD RECIPIENT (+ their first occasion) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_recipient'])) {
    $rel_choice = $_POST['relationship_choice'] ?? 'Other';
    if ($rel_choice === 'Other') {
        $relationship = trim($_POST['relationship_other'] ?? '') ?: 'Other';
    } else {
        $relationship = in_array($rel_choice, recip_relationships(), true) ? $rel_choice : 'Other';
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        rl_redirect(['type' => 'error', 'msg' => 'Please enter their name.']);
    }

    $house_no = trim($_POST['house_no'] ?? '');
    $street   = trim($_POST['address'] ?? '');
    $city_line = rl_compose_city_line($_POST);
    [$photo_url, $photo_err] = rl_handle_photo_upload();

    $new_id = recip_create($conn, $user_id, [
        'name'         => $name,
        'relationship' => $relationship,
        'phone'        => $_POST['phone'] ?? '',
        'email'        => $_POST['email'] ?? '',
        'house_no'     => $house_no,
        'street'       => $street,
        'city_line'    => $city_line,
        'zip'          => $_POST['zip'] ?? '',
        'notes'        => $_POST['notes'] ?? '',
        'photo'        => $photo_url ?? '',
    ]);

    $occ_date = trim($_POST['occasion_date'] ?? '');
    if ($new_id > 0 && $occ_date !== '') {
        recip_occasion_add($conn, $new_id, $user_id, [
            'occasion_type' => $_POST['occasion_type'] ?? 'birthday',
            'label'         => $_POST['occasion_label'] ?? '',
            'occasion_date' => $occ_date,
        ]);
    }

    rl_redirect($photo_err ? ['type' => 'error', 'msg' => $photo_err] : ['type' => 'ok', 'msg' => $name . ' was added to My Relations 🎉']);
}

// --- UPDATE RECIPIENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_recipient'])) {
    $rid = (int) ($_POST['recipient_id'] ?? 0);
    $existing = recip_get($conn, $rid, $user_id);
    if (!$existing) rl_redirect(['type' => 'error', 'msg' => 'That person could not be found.']);

    $rel_choice = $_POST['relationship_choice'] ?? 'Other';
    if ($rel_choice === 'Other') {
        $relationship = trim($_POST['relationship_other'] ?? '') ?: 'Other';
    } else {
        $relationship = in_array($rel_choice, recip_relationships(), true) ? $rel_choice : 'Other';
    }

    $house_no = trim($_POST['house_no'] ?? '');
    $street   = trim($_POST['address'] ?? '');
    $city_line = rl_compose_city_line($_POST);

    [$photo_url, $photo_err] = rl_handle_photo_upload();
    if ($photo_url !== null) {
        // replaced — clean up the old Supabase-hosted photo, if any
        if (!empty($existing['photo'])) supabase_delete_image($existing['photo']);
        $final_photo = $photo_url;
    } elseif (!empty($_POST['remove_photo'])) {
        if (!empty($existing['photo'])) supabase_delete_image($existing['photo']);
        $final_photo = '';
    } else {
        $final_photo = $existing['photo'] ?? '';
    }

    recip_update($conn, $rid, $user_id, [
        'name'         => $_POST['name'] ?? '',
        'relationship' => $relationship,
        'phone'        => $_POST['phone'] ?? '',
        'email'        => $_POST['email'] ?? '',
        'house_no'     => $house_no,
        'street'       => $street,
        'city_line'    => $city_line,
        'zip'          => $_POST['zip'] ?? '',
        'notes'        => $_POST['notes'] ?? '',
        'photo'        => $final_photo,
    ]);

    rl_redirect($photo_err ? ['type' => 'error', 'msg' => $photo_err] : ['type' => 'ok', 'msg' => 'Changes saved.']);
}

// --- DELETE RECIPIENT ---
if (isset($_GET['delete_recipient'])) {
    recip_delete($conn, (int) $_GET['delete_recipient'], $user_id);
    rl_redirect(['type' => 'ok', 'msg' => 'Removed from My Relations.']);
}

// --- ADD OCCASION TO AN EXISTING RECIPIENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_occasion'])) {
    recip_occasion_add($conn, (int) ($_POST['recipient_id'] ?? 0), $user_id, [
        'occasion_type' => $_POST['occasion_type'] ?? 'other',
        'label'         => $_POST['occasion_label'] ?? '',
        'occasion_date' => $_POST['occasion_date'] ?? '',
    ]);
    rl_redirect();
}

// --- DELETE OCCASION ---
if (isset($_GET['delete_occasion'])) {
    recip_occasion_delete($conn, (int) $_GET['delete_occasion'], $user_id);
    rl_redirect();
}

$relations_flash = $_SESSION['relations_flash'] ?? null;
unset($_SESSION['relations_flash']);

$recipients = recip_list_for_user($conn, $user_id);
$upcoming = recip_upcoming_for_user($conn, $user_id, 8);
$relationships = recip_relationships();
$occasion_types = recip_occasion_types();
?>

<style>
    .rl-flash { padding: 12px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 14px; }
    .rl-flash.error { background: #fdeded; border: 1px solid #ffc1cc; color: #d32f2f; }
    .rl-flash.ok { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; }

    /* --- hero intro --- */
    .rl-hero {
        position: relative; overflow: hidden; border-radius: 24px; padding: 26px 30px; margin-bottom: 26px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff;
        display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;
    }
    .rl-hero::before {
        content: ''; position: absolute; width: 220px; height: 220px; border-radius: 50%;
        background: rgba(255,255,255,0.12); top: -90px; right: -60px;
    }
    .rl-hero::after {
        content: ''; position: absolute; width: 140px; height: 140px; border-radius: 50%;
        background: rgba(255,255,255,0.10); bottom: -70px; right: 90px;
    }
    .rl-hero-text { position: relative; z-index: 1; }
    .rl-hero-title { font-size: 22px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; }
    .rl-hero-sub { font-size: 13.5px; opacity: 0.92; max-width: 480px; line-height: 1.5; }
    .rl-hero-stats { position: relative; z-index: 1; display: flex; gap: 10px; flex-wrap: wrap; }
    .rl-stat-pill {
        background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); border-radius: 16px;
        padding: 10px 16px; text-align: center; min-width: 84px; backdrop-filter: blur(4px);
    }
    .rl-stat-pill .n { font-size: 20px; font-weight: 700; line-height: 1; }
    .rl-stat-pill .l { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.4px; opacity: 0.9; margin-top: 3px; }
    .rl-hero-add {
        position: relative; z-index: 1; background: #fff; color: #ff8ba7; border: none; padding: 12px 26px;
        border-radius: 50px; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.2s; white-space: nowrap;
        box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    }
    .rl-hero-add:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }

    .rl-section-label { font-size: 13px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }

    /* --- upcoming reminders: horizontal scroll strip --- */
    /* .profile-main is a flex item (defined in profile.php); without min-width:0 its
       default min-width:auto lets the upcoming strip's un-shrinking cards (up to 8 at
       260px each) force the whole page wider than the navbar. */
    .profile-main { min-width: 0; }
    .rl-upcoming { display: flex; gap: 14px; overflow-x: auto; min-width: 0; max-width: 100%; padding: 4px 4px 14px; margin-bottom: 10px; scroll-snap-type: x proximity; }
    .rl-grid { min-width: 0; max-width: 100%; }
    .rl-upcoming::-webkit-scrollbar { height: 6px; }
    .rl-upcoming::-webkit-scrollbar-thumb { background: #ffd6e0; border-radius: 10px; }
    .rl-up-card {
        flex: 0 0 260px; scroll-snap-align: start; display: flex; align-items: center; gap: 12px;
        border: 1px solid #f0f0f0; border-radius: 18px; padding: 14px 16px; transition: 0.2s; background: #fff;
        box-shadow: 0 3px 10px rgba(0,0,0,0.02);
    }
    .rl-up-card:hover { border-color: #ffc1cc; box-shadow: 0 8px 20px rgba(255,139,167,0.12); transform: translateY(-2px); }
    .rl-up-card.urgent { border-color: #ffc1cc; background: linear-gradient(180deg, #fff8fa 0%, #ffffff 100%); }
    .rl-up-date {
        flex: 0 0 48px; height: 48px; border-radius: 14px; text-align: center; display: flex; flex-direction: column;
        align-items: center; justify-content: center; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff;
    }
    .rl-up-date .d { font-size: 17px; font-weight: 700; line-height: 1; }
    .rl-up-date .m { font-size: 9.5px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.4px; opacity: 0.9; }
    .rl-up-avatar, .rl-avatar {
        flex: 0 0 44px; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center;
        justify-content: center; color: #fff; font-weight: 700; font-size: 15px; overflow: hidden; position: relative;
    }
    .rl-up-avatar img, .rl-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .rl-avatar { flex-basis: 52px; width: 52px; height: 52px; font-size: 18px; box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
    .rl-up-info { flex: 1; min-width: 0; }
    .rl-up-info .who { font-size: 12.5px; color: #999; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rl-up-info .occ { font-size: 14px; font-weight: 700; color: #222; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rl-up-days { flex: 0 0 auto; font-size: 11px; font-weight: 700; color: #e65100; background: #fff3e0; padding: 4px 10px; border-radius: 50px; white-space: nowrap; margin-top: 4px; display: inline-block; }
    .rl-up-days.soon { color: #d32f2f; background: #fdeded; animation: rlPulse 1.8s ease-in-out infinite; }
    @keyframes rlPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(211,47,47,0.15); } 50% { box-shadow: 0 0 0 5px rgba(211,47,47,0); } }
    .rl-gift-btn {
        flex: 0 0 auto; width: 36px; height: 36px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; font-size: 14px;
        display: flex; align-items: center; justify-content: center; transition: 0.2s;
    }
    .rl-gift-btn:hover { transform: scale(1.12) rotate(-8deg); box-shadow: 0 4px 12px rgba(255,139,167,0.4); }

    /* --- recipient cards --- */
    .rl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 18px; }
    .rl-card {
        border: 1px solid #f0f0f0; border-radius: 22px; padding: 20px; transition: 0.25s; position: relative; overflow: hidden;
        opacity: 0; animation: rlPop 0.4s ease forwards;
    }
    @keyframes rlPop { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .rl-card:hover { box-shadow: 0 14px 34px rgba(0,0,0,0.07); border-color: #ffe1e8; transform: translateY(-3px); }
    .rl-card-cover { position: absolute; top: 0; left: 0; right: 0; height: 46px; opacity: 0.14; }
    .rl-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; position: relative; z-index: 5; }
    .rl-card-name { font-size: 16px; font-weight: 700; color: #222; }
    .rl-rel-pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 10px; border-radius: 50px; margin-top: 3px; }

    /* three-dot menu */
    .rl-menu-wrap { margin-left: auto; position: relative; }
    .rl-icon-btn { width: 32px; height: 32px; border-radius: 50%; border: none; background: #f5f5f5; color: #888; cursor: pointer; transition: 0.2s; }
    .rl-icon-btn:hover { background: #ffe1e8; color: #ff8ba7; }
    .rl-menu-drop {
        display: none; position: absolute; right: 0; top: 38px; background: #fff; border-radius: 14px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.14); border: 1px solid #f0f0f0; overflow: hidden; z-index: 20; min-width: 140px;
    }
    .rl-menu-drop.open { display: block; }
    .rl-menu-drop button { width: 100%; text-align: left; padding: 11px 16px; border: none; background: none; cursor: pointer; font-size: 13px; color: #444; display: flex; align-items: center; gap: 8px; font-family: 'Poppins'; }
    .rl-menu-drop button:hover { background: #fff0f5; color: #ff8ba7; }
    .rl-menu-drop button.danger:hover { background: #fdeded; color: #d32f2f; }

    .rl-occ-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; position: relative; z-index: 1; }
    .rl-occ-chip { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #555; background: #fafafa; border-radius: 12px; padding: 7px 10px; }
    .rl-occ-chip .lbl { font-weight: 600; color: #333; }
    .rl-occ-chip .date { color: #999; margin-left: auto; margin-right: 4px; }
    .rl-occ-chip .cd { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 50px; background: #eef3ff; color: #3f7fd6; }
    .rl-occ-chip .cd.soon { background: #fdeded; color: #d32f2f; }
    .rl-occ-del { border: none; background: none; color: #ccc; cursor: pointer; font-size: 12px; padding: 2px; }
    .rl-occ-del:hover { color: #d32f2f; }
    .rl-add-occ-toggle { font-size: 12px; color: #ff8ba7; font-weight: 600; cursor: pointer; margin-bottom: 12px; display: inline-block; position: relative; z-index: 1; }
    .rl-add-occ-form { display: none; background: #fff8fa; border-radius: 14px; padding: 12px; margin-bottom: 12px; position: relative; z-index: 1; }
    .rl-add-occ-form.show { display: block; }
    .rl-mini-input { width: 100%; padding: 9px 10px; border: 1.5px solid #eee; border-radius: 10px; font-size: 12.5px; font-family: 'Poppins'; margin-bottom: 8px; background: #fff; }
    .rl-mini-save { width: 100%; padding: 9px; border: none; border-radius: 10px; background: linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%); color: #fff; font-weight: 600; font-size: 12.5px; cursor: pointer; }

    .rl-card-footer { display: flex; position: relative; z-index: 1; }
    .rl-gift-btn-wide {
        flex: 1; padding: 10px; border: none; border-radius: 50px; cursor: pointer; font-size: 13px; font-weight: 600;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; transition: 0.2s;
    }
    .rl-gift-btn-wide:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.4); }

    /* --- empty state --- */
    .rl-empty { text-align: center; padding: 50px 20px; }
    .rl-empty-icon {
        width: 84px; height: 84px; border-radius: 50%; margin: 0 auto 18px; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #fff0f5 0%, #ffe1e8 100%); color: #ff8ba7; font-size: 34px;
    }
    .rl-empty h3 { font-size: 18px; color: #333; margin-bottom: 6px; }
    .rl-empty p { color: #999; font-size: 13.5px; max-width: 380px; margin: 0 auto 18px; line-height: 1.6; }

    /* modal (shared with the address-book style used elsewhere on this page) */
    .rl-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);
        backdrop-filter: blur(6px); z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px;
    }
    .rl-modal-box {
        background: #fff; border-radius: 30px; padding: 36px; max-width: 560px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15); max-height: 88vh; overflow-y: auto; animation: rlFadeUp 0.3s ease;
    }
    @keyframes rlFadeUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .rl-modal-title { font-size: 20px; font-weight: 700; color: #222; margin-bottom: 18px; }

    /* photo picker */
    .rl-photo-picker { display: flex; justify-content: center; margin-bottom: 20px; }
    .rl-photo-circle-wrap { position: relative; width: 92px; height: 92px; cursor: pointer; }
    .rl-photo-circle {
        width: 100%; height: 100%; border-radius: 50%;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 30px; font-weight: 700; overflow: hidden; border: 3px solid #fff; box-shadow: 0 4px 16px rgba(255,139,167,0.3);
    }
    .rl-photo-circle img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .rl-photo-circle.has-photo img { display: block; }
    .rl-photo-circle.has-photo .rl-photo-initial { display: none; }
    .rl-photo-badge {
        position: absolute; bottom: 0; right: 0; width: 30px; height: 30px; border-radius: 50%; background: #fff;
        color: #ff8ba7; display: flex; align-items: center; justify-content: center; font-size: 13px; border: 2px solid #ffe1e8;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .rl-photo-remove { display: block; margin: 8px auto 0; font-size: 12px; color: #d32f2f; background: none; border: none; cursor: pointer; text-decoration: underline; }

    .rl-form-section-title { font-size: 12.5px; font-weight: 700; color: #ff8ba7; text-transform: uppercase; letter-spacing: 0.5px; margin: 20px 0 10px; display: flex; align-items: center; gap: 6px; }
    .rl-form-section-title:first-of-type { margin-top: 0; }
    .rl-form-row { display: flex; gap: 16px; margin-bottom: 14px; }
    .rl-form-group { flex: 1; display: flex; flex-direction: column; }
    .rl-form-group label { font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; }
    .rl-form-input { padding: 12px 14px; border: 1.5px solid #eee; border-radius: 14px; font-size: 13.5px; font-family: 'Poppins'; background: #fafafa; outline: none; width: 100%; }
    .rl-form-actions { display: flex; gap: 10px; margin-top: 6px; }
    .rl-btn-cancel { padding: 12px 24px; border-radius: 50px; border: 1px solid #eee; background: #fff; cursor: pointer; font-weight: 500; }
    @media (max-width: 700px) { .rl-form-row { flex-direction: column; gap: 12px; } }

    .rl-delete-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);
        backdrop-filter: blur(6px); z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px;
    }
    .rl-delete-box { background: #fff; border-radius: 30px; padding: 40px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
</style>

<div class="rl-hero">
    <div class="rl-hero-text">
        <div class="rl-hero-title"><i class="fas fa-address-book"></i> My Relations</div>
        <div class="rl-hero-sub">Save the people you gift so you never miss a birthday or anniversary — then gift them in one click, prefilled and ready.</div>
    </div>
    <div class="rl-hero-stats">
        <div class="rl-stat-pill"><div class="n"><?php echo count($recipients); ?></div><div class="l">Saved</div></div>
        <div class="rl-stat-pill"><div class="n"><?php echo count($upcoming); ?></div><div class="l">Upcoming</div></div>
    </div>
    <button onclick="rlOpenAdd()" class="rl-hero-add"><i class="fas fa-plus"></i> Add Person</button>
</div>

<?php if ($relations_flash): ?>
    <div class="rl-flash <?php echo $relations_flash['type']; ?>"><?php echo htmlspecialchars($relations_flash['msg']); ?></div>
<?php endif; ?>

<?php if (!empty($upcoming)): ?>
    <div class="rl-section-label">Upcoming Reminders</div>
    <div class="rl-upcoming">
        <?php foreach ($upcoming as $u):
            $days = $u['days_until'];
            $next = recip_next_occurrence($u['occasion_date']);
            $soon = $days <= 3;
        ?>
            <div class="rl-up-card <?php echo $soon ? 'urgent' : ''; ?>">
                <div class="rl-up-date">
                    <div class="d"><?php echo $next->format('d'); ?></div>
                    <div class="m"><?php echo $next->format('M'); ?></div>
                </div>
                <div class="rl-up-avatar" style="background:<?php echo recip_avatar_gradient($u['recipient_id']); ?>;">
                    <?php if (!empty($u['photo'])): ?>
                        <img src="<?php echo htmlspecialchars(img_url($u['photo'])); ?>" alt="">
                    <?php else: ?>
                        <?php echo strtoupper(substr($u['recipient_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="rl-up-info">
                    <div class="who"><?php echo htmlspecialchars($u['recipient_name']); ?></div>
                    <div class="occ"><?php echo recip_occasion_icon($u['occasion_type']); ?> <?php echo htmlspecialchars(recip_occasion_label($u)); ?></div>
                    <div class="rl-up-days <?php echo $soon ? 'soon' : ''; ?>">
                        <?php echo $days === 0 ? 'Today!' : ($days === 1 ? 'Tomorrow' : "$days days"); ?>
                    </div>
                </div>
                <a href="gift_start.php?recipient_id=<?php echo (int) $u['recipient_id']; ?>&occasion_id=<?php echo (int) $u['id']; ?>" class="rl-gift-btn" title="Send a gift">
                    <i class="fas fa-gift"></i>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($recipients)): ?>
    <div class="rl-section-label" style="margin-top:8px;">Everyone You're Gifting</div>
    <div class="rl-grid">
        <?php foreach ($recipients as $idx => $r):
            $soonest = null;
            foreach ($r['occasions'] as $o) { if ($soonest === null || $o['days_until'] < $soonest['days_until']) $soonest = $o; }
            $gift_link = 'gift_start.php?recipient_id=' . (int) $r['id'] . ($soonest ? '&occasion_id=' . (int) $soonest['id'] : '');
            [$rel_bg, $rel_fg] = recip_relationship_color($r['relationship'] ?: 'Other');
            $gradient = recip_avatar_gradient($r['id']);
        ?>
            <div class="rl-card" style="animation-delay: <?php echo min($idx, 8) * 0.05; ?>s;">
                <div class="rl-card-cover" style="background:<?php echo $gradient; ?>;"></div>
                <div class="rl-card-head">
                    <div class="rl-avatar" style="background:<?php echo $gradient; ?>;">
                        <?php if (!empty($r['photo'])): ?>
                            <img src="<?php echo htmlspecialchars(img_url($r['photo'])); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(substr($r['name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="rl-card-name"><?php echo htmlspecialchars($r['name']); ?></div>
                        <span class="rl-rel-pill" style="background:<?php echo $rel_bg; ?>; color:<?php echo $rel_fg; ?>;"><?php echo htmlspecialchars($r['relationship'] ?: 'Relation'); ?></span>
                    </div>
                    <div class="rl-menu-wrap">
                        <button class="rl-icon-btn" title="More" onclick="rlToggleMenu(event, <?php echo (int) $r['id']; ?>)"><i class="fas fa-ellipsis-vertical"></i></button>
                        <div class="rl-menu-drop" id="rlMenu<?php echo (int) $r['id']; ?>">
                            <button type="button"
                                onclick='rlOpenEdit(<?php echo json_encode([
                                    "id" => (int) $r["id"], "name" => $r["name"], "relationship" => $r["relationship"],
                                    "phone" => $r["phone"], "email" => $r["email"], "house_no" => $r["house_no"],
                                    "street" => $r["street"], "city_line" => $r["city_line"], "zip" => $r["zip"], "notes" => $r["notes"],
                                    "photo" => $r["photo"] ? img_url($r["photo"]) : "",
                                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                <i class="fas fa-pen"></i> Edit
                            </button>
                            <button type="button" class="danger" onclick="rlOpenDelete(<?php echo (int) $r['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                        </div>
                    </div>
                </div>

                <?php if (!empty($r['occasions'])): ?>
                    <div class="rl-occ-list">
                        <?php foreach ($r['occasions'] as $o):
                            $od = DateTime::createFromFormat('Y-m-d', $o['occasion_date']);
                        ?>
                            <div class="rl-occ-chip">
                                <span><?php echo recip_occasion_icon($o['occasion_type']); ?></span>
                                <span class="lbl"><?php echo htmlspecialchars(recip_occasion_label($o)); ?></span>
                                <span class="date"><?php echo $od ? $od->format('M j') : ''; ?></span>
                                <span class="cd <?php echo $o['days_until'] <= 3 ? 'soon' : ''; ?>"><?php echo (int) $o['days_until']; ?>d</span>
                                <button class="rl-occ-del" onclick="rlOpenOccDelete(<?php echo (int) $o['id']; ?>)"><i class="fas fa-xmark"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#bbb; font-size:12.5px; margin-bottom:12px; position:relative; z-index:1;">No occasions saved yet.</p>
                <?php endif; ?>

                <span class="rl-add-occ-toggle" onclick="document.getElementById('rlOcc<?php echo (int) $r['id']; ?>').classList.toggle('show')">+ Add occasion</span>
                <div class="rl-add-occ-form" id="rlOcc<?php echo (int) $r['id']; ?>">
                    <form method="POST" action="profile.php?tab=relations">
                        <input type="hidden" name="add_occasion" value="1">
                        <input type="hidden" name="recipient_id" value="<?php echo (int) $r['id']; ?>">
                        <select name="occasion_type" class="rl-mini-input" onchange="this.form.querySelector('.rl-occ-label-wrap').style.display = this.value === 'other' ? 'block' : 'none';">
                            <?php foreach ($occasion_types as $key => $t): ?>
                                <option value="<?php echo $key; ?>"><?php echo $t['icon']; ?> <?php echo $t['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="rl-occ-label-wrap" style="display:none;">
                            <input type="text" name="occasion_label" class="rl-mini-input" placeholder="e.g. Dala Ceremony">
                        </div>
                        <input type="date" name="occasion_date" class="rl-mini-input" required>
                        <button type="submit" class="rl-mini-save">Save occasion</button>
                    </form>
                </div>

                <div class="rl-card-footer">
                    <a href="<?php echo $gift_link; ?>" class="rl-gift-btn-wide"><i class="fas fa-gift"></i> Send a gift</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="rl-empty">
        <div class="rl-empty-icon"><i class="fas fa-people-arrows"></i></div>
        <h3>No one saved yet</h3>
        <p>Add the people you love to gift — their birthday, anniversary, even a photo — and we'll remind you before their special days.</p>
        <button onclick="rlOpenAdd()" class="rl-hero-add" style="background:linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color:#fff;"><i class="fas fa-plus"></i> Add your first person</button>
    </div>
<?php endif; ?>

<!-- ADD / EDIT RECIPIENT MODAL -->
<div class="rl-modal-overlay" id="rlModal">
    <div class="rl-modal-box">
        <div class="rl-modal-title" id="rlModalTitle">Add Person</div>
        <form method="POST" action="profile.php?tab=relations" id="rlForm" enctype="multipart/form-data">
            <input type="hidden" name="add_recipient" value="1" id="rlFormAction">
            <input type="hidden" name="recipient_id" id="rlRecipientId" value="">
            <input type="hidden" name="remove_photo" id="rlRemovePhoto" value="">

            <div class="rl-photo-picker">
                <div class="rl-photo-circle-wrap" onclick="document.getElementById('rlPhotoInput').click()">
                    <div class="rl-photo-circle" id="rlPhotoCircle">
                        <span class="rl-photo-initial" id="rlPhotoInitial">?</span>
                        <img id="rlPhotoPreview" src="" alt="">
                    </div>
                    <span class="rl-photo-badge"><i class="fas fa-camera"></i></span>
                </div>
            </div>
            <input type="file" name="photo" id="rlPhotoInput" accept="image/*" style="display:none;" onchange="rlPreviewPhoto(this)">
            <button type="button" class="rl-photo-remove" id="rlPhotoRemoveBtn" style="display:none;" onclick="rlRemovePhoto()">Remove photo</button>

            <div class="rl-form-section-title"><i class="fas fa-user"></i> Basic Info</div>
            <div class="rl-form-row">
                <div class="rl-form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="rlName" class="rl-form-input" placeholder="e.g. Mom" required oninput="rlSyncInitial()">
                </div>
                <div class="rl-form-group">
                    <label>Relationship</label>
                    <select name="relationship_choice" id="rlRelChoice" class="rl-form-input" onchange="document.getElementById('rlRelOtherWrap').style.display = this.value === 'Other' ? 'block' : 'none';">
                        <?php foreach ($relationships as $rel): ?>
                            <option value="<?php echo $rel; ?>"><?php echo $rel; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="rl-form-group" id="rlRelOtherWrap" style="display:none; margin-bottom:14px;">
                <label>Custom relationship</label>
                <input type="text" name="relationship_other" id="rlRelOther" class="rl-form-input" placeholder="e.g. Bestfriend">
            </div>

            <div class="rl-form-row">
                <div class="rl-form-group">
                    <label>Phone <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                    <input type="tel" name="phone" id="rlPhone" class="rl-form-input" placeholder="09XXXXXXXXX">
                </div>
                <div class="rl-form-group">
                    <label>Email <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                    <input type="email" name="email" id="rlEmail" class="rl-form-input" placeholder="name@email.com">
                </div>
            </div>

            <div class="rl-form-section-title"><i class="fas fa-location-dot"></i> Address <span style="color:#bbb;font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></div>

            <?php $maps_id = 'rl'; include 'maps_address.php'; ?>

            <div class="rl-form-row">
                <div class="rl-form-group" style="flex: 0 0 38%;">
                    <label>House / Unit / Block <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                    <input type="text" name="house_no" id="rl_house" class="rl-form-input">
                </div>
                <div class="rl-form-group">
                    <label>Street Address</label>
                    <input type="text" name="address" id="rl_street" class="rl-form-input">
                </div>
            </div>
            <div class="rl-form-row">
                <div class="rl-form-group">
                    <label>Barangay</label>
                    <input type="text" name="barangay" id="rl_brgy" class="rl-form-input">
                </div>
                <div class="rl-form-group">
                    <label>City / Municipality</label>
                    <input type="text" name="city_town" id="rl_city" class="rl-form-input">
                </div>
            </div>
            <div class="rl-form-row">
                <div class="rl-form-group">
                    <label>Province</label>
                    <input type="text" name="province" id="rl_prov" class="rl-form-input">
                </div>
                <div class="rl-form-group">
                    <label>ZIP Code <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                    <input type="text" name="zip" id="rl_zip" class="rl-form-input" inputmode="numeric" maxlength="4">
                </div>
            </div>
            <script>
                (function () {
                    var m = document.getElementById('rl_maps');
                    if (!m) return;
                    m.addEventListener('maps:address', function (e) {
                        var d = e.detail;
                        if (d.street) document.getElementById('rl_street').value = d.street;
                        if (d.barangay) document.getElementById('rl_brgy').value = d.barangay;
                        document.getElementById('rl_city').value = d.city || '';
                        document.getElementById('rl_prov').value = d.province || d.region || '';
                        if (d.zip) document.getElementById('rl_zip').value = d.zip;
                    });
                })();
            </script>

            <div class="rl-form-group" style="margin-bottom:16px;">
                <label>Notes <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                <input type="text" name="notes" id="rlNotes" class="rl-form-input" placeholder="Favorite color, sizes, allergies…">
            </div>

            <div id="rlOccasionSection">
                <div class="rl-form-section-title"><i class="fas fa-cake-candles"></i> First Occasion <span style="color:#bbb;font-weight:400;text-transform:none;letter-spacing:0;">(optional — you can add more later)</span></div>
                <div class="rl-form-row">
                    <div class="rl-form-group">
                        <label>Type</label>
                        <select name="occasion_type" id="rlOccType" class="rl-form-input" onchange="document.getElementById('rlOccLabelWrap').style.display = this.value === 'other' ? 'block' : 'none';">
                            <?php foreach ($occasion_types as $key => $t): ?>
                                <option value="<?php echo $key; ?>"><?php echo $t['icon']; ?> <?php echo $t['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rl-form-group">
                        <label>Date</label>
                        <input type="date" name="occasion_date" id="rlOccDate" class="rl-form-input">
                    </div>
                </div>
                <div class="rl-form-group" id="rlOccLabelWrap" style="display:none; margin-bottom:14px;">
                    <label>Occasion name</label>
                    <input type="text" name="occasion_label" id="rlOccLabel" class="rl-form-input" placeholder="e.g. Dala Ceremony">
                </div>
            </div>

            <div class="rl-form-actions">
                <button type="submit" class="btn-save" style="padding: 12px 30px; font-size:14px; margin:0;">Save</button>
                <button type="button" class="rl-btn-cancel" onclick="rlCloseModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="rl-delete-overlay" id="rlDeleteModal">
    <div class="rl-delete-box">
        <div style="font-size:50px; color:#d32f2f; margin-bottom:15px;"><i class="fas fa-trash-alt"></i></div>
        <div style="font-size:22px; font-weight:700; color:#222; margin-bottom:5px;">Remove this person?</div>
        <div style="font-size:14px; color:#888; margin-bottom:25px; line-height:1.5;">Their saved occasions and reminders will be removed too. This can't be undone.</div>
        <div style="display:flex; gap:15px; justify-content:center;">
            <button class="btn-delete-cancel" onclick="document.getElementById('rlDeleteModal').style.display='none';" style="flex:1; padding:14px; border:none; border-radius:50px; background:#eaeaea; color:#555; font-weight:600; cursor:pointer;">Cancel</button>
            <button class="btn-delete-confirm" id="rlDeleteConfirmBtn" style="flex:1; padding:14px; border:none; border-radius:50px; background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%); color:#fff; font-weight:600; cursor:pointer;">Yes, Remove</button>
        </div>
    </div>
</div>

<!-- DELETE OCCASION CONFIRM MODAL -->
<div class="rl-delete-overlay" id="rlOccDeleteModal">
    <div class="rl-delete-box">
        <div style="font-size:50px; color:#d32f2f; margin-bottom:15px;"><i class="fas fa-calendar-xmark"></i></div>
        <div style="font-size:22px; font-weight:700; color:#222; margin-bottom:5px;">Remove this occasion?</div>
        <div style="font-size:14px; color:#888; margin-bottom:25px; line-height:1.5;">You'll stop getting reminders for it. This can't be undone.</div>
        <div style="display:flex; gap:15px; justify-content:center;">
            <button class="btn-delete-cancel" onclick="document.getElementById('rlOccDeleteModal').style.display='none';" style="flex:1; padding:14px; border:none; border-radius:50px; background:#eaeaea; color:#555; font-weight:600; cursor:pointer;">Cancel</button>
            <button class="btn-delete-confirm" id="rlOccDeleteConfirmBtn" style="flex:1; padding:14px; border:none; border-radius:50px; background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%); color:#fff; font-weight:600; cursor:pointer;">Yes, Remove</button>
        </div>
    </div>
</div>

<script>
    function rlResetPhoto() {
        document.getElementById('rlPhotoInput').value = '';
        document.getElementById('rlRemovePhoto').value = '';
        document.getElementById('rlPhotoPreview').src = '';
        document.getElementById('rlPhotoCircle').classList.remove('has-photo');
        document.getElementById('rlPhotoRemoveBtn').style.display = 'none';
    }

    function rlSyncInitial() {
        var name = document.getElementById('rlName').value.trim();
        document.getElementById('rlPhotoInitial').textContent = name ? name.charAt(0).toUpperCase() : '?';
    }

    function rlPreviewPhoto(input) {
        if (!input.files || !input.files[0]) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('rlPhotoPreview').src = e.target.result;
            document.getElementById('rlPhotoCircle').classList.add('has-photo');
            document.getElementById('rlRemovePhoto').value = '';
            document.getElementById('rlPhotoRemoveBtn').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }

    function rlRemovePhoto() {
        document.getElementById('rlPhotoInput').value = '';
        document.getElementById('rlPhotoPreview').src = '';
        document.getElementById('rlPhotoCircle').classList.remove('has-photo');
        document.getElementById('rlRemovePhoto').value = '1';
        document.getElementById('rlPhotoRemoveBtn').style.display = 'none';
    }

    function rlOpenAdd() {
        document.getElementById('rlModalTitle').textContent = 'Add Person';
        document.getElementById('rlFormAction').name = 'add_recipient';
        document.getElementById('rlForm').reset();
        document.getElementById('rlRecipientId').value = '';
        document.getElementById('rlRelOtherWrap').style.display = 'none';
        document.getElementById('rlOccLabelWrap').style.display = 'none';
        document.getElementById('rlOccasionSection').style.display = 'block';
        document.getElementById('rlOccDate').required = false;
        rlResetPhoto();
        rlSyncInitial();
        document.getElementById('rlModal').style.display = 'flex';
    }

    function rlOpenEdit(r) {
        document.getElementById('rlModalTitle').textContent = 'Edit ' + r.name;
        document.getElementById('rlFormAction').name = 'update_recipient';
        document.getElementById('rlRecipientId').value = r.id;
        document.getElementById('rlName').value = r.name || '';
        document.getElementById('rlPhone').value = r.phone || '';
        document.getElementById('rlEmail').value = r.email || '';
        document.getElementById('rl_house').value = r.house_no || '';
        document.getElementById('rl_street').value = r.street || '';
        document.getElementById('rlNotes').value = r.notes || '';

        rlResetPhoto();
        rlSyncInitial();
        if (r.photo) {
            document.getElementById('rlPhotoPreview').src = r.photo;
            document.getElementById('rlPhotoCircle').classList.add('has-photo');
            document.getElementById('rlPhotoRemoveBtn').style.display = 'block';
        }

        var relSelect = document.getElementById('rlRelChoice');
        var known = Array.from(relSelect.options).some(function (o) { return o.value === r.relationship; });
        if (known) {
            relSelect.value = r.relationship;
            document.getElementById('rlRelOtherWrap').style.display = 'none';
        } else {
            relSelect.value = 'Other';
            document.getElementById('rlRelOtherWrap').style.display = 'block';
            document.getElementById('rlRelOther').value = r.relationship || '';
        }

        // city_line was stored combined ("Brgy. X, City, Province") — best-effort split back into the two visible fields
        var parts = (r.city_line || '').split(',').map(function (s) { return s.trim(); });
        document.getElementById('rl_brgy').value = parts[0] || '';
        document.getElementById('rl_city').value = parts[1] || '';
        document.getElementById('rl_prov').value = parts[2] || '';
        document.getElementById('rl_zip').value = r.zip || '';

        // editing a person doesn't add a new occasion here — use the card's "+ Add occasion"
        document.getElementById('rlOccasionSection').style.display = 'none';
        document.getElementById('rlOccDate').required = false;

        document.getElementById('rlModal').style.display = 'flex';
    }

    function rlCloseModal() { document.getElementById('rlModal').style.display = 'none'; }
    document.getElementById('rlModal').addEventListener('click', function (e) { if (e.target === this) rlCloseModal(); });

    /* --- card "..." menu --- */
    function rlToggleMenu(e, id) {
        e.stopPropagation();
        var menu = document.getElementById('rlMenu' + id);
        var wasOpen = menu.classList.contains('open');
        document.querySelectorAll('.rl-menu-drop.open').forEach(function (m) { m.classList.remove('open'); });
        if (!wasOpen) menu.classList.add('open');
    }
    document.addEventListener('click', function () {
        document.querySelectorAll('.rl-menu-drop.open').forEach(function (m) { m.classList.remove('open'); });
    });

    var rlDeleteTarget = 0;
    function rlOpenDelete(id) {
        rlDeleteTarget = id;
        document.getElementById('rlDeleteModal').style.display = 'flex';
    }
    document.getElementById('rlDeleteConfirmBtn').addEventListener('click', function () {
        if (rlDeleteTarget > 0) window.location = 'profile.php?tab=relations&delete_recipient=' + rlDeleteTarget;
    });
    document.getElementById('rlDeleteModal').addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });

    var rlOccDeleteTarget = 0;
    function rlOpenOccDelete(id) {
        rlOccDeleteTarget = id;
        document.getElementById('rlOccDeleteModal').style.display = 'flex';
    }
    document.getElementById('rlOccDeleteConfirmBtn').addEventListener('click', function () {
        if (rlOccDeleteTarget > 0) window.location = 'profile.php?tab=relations&delete_occasion=' + rlOccDeleteTarget;
    });
    document.getElementById('rlOccDeleteModal').addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });
</script>
