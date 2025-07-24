<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // In a real system, you would generate a reset token and send an email here
        header("Location: ../reset_password.php?email=" . urlencode($email));
        exit();
    } else {
        echo "Invalid email address.";
    }
} else {
    echo "Email not provided.";
}
?>
