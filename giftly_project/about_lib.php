<?php
/**
 * Shared pieces for the four About pages — company-history.php, services.php,
 * about-app.php and developers.php (the old single about.php was split up and
 * now just redirects). Mirrors the mobile app's four separate About screens.
 *
 * Real photos live in  uploads/about/  as store-1 … store-4 and owner-1 …
 * owner-5 (jpg / jpeg / png / webp); anything missing falls back to a styled
 * placeholder.
 */

if (!function_exists('about_img')) {

    function about_img($base) {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $p = "uploads/about/{$base}.{$ext}";
            if (file_exists(__DIR__ . '/' . $p)) return $p;
        }
        return null;
    }

    function about_owners() {
        return [
            ['name' => 'Peatzie Cosino',   'role' => 'Founder & CEO',          'bio' => 'Started Giftly from a kitchen table with a glue gun and a lot of ribbon.'],
            ['name' => 'Angela Castillo',  'role' => 'Head of Design',         'bio' => 'Obsesses over paper weight, palette, and the perfect bow.'],
            ['name' => 'Feliciti Gacilla', 'role' => 'Operations & Logistics', 'bio' => 'Makes sure every box arrives on time and in one beautiful piece.'],
            ['name' => 'Gabriel Edpao',    'role' => 'Head of Curation',       'bio' => 'Hunts down the small-batch makers behind our favourite finds.'],
            ['name' => 'Rachelle Dilig',   'role' => 'Customer Happiness',     'bio' => 'The voice on the other end of every message — and every thank-you note.'],
        ];
    }

    /** The four pages, in tab order: file => label. */
    function about_pages() {
        return [
            'company-history.php' => 'Company History',
            'services.php'        => 'Services',
            'about-app.php'       => 'About the App',
            'developers.php'      => 'Developers',
        ];
    }

    /** Emits the shared CSS, opens .ab-wrap, and prints the tab bar. Pages close the div themselves. */
    function about_open($active) {
        ?>
<style>
    .ab-wrap { max-width: 1100px; margin: 0 auto; padding: 130px 20px 0; }

    /* TAB BAR — links the four About pages together */
    .ab-tabs { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 44px; }
    .ab-tabs a { padding: 10px 24px; border-radius: 50px; background: #fff0f5; color: #ff8ba7; font-size: 14px; font-weight: 600; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; }
    .ab-tabs a:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(255, 139, 167, 0.2); }
    .ab-tabs a.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; box-shadow: 0 8px 20px rgba(254, 165, 182, 0.4); }

    /* HERO */
    .ab-hero { text-align: center; margin-bottom: 70px; }
    .ab-hero .kicker { display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff8ba7; background: #fff0f5; padding: 6px 16px; border-radius: 50px; margin-bottom: 18px; }
    .ab-hero h1 { font-size: 42px; font-weight: 700; color: #222; line-height: 1.2; margin-bottom: 16px; }
    .ab-hero h1 span { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .ab-hero p { font-size: 16px; color: #888; max-width: 620px; margin: 0 auto; line-height: 1.7; }

    .ab-section { margin-bottom: 80px; }
    .ab-section-head { text-align: center; margin-bottom: 44px; }
    .ab-section-head h2 { font-size: 28px; font-weight: 700; color: #222; margin-bottom: 8px; }
    .ab-section-head p { font-size: 15px; color: #999; }

    /* TIMELINE (Company History) */
    .ab-timeline { max-width: 740px; margin: 0 auto; }
    .ab-step { position: relative; display: flex; gap: 24px; padding-bottom: 36px; }
    .ab-step:last-child { padding-bottom: 0; }
    .ab-step:not(:last-child)::before { content: ''; position: absolute; left: 21px; top: 50px; bottom: 6px; width: 3px; border-radius: 3px; background: linear-gradient(to bottom, #ffb9c8, #ffe3ea); }
    .ab-dot { flex-shrink: 0; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 17px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.45); }
    .ab-step-body { padding-top: 6px; }
    .ab-step-body h3 { font-size: 19px; font-weight: 700; color: #222; margin-bottom: 6px; }
    .ab-step-body p { font-size: 15px; color: #777; line-height: 1.8; }

    /* STORE GALLERY */
    .ab-gallery { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: 150px; gap: 16px; }
    .ab-shot { border-radius: 22px; overflow: hidden; position: relative; background: #f4f4f6; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
    .ab-shot:nth-child(1) { grid-column: span 2; grid-row: span 2; }
    .ab-shot:nth-child(4) { grid-column: span 2; }
    .ab-shot img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .ab-shot:hover img { transform: scale(1.06); }
    .ab-shot .cap { position: absolute; left: 0; right: 0; bottom: 0; padding: 30px 16px 12px; font-size: 13px; font-weight: 600; color: #fff; background: linear-gradient(to top, rgba(0,0,0,0.5), rgba(0,0,0,0)); }
    .ab-shot .ph { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #d9a7b6; background: linear-gradient(135deg, #fff0f5 0%, #ffe4ec 100%); }
    .ab-shot .ph i { font-size: 30px; }
    .ab-shot .ph span { font-size: 12px; font-weight: 600; letter-spacing: 0.5px; }

    /* TEAM (Developers) */
    .ab-owners { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 24px; }
    .ab-owner { background: #fff; border-radius: 24px; padding: 28px 20px; text-align: center; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #f5f5f5; transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.3s; }
    .ab-owner:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(255,139,167,0.14); }
    .ab-avatar { width: 116px; height: 116px; border-radius: 50%; margin: 0 auto 16px; padding: 4px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); }
    .ab-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; object-position: center 20%; display: block; background: #fff; border: 3px solid #fff; }
    .ab-avatar .initials { width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff0f5; color: #ff8ba7; font-size: 34px; font-weight: 700; }
    .ab-owner h3 { font-size: 16px; font-weight: 700; color: #222; }
    /* Fixed-height role row so a two-line role pill doesn't push that card's bio out of line. */
    .ab-owner .role-wrap { min-height: 52px; display: flex; align-items: center; justify-content: center; margin: 6px 0 8px; }
    .ab-owner .role { font-size: 11px; font-weight: 700; line-height: 1.35; color: #ff8ba7; background: #fff0f5; text-transform: uppercase; letter-spacing: 0.6px; padding: 5px 12px; border-radius: 50px; }
    .ab-owner .bio { font-size: 13px; color: #888; line-height: 1.6; }

    /* CARDS (Services offerings, values, app features) */
    .ab-values { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 22px; }
    .ab-value { display: block; background: #fff; border-radius: 22px; padding: 32px 26px; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #f5f5f5; text-decoration: none; }
    a.ab-value { transition: transform 0.25s, box-shadow 0.25s; }
    a.ab-value:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(255,139,167,0.16); }
    .ab-value .ic { width: 52px; height: 52px; border-radius: 16px; background: #fff0f5; color: #ff8ba7; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px; }
    .ab-value h3 { font-size: 17px; font-weight: 700; color: #222; margin-bottom: 8px; }
    .ab-value p { font-size: 14px; color: #888; line-height: 1.7; }
    .ab-value .more { display: inline-block; margin-top: 14px; font-size: 14px; font-weight: 700; color: #ff8ba7; }

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
        .ab-step { gap: 16px; }
    }
</style>

<div class="ab-wrap">
    <div class="ab-tabs">
        <?php foreach (about_pages() as $file => $label): ?>
            <a href="<?php echo $file; ?>" class="<?php echo $file === $active ? 'active' : ''; ?>"><?php echo htmlspecialchars($label); ?></a>
        <?php endforeach; ?>
    </div>
<?php
    }

    function about_cta() {
        ?>
    <div class="ab-cta">
        <h2>Ready to send something lovely?</h2>
        <p>Build your own box, piece by piece — or browse our ready-made ones.</p>
        <a href="build-a-box.php"><i class="fas fa-gift" style="margin-right:8px;"></i> Start building</a>
    </div>
<?php
    }
}
