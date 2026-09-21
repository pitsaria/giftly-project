<?php
include 'db_connect.php';
include 'header.php';
include_once 'about_lib.php';

// What you can order — each links to the matching shop page (null = info only).
$offerings = [
    ['fa-gift',          'Build-a-Box',     'Pick your own box and fill it item by item, exactly to their taste.',                                   'build-a-box.php'],
    ['fa-cake-candles',  'Occasion Boxes',  'Ready-made boxes for birthdays, anniversaries and every occasion in between.',                          'occasion-boxes.php'],
    ['fa-basket-shopping', 'Baskets',       'Beautifully arranged baskets filled with premium goodies for any celebration.',                          'baskets.php'],
    ['fa-truck-fast',    'Tracked delivery', 'Every order is tracked from checkout to doorstep, delivered to you or straight to the recipient.',        null],
];
$values = [
    ['fa-magnifying-glass', 'Thoughtful curation',    'Every item is chosen by hand from makers we actually love. Nothing goes in a box just to fill space.'],
    ['fa-heart',            'Handmade with care',     'We wrap, tie and hand-write each box in-house — the way you would for someone you love.'],
    ['fa-truck-fast',       'Delivered with respect', "We treat your surprise like it's ours: tracked, protected, and on time for the moment that matters."],
];

about_open('services.php');
?>

    <!-- HERO -->
    <div class="ab-hero">
        <span class="kicker">What We Offer</span>
        <h1>Gifting, <span>handled</span>.</h1>
        <p>From ready-made boxes to ones you build yourself, every order is curated, wrapped and delivered with the same care.</p>
    </div>

    <!-- OFFERINGS -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>What you can order</h2>
        </div>
        <div class="ab-values">
            <?php foreach ($offerings as $o):
                $tag = $o[3] ? 'a' : 'div';
            ?>
                <<?php echo $tag; ?> class="ab-value"<?php if ($o[3]) echo ' href="' . htmlspecialchars($o[3]) . '"'; ?>>
                    <div class="ic"><i class="fas <?php echo $o[0]; ?>"></i></div>
                    <h3><?php echo htmlspecialchars($o[1]); ?></h3>
                    <p><?php echo htmlspecialchars($o[2]); ?></p>
                    <?php if ($o[3]): ?><span class="more">Explore &rarr;</span><?php endif; ?>
                </<?php echo $tag; ?>>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- VALUES -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>What we care about</h2>
        </div>
        <div class="ab-values">
            <?php foreach ($values as $v): ?>
                <div class="ab-value">
                    <div class="ic"><i class="fas <?php echo $v[0]; ?>"></i></div>
                    <h3><?php echo htmlspecialchars($v[1]); ?></h3>
                    <p><?php echo htmlspecialchars($v[2]); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php about_cta(); ?>

</div>

<?php include 'footer.php'; ?>
