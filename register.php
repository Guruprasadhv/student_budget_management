<?php
session_start();

require_once 'db.php';

// Check database connection
if ($conn === null) {
    $_SESSION['register_error'] = "Database not connected.";
    header("Location: ../register.php");
    exit();
}

// Get form data
$name = $_POST['name'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Insert user
$sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param("sss", $name, $email, $password);

    if ($stmt->execute()) {
        $_SESSION['register_success'] = "Registration successful!";
    } else {
        $_SESSION['register_error'] = "Registration failed!";
    }

    $stmt->close();

} else {
    $_SESSION['register_error'] = "Database prepare failed.";
}

header("Location: ../register.php");
exit();
?>
