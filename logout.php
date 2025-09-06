<?php
require_once 'config/config.php';

startSession();

// Log logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'User logged out');
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header('Location: login.php?logout=1');
exit();
?>
