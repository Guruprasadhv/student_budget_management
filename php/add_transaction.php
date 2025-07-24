<?php
session_start();
require_once('db.php');

// Redirect to login if user not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Validate input
if (
    isset($_POST['type'], $_POST['amount'], $_POST['category'], $_POST['description'], $_POST['date']) &&
    in_array($_POST['type'], ['income', 'expense']) &&
    is_numeric($_POST['amount']) && floatval($_POST['amount']) >= 0
) {
    $user_id    = $_SESSION['user_id'];
    $type       = $_POST['type'];
    $amount     = floatval($_POST['amount']);
    $category   = trim($_POST['category']);
    $description = trim($_POST['description']);
    $date       = $_POST['date'];

    // Insert into transactions table
    $sql = "INSERT INTO transactions (user_id, type, amount, category, description, date) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdsss", $user_id, $type, $amount, $category, $description, $date);

    if ($stmt->execute()) {
        header("Location: ../dashboard.php");
        exit();
    } else {
        echo "Error while saving transaction: " . $stmt->error;
    }
} else {
    echo "Invalid input. Please check all required fields.";
}
?>
