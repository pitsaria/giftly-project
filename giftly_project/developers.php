<?php
include 'db_connect.php';
include 'header.php';
include_once 'about_lib.php';

$owners = about_owners();

about_open('developers.php');
?>

    <!-- HERO -->
    <div class="ab-hero">
        <span class="kicker">The Team</span>
        <h1>The people behind <span>Giftly</span>.</h1>
        <p>A small team building boxes we'd be proud to receive ourselves. The people who read your gift messages before they're sent.</p>
    </div>

    <!-- TEAM -->
    <div class="ab-section">
        <div class="ab-owners">
            <?php foreach ($owners as $i => $o):
                $src = about_img('owner-' . ($i + 1));
                $parts = preg_split('/\s+/', trim($o['name']));
                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
            ?>
                <div class="ab-owner">
                    <div class="ab-avatar">
                        <?php if ($src): ?>
                            <img src="<?php echo htmlspecialchars($src); ?>" alt="<?php echo htmlspecialchars($o['name']); ?>">
                        <?php else: ?>
                            <div class="initials"><?php echo htmlspecialchars($initials); ?></div>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($o['name']); ?></h3>
                    <div class="role-wrap"><div class="role"><?php echo htmlspecialchars($o['role']); ?></div></div>
                    <div class="bio"><?php echo htmlspecialchars($o['bio']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php about_cta(); ?>

</div>

<?php include 'footer.php'; ?>
