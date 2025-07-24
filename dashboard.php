<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// DB connection
$host = 'localhost';
$user = 'root';
$password = '';
$db = 'student_budget_management';
$port = 3306;

$conn = new mysqli($host, $user, $password, $db, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Fetch totals
$income = $conn->query("SELECT SUM(amount) AS total FROM transactions WHERE user_id=$user_id AND type='income'")->fetch_assoc()['total'] ?? 0;
$expense = $conn->query("SELECT SUM(amount) AS total FROM transactions WHERE user_id=$user_id AND type='expense'")->fetch_assoc()['total'] ?? 0;

// Fetch recent transactions
$recentIncome = $conn->query("SELECT * FROM transactions WHERE user_id=$user_id AND type='income' ORDER BY transaction_date DESC LIMIT 2");
$recentExpenses = $conn->query("SELECT * FROM transactions WHERE user_id=$user_id AND type='expense' ORDER BY transaction_date DESC LIMIT 3");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Student Budget Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Student Budget Tracker</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="add_income.php">Add Income</a></li>
                    <li class="nav-item"><a class="nav-link" href="add_expense.php">Add Expense</a></li>
                    <li class="nav-item"><a class="nav-link" href="history.php">History</a></li>
                    <li class="nav-item"><a class="nav-link" href="report.php">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="account.php">My Account</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4 text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Income</h5>
                        <h3 class="text-success">₹<?= number_format($income, 2) ?></h3>
                    </div>
                </div>
                <div class="card mb-4 text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Expenses</h5>
                        <h3 class="text-danger">₹<?= number_format($expense, 2) ?></h3>
                    </div>
                </div>
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Balance</h5>
                        <h3 class="text-primary">₹<?= number_format($income - $expense, 2) ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Monthly Overview</h5>
                        <canvas id="monthlyChart" height="200"></canvas>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Recent Income</h5>
                                <ul class="list-group">
                                    <?php while ($row = $recentIncome->fetch_assoc()): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($row['category']) ?>
                                            <span class="badge bg-success rounded-pill">₹<?= number_format($row['amount'], 2) ?></span>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Expenses</h5>
                                <ul class="list-group">
                                    <?php while ($row = $recentExpenses->fetch_assoc()): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($row['category']) ?>
                                            <span class="badge bg-danger rounded-pill">₹<?= number_format($row['amount'], 2) ?></span>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [
                    {
                        label: 'Income',
                        data: [2000, 1500, 2500, 1500], // Replace with dynamic values later
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Expenses',
                        data: [1200, 1000, 1500, 500], // Replace with dynamic values later
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
