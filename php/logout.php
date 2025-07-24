<?php
session_start();
include('db.php'); // Ensure this file exists at the correct path: php/db.php

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Update last logout timestamp
    $sql = "UPDATE users SET last_logout = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Logout update failed: " . $conn->error);
    }
}

// Clear all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: ../index.php");
exit();
?>
