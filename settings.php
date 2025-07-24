<?php
session_start();
require_once('php/db.php');
// require 'vendor/autoload.php'; // If using Composer autoload
use PhpOffice\PhpSpreadsheet\IOFactory;

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle template download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transaction_template.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['amount', 'type', 'transaction_date', 'description', 'category']);
    fputcsv($output, ['100.00', 'income', date('Y-m-d'), 'Salary payment', '1']);
    fputcsv($output, ['25.50', 'expense', date('Y-m-d'), 'Grocery shopping', '2']);
    fclose($output);
    exit();
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$message = '';
$errors = [];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token. Please try again.";
    } else {
        // Handle password change
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validate current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect.";
            } elseif (strlen($new_password) < 8) {
                $errors[] = "New password must be at least 8 characters long.";
            } elseif ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Password changed successfully!";
                } else {
                    $errors[] = "Failed to update password. Please try again.";
                }
            }
        }
        
        // Handle theme preference
        if (isset($_POST['update_theme'])) {
            $theme = filter_input(INPUT_POST, 'theme', FILTER_SANITIZE_STRING);
            $allowed_themes = ['light', 'dark', 'read'];
            
            if (in_array($theme, $allowed_themes)) {
                $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
                $stmt->bind_param("si", $theme, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['theme'] = $theme;
                    $message = "Theme preference updated!";
                }
            }
        }
        
        // Handle budget goals
        if (isset($_POST['update_budget_goals'])) {
            $budget_goals = isset($_POST['categories']) ? $_POST['categories'] : [];
            $budget_goals_json = json_encode($budget_goals);
            
            $stmt = $conn->prepare("UPDATE users SET budget_goals = ? WHERE id = ?");
            $stmt->bind_param("si", $budget_goals_json, $user_id);
            
            if ($stmt->execute()) {
                $message = "Budget goals updated successfully!";
            } else {
                $errors[] = "Failed to update budget goals.";
            }
        }
        
        // Handle categories update
        if (isset($_POST['update_categories'])) {
            $new_category = filter_input(INPUT_POST, 'new_category', FILTER_SANITIZE_STRING);
            
            if (!empty($new_category)) {
                $stmt = $conn->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $new_category);

                try {
                    if ($stmt->execute()) {
                        $message = "Category added successfully!";
                    } else {
                        $errors[] = "Failed to add category. It may already exist.";
                    }
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() == 1062) { // Duplicate entry error code
                        $errors[] = "Category already exists.";
                    } else {
                        $errors[] = "Database error: " . $e->getMessage();
                    }
                }
            }
        }
        
        // Handle privacy settings
        if (isset($_POST['update_privacy'])) {
            $two_factor = isset($_POST['two_factor']) ? 1 : 0;
            $data_sharing = isset($_POST['data_sharing']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = ?, data_sharing = ? WHERE id = ?");
            $stmt->bind_param("iii", $two_factor, $data_sharing, $user_id);
            
            if ($stmt->execute()) {
                $message = "Privacy settings updated!";
            } else {
                $errors[] = "Failed to update privacy settings.";
            }
        }
        
        // Handle notification settings
        if (isset($_POST['update_notifications'])) {
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
            $low_balance_alert = isset($_POST['low_balance_alert']) ? 1 : 0;
            $large_expense_alert = isset($_POST['large_expense_alert']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE users SET 
                email_notifications = ?, 
                push_notifications = ?,
                low_balance_alert = ?,
                large_expense_alert = ?
                WHERE id = ?");
            $stmt->bind_param("iiiii", $email_notifications, $push_notifications, $low_balance_alert, $large_expense_alert, $user_id);
            
            if ($stmt->execute()) {
                $message = "Notification settings updated!";
            } else {
                $errors[] = "Failed to update notification settings.";
            }
        }
        
        // Handle data export
        if (isset($_POST['export_data'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $errors[] = "Invalid CSRF token. Please try again.";
            } else {
                $export_type = filter_input(INPUT_POST, 'export_type', FILTER_SANITIZE_STRING);
                $allowed_types = ['excel', 'csv', 'pdf']; // Added 'pdf' to allowed types
                if (in_array($export_type, $allowed_types)) {
                    // Fetch all transactions for the user
                    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $transactions = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    if (empty($transactions)) {
                        $errors[] = "No data available to export.";
                    } else {
                        if ($export_type === 'csv') {
                            header('Content-Type: text/csv');
                            header('Content-Disposition: attachment; filename="transactions_'.date('Y-m-d').'.csv"');
                            $output = fopen('php://output', 'w');
                            fputcsv($output, array_keys($transactions[0]));
                            foreach ($transactions as $row) {
                                fputcsv($output, $row);
                            }
                            fclose($output);
                            exit();
                        } elseif ($export_type === 'excel') {
                            header('Content-Type: application/vnd.ms-excel');
                            header('Content-Disposition: attachment; filename="transactions_'.date('Y-m-d').'.xls"');
                            echo '<table border="1">';
                            echo '<tr>';
                            foreach (array_keys($transactions[0]) as $header) {
                                echo '<th>'.htmlspecialchars($header).'</th>';
                            }
                            echo '</tr>';
                            foreach ($transactions as $row) {
                                echo '<tr>';
                                foreach ($row as $cell) {
                                    echo '<td>'.htmlspecialchars($cell).'</td>';
                                }
                                echo '</tr>';
                            }
                            echo '</table>';
                            exit();
                        } elseif ($export_type === 'pdf') {
                            require_once('php/fpdf/fpdf.php'); // Make sure FPDF is installed in php/fpdf/
                            $pdf = new FPDF();
                            $pdf->AddPage();
                            $pdf->SetFont('Arial','B',12);

                            // Table header
                            foreach (array_keys($transactions[0]) as $header) {
                                $pdf->Cell(40,10,$header,1);
                            }
                            $pdf->Ln();

                            // Table rows
                            foreach ($transactions as $row) {
                                foreach ($row as $cell) {
                                    $pdf->Cell(40,10,$cell,1);
                                }
                                $pdf->Ln();
                            }
                            $pdf->Output('D', 'transactions_'.date('Y-m-d').'.pdf');
                            exit();
                        }
                    }
                } else {
                    $errors[] = "Invalid export type selected.";
                }
            }
        }
        
        // Handle data import
        if (isset($_POST['import_data'])) {
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                $file_type = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
                
                if ($file_type === 'csv') {
                    try {
                        $file = fopen($_FILES['import_file']['tmp_name'], 'r');
                        $headers = fgetcsv($file);
                        
                        // Validate CSV structure: expect amount, type, transaction_date, description, category
                        $required_columns = ['amount', 'type', 'transaction_date', 'description', 'category'];
                        $missing_columns = array_diff($required_columns, $headers);
                        
                        if (!empty($missing_columns)) {
                            throw new Exception("Missing required columns: " . implode(', ', $missing_columns));
                        }
                        
                        $conn->begin_transaction();
                        $imported_count = 0;
                        
                        while (($row = fgetcsv($file)) !== false) {
                            $data = array_combine($headers, $row);
                            
                            // Validate and sanitize data
                            $amount = filter_var($data['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                            $type = in_array(strtolower($data['type']), ['income', 'expense']) ? strtolower($data['type']) : 'expense';
                            $transaction_date = date('Y-m-d', strtotime($data['transaction_date']));
                            $description = filter_var($data['description'], FILTER_SANITIZE_STRING);
                            $category = isset($data['category']) ? trim($data['category']) : '';
                            
                            if (!$amount || !$transaction_date || !$category) {
                                continue; // Skip invalid rows
                            }
                            
                            // Insert transaction
                            $stmt = $conn->prepare("
                                INSERT INTO transactions 
                                (user_id, amount, type, transaction_date, description, category, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->bind_param("idssss", $user_id, $amount, $type, $transaction_date, $description, $category);
                            $stmt->execute();
                            $imported_count++;
                        }
                        
                        $conn->commit();
                        $message = "Successfully imported $imported_count transactions!";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $errors[] = "Import failed: " . $e->getMessage();
                    } finally {
                        if (isset($file)) {
                            fclose($file);
                        }
                    }
                } elseif (in_array($file_type, ['xls', 'xlsx'])) {
                    try {
                        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['import_file']['tmp_name']);
                        $sheet = $spreadsheet->getActiveSheet();
                        $rows = $sheet->toArray();
                        $headers = array_map('strtolower', $rows[0]);
                        $required_columns = ['amount', 'type', 'transaction_date', 'description', 'category'];
                        $missing_columns = array_diff($required_columns, $headers);
                        
                        if (!empty($missing_columns)) {
                            throw new Exception("Missing required columns: " . implode(', ', $missing_columns));
                        }
                        
                        $conn->begin_transaction();
                        $imported_count = 0;
                        
                        for ($i = 1; $i < count($rows); $i++) {
                            $data = array_combine($headers, $rows[$i]);
                            
                            // Validate and sanitize data
                            $amount = filter_var($data['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                            $type = in_array(strtolower($data['type']), ['income', 'expense']) ? strtolower($data['type']) : 'expense';
                            $transaction_date = date('Y-m-d', strtotime($data['transaction_date']));
                            $description = filter_var($data['description'], FILTER_SANITIZE_STRING);
                            $category = isset($data['category']) ? trim($data['category']) : '';
                            
                            if (!$amount || !$transaction_date || !$category) {
                                continue; // Skip invalid rows
                            }
                            
                            // Insert transaction
                            $stmt = $conn->prepare("
                                INSERT INTO transactions 
                                (user_id, amount, type, transaction_date, description, category, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->bind_param("idssss", $user_id, $amount, $type, $transaction_date, $description, $category);
                            $stmt->execute();
                            $imported_count++;
                        }
                        
                        $conn->commit();
                        $message = "Successfully imported $imported_count transactions!";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $errors[] = "Import failed: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Only CSV or Excel files are supported for import.";
                }
            } else {
                $errors[] = "Please select a valid file to import.";
            }
        }
    }
}

// Handle AJAX category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category_ajax'])) {
    header('Content-Type: application/json');
    // CSRF protection
    $headers = getallheaders();
    if (!isset($headers['X-CSRF-Token']) || $headers['X-CSRF-Token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $category_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($category_id > 0) {
        // Get the category name
        $stmt = $conn->prepare("SELECT name FROM categories WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $category_id, $user_id);
        $stmt->execute();
        $stmt->bind_result($category_name);
        $stmt->fetch();
        $stmt->close();
        if (!$category_name) {
            echo json_encode(['success' => false, 'message' => 'Category not found']);
            exit;
        }
        // Update all transactions to "Uncategorized"
        $uncat = "Uncategorized";
        $stmt = $conn->prepare("UPDATE transactions SET category=? WHERE user_id=? AND category=?");
        $stmt->bind_param("sis", $uncat, $user_id, $category_name);
        $stmt->execute();
        $stmt->close();
        // Delete the category
        $stmt = $conn->prepare("DELETE FROM categories WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $category_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
    }
    exit;
}

// Fetch user settings with error handling
try {
    $stmt = $conn->prepare("SELECT 
        name, email, 
        COALESCE(theme, 'dark') AS theme,
        COALESCE(currency, 'USD') AS currency,
        COALESCE(avatar, 'default.png') AS avatar,
        budget_goals,
        COALESCE(two_factor_enabled, 0) AS two_factor_enabled,
        COALESCE(data_sharing, 0) AS data_sharing,
        COALESCE(email_notifications, 1) AS email_notifications,
        COALESCE(push_notifications, 1) AS push_notifications,
        COALESCE(low_balance_alert, 1) AS low_balance_alert,
        COALESCE(large_expense_alert, 1) AS large_expense_alert
        FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Set default values
    $user['theme'] = $user['theme'] ?? 'dark';
    $user['currency'] = $user['currency'] ?? 'USD';
    $user['avatar'] = $user['avatar'] ?? 'default.png';
    $user['budget_goals'] = isset($user['budget_goals']) ? json_decode($user['budget_goals'], true) : [];
    $user['two_factor_enabled'] = $user['two_factor_enabled'] ?? 0;
    $user['data_sharing'] = $user['data_sharing'] ?? 0;
    $user['email_notifications'] = $user['email_notifications'] ?? 1;
    $user['push_notifications'] = $user['push_notifications'] ?? 1;
    $user['low_balance_alert'] = $user['low_balance_alert'] ?? 1;
    $user['large_expense_alert'] = $user['large_expense_alert'] ?? 1;
    
} catch (mysqli_sql_exception $e) {
    error_log("Database error: " . $e->getMessage());
    $errors[] = "A database error occurred. Please try again later.";
    $user = [
        'theme' => 'dark',
        'currency' => 'USD',
        'avatar' => 'default.png',
        'budget_goals' => [],
        'two_factor_enabled' => 0,
        'data_sharing' => 0,
        'email_notifications' => 1,
        'push_notifications' => 1,
        'low_balance_alert' => 1,
        'large_expense_alert' => 1
    ];
}

// Load settings for the user
$stmt = $conn->prepare("SELECT * FROM settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Save settings if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_budget_settings'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token. Please try again.";
    } else {
        $monthly_budget_goal = $_POST['monthly_budget_goal'] ?? 0;
        $monthly_savings_goal = $_POST['monthly_savings_goal'] ?? 0;
        $weekly_spending_limit = $_POST['weekly_spending_limit'] ?? 0;
        $budget_reset_day = $_POST['budget_reset_day'] ?? '1st of the month';

        $stmt = $conn->prepare("UPDATE settings SET monthly_budget_goal=?, monthly_savings_goal=?, weekly_spending_limit=?, budget_reset_day=? WHERE user_id=?");
        $stmt->bind_param("ddssi", $monthly_budget_goal, $monthly_savings_goal, $weekly_spending_limit, $budget_reset_day, $user_id);
        if ($stmt->execute()) {
            $message = "Budget settings updated successfully!";
        } else {
            $errors[] = "Failed to update budget settings.";
        }
        $stmt->close();
        // Reload settings after update
        $stmt = $conn->prepare("SELECT * FROM settings WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Fetch expense categories
$categories = [];
try {
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Categories error: " . $e->getMessage());
    $errors[] = "Could not load categories.";
}

// Fetch user's budget goals
$budget_goals = [];
try {
    $stmt = $conn->prepare("SELECT budget_goals FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($budget_goals_json);
    $stmt->fetch();
    $stmt->close();
    $budget_goals = $budget_goals_json ? json_decode($budget_goals_json, true) : [];
} catch (mysqli_sql_exception $e) {
    error_log("Budget goals error: " . $e->getMessage());
}

// Recommended default budget goals for common categories
$default_budget_goals = [
    'Rent' => 8000,
    'Food' => 3000,
    'Transport' => 1000,
    'Books' => 500,
    'Stationery' => 200,
    'Utilities' => 700,
    'Internet' => 500,
    'Mobile' => 300,
    'Laundry' => 300,
    'Shopping' => 1000,
    'Entertainment' => 500,
    'Medical' => 400,
    'Sports' => 300,
    'Subscription' => 200,
    'Gifts' => 200,
    'Miscellaneous' => 500,
    'Tuition' => 0
];
?>

<!DOCTYPE html>
<html lang="en" class="<?= htmlspecialchars($user['theme']) ?>-mode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Student Budget Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        :root {
            --accent-color: #0d6efd;
        }
        
        /* Theme styles */
        body.light-mode {
            background-color: #f8f9fa;
            color: #212529;
            --bs-body-bg: #f8f9fa;
        }
        
        body.dark-mode {
            background-color: #212529;
            color: #f8f9fa;
            --bs-body-bg: #212529;
        }
        
        body.read-mode {
            background-color: #f8f6f2;
            color: #333;
            --bs-body-bg: #f8f6f2;
        }
        
        body.dark-mode,
        body.dark-mode .card,
        body.dark-mode .list-group-item,
        body.dark-mode .modal-content,
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: #212529 !important;
            color: #f8f9fa !important;
            border-color: #495057 !important;
        }

        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            background-color: #212529 !important;
            color: #f8f9fa !important;
        }
        
        .password-meter {
            height: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .password-meter-fill {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .settings-menu .list-group-item {
            border-left: 0;
            border-right: 0;
            border-radius: 0;
        }
        
        .settings-menu .list-group-item:first-child {
            border-top: 0;
        }
        
        .settings-menu .list-group-item.active {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .theme-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .theme-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .theme-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .theme-card input[type="radio"]:checked + .card {
            border: 2px solid var(--accent-color);
        }
        
        .budget-category-card {
            transition: all 0.2s;
        }
        
        .budget-category-card:hover {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .category-badge {
            font-size: 0.8rem;
        }
        
        .data-export-card {
            border-left: 4px solid #0d6efd;
        }
        
        .data-delete-card {
            border-left: 4px solid #dc3545;
        }
        
        .export-option {
            margin-bottom: 15px;
        }
        
        .danger-zone {
            border-left: 4px solid #dc3545;
            padding-left: 15px;
            margin-top: 30px;
        }
        
        .danger-zone h4 {
            color: #dc3545;
        }
        
        .file-input-info {
            margin-top: 5px;
            font-size: 0.875em;
        }
    </style>
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
                        <a class="nav-link" href="report.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php">Settings</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="account.php">My Account</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Settings Sidebar -->
            <div class="col-md-3 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Settings Menu</h5>
                    </div>
                    <div class="list-group list-group-flush settings-menu" id="settingsMenu">
                        <a href="#password" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                            <i class="bi bi-shield-lock me-2"></i>Password
                        </a>
                        <a href="#notifications" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-bell me-2"></i>Notifications
                        </a>
                        <a href="#budget" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-graph-up me-2"></i>Budget Goals
                        </a>
                        <a href="#appearance" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-palette me-2"></i>Appearance
                        </a>
                        <a href="#categories" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-tags me-2"></i>Categories
                        </a>
                        <a href="#data" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-database me-2"></i>Data Management
                        </a>
                        <a href="#privacy" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-lock me-2"></i>Privacy & Security
                        </a>
                        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-md-9">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Account Settings</h4>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="settingsTabContent">
                            <!-- Password Tab -->
                            <div class="tab-pane fade show active" id="password" role="tabpanel">
                                <form id="passwordForm" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-3">
                                        <label for="currentPassword" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="newPassword" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                        <div class="form-text">Password must be at least 8 characters long</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirmNewPassword" name="confirm_password" required>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Notifications Tab -->
                            <div class="tab-pane fade" id="notifications" role="tabpanel">
                                <form id="notificationsForm" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <h4 class="mb-4">Notification Settings</h4>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Notification Methods</h5>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="emailNotifications" 
                                                       name="email_notifications" <?= $user['email_notifications'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="emailNotifications">
                                                    Email Notifications
                                                </label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="pushNotifications" 
                                                       name="push_notifications" <?= $user['push_notifications'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="pushNotifications">
                                                    Push Notifications
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Alert Preferences</h5>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="lowBalanceAlert" 
                                                       name="low_balance_alert" <?= $user['low_balance_alert'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="lowBalanceAlert">
                                                    Low Balance Alerts
                                                </label>
                                                <div class="form-text">Receive alerts when your balance falls below a threshold</div>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="largeExpenseAlert" 
                                                       name="large_expense_alert" <?= $user['large_expense_alert'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="largeExpenseAlert">
                                                    Large Expense Alerts
                                                </label>
                                                <div class="form-text">Receive alerts for unusually large expenses</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="update_notifications" class="btn btn-primary">Save Notification Settings</button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Budget Goals Tab -->
                            <div class="tab-pane fade show active" id="budget" role="tabpanel">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h4 class="mb-0">Account Settings</h4>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($errors)): ?>
                                            <div class="alert alert-danger">
                                                <?php foreach ($errors as $error): ?>
                                                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($message)): ?>
                                            <div class="alert alert-success">
                                                <?= htmlspecialchars($message) ?>
                                            </div>
                                        <?php endif; ?>
                                        <form method="post" action="settings.php#budget">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <div class="mb-3">
                                                <label for="monthly_budget_goal" class="form-label">Monthly Budget Goal (₹)</label>
                                                <input type="number" class="form-control" id="monthly_budget_goal" name="monthly_budget_goal"
                                                    value="<?= htmlspecialchars($settings['monthly_budget_goal'] ?? '') ?>" min="0" step="0.01">
                                            </div>
                                            <div class="mb-3">
                                                <label for="monthly_savings_goal" class="form-label">Monthly Savings Goal (₹)</label>
                                                <input type="number" class="form-control" id="monthly_savings_goal" name="monthly_savings_goal"
                                                    value="<?= htmlspecialchars($settings['monthly_savings_goal'] ?? '') ?>" min="0" step="0.01">
                                            </div>
                                            <div class="mb-3">
                                                <label for="weekly_spending_limit" class="form-label">Weekly Spending Limit (₹)</label>
                                                <input type="number" class="form-control" id="weekly_spending_limit" name="weekly_spending_limit"
                                                    value="<?= htmlspecialchars($settings['weekly_spending_limit'] ?? '') ?>" min="0" step="0.01">
                                            </div>
                                            <div class="mb-3">
                                                <label for="budget_reset_day" class="form-label">Budget Reset Day</label>
                                                <select class="form-select" id="budget_reset_day" name="budget_reset_day">
                                                    <option value="1st of the month" <?= ($settings['budget_reset_day'] ?? '') == '1st of the month' ? 'selected' : '' ?>>1st of the month</option>
                                                    <option value="15th of the month" <?= ($settings['budget_reset_day'] ?? '') == '15th of the month' ? 'selected' : '' ?>>15th of the month</option>
                                                    <option value="Last day of the month" <?= ($settings['budget_reset_day'] ?? '') == 'Last day of the month' ? 'selected' : '' ?>>Last day of the month</option>
                                                </select>
                                            </div>
                                            <button type="submit" name="save_budget_settings" class="btn btn-primary w-100">Save Budget Settings</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Appearance Tab -->
                            <div class="tab-pane fade" id="appearance" role="tabpanel">
                                <form id="appearanceForm" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-4">
                                        <h5 class="mb-3">Theme Settings</h5>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="theme-card">
                                                    <input type="radio" name="theme" value="light" <?= ($user['theme'] ?? '') === 'light' ? 'checked' : '' ?> class="d-none">
                                                    <div class="card h-100">
                                                        <div class="card-body text-center">
                                                            <div class="mb-3">
                                                                <i class="bi bi-sun fs-1"></i>
                                                            </div>
                                                            <h5>Light Mode</h5>
                                                            <p class="text-muted">Bright and clear interface</p>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="theme-card">
                                                    <input type="radio" name="theme" value="dark" <?= ($user['theme'] ?? '') === 'dark' ? 'checked' : '' ?> class="d-none">
                                                    <div class="card h-100">
                                                        <div class="card-body text-center">
                                                            <div class="mb-3">
                                                                <i class="bi bi-moon fs-1"></i>
                                                            </div>
                                                            <h5>Dark Mode</h5>
                                                            <p class="text-muted">Easy on the eyes in low light</p>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="theme-card">
                                                    <input type="radio" name="theme" value="read" <?= ($user['theme'] ?? '') === 'read' ? 'checked' : '' ?> class="d-none">
                                                    <div class="card h-100">
                                                        <div class="card-body text-center">
                                                            <div class="mb-3">
                                                                <i class="bi bi-book fs-1"></i>
                                                            </div>
                                                            <h5>Read Mode</h5>
                                                            <p class="text-muted">Reduced eye strain for reading</p>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <h5 class="mb-3">Accent Color</h5>
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <div class="d-flex flex-wrap gap-2">
                                                <input type="radio" class="btn-check" name="accent_color" id="colorBlue" value="#0d6efd" checked>
                                                <label class="btn btn-sm" style="background-color: #0d6efd;" for="colorBlue"></label>
                                                
                                                <input type="radio" class="btn-check" name="accent_color" id="colorPurple" value="#6f42c1">
                                                <label class="btn btn-sm" style="background-color: #6f42c1;" for="colorPurple"></label>
                                                
                                                <input type="radio" class="btn-check" name="accent_color" id="colorPink" value="#d63384">
                                                <label class="btn btn-sm" style="background-color: #d63384;" for="colorPink"></label>
                                                
                                                <input type="radio" class="btn-check" name="accent_color" id="colorRed" value="#dc3545">
                                                <label class="btn btn-sm" style="background-color: #dc3545;" for="colorRed"></label>
                                                
                                                <input type="radio" class="btn-check" name="accent_color" id="colorOrange" value="#fd7e14">
                                                <label class="btn btn-sm" style="background-color: #fd7e14;" for="colorOrange"></label>
                                                
                                                <input type="radio" class="btn-check" name="accent_color" id="colorGreen" value="#198754">
                                                <label class="btn btn-sm" style="background-color: #198754;" for="colorGreen"></label>
                                                
                                                <input type="radio" class="btn-check" name="accent_color" id="colorTeal" value="#20c997">
                                                <label class="btn btn-sm" style="background-color: #20c997;" for="colorTeal"></label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="update_theme" class="btn btn-primary">Save Appearance Settings</button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Categories Tab -->
                            <div class="tab-pane fade" id="categories" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <form id="categoriesForm" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <h4 class="mb-4">Manage Categories</h4>
                                            
                                            <div class="mb-3">
                                                <label for="newCategory" class="form-label">Add New Category</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="newCategory" name="new_category" placeholder="Category name">
                                                    <button class="btn btn-primary" type="submit" name="update_categories">Add</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Your Categories</h5>
                                        <?php if (!empty($categories)): ?>
                                            <div class="list-group">
                                                <?php foreach ($categories as $id => $name): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?= htmlspecialchars($name) ?>
                                                        <button class="btn btn-sm btn-outline-danger delete-category" data-id="<?= $id ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info text-center fade show">
                                                <i class="bi bi-tags display-4 mb-2 text-secondary"></i>
                                                <div class="mb-2">You haven't created any categories yet.</div>
                                                <button class="btn btn-primary btn-sm" onclick="document.getElementById('newCategory').focus();">
                                                    <i class="bi bi-plus-circle"></i> Add Your First Category
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data Management Tab -->
                            <div class="tab-pane fade" id="data" role="tabpanel">
                                <h4 class="mb-4">Data Management</h4>
                                
                                <div class="mb-4">
                                    <h5>Export Data</h5>
                                    <p>Download your financial data for backup or analysis.</p>
                                    
                                    <form id="exportForm" method="post" action="settings.php#data">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        
                                        <div class="export-option mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="export_type" id="exportExcel" value="excel" checked>
                                                <label class="form-check-label" for="exportExcel">
                                                    Export to Excel (.xls)
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="export-option mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="export_type" id="exportCSV" value="csv">
                                                <label class="form-check-label" for="exportCSV">
                                                    Export to CSV (.csv)
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="export-option mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="export_type" id="exportPDF" value="pdf">
                                                <label class="form-check-label" for="exportPDF">
                                                    Export to PDF (.pdf)
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="export_data" class="btn btn-primary mt-3">
                                            <i class="bi bi-download me-2"></i>Export Data
                                        </button>
                                    </form>
                                    <?php if (!empty($errors) && isset($_POST['export_data'])): ?>
                                        <div class="alert alert-danger mt-3">
                                            <?php foreach ($errors as $error): ?>
                                                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-4">
                                    <h5>Import Data</h5>
                                    <p>Import transactions from other systems. (CSV or Excel format only)</p>
                                    
                                    <form id="importForm" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        
                                        <div class="mb-3">
                                            <label for="importFile" class="form-label">Select CSV or Excel file to import</label>
                                            <div class="input-group">
                                                <input type="file" class="form-control" id="importFile" name="import_file" accept=".csv,.xls,.xlsx" required>
                                            </div>
                                            <div class="file-input-info" id="fileInfo">No file chosen</div>
                                            <div class="form-text">
                                                File must be in CSV or Excel format with these columns: amount, type (income/expense), date, description
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="import_data" class="btn btn-primary">
                                            <i class="bi bi-upload me-2"></i>Import Data
                                        </button>
                                    </form>
                                    
                                    <div class="mt-3">
                                        <a href="?download_template=1" class="text-decoration-none">
                                            <i class="bi bi-file-earmark-arrow-down me-1"></i>Download CSV template
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="danger-zone">
                                    <h4>Danger Zone</h4>
                                    <p>These actions cannot be undone. Proceed with caution.</p>
                                    
                                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="bi bi-trash me-2"></i>Delete All Data
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Privacy & Security Tab -->
                            <div class="tab-pane fade" id="privacy" role="tabpanel">
                                <form id="privacyForm" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <h4 class="mb-4">Privacy & Security Settings</h4>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Two-Factor Authentication</h5>
                                            <p class="card-text">Add an extra layer of security to your account.</p>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="twoFactorEnabled" 
                                                       name="two_factor" <?= $user['two_factor_enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="twoFactorEnabled">
                                                    Enable Two-Factor Authentication
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Data Sharing</h5>
                                            <p class="card-text">Help improve our service by sharing anonymous usage data.</p>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="dataSharing" 
                                                       name="data_sharing" <?= $user['data_sharing'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="dataSharing">
                                                    Allow Anonymous Data Sharing
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Active Sessions</h5>
                                            <p class="card-text">View and manage your active login sessions.</p>
                                            <div class="list-group">
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <strong>Current Session</strong>
                                                            <div class="text-muted"><?= htmlspecialchars($_SERVER['HTTP_USER_AGENT']) ?></div>
                                                            <small class="text-muted"><?= date('Y-m-d H:i:s') ?></small>
                                                        </div>
                                                        <span class="badge bg-primary">Active</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" name="update_privacy" class="btn btn-primary">Save Privacy Settings</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Data Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete all your financial data? This action cannot be undone.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmDelete">
                        <label class="form-check-label" for="confirmDelete">I understand this will permanently delete all my data</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="deleteDataBtn" disabled>Delete All Data</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Category Confirmation Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Category Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this category? All expenses in this category will be moved to "Uncategorized".</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteCategory">Delete Category</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tabs
            const tabLinks = document.querySelectorAll('#settingsMenu a[data-bs-toggle="tab"]');
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = this.getAttribute('href');
                    const tabPane = document.querySelector(target);
                    
                    // Remove active class from all tabs
                    document.querySelectorAll('#settingsMenu .list-group-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Add active class to current tab
                    this.classList.add('active');
                    
                    // Show the target tab pane
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('show', 'active');
                    });
                    tabPane.classList.add('show', 'active');
                });
            });

            // Password strength meter
            document.getElementById('newPassword').addEventListener('input', function() {
                const password = this.value;
                let strengthMeter = this.nextElementSibling;
                
                // Create meter if it doesn't exist
                if (!strengthMeter || !strengthMeter.classList.contains('password-meter')) {
                    strengthMeter = document.createElement('div');
                    strengthMeter.className = 'password-meter mt-2';
                    const strengthFill = document.createElement('div');
                    strengthFill.className = 'password-meter-fill';
                    strengthMeter.appendChild(strengthFill);
                    this.parentNode.insertBefore(strengthMeter, this.nextElementSibling);
                }
                
                const strengthFill = strengthMeter.querySelector('.password-meter-fill');
                let strength = 0;
                if (password.length >= 8) strength += 1;
                if (password.match(/[a-z]/)) strength += 1;
                if (password.match(/[A-Z]/)) strength += 1;
                if (password.match(/[0-9]/)) strength += 1;
                if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
                
                // Update meter
                const colors = ['#dc3545', '#ffc107', '#17a2b8', '#28a745', '#218838'];
                const width = (strength / 5) * 100;
                strengthFill.style.width = width + '%';
                strengthFill.style.backgroundColor = colors[strength - 1] || '#e9ecef';
            });

            // Password match checker
            document.getElementById('confirmNewPassword').addEventListener('input', function() {
                const password = document.getElementById('newPassword').value;
                const confirmPassword = this.value;
                let matchText = this.nextElementSibling;
                
                // Create match text element if it doesn't exist
                if (!matchText || (matchText.tagName !== 'SMALL' && !matchText.classList.contains('match-text'))) {
                    matchText = document.createElement('small');
                    matchText.className = 'd-block mt-1 match-text';
                    this.parentNode.appendChild(matchText);
                }
                
                if (confirmPassword === '') {
                    matchText.textContent = '';
                    matchText.className = 'd-block mt-1 match-text';
                } else if (password === confirmPassword) {
                    matchText.textContent = 'Passwords match';
                    matchText.className = 'd-block mt-1 match-text text-success';
                } else {
                    matchText.textContent = 'Passwords do not match';
                    matchText.className = 'd-block mt-1 match-text text-danger';
                }
            });

            // Delete all data button
            if (document.getElementById('deleteDataBtn')) {
                document.getElementById('confirmDelete').addEventListener('change', function() {
                    document.getElementById('deleteDataBtn').disabled = !this.checked;
                });
                
                document.getElementById('deleteDataBtn').addEventListener('click', function() {
                    if (confirm('Are you absolutely sure? This cannot be undone!')) {
                        fetch('delete_data.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                            },
                            body: JSON.stringify({ confirm: true })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('All your data has been deleted successfully.');
                                window.location.reload();
                            } else {
                                alert('Failed to delete data: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting data.');
                        });
                    }
                });
            }

            // Category deletion
            const deleteButtons = document.querySelectorAll('.delete-category');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const categoryId = this.getAttribute('data-id');
                    const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
                    
                    document.getElementById('confirmDeleteCategory').onclick = function() {
                        fetch('settings.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                            },
                            body: `delete_category_ajax=1&id=${categoryId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Category deleted successfully.');
                                window.location.reload();
                            } else {
                                alert('Failed to delete category: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting the category.');
                        });
                        
                        modal.hide();
                    };
                    
                    modal.show();
                });
            });

            // Theme preview
            const themeRadios = document.querySelectorAll('input[name="theme"]');
            themeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.body.className = this.value + '-mode';
                });
            });

            // Accent color preview
            const colorRadios = document.querySelectorAll('input[name="accent_color"]');
            colorRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.documentElement.style.setProperty('--accent-color', this.value);
                });
            });

            // File input display
            document.getElementById('importFile').addEventListener('change', function(e) {
                const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
                document.getElementById('fileInfo').textContent = fileName;
                document.getElementById('fileInfo').className = 'file-input-info text-success';
            });

            // Import form validation
            document.getElementById('importForm').addEventListener('submit', function(e) {
                const fileInput = document.getElementById('importFile');
                if (!fileInput.value) {
                    e.preventDefault();
                    alert('Please select a file to import.');
                    fileInput.focus();
                } else if (!fileInput.value.toLowerCase().endsWith('.csv') && !fileInput.value.toLowerCase().endsWith('.xls') && !fileInput.value.toLowerCase().endsWith('.xlsx')) {
                    e.preventDefault();
                    alert('Only CSV or Excel files are supported for import.');
                    fileInput.focus();
                }
            });
        });
    </script>
</body>
</html>