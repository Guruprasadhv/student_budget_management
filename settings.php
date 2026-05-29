<?php
session_start();
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/languages.php';
require_once __DIR__ . '/php/account_helpers.php';
require_once __DIR__ . '/php/settings_helpers.php';

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_no_pending_2fa();

$user_id = (int)$_SESSION['user_id'];
ensure_account_tables($conn);
touch_user_session($conn, $user_id, session_id());

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

$message = $_SESSION['settings_message'] ?? '';
$errors = $_SESSION['settings_errors'] ?? [];
unset($_SESSION['settings_message'], $_SESSION['settings_errors']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// AJAX category deletion (before main POST handler)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category_ajax'])) {
    header('Content-Type: application/json');
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $ajaxToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $ajaxToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $category_id = (int)($_POST['id'] ?? 0);
    if ($category_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT name FROM categories WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit;
    }

    $uncat = 'Uncategorized';
    $stmt = $conn->prepare("UPDATE transactions SET category=? WHERE user_id=? AND category=?");
    $stmt->bind_param("sis", $uncat, $user_id, $row['name']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM categories WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $category_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Category deleted.' : 'Database error']);
    exit;
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
                $stmt = $conn->prepare("UPDATE users SET password = ?, password_updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    settings_redirect('#password', 'Password changed successfully!');
                } else {
                    $errors[] = "Failed to update password. Please try again.";
                }
            }
        }
        
        // Handle theme preference
        if (isset($_POST['update_theme'])) {
            $theme = $_POST['theme'] ?? 'light';
            $allowed_themes = ['light', 'dark', 'read', 'system'];
            
            if (in_array($theme, $allowed_themes, true)) {
                $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
                $stmt->bind_param("si", $theme, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['theme'] = $theme;
                    if (!empty($_POST['accent_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['accent_color'])) {
                        $_SESSION['accent_color'] = $_POST['accent_color'];
                    }
                    settings_redirect('#appearance', 'Appearance settings saved!');
                }
            } else {
                $errors[] = 'Invalid theme selected.';
            }
        }
        
        // Handle budget goals
        if (isset($_POST['update_budget_goals'])) {
            $budget_goals = isset($_POST['categories']) ? $_POST['categories'] : [];
            $budget_goals_json = json_encode($budget_goals);
            
            $stmt = $conn->prepare("UPDATE users SET budget_goals = ? WHERE id = ?");
            $stmt->bind_param("si", $budget_goals_json, $user_id);
            
            if ($stmt->execute()) {
                settings_redirect('#budget', 'Budget goals updated successfully!');
            } else {
                $errors[] = "Failed to update budget goals.";
            }
        }
        
        // Handle categories update
        if (isset($_POST['update_categories'])) {
            $new_category = trim($_POST['new_category'] ?? '');
            
            if (!empty($new_category)) {
                $stmt = $conn->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $new_category);

                try {
                    if ($stmt->execute()) {
                        settings_redirect('#categories', 'Category added successfully!');
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

            if ($two_factor) {
                $check = $conn->prepare("SELECT two_factor_pin_hash FROM users WHERE id = ?");
                $check->bind_param("i", $user_id);
                $check->execute();
                $pinRow = $check->get_result()->fetch_assoc();
                $check->close();
                if (empty($pinRow['two_factor_pin_hash'])) {
                    $errors[] = 'Enable 2FA from My Account → Security first (set your 6-digit PIN).';
                    $two_factor = 0;
                }
            }

            if ($two_factor) {
                $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1, data_sharing = ? WHERE id = ?");
                $stmt->bind_param("ii", $data_sharing, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_pin_hash = NULL, data_sharing = ? WHERE id = ?");
                $stmt->bind_param("ii", $data_sharing, $user_id);
            }
            
            if (empty($errors) && $stmt->execute()) {
                settings_redirect('#privacy', 'Privacy settings updated!');
            } elseif (empty($errors)) {
                $errors[] = "Failed to update privacy settings.";
            }
            $stmt->close();
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
                settings_redirect('#notifications', 'Notification settings updated!');
            } else {
                $errors[] = "Failed to update notification settings.";
            }
            $stmt->close();
        }
        
        // Handle data export
        if (isset($_POST['export_data'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $errors[] = "Invalid CSRF token. Please try again.";
            } else {
                $export_type = $_POST['export_type'] ?? 'csv';
                $allowed_types = ['csv'];
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
                            $amount = filter_var($data['amount'], FILTER_VALIDATE_FLOAT);
                            $type = in_array(strtolower($data['type']), ['income', 'expense']) ? strtolower($data['type']) : 'expense';
                            $transaction_date = date('Y-m-d', strtotime($data['transaction_date']));
                            $description = htmlspecialchars(strip_tags($data['description'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $category = isset($data['category']) ? trim($data['category']) : '';
                            
                            if ($amount === false || $amount === '' || !$transaction_date || $category === '') {
                                continue;
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
                        settings_redirect('#data', "Successfully imported $imported_count transactions!");
                    } catch (Exception $e) {
                        $conn->rollback();
                        $errors[] = "Import failed: " . $e->getMessage();
                    } finally {
                        if (isset($file)) {
                            fclose($file);
                        }
                    }
                } else {
                    $errors[] = "Only CSV files are supported for import.";
                }
            } else {
                $errors[] = "Please select a valid file to import.";
            }
        }
    }
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
    $_SESSION['theme'] = $user['theme'];
    
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

$settings = ensure_user_settings($conn, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_budget_settings'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token. Please try again.";
    } else {
        $monthly_budget_goal = (float)($_POST['monthly_budget_goal'] ?? 0);
        $monthly_savings_goal = (float)($_POST['monthly_savings_goal'] ?? 0);
        $weekly_spending_limit = (float)($_POST['weekly_spending_limit'] ?? 0);
        $budget_reset_day = $_POST['budget_reset_day'] ?? '1st of the month';
        $allowed_reset = ['1st of the month', '15th of the month', 'Last day of the month'];
        if (!in_array($budget_reset_day, $allowed_reset, true)) {
            $budget_reset_day = '1st of the month';
        }

        $stmt = $conn->prepare("UPDATE settings SET monthly_budget_goal=?, monthly_savings_goal=?, weekly_spending_limit=?, budget_reset_day=? WHERE user_id=?");
        $stmt->bind_param("dddsi", $monthly_budget_goal, $monthly_savings_goal, $weekly_spending_limit, $budget_reset_day, $user_id);
        if ($stmt->execute()) {
            settings_redirect('#budget', 'Budget settings updated successfully!');
        } else {
            $errors[] = "Failed to update budget settings.";
        }
        $stmt->close();
        $settings = ensure_user_settings($conn, $user_id);
    }
}

$accentColor = $_SESSION['accent_color'] ?? '#0d6efd';

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
<html lang="en" data-theme="<?= isset($_SESSION['theme']) ? htmlspecialchars($_SESSION['theme']) : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('settings') ?> - <?= __('student_budget_tracker') ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/bootstrap-icons-1.13.1/bootstrap-icons.css">
    <style>
        :root {
            --accent-color: <?= htmlspecialchars($accentColor) ?>;
        }

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
            [data-theme="system"] .btn-primary {
                background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%) !important;
                border-color: #4f46e5 !important;
                color: #ffffff !important;
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
                transition: all 0.2s ease;
            }
            [data-theme="system"] .btn-primary:hover {
                background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%) !important;
                border-color: #6366f1 !important;
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35);
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

        /* Override primary button in dark theme */
        [data-theme="dark"] .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%) !important;
            border-color: #4f46e5 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
            transition: all 0.2s ease;
        }
        [data-theme="dark"] .btn-primary:hover {
            background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%) !important;
            border-color: #6366f1 !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35);
        }

        /* Override primary button in read theme */
        [data-theme="read"] .btn-primary {
            background-color: #5c544c !important;
            border-color: #5c544c !important;
            color: #ffffff !important;
            transition: all 0.2s ease;
        }
        [data-theme="read"] .btn-primary:hover {
            background-color: #4a433d !important;
            border-color: #4a433d !important;
            transform: translateY(-1px);
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

        .btn-check:checked + label {
            outline: 2px solid var(--accent-color) !important;
            outline-offset: 2px;
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
                        <h5 class="mb-0"><?= __('settings_menu') ?></h5>
                    </div>
                    <div class="list-group list-group-flush settings-menu" id="settingsMenu">
                        <a href="#password" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                            <i class="bi bi-shield-lock me-2"></i><?= __('password') ?>
                        </a>
                        <a href="#notifications" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-bell me-2"></i><?= __('notifications') ?>
                        </a>
                        <a href="#budget" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-graph-up me-2"></i><?= __('budget_goals') ?>
                        </a>
                        <a href="#appearance" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-palette me-2"></i><?= __('appearance') ?>
                        </a>
                        <a href="#categories" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-tags me-2"></i><?= __('categories') ?>
                        </a>
                        <a href="#data" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-database me-2"></i><?= __('data_management') ?>
                        </a>
                        <a href="#privacy" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-lock me-2"></i><?= __('privacy_security') ?>
                        </a>
                        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i><?= __('logout') ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-md-9">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><?= __('account_settings') ?></h4>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="settingsTabContent">
                            <!-- Password Tab -->
                            <div class="tab-pane fade show active" id="password" role="tabpanel">
                                <form id="passwordForm" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-3">
                                        <label for="currentPassword" class="form-label"><?= __('current_password') ?></label>
                                        <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="newPassword" class="form-label"><?= __('new_password') ?></label>
                                        <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                        <div class="form-text"><?= __('password_length_hint') ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmNewPassword" class="form-label"><?= __('confirm_new_password') ?></label>
                                        <input type="password" class="form-control" id="confirmNewPassword" name="confirm_password" required>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="change_password" class="btn btn-primary"><?= __('change_password') ?></button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Notifications Tab -->
                            <div class="tab-pane fade" id="notifications" role="tabpanel">
                                <form id="notificationsForm" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <h4 class="mb-4"><?= __('notification_settings') ?></h4>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= __('notification_methods') ?></h5>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="emailNotifications" 
                                                       name="email_notifications" <?= $user['email_notifications'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="emailNotifications">
                                                    <?= __('email_notifications') ?>
                                                </label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="pushNotifications" 
                                                       name="push_notifications" <?= $user['push_notifications'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="pushNotifications">
                                                    <?= __('push_notifications') ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= __('alert_preferences') ?></h5>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="lowBalanceAlert" 
                                                       name="low_balance_alert" <?= $user['low_balance_alert'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="lowBalanceAlert">
                                                    <?= __('low_balance_alerts') ?>
                                                </label>
                                                <div class="form-text"><?= __('low_balance_alerts_desc') ?></div>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="largeExpenseAlert" 
                                                       name="large_expense_alert" <?= $user['large_expense_alert'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="largeExpenseAlert">
                                                    <?= __('large_expense_alerts') ?>
                                                </label>
                                                <div class="form-text"><?= __('large_expense_alerts_desc') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="update_notifications" class="btn btn-primary"><?= __('save_notification_settings') ?></button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Budget Goals Tab -->
                            <div class="tab-pane fade" id="budget" role="tabpanel">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h4 class="mb-0"><?= __('budget_goals') ?></h4>
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
                                                <label for="monthly_budget_goal" class="form-label"><?= __('monthly_budget_goal') ?> (₹)</label>
                                                <input type="number" class="form-control" id="monthly_budget_goal" name="monthly_budget_goal"
                                                    value="<?= htmlspecialchars($settings['monthly_budget_goal'] ?? '') ?>" min="0" step="0.01">
                                            </div>
                                            <div class="mb-3">
                                                <label for="monthly_savings_goal" class="form-label"><?= __('monthly_savings_goal') ?> (₹)</label>
                                                <input type="number" class="form-control" id="monthly_savings_goal" name="monthly_savings_goal"
                                                    value="<?= htmlspecialchars($settings['monthly_savings_goal'] ?? '') ?>" min="0" step="0.01">
                                            </div>
                                            <div class="mb-3">
                                                <label for="weekly_spending_limit" class="form-label"><?= __('weekly_spending_limit') ?> (₹)</label>
                                                <input type="number" class="form-control" id="weekly_spending_limit" name="weekly_spending_limit"
                                                    value="<?= htmlspecialchars($settings['weekly_spending_limit'] ?? '') ?>" min="0" step="0.01">
                                            </div>
                                            <div class="mb-3">
                                                <label for="budget_reset_day" class="form-label"><?= __('budget_reset_day') ?></label>
                                                <select class="form-select" id="budget_reset_day" name="budget_reset_day">
                                                    <option value="1st of the month" <?= ($settings['budget_reset_day'] ?? '') == '1st of the month' ? 'selected' : '' ?>><?= __('first_of_month') ?></option>
                                                    <option value="15th of the month" <?= ($settings['budget_reset_day'] ?? '') == '15th of the month' ? 'selected' : '' ?>><?= __('fifteenth_of_month') ?></option>
                                                    <option value="Last day of the month" <?= ($settings['budget_reset_day'] ?? '') == 'Last day of the month' ? 'selected' : '' ?>><?= __('last_day_of_month') ?></option>
                                                </select>
                                            </div>
                                            <button type="submit" name="save_budget_settings" class="btn btn-primary w-100"><?= __('save_budget_settings') ?></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Appearance Tab -->
                            <div class="tab-pane fade" id="appearance" role="tabpanel">
                                <form id="appearanceForm" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-4">
                                        <h5 class="mb-3"><?= __('theme_settings') ?></h5>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="theme-card">
                                                    <input type="radio" name="theme" value="light" <?= ($user['theme'] ?? '') === 'light' ? 'checked' : '' ?> class="d-none">
                                                    <div class="card h-100">
                                                        <div class="card-body text-center">
                                                            <div class="mb-3">
                                                                <i class="bi bi-sun fs-1"></i>
                                                            </div>
                                                            <h5><?= __('light_mode') ?></h5>
                                                            <p class="text-muted"><?= __('bright_clear_interface') ?></p>
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
                                                            <h5><?= __('dark_mode') ?></h5>
                                                            <p class="text-muted"><?= __('easy_on_eyes') ?></p>
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
                                                            <h5><?= __('read_mode') ?></h5>
                                                            <p class="text-muted"><?= __('reduced_eye_strain') ?></p>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <h5 class="mb-3"><?= __('accent_color') ?></h5>
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php
                                                $accentOptions = [
                                                    'colorBlue'   => '#0d6efd',
                                                    'colorPurple' => '#6f42c1',
                                                    'colorPink'   => '#d63384',
                                                    'colorRed'    => '#dc3545',
                                                    'colorOrange' => '#fd7e14',
                                                    'colorGreen'  => '#198754',
                                                    'colorTeal'   => '#20c997',
                                                ];
                                                foreach ($accentOptions as $id => $color):
                                                    $checked = $accentColor === $color ? 'checked' : '';
                                                ?>
                                                <input type="radio" class="btn-check" name="accent_color" id="<?= $id ?>" value="<?= $color ?>" <?= $checked ?>>
                                                <label class="btn btn-sm" for="<?= $id ?>" style="background-color:<?= $color ?>;width:2rem;height:2rem;border-radius:50%;padding:0;border:3px solid transparent;outline:2px solid transparent;"></label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="update_theme" class="btn btn-primary"><?= __('save_appearance_settings') ?></button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Categories Tab -->
                            <div class="tab-pane fade" id="categories" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <form id="categoriesForm" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <h4 class="mb-4"><?= __('manage_categories') ?></h4>
                                            
                                            <div class="mb-3">
                                                <label for="newCategory" class="form-label"><?= __('add_new_category') ?></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="newCategory" name="new_category" placeholder="<?= __('category_name') ?>">
                                                    <button class="btn btn-primary" type="submit" name="update_categories"><?= __('add') ?></button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5 class="mb-3"><?= __('your_categories') ?></h5>
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
                                                <div class="mb-2"><?= __('no_categories_yet') ?></div>
                                                <button class="btn btn-primary btn-sm" onclick="document.getElementById('newCategory').focus();">
                                                    <i class="bi bi-plus-circle"></i> <?= __('add_first_category') ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data Management Tab -->
                            <div class="tab-pane fade" id="data" role="tabpanel">
                                <h4 class="mb-4"><?= __('data_management') ?></h4>
                                
                                <div class="mb-4">
                                    <h5><?= __('export_data') ?></h5>
                                    <p><?= __('export_data_desc') ?></p>
                                    
                                    <form id="exportForm" method="post" action="settings.php#data">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="export_type" value="csv">
                                        
                                        <button type="submit" name="export_data" class="btn btn-primary">
                                            <i class="bi bi-download me-2"></i><?= __('export_data') ?>
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
                                    <h5><?= __('import_data') ?></h5>
                                    <p><?= __('import_data_desc') ?></p>
                                    
                                    <form id="importForm" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        
                                        <div class="mb-3">
                                            <label for="importFile" class="form-label"><?= __('select_csv_file') ?></label>
                                            <div class="input-group">
                                                <input type="file" class="form-control" id="importFile" name="import_file" accept=".csv" required>
                                            </div>
                                            <div class="file-input-info" id="fileInfo"><?= __('no_file_chosen') ?></div>
                                            <div class="form-text">
                                                <?= __('import_file_format_desc') ?>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="import_data" class="btn btn-primary">
                                            <i class="bi bi-upload me-2"></i><?= __('import_data') ?>
                                        </button>
                                    </form>
                                    
                                    <div class="mt-3">
                                        <a href="?download_template=1" class="text-decoration-none">
                                            <i class="bi bi-file-earmark-arrow-down me-1"></i><?= __('download_csv_template') ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="danger-zone">
                                    <h4><?= __('danger_zone') ?></h4>
                                    <p><?= __('danger_zone_desc') ?></p>
                                    
                                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="bi bi-trash me-2"></i><?= __('delete_all_data') ?>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Privacy & Security Tab -->
                            <div class="tab-pane fade" id="privacy" role="tabpanel">
                                <form id="privacyForm" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <h4 class="mb-4"><?= __('privacy_security') ?></h4>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= __('two_factor_auth') ?></h5>
                                            <p class="card-text"><?= __('two_factor_desc') ?></p>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="twoFactorEnabled" 
                                                       name="two_factor" <?= $user['two_factor_enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="twoFactorEnabled">
                                                    <?= __('enable_2fa') ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= __('data_sharing') ?></h5>
                                            <p class="card-text"><?= __('data_sharing_desc') ?></p>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="dataSharing" 
                                                       name="data_sharing" <?= $user['data_sharing'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="dataSharing">
                                                    <?= __('allow_data_sharing') ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= __('active_sessions') ?></h5>
                                            <p class="card-text"><?= __('active_sessions_desc') ?></p>
                                            <div class="list-group" id="privacySessionsList">
                                                <div class="list-group-item text-muted">Loading sessions...</div>
                                            </div>
                                            <a href="account.php#security" class="btn btn-sm btn-outline-primary mt-2">Manage in My Account</a>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" name="update_privacy" class="btn btn-primary"><?= __('save_privacy_settings') ?></button>
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
                    <h5 class="modal-title"><?= __('confirm_deletion') ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= __('delete_all_data_confirm_text') ?></p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmDelete">
                        <label class="form-check-label" for="confirmDelete"><?= __('delete_all_data_understand') ?></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="deleteDataBtn" disabled><?= __('delete_all_data') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Category Confirmation Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><?= __('confirm_category_deletion') ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= __('delete_category_confirm_text') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteCategory"><?= __('delete_transaction') ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;

        document.addEventListener('DOMContentLoaded', function() {
            function activateTab(hash) {
                if (!hash || !hash.startsWith('#')) return;
                const link = document.querySelector(`#settingsMenu a[href="${hash}"]`);
                const tabPane = document.querySelector(hash);
                if (!link || !tabPane) return;

                document.querySelectorAll('#settingsMenu .list-group-item').forEach(item => {
                    item.classList.remove('active');
                });
                link.classList.add('active');

                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                tabPane.classList.add('show', 'active');

                if (hash === '#privacy') {
                    loadPrivacySessions();
                }
            }

            document.querySelectorAll('#settingsMenu a[data-bs-toggle="tab"]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    activateTab(this.getAttribute('href'));
                    history.replaceState(null, '', this.getAttribute('href'));
                });
            });

            activateTab(window.location.hash || '#password');

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
                
                document.getElementById('deleteDataBtn').addEventListener('click', async function() {
                    const btn = this;
                    btn.disabled = true;
                    try {
                        const response = await fetch('delete_data.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': CSRF_TOKEN
                            },
                            body: JSON.stringify({ confirm: true })
                        });
                        const data = await response.json();
                        if (data.success) {
                            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                            alert(data.message || 'All data deleted.');
                            window.location.href = 'settings.php#data';
                        } else {
                            alert(data.message || 'Failed to delete data.');
                            btn.disabled = false;
                        }
                    } catch (error) {
                        alert('An error occurred while deleting data.');
                        btn.disabled = false;
                    }
                });
            }

            // Category deletion
            const deleteButtons = document.querySelectorAll('.delete-category');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const categoryId = this.getAttribute('data-id');
                    const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
                    
                    document.getElementById('confirmDeleteCategory').onclick = async function() {
                        const body = new URLSearchParams();
                        body.append('delete_category_ajax', '1');
                        body.append('id', categoryId);
                        body.append('csrf_token', CSRF_TOKEN);

                        try {
                            const response = await fetch('settings.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-CSRF-Token': CSRF_TOKEN
                                },
                                body: body.toString()
                            });
                            const data = await response.json();
                            modal.hide();
                            if (data.success) {
                                window.location.href = 'settings.php#categories';
                            } else {
                                alert(data.message || 'Failed to delete category.');
                            }
                        } catch (error) {
                            alert('An error occurred while deleting the category.');
                        }
                    };
                    
                    modal.show();
                });
            });

            // Theme preview
            const themeRadios = document.querySelectorAll('input[name="theme"]');
            themeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.documentElement.setAttribute('data-theme', this.value);
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

            document.getElementById('importForm').addEventListener('submit', function(e) {
                const fileInput = document.getElementById('importFile');
                const fileName = fileInput.value.toLowerCase();
                if (!fileName) {
                    e.preventDefault();
                    alert('Please select a file to import.');
                    fileInput.focus();
                } else if (!fileName.endsWith('.csv')) {
                    e.preventDefault();
                    alert('Only CSV files are supported for import.');
                    fileInput.focus();
                }
            });

            applyThemeFromSession();
        });

        async function loadPrivacySessions() {
            const list = document.getElementById('privacySessionsList');
            if (!list) return;

            const body = new FormData();
            body.append('action', 'list_sessions');
            body.append('csrf_token', CSRF_TOKEN);

            try {
                const response = await fetch('php/account_actions.php', { method: 'POST', body });
                const data = await response.json();
                if (!data.success || !data.sessions.length) {
                    list.innerHTML = '<div class="list-group-item text-muted">No active sessions.</div>';
                    return;
                }
                list.innerHTML = '';
                data.sessions.forEach(session => {
                    const item = document.createElement('div');
                    item.className = 'list-group-item';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${escapeHtml(session.device_name)}</strong>
                                ${session.is_current ? '<span class="badge bg-primary ms-1">Current</span>' : ''}
                                <div class="text-muted small">${escapeHtml(session.location_label)}</div>
                                <small class="text-muted">${session.is_current ? 'Now' : escapeHtml(session.last_active)}</small>
                            </div>
                        </div>`;
                    list.appendChild(item);
                });
            } catch (e) {
                list.innerHTML = '<div class="list-group-item text-danger">Could not load sessions.</div>';
            }
        }

        function applyThemeFromSession() {
            const theme = <?= json_encode($_SESSION['theme'] ?? $user['theme'] ?? 'light') ?>;
            document.documentElement.removeAttribute('data-theme');
            if (theme === 'system') {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } else if (theme !== 'light') {
                document.documentElement.setAttribute('data-theme', theme);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }
    </script>
</body>
</html>