<?php 
// reset_password.php - The Slim Modal-Only Version

// Check if we are on a live server (optional check)
if (!isset($_SESSION)) { session_start(); }

// Allow CORS or basic checks if needed, but this is just a shell page now.

$token = isset($_GET['token']) ? $_GET['token'] : '';

// If there is no token, send them back home
if(empty($token)) {
    header("Location: index.php");
    exit();
}
?>

<!-- 🚨 DO NOT INCLUDE HEADER OR FOOTER HERE ANYMORE! 🚨 -->
<!-- We are only loading the reset modal directly -->

<!-- INCLUDE THE RESET MODAL -->
<?php include 'modal_reset_password.php'; ?>

<!-- Pass the token to JavaScript -->
<input type="hidden" id="validToken" value="<?php echo $token; ?>">

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fill the hidden input with the token
        document.getElementById('resetTokenInput').value = document.getElementById('validToken').value;
        
        // 🔥 GIRL, THIS IS THE MAGIC: Force the modal to open immediately
        // We use a tiny timeout to let the DOM load, then BOOM!
        setTimeout(openResetModal, 100);
    });
</script>

<!-- 🚨 DO NOT INCLUDE FOOTER HERE EITHER! 🚨 -->
<!-- The page ends immediately after the modal. -->