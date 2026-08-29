<?php
/**
 * AJAX (HTML fragment): reviews section for a product's quick-view modal.
 * GET: product_id
 */
include 'db_connect.php';
include 'reviews_lib.php';
reviews_ensure_schema($conn);

$product_id = (int) ($_GET['product_id'] ?? 0);
if ($product_id <= 0) { echo ''; exit(); }

$summary = reviews_summary($conn, $product_id);
$reviews = reviews_list($conn, $product_id, 30);

$logged_in = isset($_SESSION['user_id']);
$my_review = $logged_in ? reviews_user_review($conn, $_SESSION['user_id'], $product_id) : null;
$eligible  = $logged_in ? reviews_eligible_order($conn, $_SESSION['user_id'], $product_id) : 0;
?>
<style>
    .rv-wrap { margin-top: 22px; border-top: 1px solid #f0f0f0; padding-top: 18px; }
    .rv-head { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; flex-wrap: wrap; }
    .rv-avg { font-size: 30px; font-weight: 800; color: #222; line-height: 1; }
    .rv-stars { color: #ffb400; font-size: 14px; letter-spacing: 1px; }
    .rv-count { font-size: 13px; color: #999; }
    .rv-none { font-size: 13px; color: #999; }

    .rv-form { background: #fff8fa; border: 1px solid #ffe0e9; border-radius: 16px; padding: 16px; margin-bottom: 16px; }
    .rv-form h5 { font-size: 14px; font-weight: 700; color: #222; margin-bottom: 10px; }
    .rv-pick { display: flex; gap: 4px; font-size: 22px; color: #ddd; margin-bottom: 10px; cursor: pointer; }
    .rv-pick i { transition: 0.1s; }
    .rv-pick i.on { color: #ffb400; }
    .rv-form textarea { width: 100%; min-height: 70px; border: 1.5px solid #eee; border-radius: 12px; padding: 10px 12px; font-family: 'Poppins'; font-size: 13.5px; resize: vertical; outline: none; background: #fff; }
    .rv-form textarea:focus { border-color: #ffc1cc; }
    .rv-form button { margin-top: 10px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border: none; border-radius: 50px; padding: 9px 22px; font-family: 'Poppins'; font-weight: 600; font-size: 13px; cursor: pointer; }
    .rv-form .msg { font-size: 12px; margin-top: 8px; }

    .rv-item { padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
    .rv-item:last-child { border-bottom: none; }
    .rv-item .top { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 4px; }
    .rv-item .who { font-size: 13.5px; font-weight: 700; color: #333; }
    .rv-item .when { font-size: 11px; color: #aaa; }
    .rv-item .txt { font-size: 13px; color: #666; line-height: 1.6; }
    .rv-list-scroll { max-height: 260px; overflow-y: auto; }
</style>

<div class="rv-wrap" id="rvWrap" data-pid="<?php echo $product_id; ?>">
    <div class="rv-head">
        <h4 style="font-size:16px;font-weight:700;color:#222;margin:0;">Reviews</h4>
        <?php if ($summary['count'] > 0): ?>
            <span class="rv-avg"><?php echo number_format($summary['avg'], 1); ?></span>
            <?php echo reviews_stars($summary['avg']); ?>
            <span class="rv-count"><?php echo $summary['count']; ?> review<?php echo $summary['count'] === 1 ? '' : 's'; ?></span>
        <?php else: ?>
            <span class="rv-none">No reviews yet.</span>
        <?php endif; ?>
    </div>

    <?php if ($eligible > 0): ?>
        <div class="rv-form">
            <h5><?php echo $my_review ? 'Edit your review' : 'Write a review'; ?></h5>
            <div class="rv-pick" id="rvPick">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star<?php echo ($my_review && (int) $my_review['rating'] >= $i) ? ' on' : ''; ?>" data-v="<?php echo $i; ?>"></i>
                <?php endfor; ?>
            </div>
            <input type="hidden" id="rvRating" value="<?php echo $my_review ? (int) $my_review['rating'] : 0; ?>">
            <textarea id="rvComment" maxlength="1500" placeholder="Share an honest thought about this item…"><?php echo $my_review ? htmlspecialchars($my_review['comment']) : ''; ?></textarea>
            <button type="button" onclick="rvSubmit()"><?php echo $my_review ? 'Update review' : 'Post review'; ?></button>
            <div class="msg" id="rvMsg"></div>
        </div>
    <?php elseif ($logged_in): ?>
        <div class="rv-none" style="margin-bottom:12px;"><i class="fas fa-lock" style="margin-right:5px;"></i> You can review this once you've received an order that includes it.</div>
    <?php endif; ?>

    <?php if ($reviews): ?>
        <div class="rv-list-scroll">
            <?php foreach ($reviews as $rv): ?>
                <div class="rv-item">
                    <div class="top">
                        <span class="who"><?php echo htmlspecialchars($rv['user_name']); ?></span>
                        <span class="when"><?php echo date('M j, Y', strtotime($rv['created_at'])); ?></span>
                    </div>
                    <?php echo reviews_stars((int) $rv['rating']); ?>
                    <?php if (trim($rv['comment']) !== ''): ?>
                        <div class="txt" style="margin-top:4px;"><?php echo nl2br(htmlspecialchars($rv['comment'])); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
