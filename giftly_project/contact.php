<?php
include 'db_connect.php';
include 'contact_lib.php';
contact_ensure_schema($conn);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '')                              $errors[] = 'Please tell us your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($message) < 5)                       $errors[] = 'Your message is a little short.';

    if (!$errors) {
        $n = $conn->real_escape_string(mb_substr($name, 0, 120));
        $e = $conn->real_escape_string(mb_substr($email, 0, 160));
        $s = $conn->real_escape_string(mb_substr($subject, 0, 160));
        $m = $conn->real_escape_string(mb_substr($message, 0, 4000));
        $conn->query("INSERT INTO contact_messages (name, email, subject, message)
                      VALUES ('$n', '$e', '$s', '$m')");
        $_SESSION['contact_sent'] = true;
        header('Location: contact.php?sent=1');
        exit();
    }
    $_SESSION['contact_errors'] = $errors;
    $_SESSION['contact_old'] = compact('name', 'email', 'subject', 'message');
    header('Location: contact.php#form');
    exit();
}

$sent = isset($_GET['sent']) && !empty($_SESSION['contact_sent']);
unset($_SESSION['contact_sent']);
$errors = $_SESSION['contact_errors'] ?? [];
$old = $_SESSION['contact_old'] ?? ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
unset($_SESSION['contact_errors'], $_SESSION['contact_old']);

include 'header.php';
?>

