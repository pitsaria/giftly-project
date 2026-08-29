<?php
/**
 * AJAX (HTML): the products in one delivered+received order, each with a
 * star-rating form pre-filled with the shopper's existing review.
 * GET: order_id
 */
include 'db_connect.php';
include 'reviews_lib.php';
reviews_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) { echo '<p style="text-align:center;color:#d32f2f;">Please log in.</p>'; exit(); }
$user_id  = (int) $_SESSION['user_id'];
$order_id = (int) ($_GET['order_id'] ?? 0);

// verify the order is the user's and eligible
$o = $conn->query("SELECT id FROM orders WHERE id = $order_id AND user_id = $user_id
                   AND status = 'delivered' AND received_at IS NOT NULL");
if (!$o || $o->num_rows === 0) {
    echo '<p style="text-align:center;color:#d32f2f;">This order isn\'t ready for reviews yet.</p>';
    exit();
}

$items = $conn->query("
    SELECT DISTINCT p.id, p.name, p.image
    FROM order_items oi JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = $order_id
");

if (!$items || $items->num_rows === 0) {
    echo '<p style="text-align:center;color:#999;">No items to review.</p>';
    exit();
}

while ($it = $items->fetch_assoc()) {
    $pid  = (int) $it['id'];
    $mine = reviews_user_review($conn, $user_id, $pid);
    $rating = $mine ? (int) $mine['rating'] : 0;
    ?>
    <div class="rvm-item">
        <div class="prod">
            <img src="uploads/<?php echo htmlspecialchars($it['image']); ?>" alt="">
            <strong><?php echo htmlspecialchars($it['name']); ?></strong>
        </div>
        <input type="hidden" class="rvm-rating" value="<?php echo $rating; ?>">
        <div class="rvm-pick">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fas fa-star<?php echo $rating >= $i ? ' on' : ''; ?>" data-v="<?php echo $i; ?>"></i>
            <?php endfor; ?>
        </div>
        <textarea maxlength="1500" placeholder="Optional — what did you think?"><?php echo $mine ? htmlspecialchars($mine['comment']) : ''; ?></textarea>
        <div>
            <button type="button" class="save" onclick="rvmSave(this, <?php echo $pid; ?>)"><?php echo $mine ? 'Update' : 'Post review'; ?></button>
            <span class="st"></span>
        </div>
    </div>
    <?php
}
