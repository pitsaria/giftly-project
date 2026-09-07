<?php
// reset_password.php — legacy reset-link landing page.
// Password resets now use an emailed 6-digit code in the Forgot Password
// modal, so send any old links there.
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Location: index.php?forgot=1');
exit();
