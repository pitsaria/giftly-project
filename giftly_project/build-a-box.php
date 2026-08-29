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
$initial = ['boxId' => 0, 'sizeId' => 0, 'letter' => '', 'items' => []];
if ($edit_box) {
    $initial['boxId']  = intval($edit_box['box']['id']);
    $initial['sizeId'] = intval($edit_box['box']['box_size_id']);
    $initial['letter'] = $edit_box['box']['letter'];
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
    .bab-wrap { max-width: 1200px; margin: 0 auto; padding: 130px 20px 40px; }
    .bab-hero { text-align: center; margin-bottom: 35px; }
    .bab-hero h1 { font-size: 30px; font-weight: 700; color: #222; margin-bottom: 8px; }
    .bab-hero p { color: #888; font-size: 15px; }

    /* --- STEP INDICATOR --- */
    .bab-steps { display: flex; justify-content: center; gap: 10px; margin-bottom: 40px; flex-wrap: wrap; }
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
    .bab-prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 18px; }
    .bab-prod-card {
        background: #fff; border-radius: 20px; padding: 16px; border: 1px solid #f0f0f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04); display: flex; flex-direction: column;
    }
    .bab-prod-img { background: #f8f8fa; border-radius: 14px; height: 140px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
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
    .bab-loadmore { text-align: center; margin-top: 22px; }
    .bab-loadmore button { padding: 10px 26px; border-radius: 30px; border: 1.5px solid #ffc1cc; background: #fff; color: #ff8ba7; font-weight: 600; cursor: pointer; font-family: 'Poppins'; }

    /* --- LETTER --- */
    .bab-letter-card { background: #fff; border-radius: 22px; padding: 26px; border: 1px solid #f0f0f0; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-top: 40px; }
    .bab-letter-card textarea {
        width: 100%; min-height: 130px; border: 1.5px solid #eee; border-radius: 14px; padding: 14px 16px;
        font-family: 'Poppins'; font-size: 14px; resize: vertical; outline: none; background: #fafafa;
    }
    .bab-letter-card textarea:focus { border-color: #ffc1cc; background: #fff; }
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

    .bab-panel-empty { text-align: center; color: #bbb; font-size: 13px; padding: 24px 0; }
    .bab-totrow { display: flex; justify-content: space-between; font-size: 14px; color: #555; margin-bottom: 6px; }
    .bab-totrow.grand { border-top: 1px solid #f0f0f0; padding-top: 12px; margin-top: 10px; font-size: 17px; font-weight: 700; color: #222; }

    .bab-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
    .bab-btn { width: 100%; padding: 13px 0; border: none; border-radius: 50px; cursor: pointer; font-family: 'Poppins'; font-weight: 600; font-size: 14px; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
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

    <div class="bab-main">
        <div class="bab-left bab-locked" id="leftCol">
            <!-- STEP 2 -->
            <h2 class="bab-section-title"><span class="badge">2</span> Choose items for your box</h2>
            <div class="bab-search">
                <input type="text" id="babSearch" placeholder="Search gifts..." onkeydown="if(event.key==='Enter'){babResetAndLoad();}">
                <button onclick="babResetAndLoad()">Search</button>
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
            <div class="bab-loadmore" id="loadMoreWrap" style="display:none;">
                <button onclick="babLoadMore()">Load more</button>
            </div>

            <!-- STEP 3 -->
            <div class="bab-letter-card">
                <h2 class="bab-section-title" style="margin-bottom:8px;"><span class="badge">3</span> Write your letter</h2>
                <p style="font-size:13px;color:#888;margin-bottom:14px;">This message is tucked into the box for the recipient. (Optional)</p>
                <textarea id="babLetter" maxlength="1000" placeholder="Dear ..." oninput="babLetterInput()"></textarea>
                <div class="bab-char"><span id="babChar">0</span> / 1000</div>
            </div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="bab-right">
            <div class="bab-panel">
                <h3>Your Box</h3>
                <div class="sub" id="panelSizeName">No box selected yet</div>

                <div class="bab-counter"><b id="panelCount">0</b><span id="panelMax">/ 0 items</span></div>
                <div class="bab-bar"><i id="panelBar" style="width:0%"></i></div>

                <ul class="bab-items" id="panelItems"></ul>
                <div class="bab-panel-empty" id="panelEmpty">Your box is empty — add some gifts!</div>

                <div class="bab-totrow grand"><span>Total</span><span id="panelTotal">PHP 0.00</span></div>

                <div class="bab-actions">
                    <button class="bab-btn ghost" id="btnSave" onclick="babSubmit('saved')"><i class="fas fa-bookmark"></i> Save</button>
                    <button class="bab-btn ghost" id="btnCart" onclick="babSubmit('in_cart')"><i class="fas fa-cart-plus"></i> Add to Cart</button>
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

<div class="bab-toast" id="babToast"></div>

<script>
const BAB = {
    loggedIn: <?php echo $logged_in ? 'true' : 'false'; ?>,
    editing: <?php echo $edit_box ? 'true' : 'false'; ?>,
    state: <?php echo json_encode($initial, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    page: 1,
    totalPages: 1,
    lastSnapshot: '',
    dirty: false,
    pendingNav: null,
    eligibleIds: null
};
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
    document.getElementById('babLetter').value = BAB.state.letter || '';
    babLetterCount();
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
    if (BAB.state.sizeId === newId) return;

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
    document.getElementById('prodGrid').innerHTML = '<div class="bab-empty"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    babLoadProducts(true);
}
function babLoadMore() { BAB.page++; babLoadProducts(false); }

function babLoadProducts(replace) {
    if (!BAB.state.sizeId) return;
    const q = encodeURIComponent(document.getElementById('babSearch').value.trim());
    const url = 'build_a_box_products.php?size_id=' + BAB.state.sizeId +
                '&search=' + q + '&category=' + babCat + '&page=' + BAB.page;
    fetch(url).then(r => r.json()).then(d => {
        const grid = document.getElementById('prodGrid');
        if (d.status !== 'success') { grid.innerHTML = '<div class="bab-empty">Could not load gifts.</div>'; return; }
        BAB.totalPages = d.pagination.total_pages;
        if (replace) grid.innerHTML = '';
        if (replace && d.products.length === 0) {
            grid.innerHTML = '<div class="bab-empty"><i class="fas fa-box-open" style="font-size:36px;display:block;margin-bottom:10px;color:#ddd;"></i>No gifts match this box &amp; filter.</div>';
        }
        d.products.forEach(p => grid.insertAdjacentHTML('beforeend', babProdCard(p)));
        document.getElementById('loadMoreWrap').style.display = (BAB.page < BAB.totalPages) ? 'block' : 'none';
        babSyncGridButtons();
    });
}

function babProdCard(p) {
    return '<div class="bab-prod-card" data-pid="' + p.id + '" data-stock="' + p.quantity + '" data-price="' + p.price + '"' +
           ' data-name="' + encodeURIComponent(p.name) + '" data-image="' + encodeURIComponent(p.image) + '">' +
           '<div class="bab-prod-img"><img src="uploads/' + p.image + '" alt=""></div>' +
           '<div class="bab-prod-name">' + babEsc(p.name) + '</div>' +
           '<div class="bab-prod-price">PHP <span>' + Number(p.price).toFixed(2) + '</span></div>' +
           '<div class="foot" id="foot_' + p.id + '"></div></div>';
}
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

/* ---------- letter ---------- */
function babLetterInput() {
    BAB.state.letter = document.getElementById('babLetter').value;
    babLetterCount();
    babMarkDirty();
    if (BAB.state.letter.trim().length) {
        document.getElementById('stepInd3').classList.add('done');
    } else {
        document.getElementById('stepInd3').classList.remove('done');
    }
}
function babLetterCount() { document.getElementById('babChar').textContent = document.getElementById('babLetter').value.length; }

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

    document.getElementById('stepInd2').classList.toggle('done', count > 0);

    const hasItems = BAB.state.items.length > 0;
    document.getElementById('btnSave').disabled = !hasItems;
    document.getElementById('btnCart').disabled = !hasItems;
    document.getElementById('btnCheckout').disabled = !hasItems;
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
                setTimeout(() => { window.location.href = 'cart.php'; }, 900);
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
    if (BAB.editing && BAB.state.sizeId) {
        const card = babFindSizeCard(BAB.state.sizeId);
        if (card) card.classList.add('selected');
        babUnlock();
        document.getElementById('babLetter').value = BAB.state.letter || '';
        babLetterCount();
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
    babRender();
})();
</script>

<?php include 'footer.php'; ?>
