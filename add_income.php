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
$password = ''; // Change if you set a root password
$db = 'student_budget_management';
$port = 3306; // Change to 3309 if you're sure MySQL runs on that port

$conn = new mysqli($host, $user, $password, $db, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success = "";
$error = "";

// Handle form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST['amount'];
    $source = $_POST['source'];
    $description = $_POST['description'];
    $date = $_POST['incomeDate'];
    $user_id = $_SESSION['user_id'];

    $sql = "INSERT INTO transactions (user_id, type, amount, category, description, transaction_date)
            VALUES (?, 'income', ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("idsss", $user_id, $amount, $source, $description, $date);

    if ($stmt->execute()) {
        $success = "Income added successfully!";
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
    <title>Add Income - Student Budget Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">Add Income</h2>

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
            <label for="source" class="form-label">Source</label>
            <input type="text" name="source" id="source" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description (Optional)</label>
            <textarea name="description" id="description" class="form-control" rows="2"></textarea>
        </div>

        <div class="mb-3">
            <label for="incomeDate" class="form-label">Date</label>
            <input type="date" name="incomeDate" id="incomeDate" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">Add Income</button>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </form>
</div>
</body>
</html>
