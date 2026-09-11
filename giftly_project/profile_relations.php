<?php
$user_id = (int) $_SESSION['user_id'];
include_once 'recipients_lib.php';
recip_ensure_schema($conn);

$relations_flash = null;

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
        $relations_flash = ['type' => 'error', 'msg' => 'Please enter their name.'];
    } else {
        $house_no = trim($_POST['house_no'] ?? '');
        $street   = trim($_POST['address'] ?? '');
        $city_line = rl_compose_city_line($_POST);

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
        ]);

        $occ_date = trim($_POST['occasion_date'] ?? '');
        if ($new_id > 0 && $occ_date !== '') {
            recip_occasion_add($conn, $new_id, $user_id, [
                'occasion_type' => $_POST['occasion_type'] ?? 'birthday',
                'label'         => $_POST['occasion_label'] ?? '',
                'occasion_date' => $occ_date,
            ]);
        }

        echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=relations">';
        exit();
    }
}

// --- UPDATE RECIPIENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_recipient'])) {
    $rid = (int) ($_POST['recipient_id'] ?? 0);
    $rel_choice = $_POST['relationship_choice'] ?? 'Other';
    if ($rel_choice === 'Other') {
        $relationship = trim($_POST['relationship_other'] ?? '') ?: 'Other';
    } else {
        $relationship = in_array($rel_choice, recip_relationships(), true) ? $rel_choice : 'Other';
    }

    $house_no = trim($_POST['house_no'] ?? '');
    $street   = trim($_POST['address'] ?? '');
    $city_line = rl_compose_city_line($_POST);

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
    ]);

    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=relations">';
    exit();
}

// --- DELETE RECIPIENT ---
if (isset($_GET['delete_recipient'])) {
    recip_delete($conn, (int) $_GET['delete_recipient'], $user_id);
    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=relations">';
    exit();
}

// --- ADD OCCASION TO AN EXISTING RECIPIENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_occasion'])) {
    recip_occasion_add($conn, (int) ($_POST['recipient_id'] ?? 0), $user_id, [
        'occasion_type' => $_POST['occasion_type'] ?? 'other',
        'label'         => $_POST['occasion_label'] ?? '',
        'occasion_date' => $_POST['occasion_date'] ?? '',
    ]);
    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=relations">';
    exit();
}

// --- DELETE OCCASION ---
if (isset($_GET['delete_occasion'])) {
    recip_occasion_delete($conn, (int) $_GET['delete_occasion'], $user_id);
    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=relations">';
    exit();
}

$recipients = recip_list_for_user($conn, $user_id);
$upcoming = recip_upcoming_for_user($conn, $user_id, 8);
$relationships = recip_relationships();
$occasion_types = recip_occasion_types();
?>

