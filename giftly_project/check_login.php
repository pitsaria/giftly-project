<?php
session_start();
echo json_encode(['logged_in' => isset($_SESSION['user_id'])]);
?>