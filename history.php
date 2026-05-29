<?php
session_start();

// Enforce HTTPS only in production, not on localhost or 127.0.0.1
if (
    empty($_SERVER['HTTPS']) && 
    $_SERVER['HTTP_HOST'] !== 'localhost' && 
    $_SERVER['HTTP_HOST'] !== '127.0.0.1'
) {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/languages.php';
require_once __DIR__ . '/php/account_helpers.php';
require_no_pending_2fa();

$user_id = $_SESSION['user_id'];

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }

    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// Handle Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }

    $id = (int)$_POST['id'];
    $type = in_array($_POST['type'], ['income', 'expense']) ? $_POST['type'] : 'expense';
    $date = $_POST['date'];
    $category = htmlspecialchars(trim($_POST['category']));
    $description = htmlspecialchars(trim($_POST['description']));
    $amount = (float)$_POST['amount'];

    $stmt = $conn->prepare("UPDATE transactions SET type=?, transaction_date=?, category=?, description=?, amount=? WHERE id=? AND user_id=?");
    $stmt->bind_param("ssssdii", $type, $date, $category, $description, $amount, $id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    exit();
}

// Fetch transactions with filters
$allowed_filters = ['all', 'income', 'expense'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters) ? $_GET['filter'] : 'all';
$filter_date = '';
$filter_month = '';
if (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $filter_date = $_GET['date'];
    $filter_month = substr($filter_date, 0, 7);
} elseif (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $filter_month = $_GET['month'];
    $filter_date = $_GET['month'] . '-01';
}

// Pagination setup
$per_page = 10; // Number of transactions per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

// Count total transactions for pagination
$count_sql = "SELECT COUNT(*) FROM transactions WHERE user_id = ?";
$count_params = [$user_id];
$count_types = "i";
if ($filter === 'income') {
    $count_sql .= " AND type = 'income'";
} elseif ($filter === 'expense') {
    $count_sql .= " AND type = 'expense'";
}
if (!empty($filter_month)) {
    $count_sql .= " AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
    $count_params[] = $filter_month;
    $count_types .= "s";
}
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$count_stmt->bind_result($total_transactions);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = max(1, ceil($total_transactions / $per_page));

