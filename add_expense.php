<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// DB Config
$host = 'localhost';
$user = 'root';
$password = ''; // Change if necessary
$db = 'student_budget_management';
$port = 3306; // Change to 3309 if using custom port

$conn = new mysqli($host, $user, $password, $db, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $date = $_POST['expenseDate'];
    $user_id = $_SESSION['user_id'];

    $sql = "INSERT INTO transactions (user_id, type, amount, category, description, transaction_date)
            VALUES (?, 'expense', ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("idsss", $user_id, $amount, $category, $description, $date);

    if ($stmt->execute()) {
        $success = "Expense added successfully!";
    } else {
        $error = "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Expense - Student Budget Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">Add Expense</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="amount" class="form-label">Amount (₹)</label>
            <input type="number" name="amount" id="amount" class="form-control" required step="0.01">
        </div>

        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <input type="text" name="category" id="category" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description (Optional)</label>
            <textarea name="description" id="description" class="form-control" rows="2"></textarea>
        </div>

        <div class="mb-3">
            <label for="expenseDate" class="form-label">Date</label>
            <input type="date" name="expenseDate" id="expenseDate" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-danger">Add Expense</button>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </form>
</div>
</body>
</html>
