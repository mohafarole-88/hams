<?php
require_once 'config/config.php';

header('Content-Type: application/json');

startSession();

if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