// Fetch transactions with filters and pagination
$sql = "SELECT * FROM transactions WHERE user_id = ?";
$params = [$user_id];
$types = "i";
if ($filter === 'income') {
    $sql .= " AND type = 'income'";
} elseif ($filter === 'expense') {
    $sql .= " AND type = 'expense'";
}
if (!empty($filter_month)) {
    $sql .= " AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
    $params[] = $filter_month;
    $types .= "s";
}
$sql .= " ORDER BY transaction_date DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_SESSION['theme']) ? htmlspecialchars($_SESSION['theme']) : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('history') ?> - <?= __('student_budget_tracker') ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/bootstrap-icons-1.13.1/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/flatpickr/flatpickr.min.css">
    <style>
        :root, [data-theme="light"], [data-theme="system"] {
            --primary-bg-color: #f8f9fa;
            --secondary-bg-color: #ffffff;
            --text-color: #212529;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
        }
        [data-theme="dark"] {
            --primary-bg-color: #121212;
            --secondary-bg-color: #1e1e1e;
            --text-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --border-color: #2d2d2d;
        }
        [data-theme="read"] {
            --primary-bg-color: #f8f5f2;
            --secondary-bg-color: #f1e9e0;
            --text-color: #3a3a3a;
            --card-bg: #f1e9e0;
            --border-color: #d9d1c7;
        }
        @media (prefers-color-scheme: dark) {
            [data-theme="system"] {
                --primary-bg-color: #121212;
                --secondary-bg-color: #1e1e1e;
                --text-color: #e0e0e0;
                --card-bg: #1e1e1e;
                --border-color: #2d2d2d;
            }
        }
        body {
            background-color: var(--primary-bg-color);
            color: var(--text-color);
        }
        .card, .table, .modal-content {
            background-color: var(--card-bg) !important;
            color: var(--text-color) !important;
            border-color: var(--border-color) !important;
        }
        .table th, .table td {
            border-color: var(--border-color) !important;
            vertical-align: middle;
        }
        .table thead th {
            background-color: var(--primary-bg-color);
            font-weight: 600;
            border-bottom-width: 1px;
        }
        .form-control, .form-select {
            background-color: var(--secondary-bg-color) !important;
            color: var(--text-color) !important;
            border-color: var(--border-color) !important;
        }
        .history-card .card-header .form-select,
        .history-card .card-header .form-control {
            background-color: #fff !important;
            color: #212529 !important;
            border: none;
            font-size: 0.9rem;
        }
        .calendar-filter {
            position: relative;
            background-color: #fff;
            border-radius: 0.375rem;
        }
        .calendar-filter .form-control {
            border: none;
            border-radius: 0.375rem;
            padding-right: 2rem;
            cursor: pointer;
            min-height: 28px;
            font-size: 0.8rem;
            background-color: #fff !important;
        }
        .calendar-filter .flatpickr-input[readonly] {
            min-height: 28px;
            font-size: 0.8rem;
        }
        .calendar-filter .calendar-filter-icon {
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
            font-size: 0.85rem;
        }
        .calendar-filter .calendar-clear-btn {
            position: absolute;
            right: 0.25rem;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #6c757d;
            font-size: 1rem;
            line-height: 1;
            padding: 0 0.15rem;
            cursor: pointer;
            display: none;
        }
        .calendar-filter .calendar-clear-btn:hover {
            color: #dc3545;
        }
        .calendar-filter.has-date .calendar-clear-btn {
            display: block;
        }
        .calendar-filter.has-date .form-control {
            padding-right: 3.25rem;
        }
        .history-card {
            border: none;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .history-card .card-header {
            padding: 1rem 1.25rem;
            overflow: visible;
        }
        .flatpickr-calendar {
            z-index: 1060;
            width: 168px !important;
            font-size: 9px !important;
            line-height: 12px !important;
            padding: 2px !important;
            border-radius: 4px !important;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.12) !important;
        }
        .flatpickr-calendar .flatpickr-months {
            height: 22px;
        }
        .flatpickr-calendar .flatpickr-months .flatpickr-month {
            height: 22px;
        }
        .flatpickr-calendar .flatpickr-months .flatpickr-prev-month,
        .flatpickr-calendar .flatpickr-months .flatpickr-next-month {
            height: 22px;
            padding: 2px 4px;
        }
        .flatpickr-calendar .flatpickr-months .flatpickr-prev-month svg,
        .flatpickr-calendar .flatpickr-months .flatpickr-next-month svg {
            width: 10px;
            height: 10px;
        }
        .flatpickr-calendar .flatpickr-current-month {
            font-size: 90%;
            padding: 0;
            height: 22px;
            line-height: 22px;
        }
        .flatpickr-calendar .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-calendar .flatpickr-current-month input.cur-year {
            font-size: 9px;
            padding: 0 2px;
        }
        .flatpickr-calendar .flatpickr-weekdays {
            height: 16px;
        }
        .flatpickr-calendar .flatpickr-weekday {
            font-size: 8px;
            line-height: 16px;
        }
        .flatpickr-calendar .flatpickr-days {
            width: 168px !important;
        }
        .flatpickr-calendar .dayContainer {
            width: 168px !important;
            min-width: 168px !important;
            max-width: 168px !important;
        }
        .flatpickr-calendar .flatpickr-day {
            width: 22px !important;
            height: 22px !important;
            max-width: 22px !important;
            line-height: 22px !important;
            font-size: 9px;
            border-radius: 3px;
        }
        .history-card .card-body {
            padding: 0;
        }
        .history-card .table {
            margin-bottom: 0;
        }
        .history-card .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        .amount-cell {
            font-weight: 500;
            white-space: nowrap;
        }
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
        [data-theme="dark"] .navbar {
            background-color: #1e1e1e !important;
            border-bottom: 1px solid #2d2d2d;
        }
        [data-theme="read"] .navbar {
            background-color: #e5dacb !important;
            border-bottom: 1px solid #d9d1c7;
        }
        [data-theme="read"] .navbar-brand,
        [data-theme="read"] .nav-link {
            color: #3a3a3a !important;
        }
        [data-theme="read"] .nav-link i {
            color: #3a3a3a !important;
        }
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
    <div id="alertPlaceholder"></div>

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

    <div class="container mt-4 mb-5">
        <div class="card shadow history-card">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center g-2">
                    <div class="col-md-5 col-lg-4">
                        <h5 class="mb-0"><?= __('transaction_history') ?></h5>
                    </div>
                    <div class="col-md-7 col-lg-8">
                        <div class="row g-2 justify-content-md-end">
                            <div class="col-sm-6">
                                <select class="form-select" id="filterType" aria-label="Filter transactions">
                                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Transactions</option>
                                    <option value="income" <?= $filter === 'income' ? 'selected' : '' ?>>Income</option>
                                    <option value="expense" <?= $filter === 'expense' ? 'selected' : '' ?>>Expenses</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <div class="calendar-filter<?= !empty($filter_month) ? ' has-date' : '' ?>">
                                    <input type="text" class="form-control" id="filterDate" placeholder="dd/mm/yyyy" readonly aria-label="Filter by date">
                                    <i class="bi bi-calendar3 calendar-filter-icon"></i>
                                    <button type="button" class="calendar-clear-btn" id="clearDateFilter" aria-label="Clear date filter" title="Clear">&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="transactionsTable">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th>Type</th>
                                <th>Category/Source</th>
                                <th><?= __('description') ?></th>
                                <th class="text-end"><?= __('amount') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_transactions === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">No transactions found.</td>
                                </tr>
                            <?php else: ?>
                            <?php while ($row = $result->fetch_assoc()):
                                $isoDate = date('Y-m-d', strtotime($row['transaction_date']));
                                $displayDate = date('d/m/Y', strtotime($row['transaction_date']));
                                $isIncome = $row['type'] === 'income';
                            ?>
                                <tr data-id="<?= (int)$row['id'] ?>" data-date="<?= htmlspecialchars($isoDate) ?>">
                                    <td><?= htmlspecialchars($displayDate) ?></td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?= $isIncome ? 'success' : 'danger' ?>">
                                            <?= $isIncome ? 'Income' : 'Expense' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['category']) ?></td>
                                    <td><?= htmlspecialchars($row['description']) ?></td>
                                    <td class="text-end amount-cell text-<?= $isIncome ? 'success' : 'danger' ?>">
                                        <?= $isIncome ? '+' : '-' ?> ₹<?= number_format((float)$row['amount'], 2) ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn">Edit</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn">Delete</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 0): ?>
                <nav class="py-3" aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?filter=<?= urlencode($filter) ?><?= !empty($filter_date) ? '&date=' . urlencode($filter_date) : '' ?>&page=<?= max(1, $page - 1) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?filter=<?= urlencode($filter) ?><?= !empty($filter_date) ? '&date=' . urlencode($filter_date) : '' ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?filter=<?= urlencode($filter) ?><?= !empty($filter_date) ? '&date=' . urlencode($filter_date) : '' ?>&page=<?= min($total_pages, $page + 1) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('edit_transaction') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editTransactionForm">
                        <input type="hidden" name="id" id="editTransactionId">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update">
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('date') ?></label>
                            <input type="date" class="form-control" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category/Source</label>
                            <input type="text" class="form-control" name="category" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('description') ?></label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('amount') ?> (₹)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="amount" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-primary" id="saveTransactionChanges"><?= __('save') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('confirm_deletion') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this transaction?</p>
                    <form id="deleteTransactionForm">
                        <input type="hidden" name="id" id="deleteTransactionId">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="delete">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmDelete"><?= __('delete_transaction') ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/flatpickr/flatpickr.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = new bootstrap.Modal('#editTransactionModal');
            const deleteModal = new bootstrap.Modal('#deleteConfirmationModal');
            let currentTransactionId = null;

            // Edit button click handler
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const row = this.closest('tr');
                    currentTransactionId = row.getAttribute('data-id');
                    
                    // Fill the edit form with current data
                    const cells = row.querySelectorAll('td');
                    const type = cells[1].querySelector('.badge').textContent.trim().toLowerCase();
                    const date = row.getAttribute('data-date');
                    const category = cells[2].textContent;
                    const description = cells[3].textContent;
                    const amount = cells[4].textContent.replace(/[^0-9.]/g, '');
                    
                    document.getElementById('editTransactionId').value = currentTransactionId;
                    document.querySelector('#editTransactionForm [name="type"]').value = type;
                    document.querySelector('#editTransactionForm [name="date"]').value = date;
                    document.querySelector('#editTransactionForm [name="category"]').value = category;
                    document.querySelector('#editTransactionForm [name="description"]').value = description;
                    document.querySelector('#editTransactionForm [name="amount"]').value = amount;
                    
                    editModal.show();
                });
            });

            // Delete button click handler
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    currentTransactionId = this.closest('tr').getAttribute('data-id');
                    document.getElementById('deleteTransactionId').value = currentTransactionId;
                    deleteModal.show();
                });
            });

            // Save changes handler
            document.getElementById('saveTransactionChanges').addEventListener('click', function() {
                const formData = new FormData(document.getElementById('editTransactionForm'));
                
                fetch('history.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Transaction updated successfully!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.message || 'Error updating transaction', 'danger');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred while updating the transaction', 'danger');
                });
            });

            // Confirm delete handler
            document.getElementById('confirmDelete').addEventListener('click', function() {
                const formData = new FormData(document.getElementById('deleteTransactionForm'));
                
                fetch('history.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Transaction deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.message || 'Error deleting transaction', 'danger');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred while deleting the transaction', 'danger');
                });
            });

            // Calendar date filter (dd/mm/yyyy)
            const filterType = document.getElementById('filterType');
            const filterDateEl = document.getElementById('filterDate');
            const calendarFilterWrap = filterDateEl.closest('.calendar-filter');
            const clearDateBtn = document.getElementById('clearDateFilter');

            function goToFilter(dateValue) {
                const filter = filterType.value;
                const params = new URLSearchParams({ filter });
                if (dateValue) {
                    params.set('date', dateValue);
                }
                window.location.href = 'history.php?' + params.toString();
            }

            const datePicker = flatpickr(filterDateEl, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                allowInput: false,
                clickOpens: true,
                defaultDate: <?= !empty($filter_date) ? json_encode($filter_date) : 'null' ?>,
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        goToFilter(dateStr);
                    }
                }
            });

            clearDateBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                datePicker.clear();
                goToFilter('');
            });

            filterType.addEventListener('change', function() {
                let dateValue = '';
                if (datePicker.selectedDates.length) {
                    dateValue = datePicker.formatDate(datePicker.selectedDates[0], 'Y-m-d');
                }
                goToFilter(dateValue);
            });

            // Show alert message
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const placeholder = document.getElementById('alertPlaceholder');
                placeholder.innerHTML = '';
                placeholder.appendChild(alertDiv);
                
                setTimeout(() => {
                    bootstrap.Alert.getOrCreateInstance(alertDiv).close();
                }, 3000);
            }
        });
    </script>
</body>
</html>