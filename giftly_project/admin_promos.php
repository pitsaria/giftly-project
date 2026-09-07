<?php
include 'db_connect.php';
include_once 'promo_lib.php';

// --- admin gate ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = (int) $_SESSION['user_id'];
$me = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();
if (!$me || $me['role'] !== 'admin') { header("Location: shop.php"); exit(); }

promo_ensure_schema($conn);

$TYPES = ['percent' => 'Percent off', 'fixed' => 'Fixed amount off', 'free_shipping' => 'Free shipping'];
$SCOPES = ['all' => 'Products & boxes', 'products' => 'Products only', 'box' => 'Gift boxes only'];

/** Read + sanitise the promo form fields. Returns [data|null, errorString]. */
function promo_form_read($conn, $edit_id = 0) {
    $code_raw = strtoupper(trim($_POST['code'] ?? ''));
    $code = preg_replace('/[^A-Z0-9_-]/', '', $code_raw);
    $code = mb_substr($code, 0, 40);
    $is_auto = ($code === '');

    $name = trim($_POST['name'] ?? '');
    if ($name === '') return [null, 'Give the promo a name.'];
    $name = $conn->real_escape_string(mb_substr($name, 0, 120));

    $type = $_POST['type'] ?? 'percent';
    if (!in_array($type, ['percent', 'fixed', 'free_shipping'], true)) return [null, 'Pick a valid discount type.'];

    $value = 0.0;
    if ($type === 'percent') {
        $value = (float) ($_POST['value'] ?? 0);
        if ($value <= 0 || $value > 100) return [null, 'Percent must be between 1 and 100.'];
    } elseif ($type === 'fixed') {
        $value = (float) ($_POST['value'] ?? 0);
        if ($value <= 0) return [null, 'Enter the peso amount to take off.'];
    }

    $applies_to = $_POST['applies_to'] ?? 'all';
    if (!in_array($applies_to, ['all', 'products', 'box'], true)) $applies_to = 'all';

    $min_spend = max(0.0, (float) ($_POST['min_spend'] ?? 0));
    $first_order_only = !empty($_POST['first_order_only']) ? 'TRUE' : 'FALSE';
    $active = !empty($_POST['active']) ? 'TRUE' : 'FALSE';

    $max_discount = trim($_POST['max_discount'] ?? '');
    $max_discount_sql = ($max_discount !== '' && (float) $max_discount > 0) ? "'" . (float) $max_discount . "'" : 'NULL';

    $usage_limit = trim($_POST['usage_limit'] ?? '');
    $usage_limit_sql = ($usage_limit !== '' && (int) $usage_limit > 0) ? (int) $usage_limit : 'NULL';

    $per_user_limit = (int) ($_POST['per_user_limit'] ?? 1);
    if ($per_user_limit < 0) $per_user_limit = 0;

    $starts = trim($_POST['starts_at'] ?? '');
    $ends   = trim($_POST['ends_at'] ?? '');
    $starts_sql = preg_match('/^\d{4}-\d{2}-\d{2}$/', $starts) ? "'" . $conn->real_escape_string($starts) . " 00:00:00'" : 'NULL';
    $ends_sql   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ends)   ? "'" . $conn->real_escape_string($ends)   . " 23:59:59'" : 'NULL';

    // unique code check
    if (!$is_auto) {
        $cesc = $conn->real_escape_string($code);
        $dup = $conn->query("SELECT id FROM promos WHERE UPPER(code) = '$cesc'" . ($edit_id ? " AND id <> " . (int) $edit_id : ''));
        if ($dup && $dup->num_rows > 0) return [null, "The code \"$code\" is already in use."];
    }

    return [[
        'code_sql'         => $is_auto ? 'NULL' : "'" . $conn->real_escape_string($code) . "'",
        'name'             => $name,
        'type'             => $type,
        'value'            => $value,
        'auto'             => $is_auto ? 'TRUE' : 'FALSE',
        'min_spend'        => $min_spend,
        'first_order_only' => $first_order_only,
        'applies_to'       => $applies_to,
        'max_discount_sql' => $max_discount_sql,
        'usage_limit_sql'  => $usage_limit_sql,
        'per_user_limit'   => $per_user_limit,
        'starts_sql'       => $starts_sql,
        'ends_sql'         => $ends_sql,
        'active'           => $active,
    ], ''];
}

