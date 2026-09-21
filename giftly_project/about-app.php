<?php
include 'db_connect.php';
include 'header.php';
include_once 'about_lib.php';

$features = [
    ['fa-bag-shopping', 'Browse & shop',       'Explore curated products by category, save favourites, and check out in a few taps.'],
    ['fa-box-open',     'Build-a-Box',         "Assemble your own gift box item by item, exactly the way you'd wrap it yourself."],
    ['fa-credit-card',  'Easy checkout',       'Pay your way and choose delivery to yourself or straight to the recipient, gift message included.'],
    ['fa-location-dot', 'Order tracking',      'Follow every order from confirmation to doorstep, right from your orders list.'],
    ['fa-address-book', 'Saved recipients',    'Save the people you gift most, with their birthdays and occasions, so a repeat gift is one tap away.'],
    ['fa-bell',         'Reminders that help', "Get a nudge before an occasion, and a notification the moment your order's status changes."],
];

about_open('about-app.php');
?>

    <!-- HERO -->
    <div class="ab-hero">
        <span class="kicker">The Giftly App</span>
        <h1>Everything you need to <span>gift well</span>.</h1>
        <p>The whole gifting experience in your pocket — from browsing to Build-a-Box to tracking what's on its way, with order updates and reminders on your phone.</p>
    </div>

    <!-- FEATURES -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>What you can do</h2>
            <p>Everything on this site, plus notifications, in one app.</p>
        </div>
        <div class="ab-values">
            <?php foreach ($features as $f): ?>
                <div class="ab-value">
                    <div class="ic"><i class="fas <?php echo $f[0]; ?>"></i></div>
                    <h3><?php echo htmlspecialchars($f[1]); ?></h3>
                    <p><?php echo htmlspecialchars($f[2]); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php about_cta(); ?>

</div>

<?php include 'footer.php'; ?>
