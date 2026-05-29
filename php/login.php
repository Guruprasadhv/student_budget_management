<?php
session_start();
include('db.php');

// Check if form submitted using POST method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize input
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please enter both email and password.";
        header("Location: ../index.php");
        exit();
    }

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "Error preparing statement: " . $conn->error;
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password hash
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            require_once __DIR__ . '/account_helpers.php';
            ensure_account_tables($conn);

            if (!empty($user['two_factor_enabled'])) {
                $_SESSION['needs_2fa'] = true;
                header("Location: ../verify_2fa.php");
                exit();
            }

            $_SESSION['needs_2fa'] = false;
            register_user_session($conn, (int)$user['id'], session_id());
            header("Location: ../dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid password.";
            header("Location: ../index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid email.";
        header("Location: ../index.php");
        exit();
    }

    $stmt->close();
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../index.php");
    exit();
}
?>
