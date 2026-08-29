<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the IDs of items the user checked
    $selected_ids = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];
    
    if(empty($selected_ids)) {
        // If they didn't check anything, redirect back to cart
        header("Location: cart.php");
        exit();
    }

    // Convert array to string for SQL (e.g., "1,3,5")
    $ids_string = implode(',', array_map('intval', $selected_ids));
    
    $cart_result = $conn->query("SELECT c.product_id, c.quantity, p.price FROM carts c JOIN products p ON c.product_id = p.id WHERE c.user_id = $user_id AND c.id IN ($ids_string)");

    $total_amount = 0;
    $items = [];
    while($row = $cart_result->fetch_assoc()){
        $total_amount += $row['price'] * $row['quantity'];
        $items[] = $row;
    }

    // GET ADDRESS & PAYMENT DATA
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $recipient = isset($_POST['recipient_name']) ? mysqli_real_escape_string($conn, $_POST['recipient_name']) : NULL;
    $payment = mysqli_real_escape_string($conn, $_POST['payment_method']);

    // INSERT ORDERS WITH NEW COLUMNS
    $conn->query("INSERT INTO orders (user_id, total_amount, status, fullname, address, city, recipient_name, payment_method) 
                  VALUES ($user_id, $total_amount, 'pending', '$fullname', '$address', '$city', '$recipient', '$payment')");
    
    $order_id = $conn->insert_id;

        foreach($items as $item) {
        // 1. Save the order items
        $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ($order_id, {$item['product_id']}, {$item['quantity']}, {$item['price']})");
        
        // 2. 🚀 DECREASE THE STOCK BY THE ORDERED QUANTITY
        $conn->query("UPDATE products SET quantity = quantity - {$item['quantity']} WHERE id = {$item['product_id']}");
    }

    // Delete ONLY the items that were checked out
    $conn->query("DELETE FROM carts WHERE user_id = $user_id AND id IN ($ids_string)");
    include 'header.php'; 
    ?>
    
    <style>
        .success-container { max-width: 600px; margin: 0 auto; padding-top: 130px; padding-bottom: 80px; text-align: center; }
        .success-icon { font-size: 80px; color: #2e7d32; background: #e8f5e9; width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px auto; box-shadow: 0 10px 30px rgba(46, 125, 50, 0.15); }
        .success-title { font-size: 32px; font-weight: 700; color: #222; margin-bottom: 10px; }
        .success-sub { color: #888; font-size: 16px; margin-bottom: 25px; }
        .success-details { background: #f9f9f9; border-radius: 20px; padding: 20px; margin-bottom: 30px; display: inline-block; min-width: 250px; }
        .success-details p { margin: 5px 0; font-size: 16px; color: #555; }
        .btn-success-shop { background: #ffc1cc; color: white; padding: 14px 45px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 16px; transition: 0.2s; display: inline-block; }
        .btn-success-shop:hover { background: #ff8ba7; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(255,139,167,0.3); }
    </style>

    <div class="success-container">
        <div class="success-icon"><i class="fas fa-check"></i></div>
        <div class="success-title">Order Placed Successfully! 🎉</div>
        <div class="success-sub">Thank you for your purchase. We'll start preparing your order right away.</div>
        <div class="success-details">
            <p><strong>Order ID:</strong> #<?php echo $order_id; ?></p>
            <p><strong>Total Paid:</strong> PHP <?php echo number_format($total_amount, 2); ?></p>
            <p><strong>Payment:</strong> <?php echo ucfirst($payment); ?></p>
            <?php if($recipient): ?>
                <p><strong>Recipient:</strong> <?php echo $recipient; ?></p>
            <?php endif; ?>
        </div>
        <a href="shop.php" class="btn-success-shop">Continue Shopping</a>
    </div>

    <?php
    include 'footer.php';
    exit(); 
}
?>

<style>
    .checkout-wrapper { max-width: 550px; margin: 0 auto; padding-top: 130px; padding-bottom: 60px; }
    .checkout-card { background: #ffffff; border-radius: 30px; padding: 40px 35px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04); border: 1px solid rgba(255, 255, 255, 0.8); }
    .checkout-title { font-size: 22px; font-weight: 600; color: #222; margin-bottom: 8px; }
    .checkout-sub { font-size: 14px; color: #888; margin-bottom: 25px; }
    .form-label { display: block; font-size: 14px; font-weight: 600; color: #444; margin-bottom: 6px; }
    .form-input { width: 100%; padding: 14px 16px; border: 1.5px solid #eee; border-radius: 16px; font-size: 14px; font-family: 'Poppins'; background: #fafafa; transition: 0.3s; margin-bottom: 18px; outline: none; }
    .form-input:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1); }
    .btn-place-order { width: 100%; background: #ffc1cc; color: white; padding: 16px; border: none; border-radius: 50px; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 5px; }
    .btn-place-order:hover { background: #ff8ba7; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,139,167,0.2); }
    .summary-box { background: #fafafa; border-radius: 16px; padding: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; font-size: 16px; font-weight: 600; color: #222; }
    .delivery-options { display: flex; gap: 15px; margin-bottom: 20px; }
    .delivery-option { flex: 1; text-align: center; padding: 14px 10px; border: 2px solid #eee; border-radius: 16px; cursor: pointer; transition: 0.3s; background: #fff; font-family: 'Poppins'; font-weight: 500; font-size: 14px; color: #555; }
    .delivery-option:hover { border-color: #ffc1cc; background: #fff0f5; }
    .delivery-option input[type="radio"] { display: none; }
    .delivery-option.selected { border-color: #ff8ba7; background: #fff0f5; color: #d32f2f; box-shadow: 0 0 0 3px rgba(255, 139, 167, 0.1); }
    #recipientField { display: none; margin-top: -10px; margin-bottom: 15px; }
    #recipientField.show { display: block; }
    .payment-section { margin-top: 10px; margin-bottom: 20px; padding: 15px; background: #fafafa; border-radius: 16px; }
    .payment-options { display: flex; gap: 15px; margin-top: 10px; flex-wrap: wrap; }
    .payment-option { flex: 1; min-width: 120px; text-align: center; padding: 12px 10px; border: 2px solid #eee; border-radius: 16px; cursor: pointer; transition: 0.3s; background: #fff; font-weight: 500; font-size: 14px; color: #555; font-family: 'Poppins'; }
    .payment-option:hover { border-color: #ffc1cc; background: #fff0f5; }
    .payment-option input[type="radio"] { display: none; }
    .payment-option.selected { border-color: #ff8ba7; background: #fff0f5; color: #d32f2f; }
    .payment-option.disabled { opacity: 0.4; background: #eee; border-color: #ddd; color: #999; cursor: not-allowed; pointer-events: none; }
</style>

<div class="checkout-wrapper">
    <div class="checkout-card">
        <div style="font-size: 40px; color: #ff8ba7; margin-bottom: 10px; text-align: center;">
            <i class="fas fa-gift" style="background: #fff0f5; padding: 12px; border-radius: 50%;"></i>
        </div>
        <div class="checkout-title" style="text-align: center;">Complete Your Order</div>
        <div class="checkout-sub" style="text-align: center;">Choose delivery & payment method.</div>
        
        <?php
        $total_sum = 0;
        $sum_res = $conn->query("SELECT SUM(c.quantity * p.price) as total FROM carts c JOIN products p ON c.product_id = p.id WHERE c.user_id = $user_id");
        if($sum_res) {
            $row = $sum_res->fetch_assoc();
            $total_sum = floatval($row['total'] ?? 0);
        }
        ?>
        <div class="summary-box">
            <span>Cart Total</span>
            <span>PHP <?php echo number_format($total_sum, 2); ?></span>
        </div>

        <div class="delivery-options">
            <label class="delivery-option selected" id="optMe" onclick="selectDelivery('me')">
                <input type="radio" name="delivery_type" value="me" checked>
                <i class="fas fa-home" style="font-size: 20px; display: block; margin-bottom: 5px;"></i>
                Deliver to Me
            </label>
            <label class="delivery-option" id="optRecipient" onclick="selectDelivery('recipient')">
                <input type="radio" name="delivery_type" value="recipient">
                <i class="fas fa-user-friends" style="font-size: 20px; display: block; margin-bottom: 5px;"></i>
                Deliver to Recipient
            </label>
        </div>

        <form action="checkout.php" method="POST">
            <h3 style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 15px;">Shipping Details</h3>
            
            <label class="form-label">Full Name</label>
            <input type="text" name="fullname" class="form-input" placeholder="Enter your full name" required>
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-input" placeholder="Street address, P.O. Box" required>
            <label class="form-label">City / Province</label>
            <input type="text" name="city" class="form-input" placeholder="City, Province" required>

            <div id="recipientField">
                <label class="form-label">Recipient's Name</label>
                <input type="text" name="recipient_name" class="form-input" placeholder="Who is this gift for?">
            </div>

            <div class="payment-section">
                <label class="form-label" style="margin-bottom: 5px;">Payment Method</label>
                <div class="payment-options" id="paymentContainer">
                    <label class="payment-option selected" id="payCOD">
                        <input type="radio" name="payment_method" value="cod" checked>
                        <i class="fas fa-money-bill-wave" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>
                        Cash on Delivery
                    </label>
                    <label class="payment-option" id="payCard">
                        <input type="radio" name="payment_method" value="card">
                        <i class="fas fa-credit-card" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>
                        Credit / Debit Card
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn-place-order"><i class="fas fa-lock" style="margin-right: 8px;"></i> Place Order Now</button>
        </form>
    </div>
</div>

<script>
    function selectDelivery(type) {
        document.getElementById('optMe').classList.remove('selected');
        document.getElementById('optRecipient').classList.remove('selected');
        document.getElementById('opt' + (type === 'me' ? 'Me' : 'Recipient')).classList.add('selected');

        if(type === 'recipient') {
            document.getElementById('recipientField').classList.add('show');
            document.getElementById('payCOD').classList.add('disabled');
            if(document.getElementById('payCOD').querySelector('input[type="radio"]').checked) {
                document.getElementById('payCOD').classList.remove('selected');
                document.getElementById('payCard').classList.add('selected');
                document.getElementById('payCard').querySelector('input[type="radio"]').checked = true;
                document.getElementById('payCOD').querySelector('input[type="radio"]').checked = false;
            }
        } else {
            document.getElementById('recipientField').classList.remove('show');
            document.getElementById('payCOD').classList.remove('disabled');
        }
    }
</script>

<?php include 'footer.php'; ?>