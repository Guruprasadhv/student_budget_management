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

// Use central DB connection
require_once __DIR__ . '/php/db.php';

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
$month = isset($_GET['month']) ? $_GET['month'] : '';

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
if (!empty($month)) {
    $count_sql .= " AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
    $count_params[] = $month;
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
if (!empty($month)) {
    $sql .= " AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
    $params[] = $month;
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Student Budget Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
        }
    </style>
</head>
<body>
    <div id="alertPlaceholder"></div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Student Budget Tracker</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_income.php">Add Income</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_expense.php">Add Expense</a>    
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="history.php">History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="account.php">My Account</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Settings</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">Transaction History</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select class="form-select" id="filterType">
                                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Transactions</option>
                                    <option value="income" <?= $filter === 'income' ? 'selected' : '' ?>>Income</option>
                                    <option value="expense" <?= $filter === 'expense' ? 'selected' : '' ?>>Expenses</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="month" class="form-control" id="filterMonth" value="<?= htmlspecialchars($month) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category/Source</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr data-id="<?= htmlspecialchars($row['id']) ?>">
                                    <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['type'] === 'income' ? 'success' : 'danger' ?>">
                                            <?= ucfirst(htmlspecialchars($row['type'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['category']) ?></td>
                                    <td><?= htmlspecialchars($row['description']) ?></td>
                                    <td class="text-end text-<?= $row['type'] === 'income' ? 'success' : 'danger' ?>">
                                        <?= $row['type'] === 'income' ? '+' : '-' ?> ₹<?= number_format($row['amount'], 2) ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-btn">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger delete-btn">Delete</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&month=<?= htmlspecialchars($month) ?>&page=<?= $page-1 ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?filter=<?= $filter ?>&month=<?= htmlspecialchars($month) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&month=<?= htmlspecialchars($month) ?>&page=<?= $page+1 ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editTransactionForm">
                        <input type="hidden" name="id" id="editTransactionId">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="update">
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category/Source</label>
                            <input type="text" class="form-control" name="category" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveTransactionChanges">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this transaction?</p>
                    <form id="deleteTransactionForm">
                        <input type="hidden" name="id" id="deleteTransactionId">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                    const date = cells[0].textContent;
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

            // Filter functionality
            document.getElementById('filterType').addEventListener('change', function() {
                const filter = this.value;
                const month = document.getElementById('filterMonth').value;
                window.location.href = `history.php?filter=${filter}${month ? '&month=' + month : ''}`;
            });

            document.getElementById('filterMonth').addEventListener('change', function() {
                const month = this.value;
                const filter = document.getElementById('filterType').value;
                window.location.href = `history.php?filter=${filter}${month ? '&month=' + month : ''}`;
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