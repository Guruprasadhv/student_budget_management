<?php
session_start();
require_once('php/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Income vs Expenses per month
$monthly = $conn->query("
    SELECT 
        MONTHNAME(transaction_date) AS month,
        type,
        SUM(amount) AS total
    FROM transactions
    WHERE user_id = $user_id
    GROUP BY MONTH(transaction_date), type
");

$monthlyData = [];
while ($row = $monthly->fetch_assoc()) {
    $monthlyData[$row['month']][$row['type']] = $row['total'];
}

// Expense Categories Pie Chart
$categories = $conn->query("
    SELECT category, SUM(amount) AS total
    FROM transactions
    WHERE user_id = $user_id AND type = 'expense'
    GROUP BY category
");

$categoryLabels = [];
$categoryData = [];
while ($row = $categories->fetch_assoc()) {
    $categoryLabels[] = $row['category'];
    $categoryData[] = $row['total'];
}

// Weekly Trend (last month, split by week number)
$trend = $conn->query("
    SELECT 
        WEEK(transaction_date, 1) AS week,
        type,
        SUM(amount) AS total
    FROM transactions
    WHERE user_id = $user_id AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    GROUP BY WEEK(transaction_date, 1), type
");

$trendData = [];
while ($row = $trend->fetch_assoc()) {
    $trendData[$row['week']][$row['type']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Student Budget Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Student Budget Tracker</a>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="add_income.php">Add Income</a></li>
            <li class="nav-item"><a class="nav-link" href="add_expense.php">Add Expense</a></li>
            <li class="nav-item"><a class="nav-link" href="history.php">History</a></li>
            <li class="nav-item"><a class="nav-link active" href="report.php">Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="account.php">My Account</a></li>
            <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
        </ul>
    </div>
</nav>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Income vs Expenses</h5>
                </div>
                <div class="card-body">
                    <canvas id="incomeExpenseChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Expense Categories</h5>
                </div>
                <div class="card-body">
                    <canvas id="expenseCategoryChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Monthly Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyTrendChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
    const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
    const incomeExpenseChart = new Chart(incomeExpenseCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($monthlyData)) ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?= json_encode(array_map(fn($v) => $v['income'] ?? 0, $monthlyData)) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: <?= json_encode(array_map(fn($v) => $v['expense'] ?? 0, $monthlyData)) ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });

    const expenseCategoryCtx = document.getElementById('expenseCategoryChart').getContext('2d');
    const expenseCategoryChart = new Chart(expenseCategoryCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($categoryLabels) ?>,
            datasets: [{
                data: <?= json_encode($categoryData) ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)'
                ],
                borderWidth: 1
            }]
        },
        options: { responsive: true }
    });

    const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    const weeks = <?= json_encode(array_keys($trendData)) ?>;
    const trendIncome = <?= json_encode(array_map(fn($v) => $v['income'] ?? 0, $trendData)) ?>;
    const trendExpense = <?= json_encode(array_map(fn($v) => $v['expense'] ?? 0, $trendData)) ?>;

    const monthlyTrendChart = new Chart(monthlyTrendCtx, {
        type: 'line',
        data: {
            labels: weeks,
            datasets: [
                {
                    label: 'Income',
                    data: trendIncome,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    borderWidth: 2,
                    fill: true
                },
                {
                    label: 'Expenses',
                    data: trendExpense,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    borderWidth: 2,
                    fill: true
                }
            ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
</script>
</body>
</html>
