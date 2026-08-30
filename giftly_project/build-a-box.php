<?php
include 'db_connect.php';
include 'build_a_box_lib.php';
bab_ensure_schema($conn);
include 'header.php';

$logged_in = isset($_SESSION['user_id']);

$edit_box = null;
if ($logged_in && isset($_GET['box_id'])) {
    $edit_box = bab_load_box($conn, intval($_GET['box_id']), $_SESSION['user_id']);
}

$sizes = bab_box_sizes($conn);
$cat_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");

/* --- initial client state (JSON) --- */
$initial = ['boxId' => 0, 'sizeId' => 0, 'letter' => '', 'cardStyle' => 'simple', 'items' => []];
if ($edit_box) {
    $initial['boxId']     = intval($edit_box['box']['id']);
    $initial['sizeId']    = intval($edit_box['box']['box_size_id']);
    $initial['letter']    = $edit_box['box']['letter'];
    $initial['cardStyle'] = bab_card_style_key($edit_box['box']['card_style'] ?? 'simple');
    foreach ($edit_box['items'] as $it) {
        if ($it['unavailable'] === 'removed') continue;
        $initial['items'][] = [
            'product_id' => intval($it['product_id']),
            'name'       => $it['name'],
            'price'      => floatval($it['price']),
            'image'      => $it['image'],
            'qty'        => intval($it['quantity']),
            'stock'      => intval($it['stock']),
        ];
    }
}
?>