// --- ADD ---
if (isset($_POST['add_promo'])) {
    [$d, $err] = promo_form_read($conn);
    if ($err) { $_SESSION['promo_admin_err'] = $err; header("Location: admin_promos.php"); exit(); }
    $conn->query("INSERT INTO promos
        (code, name, type, value, auto, min_spend, first_order_only, applies_to, max_discount, usage_limit, per_user_limit, starts_at, ends_at, active)
        VALUES ({$d['code_sql']}, '{$d['name']}', '{$d['type']}', {$d['value']}, {$d['auto']}, {$d['min_spend']},
                {$d['first_order_only']}, '{$d['applies_to']}', {$d['max_discount_sql']}, {$d['usage_limit_sql']},
                {$d['per_user_limit']}, {$d['starts_sql']}, {$d['ends_sql']}, {$d['active']})");
    header("Location: admin_promos.php?msg=added");
    exit();
}

// --- EDIT ---
if (isset($_POST['edit_promo'])) {
    $id = (int) $_POST['promo_id'];
    [$d, $err] = promo_form_read($conn, $id);
    if ($err) { $_SESSION['promo_admin_err'] = $err; header("Location: admin_promos.php"); exit(); }
    $conn->query("UPDATE promos SET
        code = {$d['code_sql']}, name = '{$d['name']}', type = '{$d['type']}', value = {$d['value']},
        auto = {$d['auto']}, min_spend = {$d['min_spend']}, first_order_only = {$d['first_order_only']},
        applies_to = '{$d['applies_to']}', max_discount = {$d['max_discount_sql']}, usage_limit = {$d['usage_limit_sql']},
        per_user_limit = {$d['per_user_limit']}, starts_at = {$d['starts_sql']}, ends_at = {$d['ends_sql']}, active = {$d['active']}
        WHERE id = $id");
    header("Location: admin_promos.php?msg=updated");
    exit();
}

// --- TOGGLE ACTIVE ---
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $conn->query("UPDATE promos SET active = NOT active WHERE id = $id");
    header("Location: admin_promos.php?msg=toggled");
    exit();
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->query("DELETE FROM promos WHERE id = $id"); // cascades to promo_redemptions
    header("Location: admin_promos.php?msg=deleted");
    exit();
}

$err_flash = $_SESSION['promo_admin_err'] ?? '';
unset($_SESSION['promo_admin_err']);

include 'admin_header.php';

/** Human status for a promo row. */
function promo_status($conn, $p) {
    if (!promo_bool($p['active'])) return ['Inactive', '#9e9e9e', '#f0f0f0'];
    if (!empty($p['starts_at']) && strtotime($p['starts_at'] . ' UTC') > time()) return ['Scheduled', '#1976d2', '#e3f2fd'];
    if (!empty($p['ends_at']) && strtotime($p['ends_at'] . ' UTC') < time())     return ['Expired', '#d32f2f', '#fdeded'];
    if ($p['usage_limit'] !== null && $p['usage_limit'] !== '' && (int) $p['usage_limit'] > 0
        && promo_redemption_count($conn, (int) $p['id']) >= (int) $p['usage_limit']) return ['Used up', '#d32f2f', '#fdeded'];
    return ['Active', '#2e7d32', '#e8f5e9'];
}
function promo_effect_text($p) {
    if ($p['type'] === 'percent') {
        $t = rtrim(rtrim(number_format((float) $p['value'], 2), '0'), '.') . '% off';
        if ($p['max_discount'] !== null && $p['max_discount'] !== '' && (float) $p['max_discount'] > 0) {
            $t .= ' (max PHP ' . number_format((float) $p['max_discount'], 2) . ')';
        }
        return $t;
    }
    if ($p['type'] === 'fixed') return 'PHP ' . number_format((float) $p['value'], 2) . ' off';
    if ($p['type'] === 'free_shipping') return 'Free shipping';
    return $p['type'];
}
function promo_conditions_text($p) {
    $bits = [];
    if ((float) $p['min_spend'] > 0) $bits[] = 'Min PHP ' . number_format((float) $p['min_spend'], 2);
    if (promo_bool($p['first_order_only'])) $bits[] = 'First order only';
    if ($p['applies_to'] === 'products') $bits[] = 'Products only';
    if ($p['applies_to'] === 'box') $bits[] = 'Boxes only';
    $pu = (int) ($p['per_user_limit'] ?? 1);
    if ($pu === 1) $bits[] = 'Once per customer';
    elseif ($pu > 1) $bits[] = $pu . '× per customer';
    if (!empty($p['starts_at']) || !empty($p['ends_at'])) {
        $s = !empty($p['starts_at']) ? date('M j', strtotime($p['starts_at'])) : '…';
        $e = !empty($p['ends_at'])   ? date('M j, Y', strtotime($p['ends_at'])) : '…';
        $bits[] = "$s – $e";
    }
    return $bits ? implode(' · ', $bits) : '—';
}
?>

<style>
    .wide-container { max-width: 1050px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .promo-card { background: #fff; border-radius: 24px; padding: 28px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); margin-bottom: 24px; }
    .promo-card h3 { font-size: 16px; font-weight: 700; color: #222; margin-bottom: 18px; }
    .p-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px 18px; }
    .p-field { display: flex; flex-direction: column; }
    .p-field.full { grid-column: 1 / -1; }
    .p-field label { font-size: 12.5px; font-weight: 600; color: #555; margin-bottom: 5px; }
    .p-field .hint { font-size: 11px; color: #999; margin-top: 3px; }
    .p-input, .p-select { padding: 11px 13px; border: 1.5px solid #eee; border-radius: 12px; font-family: 'Poppins'; font-size: 13.5px; outline: none; background: #fff; }
    .p-input:focus, .p-select:focus { border-color: #ffc1cc; box-shadow: 0 0 0 4px rgba(255,193,204,.12); }
    .p-check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #444; }
    .p-check input { width: 16px; height: 16px; accent-color: #ff8ba7; }
    .btn-pink { background: linear-gradient(135deg,#FEA5B6,#ff8ba7); color: #fff; border: none; padding: 12px 28px; border-radius: 50px; font-weight: 600; font-size: 14px; cursor: pointer; }
    .btn-pink:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,.35); }

    .promo-table { width: 100%; border-collapse: collapse; }
    .promo-table th { text-align: left; padding: 12px 10px; border-bottom: 2px solid #f0f0f0; font-size: 12.5px; color: #666; font-weight: 600; }
    .promo-table td { padding: 16px 10px; border-bottom: 1px solid #f5f5f5; font-size: 13.5px; color: #333; vertical-align: top; }
    .promo-code-pill { display: inline-block; background: #222; color: #fff; font-weight: 700; font-size: 12px; letter-spacing: .5px; padding: 4px 10px; border-radius: 8px; }
    .promo-auto-pill { display: inline-block; background: #fff0f5; color: #d81b60; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 8px; }
    .status-pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
    .row-act a { font-size: 12px; font-weight: 600; text-decoration: none; margin-right: 10px; }
    .row-act .ed { color: #1976d2; }
    .row-act .tg { color: #ef6c00; }
    .row-act .dl { color: #d32f2f; }

    .alert-box { padding: 12px 16px; border-radius: 14px; text-align: center; font-weight: 500; margin-bottom: 20px; font-size: 13.5px; }
    .alert-green { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
    .alert-red { background: #fdeded; color: #d32f2f; border: 1px solid #ffc1cc; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); backdrop-filter: blur(5px); display: none; justify-content: center; align-items: center; z-index: 9999; padding: 20px; }
    .modal-box { background: #fff; border-radius: 26px; padding: 34px; max-width: 640px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 25px 60px rgba(0,0,0,.2); }
    .modal-close { position: absolute; top: 16px; right: 20px; font-size: 24px; color: #999; cursor: pointer; }
    .modal-close:hover { color: #ff8ba7; }

    @media (max-width: 720px) { .p-form-grid { grid-template-columns: 1fr; } }
</style>

<div class="wide-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:26px; flex-wrap:wrap; gap:12px;">
        <h2 style="font-size:26px; font-weight:600; color:#222;">Promo Codes</h2>
        <a href="admin_dashboard.php" style="background:#f3f3f3; padding:8px 20px; border-radius:50px; font-size:14px; color:#555; text-decoration:none;">&larr; Dashboard</a>
    </div>

    <?php
    $flash = [
        'added'   => ['alert-green', 'Promo created.'],
        'updated' => ['alert-green', 'Promo updated.'],
        'deleted' => ['alert-red', 'Promo deleted.'],
        'toggled' => ['alert-green', 'Promo status changed.'],
    ];
    if (isset($_GET['msg']) && isset($flash[$_GET['msg']])) {
        echo '<div class="alert-box ' . $flash[$_GET['msg']][0] . '">' . $flash[$_GET['msg']][1] . '</div>';
    }
    if ($err_flash) echo '<div class="alert-box alert-red">' . htmlspecialchars($err_flash) . '</div>';
    ?>

    <!-- CREATE -->
    <div class="promo-card">
        <h3><i class="fas fa-plus-circle" style="color:#ff8ba7;"></i> New promo</h3>
        <form method="POST" action="admin_promos.php">
            <?php echo promo_form_fields($TYPES, $SCOPES, null); ?>
            <div style="margin-top:18px;"><button type="submit" name="add_promo" class="btn-pink">Create promo</button></div>
        </form>
    </div>

    <!-- LIST -->
    <div class="promo-card">
        <h3><i class="fas fa-ticket-alt" style="color:#ff8ba7;"></i> All promos</h3>
        <div style="overflow-x:auto;">
        <table class="promo-table">
            <thead><tr>
                <th>Code / Name</th><th>Effect</th><th>Conditions</th><th>Used</th><th>Status</th><th style="text-align:right;">Actions</th>
            </tr></thead>
            <tbody>
            <?php
            $rows = $conn->query("SELECT * FROM promos ORDER BY active DESC, id DESC");
            if ($rows && $rows->num_rows > 0) {
                while ($p = $rows->fetch_assoc()) {
                    [$st_txt, $st_fg, $st_bg] = promo_status($conn, $p);
                    $used = promo_redemption_count($conn, (int) $p['id']);
                    $cap  = ($p['usage_limit'] !== null && $p['usage_limit'] !== '' && (int) $p['usage_limit'] > 0) ? ' / ' . (int) $p['usage_limit'] : '';
                    $head = promo_bool($p['auto'])
                        ? '<span class="promo-auto-pill">AUTOMATIC</span>'
                        : '<span class="promo-code-pill">' . htmlspecialchars($p['code']) . '</span>';
                    $attr = htmlspecialchars(json_encode($p), ENT_QUOTES);
                    echo '<tr>'
                       . '<td>' . $head . '<div style="color:#666; margin-top:6px;">' . htmlspecialchars($p['name']) . '</div></td>'
                       . '<td>' . htmlspecialchars(promo_effect_text($p)) . '</td>'
                       . '<td style="color:#777; max-width:230px;">' . htmlspecialchars(promo_conditions_text($p)) . '</td>'
                       . '<td>' . $used . $cap . '</td>'
                       . '<td><span class="status-pill" style="color:' . $st_fg . '; background:' . $st_bg . ';">' . $st_txt . '</span></td>'
                       . '<td style="text-align:right;" class="row-act">'
                       . '<a href="javascript:void(0)" class="ed" onclick=\'openPromoEdit(' . $attr . ')\'><i class="fas fa-pen"></i> Edit</a>'
                       . '<a href="admin_promos.php?toggle=' . (int) $p['id'] . '" class="tg">' . (promo_bool($p['active']) ? 'Disable' : 'Enable') . '</a>'
                       . '<a href="admin_promos.php?delete=' . (int) $p['id'] . '" class="dl" onclick="return confirm(\'Delete this promo? Its redemption history goes too.\');"><i class="fas fa-trash"></i></a>'
                       . '</td></tr>';
                }
            } else {
                echo '<tr><td colspan="6" style="text-align:center; padding:40px; color:#999;">No promos yet — create one above.</td></tr>';
            }
            ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="promoEditModal">
    <div class="modal-box">
        <span class="modal-close" onclick="document.getElementById('promoEditModal').style.display='none'">&times;</span>
        <h3 style="font-size:18px; font-weight:700; color:#222; margin-bottom:18px;">Edit promo</h3>
        <form method="POST" action="admin_promos.php" id="promoEditForm">
            <input type="hidden" name="promo_id" id="e_promo_id">
            <?php echo promo_form_fields($TYPES, $SCOPES, 'e_'); ?>
            <div style="margin-top:18px;"><button type="submit" name="edit_promo" class="btn-pink">Save changes</button></div>
        </form>
    </div>
</div>

<script>
    function syncPromoValueField(prefix) {
        var type = document.getElementById(prefix + 'type').value;
        var wrap = document.getElementById(prefix + 'value_wrap');
        var maxWrap = document.getElementById(prefix + 'maxdisc_wrap');
        wrap.style.display = (type === 'free_shipping') ? 'none' : 'flex';
        maxWrap.style.display = (type === 'percent') ? 'flex' : 'none';
        document.getElementById(prefix + 'value_label').textContent = (type === 'percent') ? 'Percent (1–100)' : 'Amount off (PHP)';
    }
    document.getElementById('type') && (document.getElementById('type').onchange = function () { syncPromoValueField(''); });
    document.getElementById('e_type') && (document.getElementById('e_type').onchange = function () { syncPromoValueField('e_'); });
    syncPromoValueField('');

    function openPromoEdit(p) {
        document.getElementById('e_promo_id').value = p.id;
        document.getElementById('e_code').value = p.code || '';
        document.getElementById('e_name').value = p.name || '';
        document.getElementById('e_type').value = p.type;
        document.getElementById('e_value').value = (p.type === 'free_shipping') ? '' : p.value;
        document.getElementById('e_applies_to').value = p.applies_to || 'all';
        document.getElementById('e_min_spend').value = parseFloat(p.min_spend) || '';
        document.getElementById('e_max_discount').value = (p.max_discount === null || p.max_discount === undefined) ? '' : p.max_discount;
        document.getElementById('e_usage_limit').value = (p.usage_limit === null || p.usage_limit === undefined) ? '' : p.usage_limit;
        document.getElementById('e_per_user_limit').value = (p.per_user_limit === null || p.per_user_limit === undefined) ? 1 : p.per_user_limit;
        document.getElementById('e_starts_at').value = p.starts_at ? String(p.starts_at).slice(0, 10) : '';
        document.getElementById('e_ends_at').value = p.ends_at ? String(p.ends_at).slice(0, 10) : '';
        document.getElementById('e_first_order_only').checked = (p.first_order_only === true || p.first_order_only === 't' || p.first_order_only === '1' || p.first_order_only === 1);
        document.getElementById('e_active').checked = (p.active === true || p.active === 't' || p.active === '1' || p.active === 1);
        syncPromoValueField('e_');
        document.getElementById('promoEditModal').style.display = 'flex';
    }
    document.getElementById('promoEditModal').addEventListener('click', function (e) {
        if (e.target === this) this.style.display = 'none';
    });
</script>

<?php
include 'admin_footer.php';

/** The shared set of form fields, prefixed for the create ('') or edit ('e_') form. */
function promo_form_fields($TYPES, $SCOPES, $prefix) {
    $p = $prefix ?: '';
    ob_start();
    ?>
    <div class="p-form-grid">
        <div class="p-field">
            <label>Code <span style="color:#bbb;font-weight:400;">(leave blank = automatic)</span></label>
            <input class="p-input" type="text" name="code" id="<?php echo $p; ?>code" maxlength="40" placeholder="e.g. SUMMER20" style="text-transform:uppercase;">
        </div>
        <div class="p-field">
            <label>Internal name / label shown to customer</label>
            <input class="p-input" type="text" name="name" id="<?php echo $p; ?>name" maxlength="120" placeholder="e.g. Summer sale · 20% off" required>
        </div>
        <div class="p-field">
            <label>Discount type</label>
            <select class="p-select" name="type" id="<?php echo $p; ?>type">
                <?php foreach ($TYPES as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="p-field" id="<?php echo $p; ?>value_wrap">
            <label id="<?php echo $p; ?>value_label">Percent (1–100)</label>
            <input class="p-input" type="number" step="0.01" min="0" name="value" id="<?php echo $p; ?>value" placeholder="e.g. 20">
        </div>
        <div class="p-field" id="<?php echo $p; ?>maxdisc_wrap">
            <label>Max discount <span style="color:#bbb;font-weight:400;">(optional, PHP)</span></label>
            <input class="p-input" type="number" step="0.01" min="0" name="max_discount" id="<?php echo $p; ?>max_discount" placeholder="cap the % discount">
        </div>
        <div class="p-field">
            <label>Applies to</label>
            <select class="p-select" name="applies_to" id="<?php echo $p; ?>applies_to">
                <?php foreach ($SCOPES as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="p-field">
            <label>Minimum spend <span style="color:#bbb;font-weight:400;">(PHP, 0 = none)</span></label>
            <input class="p-input" type="number" step="0.01" min="0" name="min_spend" id="<?php echo $p; ?>min_spend" placeholder="0">
        </div>
        <div class="p-field">
            <label>Total uses allowed <span style="color:#bbb;font-weight:400;">(blank = unlimited)</span></label>
            <input class="p-input" type="number" min="1" name="usage_limit" id="<?php echo $p; ?>usage_limit" placeholder="unlimited">
        </div>
        <div class="p-field">
            <label>Uses per customer <span style="color:#bbb;font-weight:400;">(0 = unlimited)</span></label>
            <input class="p-input" type="number" min="0" name="per_user_limit" id="<?php echo $p; ?>per_user_limit" value="1">
        </div>
        <div class="p-field">
            <label>Starts <span style="color:#bbb;font-weight:400;">(optional)</span></label>
            <input class="p-input" type="date" name="starts_at" id="<?php echo $p; ?>starts_at">
        </div>
        <div class="p-field">
            <label>Ends <span style="color:#bbb;font-weight:400;">(optional)</span></label>
            <input class="p-input" type="date" name="ends_at" id="<?php echo $p; ?>ends_at">
        </div>
        <div class="p-field full" style="flex-direction:row; gap:24px; margin-top:4px;">
            <label class="p-check"><input type="checkbox" name="first_order_only" id="<?php echo $p; ?>first_order_only" value="1"> First order only</label>
            <label class="p-check"><input type="checkbox" name="active" id="<?php echo $p; ?>active" value="1" checked> Active</label>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