<style>
    .rl-flash { padding: 12px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 14px; }
    .rl-flash.error { background: #fdeded; border: 1px solid #ffc1cc; color: #d32f2f; }

    .rl-upcoming { display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px; }
    .rl-up-card {
        display: flex; align-items: center; gap: 16px; border: 1px solid #f0f0f0; border-radius: 18px;
        padding: 14px 18px; transition: 0.2s; background: #fff;
    }
    .rl-up-card:hover { border-color: #ffc1cc; box-shadow: 0 6px 18px rgba(255,139,167,0.08); }
    .rl-up-date { flex: 0 0 54px; text-align: center; }
    .rl-up-date .d { font-size: 20px; font-weight: 700; color: #ff8ba7; line-height: 1; }
    .rl-up-date .m { font-size: 11px; color: #999; text-transform: uppercase; font-weight: 600; }
    .rl-up-avatar {
        flex: 0 0 44px; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center;
        justify-content: center; color: #fff; font-weight: 700; font-size: 15px;
    }
    .rl-up-info { flex: 1; min-width: 0; }
    .rl-up-info .who { font-size: 14px; color: #888; }
    .rl-up-info .occ { font-size: 15px; font-weight: 700; color: #222; }
    .rl-up-days { flex: 0 0 auto; font-size: 12px; font-weight: 600; color: #e65100; background: #fff3e0; padding: 5px 12px; border-radius: 50px; white-space: nowrap; }
    .rl-up-days.soon { color: #d32f2f; background: #fdeded; }
    .rl-gift-btn {
        flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; font-size: 15px;
        display: flex; align-items: center; justify-content: center; transition: 0.2s;
    }
    .rl-gift-btn:hover { transform: scale(1.1); box-shadow: 0 4px 12px rgba(255,139,167,0.4); }

    .rl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
    .rl-card { border: 1px solid #f0f0f0; border-radius: 20px; padding: 20px; transition: 0.2s; }
    .rl-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.05); border-color: #ffe1e8; }
    .rl-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .rl-avatar {
        flex: 0 0 48px; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center;
        justify-content: center; color: #fff; font-weight: 700; font-size: 17px;
    }
    .rl-card-name { font-size: 16px; font-weight: 700; color: #222; }
    .rl-card-rel { font-size: 12px; color: #999; margin-top: 1px; }
    .rl-card-actions { margin-left: auto; display: flex; gap: 6px; }
    .rl-icon-btn { width: 30px; height: 30px; border-radius: 50%; border: none; background: #f5f5f5; color: #888; cursor: pointer; transition: 0.2s; }
    .rl-icon-btn:hover { background: #ffc1cc; color: #fff; }
    .rl-icon-btn.danger:hover { background: #d32f2f; }

    .rl-occ-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    .rl-occ-chip { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #555; background: #fafafa; border-radius: 12px; padding: 7px 10px; }
    .rl-occ-chip .lbl { font-weight: 600; color: #333; }
    .rl-occ-chip .date { color: #999; margin-left: auto; margin-right: 4px; }
    .rl-occ-del { border: none; background: none; color: #ccc; cursor: pointer; font-size: 12px; padding: 2px; }
    .rl-occ-del:hover { color: #d32f2f; }
    .rl-add-occ-toggle { font-size: 12px; color: #ff8ba7; font-weight: 600; cursor: pointer; margin-bottom: 12px; display: inline-block; }
    .rl-add-occ-form { display: none; background: #fff8fa; border-radius: 14px; padding: 12px; margin-bottom: 12px; }
    .rl-add-occ-form.show { display: block; }
    .rl-mini-input { width: 100%; padding: 9px 10px; border: 1.5px solid #eee; border-radius: 10px; font-size: 12.5px; font-family: 'Poppins'; margin-bottom: 8px; background: #fff; }
    .rl-mini-save { width: 100%; padding: 9px; border: none; border-radius: 10px; background: linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%); color: #fff; font-weight: 600; font-size: 12.5px; cursor: pointer; }

    .rl-card-footer { display: flex; }
    .rl-gift-btn-wide {
        flex: 1; padding: 10px; border: none; border-radius: 50px; cursor: pointer; font-size: 13px; font-weight: 600;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; transition: 0.2s;
    }
    .rl-gift-btn-wide:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.4); }

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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
    <div class="page-title" style="margin-bottom:0;"><i class="fas fa-heart" style="color:#ff8ba7;"></i> My Relations</div>
    <button onclick="rlOpenAdd()" class="btn-save" style="padding: 10px 24px; font-size:14px; margin:0;">+ Add Person</button>
</div>
<p style="color:#999; font-size:13.5px; margin-bottom:24px;">Save the people you gift, so you never miss a birthday or anniversary — and gift them in one click.</p>

<?php if ($relations_flash): ?>
    <div class="rl-flash <?php echo $relations_flash['type']; ?>"><?php echo htmlspecialchars($relations_flash['msg']); ?></div>
<?php endif; ?>

<?php if (!empty($upcoming)): ?>
    <div style="font-size:13px; font-weight:700; color:#999; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">Upcoming Reminders</div>
    <div class="rl-upcoming">
        <?php foreach ($upcoming as $u):
            $days = $u['days_until'];
            $next = recip_next_occurrence($u['occasion_date']);
        ?>
            <div class="rl-up-card">
                <div class="rl-up-date">
                    <div class="d"><?php echo $next->format('d'); ?></div>
                    <div class="m"><?php echo $next->format('M'); ?></div>
                </div>
                <div class="rl-up-avatar" style="background:<?php echo recip_avatar_gradient($u['recipient_id']); ?>;">
                    <?php echo strtoupper(substr($u['recipient_name'], 0, 1)); ?>
                </div>
                <div class="rl-up-info">
                    <div class="who"><?php echo htmlspecialchars($u['recipient_name']); ?></div>
                    <div class="occ"><?php echo recip_occasion_icon($u['occasion_type']); ?> <?php echo htmlspecialchars(recip_occasion_label($u)); ?></div>
                </div>
                <div class="rl-up-days <?php echo $days <= 3 ? 'soon' : ''; ?>">
                    <?php echo $days === 0 ? 'Today!' : ($days === 1 ? 'Tomorrow' : "$days days left"); ?>
                </div>
                <a href="gift_start.php?recipient_id=<?php echo (int) $u['recipient_id']; ?>&occasion_id=<?php echo (int) $u['id']; ?>" class="rl-gift-btn" title="Send a gift">
                    <i class="fas fa-gift"></i>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($recipients)): ?>
    <div class="rl-grid">
        <?php foreach ($recipients as $r):
            $soonest = null;
            foreach ($r['occasions'] as $o) { if ($soonest === null || $o['days_until'] < $soonest['days_until']) $soonest = $o; }
            $gift_link = 'gift_start.php?recipient_id=' . (int) $r['id'] . ($soonest ? '&occasion_id=' . (int) $soonest['id'] : '');
        ?>
            <div class="rl-card">
                <div class="rl-card-head">
                    <div class="rl-avatar" style="background:<?php echo recip_avatar_gradient($r['id']); ?>;">
                        <?php echo strtoupper(substr($r['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="rl-card-name"><?php echo htmlspecialchars($r['name']); ?></div>
                        <div class="rl-card-rel"><?php echo htmlspecialchars($r['relationship'] ?: 'Relation'); ?></div>
                    </div>
                    <div class="rl-card-actions">
                        <button class="rl-icon-btn" title="Edit"
                            onclick='rlOpenEdit(<?php echo json_encode([
                                "id" => (int) $r["id"], "name" => $r["name"], "relationship" => $r["relationship"],
                                "phone" => $r["phone"], "email" => $r["email"], "house_no" => $r["house_no"],
                                "street" => $r["street"], "city_line" => $r["city_line"], "zip" => $r["zip"], "notes" => $r["notes"],
                            ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                            <i class="fas fa-pen"></i>
                        </button>
                        <button class="rl-icon-btn danger" title="Delete" onclick="rlOpenDelete(<?php echo (int) $r['id']; ?>)"><i class="fas fa-trash"></i></button>
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
                                <span class="date"><?php echo $od ? $od->format('M j') : ''; ?> · <?php echo $o['days_until']; ?>d</span>
                                <button class="rl-occ-del" onclick="if(confirm('Remove this occasion?')) window.location='profile_relations.php?delete_occasion=<?php echo (int) $o['id']; ?>'"><i class="fas fa-xmark"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#bbb; font-size:12.5px; margin-bottom:12px;">No occasions saved yet.</p>
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
    <p style="color:#888; text-align:center; padding:40px;">You haven't saved anyone yet. Add the people you love to gift, and we'll remind you before their special days.</p>
<?php endif; ?>

<!-- ADD / EDIT RECIPIENT MODAL -->
<div class="rl-modal-overlay" id="rlModal">
    <div class="rl-modal-box">
        <div class="rl-modal-title" id="rlModalTitle">Add Person</div>
        <form method="POST" action="profile.php?tab=relations" id="rlForm">
            <input type="hidden" name="add_recipient" value="1" id="rlFormAction">
            <input type="hidden" name="recipient_id" id="rlRecipientId" value="">

            <div class="rl-form-row">
                <div class="rl-form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="rlName" class="rl-form-input" placeholder="e.g. Mom" required>
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
                <div style="font-size:13px; font-weight:700; color:#444; margin-bottom:10px;">First occasion to remember <span style="color:#bbb;font-weight:400;">(optional — you can add more later)</span></div>
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

<script>
    function rlOpenAdd() {
        document.getElementById('rlModalTitle').textContent = 'Add Person';
        document.getElementById('rlFormAction').name = 'add_recipient';
        document.getElementById('rlForm').reset();
        document.getElementById('rlRecipientId').value = '';
        document.getElementById('rlRelOtherWrap').style.display = 'none';
        document.getElementById('rlOccLabelWrap').style.display = 'none';
        document.getElementById('rlOccasionSection').style.display = 'block';
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

    var rlDeleteTarget = 0;
    function rlOpenDelete(id) {
        rlDeleteTarget = id;
        document.getElementById('rlDeleteModal').style.display = 'flex';
    }
    document.getElementById('rlDeleteConfirmBtn').addEventListener('click', function () {
        if (rlDeleteTarget > 0) window.location = 'profile_relations.php?delete_recipient=' + rlDeleteTarget;
    });
    document.getElementById('rlDeleteModal').addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });
</script>
