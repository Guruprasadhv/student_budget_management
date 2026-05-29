<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // In a real system, you would generate a reset token and send an email here
        header("Location: ../reset_password.php?email=" . urlencode($email));
        exit();
    } else {
        $_SESSION['error'] = "Invalid email address.";
        header("Location: ../forgot_password.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Email not provided.";
    header("Location: ../forgot_password.php");
    exit();
}
?>
