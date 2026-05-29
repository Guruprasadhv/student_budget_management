<?php
session_start();
require_once('php/db.php');
require_once('php/languages.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Income vs Expenses per month
$stmt = $conn->prepare("
    SELECT 
        MONTHNAME(transaction_date) AS month,
        type,
        SUM(amount) AS total
    FROM transactions
    WHERE user_id = ?
    GROUP BY MONTH(transaction_date), MONTHNAME(transaction_date), type
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly = $stmt->get_result();

$monthlyData = [];
while ($row = $monthly->fetch_assoc()) {
    $monthlyData[$row['month']][$row['type']] = $row['total'];
}
$stmt->close();

// Expense Categories Pie Chart
$stmt = $conn->prepare("
    SELECT category, SUM(amount) AS total
    FROM transactions
    WHERE user_id = ? AND type = 'expense'
    GROUP BY category
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result();

$categoryLabels = [];
$categoryData = [];
while ($row = $categories->fetch_assoc()) {
    $categoryLabels[] = $row['category'];
    $categoryData[] = $row['total'];
}
$stmt->close();

// Weekly Trend (last month, split by week number)
$stmt = $conn->prepare("
    SELECT 
        WEEK(transaction_date, 1) AS week,
        type,
        SUM(amount) AS total
    FROM transactions
    WHERE user_id = ? AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    GROUP BY WEEK(transaction_date, 1), type
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trend = $stmt->get_result();

$trendData = [];
while ($row = $trend->fetch_assoc()) {
    $trendData[$row['week']][$row['type']] = $row['total'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_SESSION['theme']) ? htmlspecialchars($_SESSION['theme']) : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <title>Reports - Student Budget Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/bootstrap-icons-1.13.1/bootstrap-icons.css">
    <style>
        /* Default light theme variables */
        :root, [data-theme="light"], [data-theme="system"] {
            --primary-bg-color: #f8f9fa;
            --secondary-bg-color: #ffffff;
            --text-color: #212529;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
        }
        
        /* Dark theme variables */
        [data-theme="dark"] {
            --primary-bg-color: #121212;
            --secondary-bg-color: #1e1e1e;
            --text-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --border-color: #2d2d2d;
        }
        
        /* Read theme variables */
        [data-theme="read"] {
            --primary-bg-color: #f8f5f2;
            --secondary-bg-color: #f1e9e0;
            --text-color: #3a3a3a;
            --card-bg: #f1e9e0;
            --border-color: #d9d1c7;
        }

        /* System theme media query support */
        @media (prefers-color-scheme: dark) {
            [data-theme="system"] {
                --primary-bg-color: #121212;
                --secondary-bg-color: #1e1e1e;
                --text-color: #e0e0e0;
                --card-bg: #1e1e1e;
                --border-color: #2d2d2d;
            }
            [data-theme="system"] .navbar {
                background-color: #1e1e1e !important;
                border-bottom: 1px solid #2d2d2d;
            }
            [data-theme="system"] .card-header.bg-primary {
                background-color: #1e1e1e !important;
                border-bottom: 1px solid #2d2d2d !important;
                color: #e0e0e0 !important;
            }
        }
        
        body {
            background-color: var(--primary-bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .card, .list-group-item, .modal-content, .table {
            background-color: var(--card-bg) !important;
            color: var(--text-color) !important;
            border-color: var(--border-color) !important;
        }
        
        .table th, .table td {
            border-color: var(--border-color) !important;
            color: var(--text-color) !important;
        }
        
        .form-control, .form-select {
            background-color: var(--secondary-bg-color) !important;
            color: var(--text-color) !important;
            border-color: var(--border-color) !important;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--secondary-bg-color) !important;
            color: var(--text-color) !important;
        }

        /* Navbar customizations based on theme */
        [data-theme="dark"] .navbar {
            background-color: #1e1e1e !important;
            border-bottom: 1px solid #2d2d2d;
        }
        [data-theme="read"] .navbar {
            background-color: #e5dacb !important;
            border-bottom: 1px solid #d9d1c7;
        }
        [data-theme="read"] .navbar-brand, [data-theme="read"] .nav-link {
            color: #3a3a3a !important;
        }
        [data-theme="read"] .nav-link i {
            color: #3a3a3a !important;
        }

        /* Override bg-primary for card headers in dark and read themes */
        [data-theme="dark"] .card-header.bg-primary {
            background-color: #1e1e1e !important;
            border-bottom: 1px solid #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        [data-theme="read"] .card-header.bg-primary {
            background-color: #f1e9e0 !important;
            border-bottom: 1px solid #d9d1c7 !important;
            color: #3a3a3a !important;
        }
        [data-theme="read"] .card-header.bg-primary h5 {
            color: #3a3a3a !important;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand navbar-dark bg-primary">
    <div class="container-fluid px-3">
        <a class="navbar-brand" href="dashboard.php"><?= __('student_budget_tracker') ?></a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center" style="font-size: 0.8rem;">
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> <?= __('dashboard') ?></a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'add_income.php' ? 'active' : '' ?>" href="add_income.php"><i class="bi bi-plus-circle me-1"></i> <?= __('add_income') ?></a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'add_expense.php' ? 'active' : '' ?>" href="add_expense.php"><i class="bi bi-dash-circle me-1"></i> <?= __('add_expense') ?></a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : '' ?>" href="history.php"><i class="bi bi-clock-history me-1"></i> <?= __('history') ?></a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : '' ?>" href="report.php"><i class="bi bi-bar-chart-line me-1"></i> <?= __('reports') ?></a></li>
                <li class="nav-item dropdown ms-2">
                    <a class="nav-link d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding-right: 0;">
                        <span class="me-2 text-white" style="font-size: 0.85rem; font-weight: 500;"><?= htmlspecialchars(__($_SESSION['user_name'] ?? 'User')) ?></span>
                        <i class="bi bi-list fs-5" style="color: rgba(255,255,255,0.85);"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="navbarDropdown" style="font-size: 0.8rem;">
                        <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'account.php' ? 'active' : '' ?>" href="account.php"><i class="bi bi-person me-2"></i> <?= __('my_account') ?></a></li>
                        <li><a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="settings.php"><i class="bi bi-gear me-2"></i> <?= __('settings') ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> <?= __('logout') ?></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= __('income_vs_expenses') ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="incomeExpenseChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= __('expense_categories') ?></h5>
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
                    <h5 class="mb-0"><?= __('monthly_trends') ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyTrendChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/chart.min.js"></script>
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
