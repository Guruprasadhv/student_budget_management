<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/account_helpers.php';
require_no_pending_2fa();
require_once __DIR__ . '/php/languages.php';

$user_id = $_SESSION['user_id'];

// Fetch totals
$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id=? AND type='income'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$income = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id=? AND type='expense'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$expense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Fetch recent transactions
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id=? AND type='income' ORDER BY transaction_date DESC LIMIT 2");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recentIncome = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id=? AND type='expense' ORDER BY transaction_date DESC LIMIT 3");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recentExpenses = $stmt->get_result();
$stmt->close();

// Fetch dynamic data for the monthly chart
$stmt = $conn->prepare("
    SELECT 
        FLOOR((DAYOFMONTH(transaction_date) - 1) / 7) + 1 AS week_of_month,
        type,
        SUM(amount) AS total
    FROM transactions
    WHERE user_id=? AND MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())
    GROUP BY week_of_month, type
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$chartResult = $stmt->get_result();

$chartIncome = [0, 0, 0, 0, 0];
$chartExpense = [0, 0, 0, 0, 0];

while ($row = $chartResult->fetch_assoc()) {
    $weekIdx = (int)$row['week_of_month'] - 1;
    if ($weekIdx >= 0 && $weekIdx < 5) {
        if ($row['type'] === 'income') {
            $chartIncome[$weekIdx] = (float)$row['total'];
        } else {
            $chartExpense[$weekIdx] = (float)$row['total'];
        }
    }
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_SESSION['theme']) ? htmlspecialchars($_SESSION['theme']) : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Student Budget Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/bootstrap-icons-1.13.1/bootstrap-icons.css">
    <script src="assets/chart.min.js"></script>
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
        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4 text-center">
                    <div class="card-body">
                        <h5 class="card-title"><?= __('total_income') ?></h5>
                        <h3 class="text-success">₹<?= number_format($income, 2) ?></h3>
                    </div>
                </div>
                <div class="card mb-4 text-center">
                    <div class="card-body">
                        <h5 class="card-title"><?= __('total_expenses') ?></h5>
                        <h3 class="text-danger">₹<?= number_format($expense, 2) ?></h3>
                    </div>
                </div>
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><?= __('balance') ?></h5>
                        <h3 class="text-primary">₹<?= number_format($income - $expense, 2) ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?= __('monthly_overview') ?></h5>
                        <canvas id="monthlyChart" height="200"></canvas>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title"><?= __('recent_income') ?></h5>
                                <ul class="list-group">
                                <?php if ($recentIncome->num_rows > 0): ?>
                                    <?php while ($row = $recentIncome->fetch_assoc()): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($row['category']) ?>
                                            <span class="badge bg-success rounded-pill">₹<?= number_format($row['amount'], 2) ?></span>
                                        </li>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-muted"><?= __('no_recent_income') ?></li>
                                <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?= __('recent_expenses') ?></h5>
                                <ul class="list-group">
                                <?php if ($recentExpenses->num_rows > 0): ?>
                                    <?php while ($row = $recentExpenses->fetch_assoc()): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($row['category']) ?>
                                            <span class="badge bg-danger rounded-pill">₹<?= number_format($row['amount'], 2) ?></span>
                                        </li>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-muted"><?= __('no_recent_expenses') ?></li>
                                <?php endif; ?>
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
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                datasets: [
                    {
                        label: 'Income',
                    data: <?= json_encode($chartIncome) ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Expenses',
                    data: <?= json_encode($chartExpense) ?>,
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

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
