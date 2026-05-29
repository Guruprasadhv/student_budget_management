<?php
session_start();
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/account_helpers.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['needs_2fa'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = preg_replace('/\D/', '', $_POST['pin'] ?? '');

    $stmt = $conn->prepare("SELECT two_factor_pin_hash FROM users WHERE id = ? AND two_factor_enabled = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($pin, $user['two_factor_pin_hash'])) {
        $_SESSION['needs_2fa'] = false;
        $_SESSION['2fa_verified'] = true;
        register_user_session($conn, $user_id, session_id());
        header('Location: dashboard.php');
        exit();
    }

    $error = 'Invalid security PIN. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Security PIN</title>
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="card shadow p-4" style="max-width: 400px; width: 100%;">
            <h4 class="text-center mb-3">Two-Factor Verification</h4>
            <p class="text-muted text-center">Enter your 6-digit security PIN to continue.</p>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label for="pin" class="form-label">Security PIN</label>
                    <input type="password" class="form-control text-center" id="pin" name="pin" maxlength="6" pattern="\d{6}" inputmode="numeric" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100">Verify</button>
            </form>
            <div class="text-center mt-3">
                <a href="logout.php">Cancel and logout</a>
            </div>
        </div>
    </div>
</body>
</html>