<style>
    .bab-wrap { max-width: 1180px; margin: 0 auto; padding: 120px 20px 40px; }
    .bab-hero { text-align: center; margin-bottom: 22px; }
    .bab-hero h1 { font-size: 25px; font-weight: 700; color: #222; margin-bottom: 6px; }
    .bab-hero p { color: #888; font-size: 14px; }

    /* --- STEP INDICATOR --- */
    .bab-steps { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
    .bab-step { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; color: #aaa; }
    .bab-step .num {
        width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center;
        justify-content: center; font-size: 13px; font-weight: 700; background: #eee; color: #999;
        transition: 0.3s;
    }
    .bab-step.active { color: #ff8ba7; }
    .bab-step.active .num { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; box-shadow: 0 4px 12px rgba(254,165,182,0.35); }
    .bab-step.done .num { background: #e8f5e9; color: #2e7d32; }
    .bab-step-sep { width: 30px; height: 2px; background: #eee; align-self: center; }

    .bab-section-title { font-size: 19px; font-weight: 700; color: #222; margin: 0 0 18px; display: flex; align-items: center; gap: 10px; }
    .bab-section-title .badge {
        width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;
    }

    /* --- BOX SIZE CARDS --- */
    .bab-size-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; margin-bottom: 45px; }
    .bab-size-card {
        background: #fff; border: 2px solid #eee; border-radius: 22px; padding: 26px 22px; cursor: pointer;
        transition: all 0.25s ease; text-align: center; position: relative;
    }
    .bab-size-card:hover { border-color: #ffc1cc; background: #fff8fa; transform: translateY(-3px); }
    .bab-size-card.selected { border-color: #ff8ba7; background: #fff0f5; box-shadow: 0 0 0 4px rgba(255,139,167,0.12); }
    .bab-size-card .ico { font-size: 34px; color: #ff8ba7; margin-bottom: 10px; }
    .bab-size-card h3 { font-size: 18px; font-weight: 700; color: #222; margin-bottom: 4px; }
    .bab-size-card .cap { font-size: 13px; color: #888; }
    .bab-size-card .tick {
        position: absolute; top: 12px; right: 14px; color: #ff8ba7; font-size: 18px; opacity: 0; transition: 0.2s;
    }
    .bab-size-card.selected .tick { opacity: 1; }

    /* --- MAIN 2-COLUMN --- */
    .bab-main { display: flex; gap: 35px; align-items: flex-start; flex-wrap: wrap; }
    .bab-left { flex: 1; min-width: 320px; }
    .bab-right { width: 350px; flex-shrink: 0; }
    .bab-locked { opacity: 0.45; pointer-events: none; filter: grayscale(0.4); }

    /* --- SEARCH + CHIPS --- */
    .bab-search { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
    .bab-search input {
        flex: 1; min-width: 180px; padding: 11px 18px; border-radius: 30px; border: 1.5px solid #eee;
        outline: none; font-family: 'Poppins'; background: #fff;
    }
    .bab-search input:focus { border-color: #ffc1cc; }
    .bab-search button {
        padding: 11px 22px; border-radius: 30px; border: none; cursor: pointer; font-family: 'Poppins'; font-weight: 500;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff;
    }
    .bab-chips { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
    .bab-chip {
        padding: 6px 16px; border-radius: 50px; border: 1.5px solid #ddd; font-size: 13px; color: #555;
        background: transparent; cursor: pointer; transition: 0.2s; font-family: 'Poppins';
    }
    .bab-chip:hover { border-color: #ffc1cc; color: #ff8ba7; }
    .bab-chip.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; }

    /* --- PRODUCT GRID --- */
    .bab-prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(185px, 1fr)); gap: 14px; }
    .bab-prod-card {
        background: #fff; border-radius: 18px; padding: 13px; border: 1px solid #f0f0f0;
        box-shadow: 0 3px 12px rgba(0,0,0,0.04); display: flex; flex-direction: column;
    }
    .bab-prod-img { background: #f8f8fa; border-radius: 12px; height: 116px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
    .bab-prod-img img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .bab-prod-name { font-size: 14px; font-weight: 600; color: #222; margin-bottom: 4px; line-height: 1.35; }
    .bab-prod-price { font-size: 13px; color: #888; margin-bottom: 12px; }
    .bab-prod-price span { font-weight: 700; color: #222; }
    .bab-prod-card .foot { margin-top: auto; }
    .bab-add-btn {
        width: 100%; padding: 9px 0; border: none; border-radius: 50px; cursor: pointer; font-family: 'Poppins';
        font-weight: 600; font-size: 13px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff;
        display: flex; align-items: center; justify-content: center; gap: 7px; transition: 0.2s;
    }
    .bab-add-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.3); }
    .bab-add-btn.disabled { background: #ddd; color: #888; cursor: not-allowed; transform: none; box-shadow: none; }
    .bab-mini-qty { display: flex; align-items: center; justify-content: space-between; border: 1.5px solid #ffc1cc; border-radius: 50px; padding: 4px 8px; }
    .bab-mini-qty button { background: transparent; border: none; font-size: 16px; color: #ff8ba7; cursor: pointer; padding: 0 8px; }
    .bab-mini-qty span { font-size: 14px; font-weight: 700; color: #222; }

    .bab-empty { grid-column: 1 / -1; text-align: center; padding: 50px 20px; color: #999; }

    /* --- PAGINATION --- */
    .bab-pager { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 26px; flex-wrap: wrap; }
    .bab-pager button {
        min-width: 36px; height: 36px; padding: 0 10px; border-radius: 50px; border: 1.5px solid #eee;
        background: #fff; color: #666; font-family: 'Poppins'; font-weight: 600; font-size: 13px; cursor: pointer; transition: 0.2s;
    }
    .bab-pager button:hover:not(:disabled) { border-color: #ffc1cc; color: #ff8ba7; }
    .bab-pager button.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; }
    .bab-pager button:disabled { opacity: 0.4; cursor: not-allowed; }
    .bab-pager .dots { border: none; background: none; cursor: default; color: #bbb; }

    /* --- CHOSEN-SIZE COMPACT BAR --- */
    .bab-chosen-bar {
        display: none; align-items: center; gap: 12px; background: #fff0f5; border: 1.5px solid #ffd9e4;
        border-radius: 16px; padding: 12px 18px; margin-bottom: 40px;
    }
    .bab-chosen-bar .ic { color: #ff8ba7; font-size: 18px; }
    .bab-chosen-bar .txt { font-size: 14px; color: #444; font-weight: 500; }
    .bab-chosen-bar .txt b { color: #222; }
    .bab-chosen-bar button {
        margin-left: auto; border: none; background: #fff; color: #ff8ba7; border: 1.5px solid #ffc1cc;
        border-radius: 50px; padding: 7px 16px; font-family: 'Poppins'; font-weight: 600; font-size: 13px; cursor: pointer;
    }
    .bab-chosen-bar button:hover { background: #ff8ba7; color: #fff; }

    /* --- TOOLBAR: search + view toggle --- */
    .bab-toolbar { display: flex; gap: 10px; align-items: stretch; margin-bottom: 14px; flex-wrap: wrap; }
    .bab-toolbar .bab-search { flex: 1; margin-bottom: 0; }
    .bab-view-toggle { display: flex; gap: 3px; background: #f3f3f3; border-radius: 50px; padding: 3px; flex-shrink: 0; }
    .bab-view-toggle button { border: none; background: none; width: 36px; border-radius: 50px; color: #999; cursor: pointer; font-size: 13px; transition: 0.15s; }
    .bab-view-toggle button.active { background: #fff; color: #ff8ba7; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }

    /* --- LIST VIEW --- */
    .bab-prod-grid.list-view { display: flex; flex-direction: column; gap: 8px; }
    .bab-prod-grid.list-view .bab-prod-card { flex-direction: row; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 14px; }
    .bab-prod-grid.list-view .bab-prod-img { width: 52px; height: 52px; margin-bottom: 0; flex-shrink: 0; border-radius: 10px; }
    .bab-prod-grid.list-view .bab-prod-name { margin-bottom: 0; flex: 1; font-size: 13.5px; }
    .bab-prod-grid.list-view .bab-prod-price { margin-bottom: 0; margin-right: 6px; white-space: nowrap; font-size: 12.5px; }
    .bab-prod-grid.list-view .foot { margin-top: 0; width: 150px; flex-shrink: 0; }
    @media (max-width: 520px) { .bab-prod-grid.list-view .foot { width: 118px; } }

    /* --- LETTER TRIGGER (step 3, in panel) --- */
    .bab-letter-trigger {
        width: 100%; display: flex; align-items: center; gap: 12px; text-align: left;
        padding: 14px 16px; margin: 10px 0 14px; border: 1.5px solid #ffd0dd; border-radius: 16px;
        background: linear-gradient(135deg, #fff6f9 0%, #ffeef4 100%);
        font-family: 'Poppins'; cursor: pointer; transition: 0.2s;
    }
    .bab-letter-trigger:hover { border-color: #ff8ba7; box-shadow: 0 4px 14px rgba(255,139,167,0.16); }
    .bab-letter-trigger .lt-badge {
        width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;
    }
    .bab-letter-trigger .lt-body { flex: 1; min-width: 0; }
    .bab-letter-trigger .lt-title { display: block; font-size: 13.5px; font-weight: 700; color: #222; }
    .bab-letter-trigger .lt-sub { display: block; font-size: 11.5px; color: #999; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bab-letter-trigger .lt-cta {
        flex-shrink: 0; font-size: 12px; font-weight: 700; color: #ff8ba7;
        background: #fff; border: 1.5px solid #ffc1cc; border-radius: 50px; padding: 6px 15px;
    }
    .bab-letter-trigger:hover .lt-cta { background: #ff8ba7; color: #fff; border-color: #ff8ba7; }
    .bab-letter-trigger.nudge { animation: babNudge 1.9s ease-in-out infinite; }
    @keyframes babNudge {
        0%, 100% { box-shadow: 0 0 0 0 rgba(255,139,167,0); }
        50% { box-shadow: 0 0 0 6px rgba(255,139,167,0.13); }
    }
    .bab-letter-trigger.set { background: #f4faf5; border-color: #cfe9d4; }
    .bab-letter-trigger.set .lt-badge { background: #e8f5e9; color: #2e7d32; }
    .bab-letter-trigger.set .lt-cta { color: #2e7d32; border-color: #b6dfbb; }
    .bab-letter-trigger.set:hover { border-color: #2e7d32; box-shadow: 0 4px 14px rgba(46,125,50,0.15); }
    .bab-letter-trigger.set:hover .lt-cta { background: #2e7d32; color: #fff; border-color: #2e7d32; }

    /* --- LETTER MODAL --- */
    .bab-letter-modal {
        background: #fff; border-radius: 24px; padding: 26px; max-width: 440px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.18); animation: babUp 0.3s ease; max-height: 88vh; overflow-y: auto;
    }
    .lm-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
    .lm-head h3 { font-size: 18px; font-weight: 700; color: #222; display: flex; align-items: center; gap: 8px; }
    .lm-head h3 i { color: #ff8ba7; }
    .lm-x { border: none; background: none; font-size: 24px; color: #999; cursor: pointer; line-height: 1; }
    .lm-label { display: block; font-size: 11px; font-weight: 700; color: #888; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .lm-styles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .lm-style { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 10px 3px; border: 1.5px solid #eee; border-radius: 14px; background: #fff; cursor: pointer; transition: 0.15s; font-family: 'Poppins'; }
    .lm-style:hover { border-color: #ffc1cc; }
    .lm-style.active { border-color: #ff8ba7; background: #fff0f5; box-shadow: 0 0 0 3px rgba(255,139,167,0.12); }
    .lm-style .emo { font-size: 20px; line-height: 1; }
    .lm-style .lbl { font-size: 10px; font-weight: 600; color: #555; text-align: center; line-height: 1.2; }
    .bab-letter-modal textarea {
        width: 100%; min-height: 120px; border: 1.5px solid #eee; border-radius: 14px; padding: 14px 16px;
        font-family: 'Poppins'; font-size: 14px; resize: vertical; outline: none; background: #fafafa;
    }
    .bab-letter-modal textarea:focus { border-color: #ffc1cc; background: #fff; }
    .lm-actions { display: flex; gap: 10px; margin-top: 16px; }
    .lm-actions .bab-btn { flex: 1; }
    .bab-char { text-align: right; font-size: 12px; color: #999; margin-top: 6px; }

    /* --- RIGHT PANEL --- */
    .bab-panel { background: #fff; border-radius: 24px; padding: 26px; box-shadow: 0 8px 30px rgba(0,0,0,0.05); border: 1px solid #f5f5f5; position: sticky; top: 110px; }
    .bab-panel h3 { font-size: 17px; font-weight: 700; color: #222; margin-bottom: 4px; }
    .bab-panel .sub { font-size: 13px; color: #888; margin-bottom: 16px; }
    .bab-counter {
        display: flex; align-items: baseline; gap: 6px; margin-bottom: 6px;
    }
    .bab-counter b { font-size: 26px; font-weight: 700; color: #ff8ba7; }
    .bab-counter span { font-size: 14px; color: #888; }
    .bab-bar { height: 8px; border-radius: 10px; background: #f0f0f0; overflow: hidden; margin-bottom: 18px; }
    .bab-bar > i { display: block; height: 100%; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); transition: width 0.3s ease; }

    .bab-items { list-style: none; padding: 0; margin: 0 0 16px; max-height: 320px; overflow-y: auto; }
    .bab-items li { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
    .bab-items li:last-child { border-bottom: none; }
    .bab-items img { width: 44px; height: 44px; object-fit: contain; background: #fafafa; border-radius: 10px; padding: 4px; }
    .bab-items .n { flex: 1; font-size: 13px; font-weight: 600; color: #333; }
    .bab-items .n small { display: block; font-weight: 400; color: #999; }
    .bab-items .stepper { display: flex; align-items: center; gap: 4px; }
    .bab-items .stepper button { width: 24px; height: 24px; border: 1px solid #eee; background: #fff; border-radius: 50%; cursor: pointer; color: #666; font-size: 13px; }
    .bab-items .stepper button:hover { border-color: #ff8ba7; color: #ff8ba7; }
    .bab-items .stepper span { min-width: 18px; text-align: center; font-size: 13px; font-weight: 700; }
    .bab-items .rm { background: none; border: none; color: #ccc; cursor: pointer; font-size: 14px; }
    .bab-items .rm:hover { color: #d32f2f; }
    .bab-items li.bad { background: #fff5f5; border-radius: 10px; }

    .bab-panel-empty { text-align: center; color: #c8c8c8; font-size: 13px; padding: 40px 12px; border: 2px dashed #eee; border-radius: 16px; margin-bottom: 16px; }
    .bab-panel-empty i { font-size: 30px; display: block; margin-bottom: 10px; color: #eee; }
    .bab-totrow { display: flex; justify-content: space-between; font-size: 14px; color: #555; margin-bottom: 6px; }
    .bab-totrow.grand { border-top: 1px solid #f0f0f0; padding-top: 12px; margin-top: 10px; font-size: 17px; font-weight: 700; color: #222; }

    .bab-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 18px; }
    .bab-actions .row { display: flex; gap: 10px; }
    .bab-actions .row .bab-btn { flex: 1; }
    .bab-btn { width: 100%; padding: 12px 0; border: none; border-radius: 50px; cursor: pointer; font-family: 'Poppins'; font-weight: 600; font-size: 13.5px; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 7px; }
    .bab-btn.primary { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; box-shadow: 0 4px 12px rgba(254,165,182,0.25); }
    .bab-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(254,165,182,0.4); }
    .bab-btn.ghost { background: #f4f4f4; color: #555; }
    .bab-btn.ghost:hover { background: #eaeaea; }
    .bab-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

    /* --- restore banner --- */
    .bab-restore { background: #fff8e1; border: 1px solid #ffd54f; border-radius: 14px; padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-size: 14px; color: #7a5c00; }
    .bab-restore button { margin-left: auto; border: none; background: #ff8ba7; color: #fff; border-radius: 30px; padding: 6px 16px; font-family: 'Poppins'; font-weight: 600; cursor: pointer; }
    .bab-restore a { color: #7a5c00; text-decoration: underline; cursor: pointer; font-size: 13px; }

    /* --- cute modal (matches site) --- */
    .bab-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(6px); display: none; justify-content: center; align-items: center; z-index: 999999; padding: 20px; }
    .bab-modal-box { background: #fff; border-radius: 30px; padding: 38px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15); animation: babUp 0.3s ease; }
    @keyframes babUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .bab-modal-box .ico { font-size: 46px; color: #ff8ba7; margin-bottom: 14px; }
    .bab-modal-box .ico i { background: #fff0f5; padding: 14px; border-radius: 50%; }
    .bab-modal-box h3 { font-size: 21px; font-weight: 700; color: #222; margin-bottom: 6px; }
    .bab-modal-box p { font-size: 14px; color: #888; margin-bottom: 22px; line-height: 1.5; }
    .bab-modal-btns { display: flex; gap: 12px; }
    .bab-modal-btns button { flex: 1; padding: 12px; border: none; border-radius: 50px; font-family: 'Poppins'; font-weight: 600; font-size: 14px; cursor: pointer; }
    .bab-modal-btns .go { background: #eaeaea; color: #555; }
    .bab-modal-btns .stay { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; }

    .bab-toast { position: fixed; left: 50%; bottom: 30px; transform: translateX(-50%) translateY(120px); background: rgba(255,255,255,0.96); backdrop-filter: blur(12px); color: #333; padding: 15px 28px; border-radius: 16px; border: 1px solid rgba(255,139,167,0.2); box-shadow: 0 10px 40px rgba(0,0,0,0.08); z-index: 999999; font-weight: 500; font-size: 14px; opacity: 0; transition: all 0.4s cubic-bezier(0.175,0.885,0.32,1.275); }
    .bab-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

    @media (max-width: 900px) {
        .bab-right { width: 100%; }
        .bab-panel { position: static; }
    }

    /* --- product quick-view (description + ratings, like the shop) --- */
    .bab-prod-card .bab-prod-img, .bab-prod-card .bab-prod-name { cursor: pointer; }
    .bab-prod-rating { font-size: 11.5px; color: #999; margin-bottom: 8px; }
    .bab-prod-rating .rv-stars { color: #ffb400; font-size: 11.5px; letter-spacing: 0.5px; }
    .bab-prod-grid.list-view .bab-prod-rating { display: none; }

    .bab-qv-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(8px); display: none; justify-content: center; align-items: center; z-index: 999999; padding: 20px; }
    .bab-qv-box { background: #fff; border-radius: 28px; max-width: 780px; width: 100%; max-height: 90vh; box-shadow: 0 25px 60px rgba(0,0,0,0.2); position: relative; display: flex; flex-wrap: wrap; overflow: hidden; animation: babUp 0.3s ease; }
    .bab-qv-close { position: absolute; top: 14px; right: 18px; font-size: 24px; color: #999; cursor: pointer; z-index: 3; background: none; border: none; }
    .bab-qv-close:hover { color: #ff8ba7; }
    .bab-qv-left { flex: 0.9; min-width: 260px; background: #fafafa; padding: 34px; display: flex; align-items: center; justify-content: center; align-self: stretch; }
    .bab-qv-left img { max-width: 100%; max-height: 280px; object-fit: contain; }
    .bab-qv-right { flex: 1.1; min-width: 280px; padding: 34px 32px; display: flex; flex-direction: column; max-height: 90vh; overflow-y: auto; }
    .bab-qv-right h3 { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 6px; }
    .bab-qv-price { font-size: 20px; font-weight: 700; color: #111; margin-bottom: 12px; }
    .bab-qv-price span { color: #888; font-weight: 500; font-size: 14px; }
    .bab-qv-desc { font-size: 14px; color: #666; line-height: 1.65; margin-bottom: 14px; }
    .bab-qv-stock { font-size: 13px; font-weight: 600; margin-bottom: 14px; }
    .bab-qv-add { width: 100%; padding: 12px 0; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'Poppins'; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .bab-qv-add:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.4); }
    .bab-qv-add:disabled { background: #ddd; color: #888; cursor: not-allowed; transform: none; box-shadow: none; }
    #modalReviews { width: 100%; max-height: 230px; overflow-y: auto; margin-top: 8px; }
    #modalReviews .rv-list-scroll { max-height: none; overflow: visible; }
    @media (max-width: 640px) {
        .bab-qv-left { align-self: auto; }
        .bab-qv-right { max-height: none; overflow: visible; }
        #modalReviews { max-height: none; overflow: visible; }
    }
</style>

<div class="bab-wrap">
    <div class="bab-hero">
        <h1><?php echo $edit_box ? 'Edit your box' : 'Build a Box'; ?></h1>
        <p>Pick a box, fill it with hand-picked gifts, and add a little letter. 💌</p>
    </div>

    <div class="bab-steps">
        <div class="bab-step active" id="stepInd1"><span class="num">1</span> Choose a box</div>
        <div class="bab-step-sep"></div>
        <div class="bab-step" id="stepInd2"><span class="num">2</span> Choose items</div>
        <div class="bab-step-sep"></div>
        <div class="bab-step" id="stepInd3"><span class="num">3</span> Write a letter</div>
    </div>

    <div id="restoreBanner" class="bab-restore" style="display:none;">
        <i class="fas fa-clock-rotate-left"></i>
        <span>You have an unsaved box from earlier.</span>
        <button onclick="babRestoreDraft()">Restore</button>
        <a onclick="babDiscardDraft()">Discard</a>
    </div>

    <!-- STEP 1 -->
    <div id="sizeSection">
        <h2 class="bab-section-title"><span class="badge">1</span> Choose your box size</h2>
        <div class="bab-size-grid" id="sizeGrid">
            <?php foreach ($sizes as $i => $s): ?>
                <div class="bab-size-card" data-id="<?php echo $s['id']; ?>" data-max="<?php echo $s['max_items']; ?>"
                     data-name="<?php echo htmlspecialchars($s['name']); ?>" onclick="babChooseSize(this)">
                    <span class="tick"><i class="fas fa-check-circle"></i></span>
                    <div class="ico"><i class="fas fa-gift"></i></div>
                    <h3><?php echo htmlspecialchars($s['name']); ?></h3>
                    <div class="cap">Holds up to <strong><?php echo $s['max_items']; ?></strong> items</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bab-chosen-bar" id="sizeChosenBar">
        <i class="fas fa-gift ic"></i>
        <span class="txt"><b id="chosenSizeName"></b> &middot; holds up to <span id="chosenSizeMax"></span> items</span>
        <button type="button" onclick="babChangeSize()"><i class="fas fa-arrows-rotate"></i> Change box</button>
    </div>

    <div class="bab-main">
        <div class="bab-left bab-locked" id="leftCol">
            <!-- STEP 2 -->
            <h2 class="bab-section-title"><span class="badge">2</span> Choose items for your box</h2>
            <div class="bab-toolbar">
                <div class="bab-search">
                    <input type="text" id="babSearch" placeholder="Search gifts..." onkeydown="if(event.key==='Enter'){babResetAndLoad();}">
                    <button onclick="babResetAndLoad()">Search</button>
                </div>
                <div class="bab-view-toggle" title="Change layout">
                    <button data-view="grid" class="active" onclick="babSetView('grid')" aria-label="Grid view"><i class="fas fa-th-large"></i></button>
                    <button data-view="list" onclick="babSetView('list')" aria-label="List view"><i class="fas fa-list"></i></button>
                </div>
            </div>
            <div class="bab-chips" id="babChips">
                <button class="bab-chip active" data-cat="0" onclick="babPickCat(this)">All Items</button>
                <?php while ($cat_result && $c = $cat_result->fetch_assoc()): ?>
                    <button class="bab-chip" data-cat="<?php echo $c['id']; ?>" onclick="babPickCat(this)"><?php echo htmlspecialchars($c['name']); ?></button>
                <?php endwhile; ?>
            </div>
            <div class="bab-prod-grid" id="prodGrid">
                <div class="bab-empty">Choose a box size to see gifts that fit.</div>
            </div>
            <div class="bab-pager" id="babPager"></div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="bab-right">
            <div class="bab-panel">
                <h3>Your Box</h3>
                <div class="sub" id="panelSizeName">No box selected yet</div>

                <div class="bab-counter"><b id="panelCount">0</b><span id="panelMax">/ 0 items</span></div>
                <div class="bab-bar"><i id="panelBar" style="width:0%"></i></div>

                <ul class="bab-items" id="panelItems"></ul>
                <div class="bab-panel-empty" id="panelEmpty"><i class="fas fa-gift"></i>Your box is empty — add some gifts!</div>

                <button type="button" class="bab-letter-trigger" id="letterTrigger" onclick="babOpenLetter()">
                    <span class="lt-badge" id="ltBadge">3</span>
                    <span class="lt-body">
                        <span class="lt-title" id="ltTitle">Step 3 &middot; Write a letter</span>
                        <span class="lt-sub" id="ltSub">Add a note &amp; choose a card style</span>
                    </span>
                    <span class="lt-cta" id="ltCta">Write</span>
                </button>

                <div class="bab-totrow grand"><span>Total</span><span id="panelTotal">PHP 0.00</span></div>

                <div class="bab-actions">
                    <div class="row">
                        <button class="bab-btn ghost" id="btnSave" onclick="babSubmit('saved')"><i class="fas fa-bookmark"></i> Save</button>
                        <button class="bab-btn ghost" id="btnCart" onclick="babSubmit('in_cart')"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                    </div>
                    <button class="bab-btn primary" id="btnCheckout" onclick="babSubmit('checkout')"><i class="fas fa-lock"></i> Proceed to Checkout</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- unsaved-changes modal -->
<div class="bab-modal-overlay" id="babLeaveModal">
    <div class="bab-modal-box">
        <div class="ico"><i class="fas fa-exclamation-circle"></i></div>
        <h3>Unsaved box</h3>
        <p>You have changes to your box that haven't been saved. What would you like to do?</p>
        <div class="bab-modal-btns" style="flex-direction:column;">
            <button class="stay" onclick="babLeaveModalSave()"><i class="fas fa-bookmark"></i> Save &amp; continue</button>
            <button class="go" onclick="babLeaveModalLeave()">Leave without saving</button>
            <button class="go" style="background:#fff;color:#999;" onclick="babCloseLeaveModal()">Stay on page</button>
        </div>
    </div>
</div>

<!-- letter modal -->
<div class="bab-modal-overlay" id="babLetterModal" onclick="if(event.target===this)babCloseLetter()">
    <div class="bab-letter-modal">
        <div class="lm-head">
            <h3><i class="fas fa-envelope-open-text"></i> Your letter</h3>
            <button class="lm-x" onclick="babCloseLetter()" aria-label="Close">&times;</button>
        </div>

        <label class="lm-label">Card style</label>
        <div class="lm-styles" id="lmStyles">
            <?php foreach (bab_card_styles() as $k => $s): ?>
                <button type="button" class="lm-style" data-style="<?php echo $k; ?>" onclick="babPickCardStyle('<?php echo $k; ?>')">
                    <span class="emo"><?php echo $s['emoji']; ?></span>
                    <span class="lbl"><?php echo htmlspecialchars($s['label']); ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <label class="lm-label">Message <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#999;">(optional)</span></label>
        <textarea id="babLetterText" maxlength="1000" placeholder="Dear ..." oninput="babLetterCount()"></textarea>
        <div class="bab-char"><span id="babChar">0</span> / 1000</div>

        <div class="lm-actions">
            <button type="button" class="bab-btn ghost" onclick="babCloseLetter()">Cancel</button>
            <button type="button" class="bab-btn primary" onclick="babSaveLetter()"><i class="fas fa-check"></i> Save letter</button>
        </div>
    </div>
</div>

<div class="bab-toast" id="babToast"></div>

<!-- product quick-view -->
<div class="bab-qv-overlay" id="babQv">
    <div class="bab-qv-box">
        <button class="bab-qv-close" onclick="babCloseQv()" aria-label="Close">&times;</button>
        <div class="bab-qv-left"><img id="babQvImg" src="" alt=""></div>
        <div class="bab-qv-right">
            <h3 id="babQvName"></h3>
            <div class="bab-qv-price" id="babQvPrice"></div>
            <div class="bab-qv-desc" id="babQvDesc"></div>
            <div class="bab-qv-stock" id="babQvStock"></div>
            <button class="bab-qv-add" id="babQvAddBtn" onclick="babQvAddToBox()"><i class="fas fa-plus"></i> Add to box</button>
            <div id="modalReviews"></div>
        </div>
    </div>
</div>
<script src="reviews_widget.js"></script>

<script>
const BAB = {
    loggedIn: <?php echo $logged_in ? 'true' : 'false'; ?>,
    editing: <?php echo $edit_box ? 'true' : 'false'; ?>,
    state: <?php echo json_encode($initial, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    cardStyles: <?php echo json_encode(bab_card_styles(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    view: 'grid',
    page: 1,
    totalPages: 1,
    lastSnapshot: '',
    dirty: false,
    pendingNav: null,
    eligibleIds: null,
    productCache: {}
};
if (!BAB.state.cardStyle) BAB.state.cardStyle = 'simple';
BAB.lastSnapshot = JSON.stringify(BAB.state);

/* ---------- helpers ---------- */
function babToast(msg) {
    const t = document.getElementById('babToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('show'), 2600);
}
function peso(n) { return 'PHP ' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
function babCount() { return BAB.state.items.reduce((a, it) => a + it.qty, 0); }
function babFindSizeCard(id) { return document.querySelector('.bab-size-card[data-id="' + id + '"]'); }

function babSnapshot() { return JSON.stringify(BAB.state); }
function babMarkDirty() {
    BAB.dirty = (babSnapshot() !== BAB.lastSnapshot);
    babSaveDraft();
}
function babClean() {
    BAB.lastSnapshot = babSnapshot();
    BAB.dirty = false;
    try { localStorage.removeItem('giftly_bab_draft'); } catch (e) {}
}

/* ---------- draft persistence ---------- */
function babSaveDraft() {
    if (BAB.editing) return;
    try {
        if (BAB.state.sizeId || BAB.state.items.length || BAB.state.letter) {
            localStorage.setItem('giftly_bab_draft', babSnapshot());
        }
    } catch (e) {}
}
function babRestoreDraft() {
    try {
        const raw = localStorage.getItem('giftly_bab_draft');
        if (!raw) return;
        BAB.state = JSON.parse(raw);
    } catch (e) { return; }
    document.getElementById('restoreBanner').style.display = 'none';
    if (BAB.state.sizeId) {
        const card = babFindSizeCard(BAB.state.sizeId);
        if (card) {
            document.querySelectorAll('.bab-size-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            babUnlock();
            babResetAndLoad();
        }
    }
    if (!BAB.state.cardStyle) BAB.state.cardStyle = 'simple';
    babRenderLetterTrigger();
    babRender();
    BAB.lastSnapshot = babSnapshot();
    BAB.dirty = false;
    babToast('Box restored');
}
function babDiscardDraft() {
    try { localStorage.removeItem('giftly_bab_draft'); } catch (e) {}
    document.getElementById('restoreBanner').style.display = 'none';
}

/* ---------- step 1: size ---------- */
function babChooseSize(card) {
    const newId = parseInt(card.dataset.id);
    const newMax = parseInt(card.dataset.max);
    if (BAB.state.sizeId === newId) {
        // same size re-picked — just collapse back
        document.getElementById('sizeSection').style.display = 'none';
        document.getElementById('sizeChosenBar').style.display = 'flex';
        return;
    }

    const proceed = () => {
        document.querySelectorAll('.bab-size-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        BAB.state.sizeId = newId;
        babUnlock();
        babPruneForSize(newMax);
        babMarkDirty();
        babRender();
        babResetAndLoad();
    };

    if (BAB.state.items.length && (newMax < babCount())) {
        babConfirm('Smaller box',
            'Your box has ' + babCount() + ' items but a ' + card.dataset.name.toLowerCase() +
            ' holds ' + newMax + '. Extra items will be removed.', proceed);
    } else if (BAB.state.items.length) {
        proceed();
    } else {
        proceed();
    }
}

function babUnlock() {
    document.getElementById('leftCol').classList.remove('bab-locked');
    document.getElementById('stepInd1').classList.add('done');
    document.getElementById('stepInd1').classList.remove('active');
    document.getElementById('stepInd2').classList.add('active');

    // collapse step 1 into a compact bar
    const card = babFindSizeCard(BAB.state.sizeId);
    if (card) {
        document.getElementById('chosenSizeName').textContent = card.dataset.name;
        document.getElementById('chosenSizeMax').textContent = card.dataset.max;
        document.getElementById('sizeSection').style.display = 'none';
        document.getElementById('sizeChosenBar').style.display = 'flex';
    }
}

function babChangeSize() {
    document.getElementById('sizeSection').style.display = 'block';
    document.getElementById('sizeChosenBar').style.display = 'none';
    document.getElementById('sizeSection').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* ---------- product view toggle ---------- */
function babSetView(v) {
    BAB.view = v;
    document.getElementById('prodGrid').classList.toggle('list-view', v === 'list');
    document.querySelectorAll('.bab-view-toggle button').forEach(b => b.classList.toggle('active', b.dataset.view === v));
    try { localStorage.setItem('giftly_bab_view', v); } catch (e) {}
}

/* ---------- letter modal ---------- */
function babOpenLetter() {
    document.getElementById('babLetterText').value = BAB.state.letter || '';
    babLetterCount();
    babPickCardStyle(BAB.state.cardStyle || 'simple', true);
    document.getElementById('babLetterModal').style.display = 'flex';
}
function babCloseLetter() {
    document.getElementById('babLetterModal').style.display = 'none';
}
function babPickCardStyle(key, silent) {
    BAB._pendingStyle = key;
    document.querySelectorAll('#lmStyles .lm-style').forEach(b => b.classList.toggle('active', b.dataset.style === key));
}
function babSaveLetter() {
    BAB.state.letter = document.getElementById('babLetterText').value.trim();
    BAB.state.cardStyle = BAB._pendingStyle || 'simple';
    babCloseLetter();
    babMarkDirty();
    babRenderLetterTrigger();
    babToast('Letter saved');
}
function babLetterCount() {
    const el = document.getElementById('babLetterText');
    document.getElementById('babChar').textContent = el ? el.value.length : 0;
}
function babRenderLetterTrigger() {
    const trg = document.getElementById('letterTrigger');
    const badge = document.getElementById('ltBadge');
    const title = document.getElementById('ltTitle');
    const sub = document.getElementById('ltSub');
    const cta = document.getElementById('ltCta');
    const ind3 = document.getElementById('stepInd3');
    const st = BAB.cardStyles[BAB.state.cardStyle] || BAB.cardStyles.simple;
    const letter = (BAB.state.letter || '').trim();
    const hasLetter = letter.length > 0;
    const styled = BAB.state.cardStyle && BAB.state.cardStyle !== 'simple';

    if (hasLetter || styled) {
        trg.classList.add('set');
        trg.classList.remove('nudge');
        badge.innerHTML = '<i class="fas fa-check"></i>';
        title.textContent = st.emoji + ' ' + st.label + ' card';
        sub.textContent = hasLetter
            ? '“' + letter.slice(0, 44) + (letter.length > 44 ? '…' : '') + '”'
            : 'No message — tap to add one';
        cta.textContent = 'Edit';
        ind3.classList.add('done');
        ind3.classList.remove('active');
    } else {
        trg.classList.remove('set');
        badge.textContent = '3';
        title.textContent = 'Step 3 · Write a letter';
        sub.textContent = 'Add a note & choose a card style';
        cta.textContent = 'Write';
        ind3.classList.remove('done');
        const nudge = BAB.state.items.length > 0;
        trg.classList.toggle('nudge', nudge);
        ind3.classList.toggle('active', nudge);
    }
}

function babPruneForSize(newMax) {
    // trim overflow from the end
    let total = babCount();
    while (total > newMax && BAB.state.items.length) {
        const last = BAB.state.items[BAB.state.items.length - 1];
        const canTrim = Math.min(last.qty, total - newMax);
        last.qty -= canTrim;
        total -= canTrim;
        if (last.qty <= 0) BAB.state.items.pop();
    }
    // prune items not eligible for the new size (checked once ids arrive)
    fetch('build_a_box_products.php?ids_only=1&size_id=' + BAB.state.sizeId)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') return;
            BAB.eligibleIds = new Set(d.ids);
            const before = BAB.state.items.length;
            BAB.state.items = BAB.state.items.filter(it => BAB.eligibleIds.has(it.product_id));
            if (BAB.state.items.length !== before) {
                babToast('Some items were removed — not available in this box size');
            }
            babMarkDirty();
            babRender();
            babSyncGridButtons();
        });
}

/* ---------- step 2: products ---------- */
let babCat = 0;
function babPickCat(btn) {
    document.querySelectorAll('#babChips .bab-chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    babCat = parseInt(btn.dataset.cat);
    babResetAndLoad();
}
function babResetAndLoad() {
    BAB.page = 1;
    babLoadProducts();
}
function babGoPage(n) {
    if (n < 1 || n > BAB.totalPages || n === BAB.page) return;
    BAB.page = n;
    babLoadProducts();
    const anchor = document.getElementById('babSearch');
    if (anchor) window.scrollTo({ top: anchor.getBoundingClientRect().top + window.pageYOffset - 90, behavior: 'smooth' });
}

function babLoadProducts() {
    if (!BAB.state.sizeId) return;
    const grid = document.getElementById('prodGrid');
    grid.innerHTML = '<div class="bab-empty"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    const q = encodeURIComponent(document.getElementById('babSearch').value.trim());
    const url = 'build_a_box_products.php?size_id=' + BAB.state.sizeId +
                '&search=' + q + '&category=' + babCat + '&page=' + BAB.page;
    fetch(url).then(r => r.json()).then(d => {
        if (d.status !== 'success') { grid.innerHTML = '<div class="bab-empty">Could not load gifts.</div>'; babRenderPager(); return; }
        BAB.totalPages = d.pagination.total_pages;
        grid.innerHTML = '';
        if (d.products.length === 0) {
            grid.innerHTML = '<div class="bab-empty"><i class="fas fa-box-open" style="font-size:36px;display:block;margin-bottom:10px;color:#ddd;"></i>No gifts match this box &amp; filter.</div>';
        }
        d.products.forEach(p => {
            BAB.productCache[p.id] = p;
            grid.insertAdjacentHTML('beforeend', babProdCard(p));
        });
        babRenderPager();
        babSyncGridButtons();
    });
}

function babRenderPager() {
    const el = document.getElementById('babPager');
    const total = BAB.totalPages || 1;
    if (total <= 1) { el.innerHTML = ''; return; }
    const cur = BAB.page;
    let html = '<button ' + (cur <= 1 ? 'disabled' : '') + ' onclick="babGoPage(' + (cur - 1) + ')">&lsaquo;</button>';
    const pages = [];
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= cur - 1 && i <= cur + 1)) pages.push(i);
        else if (pages[pages.length - 1] !== '…') pages.push('…');
    }
    pages.forEach(p => {
        if (p === '…') html += '<button class="dots" disabled>…</button>';
        else html += '<button class="' + (p === cur ? 'active' : '') + '" onclick="babGoPage(' + p + ')">' + p + '</button>';
    });
    html += '<button ' + (cur >= total ? 'disabled' : '') + ' onclick="babGoPage(' + (cur + 1) + ')">&rsaquo;</button>';
    el.innerHTML = html;
}

function babStarsHtml(avg) {
    avg = Number(avg) || 0;
    let h = '';
    for (let i = 1; i <= 5; i++) {
        if (avg >= i) h += '<i class="fas fa-star"></i>';
        else if (avg >= i - 0.5) h += '<i class="fas fa-star-half"></i>';
        else h += '<i class="far fa-star"></i>';
    }
    return '<span class="rv-stars">' + h + '</span>';
}

function babProdCard(p) {
    const rating = (p.rating_count > 0)
        ? '<div class="bab-prod-rating" onclick="babQuickView(' + p.id + ')">' + babStarsHtml(p.rating) +
          ' <span>(' + p.rating_count + ')</span></div>'
        : '';
    return '<div class="bab-prod-card" data-pid="' + p.id + '" data-stock="' + p.quantity + '" data-price="' + p.price + '"' +
           ' data-name="' + encodeURIComponent(p.name) + '" data-image="' + encodeURIComponent(p.image) + '">' +
           '<div class="bab-prod-img" onclick="babQuickView(' + p.id + ')"><img src="uploads/' + p.image + '" alt=""></div>' +
           '<div class="bab-prod-name" onclick="babQuickView(' + p.id + ')">' + babEsc(p.name) + '</div>' +
           rating +
           '<div class="bab-prod-price">PHP <span>' + Number(p.price).toFixed(2) + '</span></div>' +
           '<div class="foot" id="foot_' + p.id + '"></div></div>';
}

/* ---------- product quick-view ---------- */
let babQvPid = 0;
function babQuickView(pid) {
    const p = BAB.productCache[pid];
    if (!p) return;
    babQvPid = pid;
    document.getElementById('babQvImg').src = 'uploads/' + p.image;
    document.getElementById('babQvImg').alt = p.name;
    document.getElementById('babQvName').textContent = p.name;
    document.getElementById('babQvPrice').innerHTML = 'PHP ' + Number(p.price).toFixed(2);
    document.getElementById('babQvDesc').textContent = p.description || 'No description available.';
    const stockEl = document.getElementById('babQvStock');
    const addBtn = document.getElementById('babQvAddBtn');
    const inBox = BAB.state.items.find(i => i.product_id === pid);
    if (p.quantity > 0) {
        stockEl.style.color = '#2e7d32';
        stockEl.textContent = 'In stock: ' + p.quantity + ' available' + (inBox ? ' · ' + inBox.qty + ' in your box' : '');
        addBtn.disabled = false;
        addBtn.innerHTML = inBox ? '<i class="fas fa-plus"></i> Add one more' : '<i class="fas fa-plus"></i> Add to box';
    } else {
        stockEl.style.color = '#d32f2f';
        stockEl.textContent = 'Out of stock';
        addBtn.disabled = true;
        addBtn.innerHTML = '<i class="fas fa-times-circle"></i> Unavailable';
    }
    document.getElementById('babQv').style.display = 'flex';
    if (window.loadProductReviews) loadProductReviews(pid);
}
function babCloseQv() { document.getElementById('babQv').style.display = 'none'; }
function babQvAddToBox() {
    if (!babQvPid) return;
    const inBox = BAB.state.items.find(i => i.product_id === babQvPid);
    if (inBox) babInc(babQvPid);
    else babAdd(babQvPid);
    babCloseQv();
}
document.getElementById('babQv').addEventListener('click', function (e) { if (e.target === this) babCloseQv(); });
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') babCloseQv(); });
function babEsc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function babSyncGridButtons() {
    document.querySelectorAll('.bab-prod-card').forEach(card => {
        const pid = parseInt(card.dataset.pid);
        const stock = parseInt(card.dataset.stock);
        const foot = card.querySelector('.foot');
        const item = BAB.state.items.find(i => i.product_id === pid);
        const full = babCount() >= babMax();
        if (item) {
            const plusDisabled = (item.qty >= stock || full);
            foot.innerHTML = '<div class="bab-mini-qty">' +
                '<button onclick="babDec(' + pid + ')">&minus;</button>' +
                '<span>' + item.qty + ' in box</span>' +
                '<button ' + (plusDisabled ? 'disabled style="opacity:.3;cursor:not-allowed;"' : '') +
                ' onclick="babInc(' + pid + ')">+</button></div>';
        } else if (full) {
            foot.innerHTML = '<button class="bab-add-btn disabled"><i class="fas fa-check"></i> Box full</button>';
        } else {
            foot.innerHTML = '<button class="bab-add-btn" onclick="babAdd(' + pid + ')"><i class="fas fa-plus"></i> Add to box</button>';
        }
    });
}

function babMax() {
    const card = babFindSizeCard(BAB.state.sizeId);
    return card ? parseInt(card.dataset.max) : 0;
}

/* ---------- box mutations ---------- */
function babAdd(pid) {
    if (babCount() >= babMax()) { babToast('This box is full'); return; }
    const card = document.querySelector('.bab-prod-card[data-pid="' + pid + '"]');
    if (!card) return;
    const stock = parseInt(card.dataset.stock);
    if (stock <= 0) { babToast('Out of stock'); return; }
    BAB.state.items.push({
        product_id: pid,
        name: decodeURIComponent(card.dataset.name),
        price: parseFloat(card.dataset.price),
        image: decodeURIComponent(card.dataset.image),
        qty: 1,
        stock: stock
    });
    babMarkDirty(); babRender(); babSyncGridButtons();
}
function babInc(pid) {
    const it = BAB.state.items.find(i => i.product_id === pid);
    if (!it) return;
    if (babCount() >= babMax()) { babToast('This box is full'); return; }
    if (it.qty >= it.stock) { babToast('Only ' + it.stock + ' in stock'); return; }
    it.qty++;
    babMarkDirty(); babRender(); babSyncGridButtons();
}
function babDec(pid) {
    const idx = BAB.state.items.findIndex(i => i.product_id === pid);
    if (idx < 0) return;
    BAB.state.items[idx].qty--;
    if (BAB.state.items[idx].qty <= 0) BAB.state.items.splice(idx, 1);
    babMarkDirty(); babRender(); babSyncGridButtons();
}
function babRemove(pid) {
    BAB.state.items = BAB.state.items.filter(i => i.product_id !== pid);
    babMarkDirty(); babRender(); babSyncGridButtons();
}

/* ---------- render ---------- */
function babRender() {
    const count = babCount();
    const max = babMax();
    const card = babFindSizeCard(BAB.state.sizeId);
    document.getElementById('panelSizeName').textContent = card ? card.dataset.name : 'No box selected yet';
    document.getElementById('panelCount').textContent = count;
    document.getElementById('panelMax').textContent = '/ ' + max + ' items';
    document.getElementById('panelBar').style.width = max ? Math.min(100, (count / max) * 100) + '%' : '0%';

    const ul = document.getElementById('panelItems');
    const empty = document.getElementById('panelEmpty');
    ul.innerHTML = '';
    if (BAB.state.items.length === 0) {
        empty.style.display = 'block';
    } else {
        empty.style.display = 'none';
        BAB.state.items.forEach(it => {
            ul.insertAdjacentHTML('beforeend',
                '<li>' +
                '<img src="uploads/' + it.image + '" alt="">' +
                '<div class="n">' + babEsc(it.name) + '<small>' + peso(it.price) + '</small></div>' +
                '<div class="stepper">' +
                '<button onclick="babDec(' + it.product_id + ')">&minus;</button>' +
                '<span>' + it.qty + '</span>' +
                '<button onclick="babInc(' + it.product_id + ')">+</button>' +
                '</div>' +
                '<button class="rm" onclick="babRemove(' + it.product_id + ')"><i class="fas fa-times"></i></button>' +
                '</li>');
        });
    }

    let subtotal = BAB.state.items.reduce((a, it) => a + it.price * it.qty, 0);
    document.getElementById('panelTotal').textContent = peso(subtotal);

    if (BAB.state.sizeId) {
        document.getElementById('stepInd2').classList.toggle('done', count > 0);
        document.getElementById('stepInd2').classList.toggle('active', count === 0);
    }

    const hasItems = BAB.state.items.length > 0;
    document.getElementById('btnSave').disabled = !hasItems;
    document.getElementById('btnCart').disabled = !hasItems;
    document.getElementById('btnCheckout').disabled = !hasItems;

    babRenderLetterTrigger();
}

/* ---------- submit ---------- */
function babSubmit(mode) {
    if (!BAB.loggedIn) { babToast('Please log in to continue'); if (window.openLoginModal) setTimeout(openLoginModal, 250); return; }
    if (BAB.state.items.length === 0) { babToast('Add at least one gift'); return; }

    const status = (mode === 'checkout') ? 'in_cart' : mode;
    const payload = new URLSearchParams();
    payload.append('action', 'save');
    payload.append('box_id', BAB.state.boxId || 0);
    payload.append('size_id', BAB.state.sizeId);
    payload.append('letter', BAB.state.letter || '');
    payload.append('card_style', BAB.state.cardStyle || 'simple');
    payload.append('status', status);
    payload.append('items', JSON.stringify(BAB.state.items.map(i => ({ product_id: i.product_id, quantity: i.qty }))));

    const btns = ['btnSave', 'btnCart', 'btnCheckout'].map(id => document.getElementById(id));
    btns.forEach(b => b.disabled = true);

    fetch('box_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload })
        .then(r => r.json())
        .then(d => {
            btns.forEach(b => b.disabled = false);
            if (d.status !== 'success') {
                if (d.code === 'login_required') { if (window.openLoginModal) openLoginModal(); return; }
                babToast(d.message || 'Something went wrong');
                return;
            }
            BAB.state.boxId = d.box_id;
            babClean();
            if (mode === 'checkout') {
                window.location.href = 'box_checkout.php?box_id=' + d.box_id;
            } else if (mode === 'in_cart') {
                babToast('Box added to cart 🎁');
                setTimeout(() => { window.location.href = 'cart.php#boxes'; }, 900);
            } else {
                babToast('Box saved 💾');
                setTimeout(() => { window.location.href = 'profile.php?tab=boxes'; }, 900);
            }
        })
        .catch(() => { btns.forEach(b => b.disabled = false); babToast('Network error'); });
}

/* ---------- confirm modal ---------- */
let babConfirmCb = null;
function babConfirm(title, body, cb) {
    babConfirmCb = cb;
    const m = document.getElementById('babLeaveModal');
    m.querySelector('h3').textContent = title;
    m.querySelector('p').textContent = body;
    m.querySelector('.bab-modal-btns').innerHTML =
        '<button class="go" onclick="babCloseLeaveModal()">Cancel</button>' +
        '<button class="stay" onclick="babConfirmYes()">Continue</button>';
    m.style.display = 'flex';
}
function babConfirmYes() { document.getElementById('babLeaveModal').style.display = 'none'; if (babConfirmCb) babConfirmCb(); babConfirmCb = null; }

/* ---------- unsaved-changes guard ---------- */
function babCloseLeaveModal() { document.getElementById('babLeaveModal').style.display = 'none'; BAB.pendingNav = null; babConfirmCb = null; }
function babLeaveModalLeave() { const url = BAB.pendingNav; babClean(); BAB.pendingNav = null; document.getElementById('babLeaveModal').style.display = 'none'; if (url) window.location.href = url; }
function babLeaveModalSave() {
    document.getElementById('babLeaveModal').style.display = 'none';
    const url = BAB.pendingNav; BAB.pendingNav = null;
    if (!BAB.loggedIn || BAB.state.items.length === 0) { babClean(); if (url) window.location.href = url; return; }
    // reuse submit as "save", then navigate
    const payload = new URLSearchParams();
    payload.append('action', 'save');
    payload.append('box_id', BAB.state.boxId || 0);
    payload.append('size_id', BAB.state.sizeId);
    payload.append('letter', BAB.state.letter || '');
    payload.append('card_style', BAB.state.cardStyle || 'simple');
    payload.append('status', 'saved');
    payload.append('items', JSON.stringify(BAB.state.items.map(i => ({ product_id: i.product_id, quantity: i.qty }))));
    fetch('box_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload })
        .then(r => r.json()).then(() => { babClean(); if (url) window.location.href = url; });
}

window.addEventListener('beforeunload', function (e) {
    if (BAB.dirty) { e.preventDefault(); e.returnValue = ''; return ''; }
});

document.addEventListener('click', function (e) {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || a.target === '_blank') return;
    if (a.hasAttribute('onclick')) return;
    if (!BAB.dirty) return;
    e.preventDefault();
    BAB.pendingNav = a.href;
    const m = document.getElementById('babLeaveModal');
    m.querySelector('h3').textContent = 'Unsaved box';
    m.querySelector('p').textContent = "You have changes to your box that haven't been saved. What would you like to do?";
    m.querySelector('.bab-modal-btns').innerHTML =
        '<div style="display:flex;flex-direction:column;gap:10px;width:100%;">' +
        '<button class="stay" onclick="babLeaveModalSave()"><i class="fas fa-bookmark"></i> Save &amp; continue</button>' +
        '<button class="go" onclick="babLeaveModalLeave()">Leave without saving</button>' +
        '<button class="go" style="background:#fff;color:#999;" onclick="babCloseLeaveModal()">Stay on page</button>' +
        '</div>';
    m.style.display = 'flex';
}, true);

/* ---------- init ---------- */
(function init() {
    // restore saved product-layout preference
    try {
        const v = localStorage.getItem('giftly_bab_view');
        if (v === 'list' || v === 'grid') babSetView(v);
    } catch (e) {}

    if (BAB.editing && BAB.state.sizeId) {
        const card = babFindSizeCard(BAB.state.sizeId);
        if (card) card.classList.add('selected');
        babUnlock();
        babResetAndLoad();
    } else {
        try {
            const raw = localStorage.getItem('giftly_bab_draft');
            if (raw) {
                const d = JSON.parse(raw);
                if (d && (d.sizeId || (d.items && d.items.length))) {
                    document.getElementById('restoreBanner').style.display = 'flex';
                }
            }
        } catch (e) {}
    }
    babRenderLetterTrigger();
    babRender();
})();
</script>

<?php include 'footer.php'; ?>