<style>
    .ct-wrap { max-width: 1100px; margin: 0 auto; padding: 130px 20px 0; }

    .ct-hero { text-align: center; margin-bottom: 55px; }
    .ct-hero .kicker { display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff8ba7; background: #fff0f5; padding: 6px 16px; border-radius: 50px; margin-bottom: 18px; }
    .ct-hero h1 { font-size: 40px; font-weight: 700; color: #222; margin-bottom: 14px; }
    .ct-hero h1 span { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .ct-hero p { font-size: 16px; color: #888; max-width: 560px; margin: 0 auto; line-height: 1.7; }

    .ct-grid { display: grid; grid-template-columns: 0.85fr 1.15fr; gap: 34px; align-items: start; margin-bottom: 80px; }

    .ct-info-card { background: #fff; border-radius: 24px; padding: 30px 28px; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #f5f5f5; margin-bottom: 18px; }
    .ct-info-card h3 { font-size: 15px; font-weight: 700; color: #222; margin-bottom: 18px; }
    .ct-row { display: flex; gap: 14px; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
    .ct-row:last-child { border-bottom: none; padding-bottom: 0; }
    .ct-row .ic { width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px; background: #fff0f5; color: #ff8ba7; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    .ct-row .lbl { font-size: 12px; color: #aaa; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .ct-row .val { font-size: 14.5px; color: #333; font-weight: 500; margin-top: 2px; }
    .ct-row .val a { color: #333; text-decoration: none; }
    .ct-row .val a:hover { color: #ff8ba7; }

    .ct-socials { display: flex; gap: 10px; margin-top: 4px; }
    .ct-socials a { width: 40px; height: 40px; border-radius: 12px; background: #fafafa; color: #888; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: 0.2s; }
    .ct-socials a:hover { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; transform: translateY(-2px); }

    /* FORM */
    .ct-form-card { background: #fff; border-radius: 28px; padding: 38px 36px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); border: 1px solid #f5f5f5; }
    .ct-form-card h2 { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 6px; }
    .ct-form-card .sub { font-size: 14px; color: #999; margin-bottom: 24px; }
    .ct-field { margin-bottom: 16px; }
    .ct-field label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
    .ct-field input, .ct-field textarea { width: 100%; padding: 13px 16px; border: 1.5px solid #eee; border-radius: 14px; font-family: 'Poppins', sans-serif; font-size: 14px; background: #fafafa; outline: none; transition: 0.25s; }
    .ct-field input:focus, .ct-field textarea:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255,193,204,0.15); }
    .ct-field textarea { resize: vertical; min-height: 130px; }
    .ct-row-2 { display: flex; gap: 16px; }
    .ct-row-2 .ct-field { flex: 1; }
    .ct-submit { width: 100%; padding: 15px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; font-family: 'Poppins'; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 14px rgba(254,165,182,0.3); margin-top: 6px; }
    .ct-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(254,165,182,0.45); }

    .ct-alert { background: #fdeded; border: 1px solid #ffc1cc; color: #d32f2f; border-radius: 14px; padding: 12px 16px; font-size: 13.5px; margin-bottom: 18px; }
    .ct-alert ul { margin: 4px 0 0 18px; }

    .ct-success { text-align: center; padding: 30px 10px; }
    .ct-success .badge { width: 78px; height: 78px; border-radius: 50%; background: #e8f5e9; color: #2e7d32; display: flex; align-items: center; justify-content: center; font-size: 34px; margin: 0 auto 18px; animation: ctPop 0.5s cubic-bezier(0.175,0.885,0.32,1.275); }
    @keyframes ctPop { 0% { transform: scale(0); } 100% { transform: scale(1); } }
    .ct-success h2 { font-size: 22px; color: #222; margin-bottom: 8px; }
    .ct-success p { font-size: 14px; color: #888; line-height: 1.7; margin-bottom: 22px; }
    .ct-success a { display: inline-block; background: #fff0f5; color: #ff8ba7; font-weight: 700; font-size: 14px; padding: 12px 26px; border-radius: 50px; text-decoration: none; }
    .ct-success a:hover { background: #ffe4ec; }

    /* FAQ */
    .ct-faq { max-width: 760px; margin: 0 auto 80px; }
    .ct-faq h2 { text-align: center; font-size: 26px; font-weight: 700; color: #222; margin-bottom: 30px; }
    .ct-q { background: #fff; border: 1px solid #f0f0f0; border-radius: 18px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
    .ct-q summary { list-style: none; cursor: pointer; padding: 18px 22px; font-size: 15px; font-weight: 600; color: #333; display: flex; justify-content: space-between; align-items: center; }
    .ct-q summary::-webkit-details-marker { display: none; }
    .ct-q summary .chev { color: #ff8ba7; transition: transform 0.25s; }
    .ct-q[open] summary .chev { transform: rotate(180deg); }
    .ct-q .body { padding: 0 22px 20px; font-size: 14px; color: #888; line-height: 1.7; }

    @media (max-width: 820px) {
        .ct-grid { grid-template-columns: 1fr; }
        .ct-hero h1 { font-size: 32px; }
        .ct-row-2 { flex-direction: column; gap: 0; }
    }
</style>

<div class="ct-wrap">

    <div class="ct-hero">
        <span class="kicker">Contact</span>
        <h1>We'd <span>love</span> to hear from you</h1>
        <p>Questions about an order, a custom box, or a bulk request for your team? Send us a note — a real person on our team reads every one.</p>
    </div>

    <div class="ct-grid">

        <!-- LEFT: INFO -->
        <div>
            <div class="ct-info-card">
                <h3>Reach us directly</h3>
                <div class="ct-row">
                    <div class="ic"><i class="fas fa-envelope"></i></div>
                    <div><div class="lbl">Email</div><div class="val"><a href="mailto:giftly@gmail.com">giftly@gmail.com</a></div></div>
                </div>
                <div class="ct-row">
                    <div class="ic"><i class="fas fa-phone-alt"></i></div>
                    <div><div class="lbl">Phone</div><div class="val"><a href="tel:09123456789">0912 345 6789</a></div></div>
                </div>
                <div class="ct-row">
                    <div class="ic"><i class="fas fa-clock"></i></div>
                    <div><div class="lbl">Hours</div><div class="val">Mon – Sat, 9:00 AM – 6:00 PM</div></div>
                </div>
                <div class="ct-row">
                    <div class="ic"><i class="fas fa-location-dot"></i></div>
                    <div><div class="lbl">Studio</div><div class="val">Giftly Studio, Metro Manila<br><span style="color:#aaa;font-weight:400;font-size:13px;">Visits by appointment</span></div></div>
                </div>
            </div>

            <div class="ct-info-card">
                <h3>Follow along</h3>
                <div class="ct-socials">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                </div>
                <p style="font-size:13px;color:#aaa;margin-top:14px;line-height:1.6;">Peeks behind the wrapping bench, new arrivals and the occasional gift-wrapping tutorial.</p>
            </div>
        </div>

        <!-- RIGHT: FORM -->
        <div class="ct-form-card" id="form">
            <?php if ($sent): ?>
                <div class="ct-success">
                    <div class="badge"><i class="fas fa-check"></i></div>
                    <h2>Message sent! 💌</h2>
                    <p>Thanks for reaching out. We'll get back to you within one business day at the email you gave us.</p>
                    <a href="shop.php">Continue shopping</a>
                </div>
            <?php else: ?>
                <h2>Send a message</h2>
                <div class="sub">We usually reply within a business day.</div>

                <?php if ($errors): ?>
                    <div class="ct-alert">
                        <strong>Please check the form:</strong>
                        <ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="contact.php">
                    <input type="hidden" name="send_message" value="1">
                    <div class="ct-row-2">
                        <div class="ct-field">
                            <label for="ct_name">Your name</label>
                            <input type="text" id="ct_name" name="name" value="<?php echo htmlspecialchars($old['name']); ?>" required>
                        </div>
                        <div class="ct-field">
                            <label for="ct_email">Email</label>
                            <input type="email" id="ct_email" name="email" value="<?php echo htmlspecialchars($old['email']); ?>" required>
                        </div>
                    </div>
                    <div class="ct-field">
                        <label for="ct_subject">Subject <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                        <input type="text" id="ct_subject" name="subject" value="<?php echo htmlspecialchars($old['subject']); ?>" placeholder="Order help, custom box, partnership…">
                    </div>
                    <div class="ct-field">
                        <label for="ct_message">Message</label>
                        <textarea id="ct_message" name="message" required placeholder="How can we help?"><?php echo htmlspecialchars($old['message']); ?></textarea>
                    </div>
                    <button type="submit" class="ct-submit"><i class="fas fa-paper-plane" style="margin-right:8px;"></i> Send message</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- FAQ -->
    <div class="ct-faq">
        <h2>Quick answers</h2>
        <details class="ct-q">
            <summary>How long does delivery take? <span class="chev"><i class="fas fa-chevron-down"></i></span></summary>
            <div class="body">Please allow at least 3 days for us to prepare and dispatch your box. Delivery windows are between 8:00 AM and 8:00 PM, and you choose the date at checkout.</div>
        </details>
        <details class="ct-q">
            <summary>Can I send a box straight to someone else? <span class="chev"><i class="fas fa-chevron-down"></i></span></summary>
            <div class="body">Yes — at checkout choose "Deliver to Recipient", add their name and number, and write a gift message. Prices are never included in the package.</div>
        </details>
        <details class="ct-q">
            <summary>Do you do custom or bulk orders? <span class="chev"><i class="fas fa-chevron-down"></i></span></summary>
            <div class="body">We love these. Use the form above with a few details (quantity, occasion, budget, date) and we'll put together a quote.</div>
        </details>
        <details class="ct-q">
            <summary>Something arrived damaged — what now? <span class="chev"><i class="fas fa-chevron-down"></i></span></summary>
            <div class="body">Message us within 48 hours with your order number and a photo. We'll sort out a replacement or refund.</div>
        </details>
    </div>

</div>

<script>
    // if the form had errors, jump the user to it
    if (window.location.hash === '#form') {
        document.getElementById('form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
</script>

<?php include 'footer.php'; ?>
