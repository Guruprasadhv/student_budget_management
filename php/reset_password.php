<?php
require_once(__DIR__ . '/db.php');
session_start();

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: ../reset_password.php?error=" . urlencode("Invalid CSRF token."));
        exit();
    }

    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $token = isset($_POST['token']) ? trim($_POST['token']) : null;

    // Validate input
    if (empty($email) || empty($new_password)) {
        header("Location: ../reset_password.php?email=" . urlencode($email) . "&error=" . urlencode("Email and new password are required."));
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../reset_password.php?email=" . urlencode($email) . "&error=" . urlencode("Invalid email format."));
        exit();
    }

    // Optional: Validate reset token here if you use one
    // Example: check if token is valid for this email

    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $sql = "UPDATE users SET password = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $email);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Success: redirect or return JSON
            header("Location: ../reset_password.php?success=" . urlencode("Password reset successfully. You can now login."));
            exit();
        } else {
            header("Location: ../reset_password.php?email=" . urlencode($email) . "&error=" . urlencode("Email not found in the system."));
            exit();
        }
    } else {
        header("Location: ../reset_password.php?email=" . urlencode($email) . "&error=" . urlencode("Error resetting password."));
        exit();
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: ../reset_password.php?error=" . urlencode("Invalid request."));
    exit();
}
?>
