<?php
session_start();
require_once(__DIR__ . '/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['register_error'] = "All fields are required.";
        header("Location: ../register.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Invalid email format.";
        header("Location: ../register.php");
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check for duplicate email
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['register_error'] = "Email already registered.";
        header("Location: ../register.php");
        exit();
    }
    $check->close();

    // Insert new user
    $sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $name, $email, $hashed_password);

    if ($stmt->execute()) {
        $newUserId = $stmt->insert_id;
        $settingsStmt = $conn->prepare("INSERT INTO settings (user_id, preferred_currency, language) VALUES (?, 'INR', 'en')");
        if ($settingsStmt) {
            $settingsStmt->bind_param("i", $newUserId);
            $settingsStmt->execute();
            $settingsStmt->close();
        }
        $_SESSION['register_success'] = "Registration successful! Please login.";
        header("Location: ../register.php");
        exit();
    } else {
        $_SESSION['register_error'] = "Error: " . $stmt->error;
        header("Location: ../register.php");
        exit();
    }

    $stmt->close();
} else {
    $_SESSION['register_error'] = "Invalid request.";
    header("Location: ../register.php");
    exit();
}
?>
