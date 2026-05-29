<?php
session_start();
include('db.php');

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/account_helpers.php';
    ensure_account_tables($conn);
    $userId = (int)$_SESSION['user_id'];
    $sessionId = session_id();
    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id = ?");
    $stmt->bind_param("is", $userId, $sessionId);
    $stmt->execute();
    $stmt->close();
}

session_unset();
session_destroy();

// Redirect to login page
header("Location: ../index.php");
exit();
?>
