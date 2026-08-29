<?php
include 'db_connect.php';
include 'header.php';

/*
 * Drop real photos into  uploads/about/  using these names (jpg / png / webp):
 *   store-1 … store-4     – photos of the shop
 *   owner-1 … owner-5     – headshots of the five owners
 * Missing files fall back to a styled placeholder automatically.
 */
function about_img($base) {
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $p = "uploads/about/{$base}.{$ext}";
        if (file_exists(__DIR__ . '/' . $p)) return $p;
    }
    return null;
}

$owners = [
    ['name' => 'Bea Marasigan',   'role' => 'Founder & CEO',            'bio' => 'Started Giftly from her apartment with a glue gun and a lot of ribbon.'],
    ['name' => 'Miguel Tan',       'role' => 'Head of Design',           'bio' => 'Obsesses over paper weight, palette and the perfect bow.'],
    ['name' => 'Carla Villanueva', 'role' => 'Operations & Logistics',   'bio' => 'Makes sure every box arrives on time and in one beautiful piece.'],
    ['name' => 'Josh Fernandez',   'role' => 'Head of Curation',         'bio' => 'Hunts down the small-batch makers behind our favourite finds.'],
    ['name' => 'Ana Delos Reyes',  'role' => 'Customer Happiness Lead',  'bio' => 'The voice on the other end of every message — and every thank-you note.'],
];

$timeline = [
    ['year' => '2021', 'title' => 'A kitchen-table idea',   'text' => 'Bea wrapped a birthday gift for a friend who lived overseas. It arrived looking like a mess — and Giftly was born from the belief that the wrapping matters as much as the gift.'],
    ['year' => '2022', 'title' => 'Our first little shop',  'text' => 'We opened a tiny studio storefront, hand-tying every box and writing every card ourselves.'],
    ['year' => '2023', 'title' => 'Giftly goes online',     'text' => 'The website launched so anyone could send a curated box across the country in a few clicks.'],
    ['year' => '2024', 'title' => 'Build-a-Box arrives',    'text' => 'Customers asked to choose exactly what goes inside — so we built the tool to let them design their own.'],
    ['year' => 'Today','title' => 'Still cherishing moments','text' => 'Thousands of boxes later, five of us run Giftly with the same rule we started with: pack it like it\'s going to someone you love.'],
];
?>

