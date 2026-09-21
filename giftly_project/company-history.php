<?php
include 'db_connect.php';
include 'header.php';
include_once 'about_lib.php';

// Draft story — placeholder copy until the team's real founding details are filled in.
$chapters = [
    ['The kitchen table',        'It started with a glue gun, a roll of ribbon, and a handful of orders from friends who kept asking where we got our wrapping done.'],
    ['A studio of our own',      'Word spread faster than we expected. The kitchen table became a small studio — pictured below — where every box is still cut, folded and tied by hand.'],
    ['Giftly, the app',          'As orders outgrew paper and pen, we built the Giftly app so customers could build their own box, follow it from checkout to doorstep, and never forget an occasion again.'],
    ['Still small, same promise', "We're still a small team, and the conviction hasn't changed: the way a gift arrives is part of the gift."],
];
$shots = [
    ['store-1', 'Our storefront'],
    ['store-2', 'The wrapping bench'],
    ['store-3', 'Curated shelves'],
    ['store-4', 'Packing day'],
];

about_open('company-history.php');
?>

    <!-- HERO -->
    <div class="ab-hero">
        <span class="kicker">Our Story</span>
        <h1>Wrapped with intention,<br>sent with <span>love</span>.</h1>
        <p>Giftly began with one badly-wrapped present and a simple conviction: the way a gift arrives is part of the gift.</p>
    </div>

    <!-- TIMELINE -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>How we got here</h2>
        </div>
        <div class="ab-timeline">
            <?php foreach ($chapters as $i => $c): ?>
                <div class="ab-step">
                    <div class="ab-dot"><?php echo $i + 1; ?></div>
                    <div class="ab-step-body">
                        <h3><?php echo htmlspecialchars($c[0]); ?></h3>
                        <p><?php echo htmlspecialchars($c[1]); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- STUDIO -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>Inside the studio</h2>
            <p>Where the ribbon-tying, letter-writing and last-minute magic happens.</p>
        </div>
        <div class="ab-gallery">
            <?php foreach ($shots as $s):
                $src = about_img($s[0]);
            ?>
                <div class="ab-shot">
                    <?php if ($src): ?>
                        <img src="<?php echo htmlspecialchars($src); ?>" alt="<?php echo htmlspecialchars($s[1]); ?>">
                        <span class="cap"><?php echo htmlspecialchars($s[1]); ?></span>
                    <?php else: ?>
                        <div class="ph"><i class="fas fa-camera-retro"></i><span><?php echo htmlspecialchars($s[1]); ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php about_cta(); ?>

</div>

<?php include 'footer.php'; ?>
