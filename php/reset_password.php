<?php
require_once(__DIR__ . '/db.php');
session_start();

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo "Invalid CSRF token.";
        exit();
    }

    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $token = isset($_POST['token']) ? trim($_POST['token']) : null;

    // Validate input
    if (empty($email) || empty($new_password)) {
        http_response_code(400);
        echo "Email and new password are required.";
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Invalid email format.";
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
            echo "✅ Password reset successfully. <a href='../index.php'>Login</a>";
        } else {
            http_response_code(404);
            echo "⚠️ Email not found in the system.";
        }
    } else {
        http_response_code(500);
        echo "❌ Error resetting password: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo "❌ Invalid request.";
}
?>