<style>
    .ab-wrap { max-width: 1100px; margin: 0 auto; padding: 130px 20px 0; }

    /* HERO */
    .ab-hero { text-align: center; margin-bottom: 70px; position: relative; }
    .ab-hero .kicker { display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff8ba7; background: #fff0f5; padding: 6px 16px; border-radius: 50px; margin-bottom: 18px; }
    .ab-hero h1 { font-size: 42px; font-weight: 700; color: #222; line-height: 1.2; margin-bottom: 16px; }
    .ab-hero h1 span { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .ab-hero p { font-size: 16px; color: #888; max-width: 620px; margin: 0 auto; line-height: 1.7; }
    .ab-hero .floaty { position: absolute; opacity: 0.5; pointer-events: none; }
    .ab-hero .f1 { top: -10px; left: 4%; width: 70px; animation: abFloat 6s ease-in-out infinite; }
    .ab-hero .f2 { bottom: -30px; right: 5%; width: 90px; animation: abFloat 7s ease-in-out infinite reverse; }
    @keyframes abFloat { 0%,100% { transform: translateY(0) rotate(-4deg); } 50% { transform: translateY(-16px) rotate(4deg); } }

    .ab-section { margin-bottom: 80px; }
    .ab-section-head { text-align: center; margin-bottom: 44px; }
    .ab-section-head h2 { font-size: 28px; font-weight: 700; color: #222; margin-bottom: 8px; }
    .ab-section-head p { font-size: 15px; color: #999; }

    /* STORY TIMELINE */
    .ab-timeline { position: relative; max-width: 760px; margin: 0 auto; padding-left: 34px; }
    .ab-timeline::before { content: ''; position: absolute; left: 7px; top: 6px; bottom: 6px; width: 3px; border-radius: 3px; background: linear-gradient(180deg, #FEA5B6 0%, #ffd9e2 100%); }
    .ab-t-item { position: relative; padding-bottom: 34px; }
    .ab-t-item:last-child { padding-bottom: 0; }
    .ab-t-item::before { content: ''; position: absolute; left: -34px; top: 4px; width: 17px; height: 17px; border-radius: 50%; background: #fff; border: 4px solid #ff8ba7; box-shadow: 0 0 0 5px #fff0f5; }
    .ab-t-year { display: inline-block; font-size: 12px; font-weight: 700; color: #ff8ba7; letter-spacing: 1px; margin-bottom: 4px; }
    .ab-t-item h3 { font-size: 18px; font-weight: 700; color: #222; margin-bottom: 6px; }
    .ab-t-item p { font-size: 14.5px; color: #777; line-height: 1.7; }

    /* STORE GALLERY */
    .ab-gallery { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: 150px; gap: 16px; }
    .ab-shot { border-radius: 22px; overflow: hidden; position: relative; background: #f4f4f6; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
    .ab-shot:nth-child(1) { grid-column: span 2; grid-row: span 2; }
    .ab-shot:nth-child(4) { grid-column: span 2; }
    .ab-shot img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .ab-shot:hover img { transform: scale(1.06); }
    .ab-shot .ph { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #d9a7b6; background: linear-gradient(135deg, #fff0f5 0%, #ffe4ec 100%); }
    .ab-shot .ph i { font-size: 30px; }
    .ab-shot .ph span { font-size: 12px; font-weight: 600; letter-spacing: 0.5px; }

    /* OWNERS */
    .ab-owners { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 24px; }
    .ab-owner { background: #fff; border-radius: 24px; padding: 28px 20px; text-align: center; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #f5f5f5; transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.3s; }
    .ab-owner:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(255,139,167,0.14); }
    .ab-avatar { width: 116px; height: 116px; border-radius: 50%; margin: 0 auto 16px; padding: 4px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); }
    .ab-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block; background: #fff; }
    .ab-avatar .initials { width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff0f5; color: #ff8ba7; font-size: 34px; font-weight: 700; }
    .ab-owner h3 { font-size: 16px; font-weight: 700; color: #222; }
    .ab-owner .role { font-size: 12.5px; font-weight: 600; color: #ff8ba7; text-transform: uppercase; letter-spacing: 0.5px; margin: 3px 0 10px; }
    .ab-owner .bio { font-size: 13px; color: #888; line-height: 1.6; }

    /* VALUES */
    .ab-values { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 22px; }
    .ab-value { background: #fff; border-radius: 22px; padding: 32px 26px; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #f5f5f5; }
    .ab-value .ic { width: 52px; height: 52px; border-radius: 16px; background: #fff0f5; color: #ff8ba7; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px; }
    .ab-value h3 { font-size: 17px; font-weight: 700; color: #222; margin-bottom: 8px; }
    .ab-value p { font-size: 14px; color: #888; line-height: 1.7; }

    /* CTA */
    .ab-cta { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); border-radius: 32px; padding: 54px 30px; text-align: center; color: #fff; box-shadow: 0 20px 50px rgba(254,165,182,0.35); margin-bottom: 40px; }
    .ab-cta h2 { font-size: 28px; font-weight: 700; margin-bottom: 10px; }
    .ab-cta p { font-size: 15px; opacity: 0.95; margin-bottom: 24px; }
    .ab-cta a { display: inline-block; background: #fff; color: #ff8ba7; font-weight: 700; font-size: 15px; padding: 14px 34px; border-radius: 50px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; }
    .ab-cta a:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(0,0,0,0.15); }

    @media (max-width: 720px) {
        .ab-hero h1 { font-size: 32px; }
        .ab-gallery { grid-template-columns: repeat(2, 1fr); grid-auto-rows: 130px; }
        .ab-shot:nth-child(1), .ab-shot:nth-child(4) { grid-column: span 2; grid-row: span 1; }
        .ab-hero .floaty { display: none; }
    }
</style>

<div class="ab-wrap">

    <!-- HERO -->
    <div class="ab-hero">
        <img src="giftbox.png" alt="" class="floaty f1">
        <img src="occasion_box.png" alt="" class="floaty f2">
        <span class="kicker">Our Story</span>
        <h1>Wrapped with intention,<br>sent with <span>love</span>.</h1>
        <p>Giftly began with one badly-wrapped present and a simple conviction: the way a gift arrives is part of the gift. Today we're a small team building boxes we'd be proud to receive ourselves.</p>
    </div>

    <!-- STORY -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>How we got here</h2>
            <p>A few moments that made Giftly what it is.</p>
        </div>
        <div class="ab-timeline">
            <?php foreach ($timeline as $t): ?>
                <div class="ab-t-item">
                    <span class="ab-t-year"><?php echo htmlspecialchars($t['year']); ?></span>
                    <h3><?php echo htmlspecialchars($t['title']); ?></h3>
                    <p><?php echo htmlspecialchars($t['text']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- STORE -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>Inside the studio</h2>
            <p>Where the ribbon-tying, letter-writing and last-minute magic happens.</p>
        </div>
        <div class="ab-gallery">
            <?php
            $shots = [
                ['store-1', 'Our storefront'],
                ['store-2', 'The wrapping bench'],
                ['store-3', 'Curated shelves'],
                ['store-4', 'Packing day'],
            ];
            foreach ($shots as $s):
                $src = about_img($s[0]);
            ?>
                <div class="ab-shot">
                    <?php if ($src): ?>
                        <img src="<?php echo htmlspecialchars($src); ?>" alt="<?php echo htmlspecialchars($s[1]); ?>">
                    <?php else: ?>
                        <div class="ph"><i class="fas fa-camera-retro"></i><span><?php echo htmlspecialchars($s[1]); ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- OWNERS -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>The five of us</h2>
            <p>The people who read your gift messages before they're sent.</p>
        </div>
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
                    <div class="role"><?php echo htmlspecialchars($o['role']); ?></div>
                    <div class="bio"><?php echo htmlspecialchars($o['bio']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- VALUES -->
    <div class="ab-section">
        <div class="ab-section-head">
            <h2>What we care about</h2>
        </div>
        <div class="ab-values">
            <div class="ab-value">
                <div class="ic"><i class="fas fa-magnifying-glass"></i></div>
                <h3>Thoughtful curation</h3>
                <p>Every item is chosen by hand from makers we actually love. Nothing goes in a box just to fill space.</p>
            </div>
            <div class="ab-value">
                <div class="ic"><i class="fas fa-heart"></i></div>
                <h3>Handmade with care</h3>
                <p>We wrap, tie and hand-write each box in-house — the way you would for someone you love.</p>
            </div>
            <div class="ab-value">
                <div class="ic"><i class="fas fa-truck-fast"></i></div>
                <h3>Delivered with respect</h3>
                <p>We treat your surprise like it's ours: tracked, protected, and on time for the moment that matters.</p>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="ab-cta">
        <h2>Ready to send something lovely?</h2>
        <p>Build your own box, piece by piece — or browse our ready-made ones.</p>
        <a href="build-a-box.php"><i class="fas fa-gift" style="margin-right:8px;"></i> Start building</a>
    </div>

</div>

<?php include 'footer.php'; ?>
