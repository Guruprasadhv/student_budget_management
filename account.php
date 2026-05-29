<?php
session_start();
include('php/db.php');
require_once __DIR__ . '/php/languages.php';
require_once __DIR__ . '/php/account_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_no_pending_2fa();

$user_id = (int)$_SESSION['user_id'];
ensure_account_tables($conn);
touch_user_session($conn, $user_id, session_id());

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$stmt = $conn->prepare("SELECT name, email, created_at, two_factor_enabled, password_updated_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "<div class='alert alert-danger'>User not found. Please log in again.</div>";
    exit();
}

$passwordChangedAt = !empty($user['password_updated_at']) ? $user['password_updated_at'] : $user['created_at'];
$twoFactorEnabled = !empty($user['two_factor_enabled']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $student_id = $_POST['student_id'];
        
        $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $name, $email, $user_id);
        $update_stmt->execute();
        
        $_SESSION['user_name'] = $name;
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: account.php");
        exit();
    }
    
    if (isset($_POST['update_preferences'])) {
        // Handle preferences update
        $theme = $_POST['theme'];
        $_SESSION['theme'] = $theme;
        $_SESSION['currency'] = $_POST['currency'];
        $_SESSION['language'] = $_POST['language'];
        
        // Persist theme to database
        $theme_stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
        $theme_stmt->bind_param("si", $theme, $user_id);
        $theme_stmt->execute();
        $theme_stmt->close();
        
        $_SESSION['success_message'] = "Preferences updated successfully!";
        header("Location: account.php#preferences");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_SESSION['theme']) ? htmlspecialchars($_SESSION['theme']) : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_account') ?> - <?= __('student_budget_tracker') ?></title>
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
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <div class="row">
            <!-- Account Sidebar -->
            <div class="col-md-3 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= __('account_menu') ?></h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                            <i class="bi bi-person me-2"></i><?= __('profile') ?>
                        </a>
                        <a href="#security" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-shield-lock me-2"></i><?= __('security') ?>
                        </a>
                        <a href="#connected" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-wallet2 me-2"></i><?= __('connected_accounts') ?>
                        </a>
                        <a href="#preferences" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-sliders me-2"></i><?= __('preferences') ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Account Content -->
            <div class="col-md-9">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><?= __('my_account') ?></h4>
                            <span class="badge bg-light text-primary"><?= __('student_plan') ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Profile Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <div class="text-center mb-4">
                                    <?php 
                                    $name_parts = explode(' ', $user['name']);
                                    $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                    ?>
                                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                                         style="width: 100px; height: 100px; font-size: 2rem;">
                                         <?= $initials ?>
                                     </div>
                                    <h4 class="mt-3"><?= htmlspecialchars(__($user['name'] ?: 'User')) ?></h4>
                                    <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
                                    <button class="btn btn-outline-primary btn-sm"><?= __('change_photo') ?></button>
                                </div>

                                <form method="POST" action="account.php">
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label"><?= __('full_name') ?></label>
                                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('phone_number') ?></label>
                                        <input type="tel" class="form-control" name="phone" placeholder="Enter phone number">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('student_id') ?></label>
                                        <input type="text" class="form-control" name="student_id" placeholder="Enter student ID">
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary"><?= __('update_profile') ?></button>
                                </form>
                            </div>

                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <h5 class="mb-4"><?= __('security_settings') ?></h5>
                                
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h6><?= __('password') ?></h6>
                                        <p class="mb-3 text-muted"><?= __('last_changed') ?> <?= date('d/m/Y', strtotime($passwordChangedAt)) ?></p>
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                            <?= __('change_password') ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h6><?= __('two_factor_auth') ?></h6>
                                        <p id="twoFactorStatus" class="<?= $twoFactorEnabled ? 'text-success' : 'text-danger' ?>">
                                            <?= $twoFactorEnabled ? 'Enabled' : __('not_enabled') ?>
                                        </p>
                                        <p class="text-muted small"><?= __('two_factor_desc') ?></p>
                                        <button type="button" class="btn btn-outline-primary" id="toggle2faBtn" data-enabled="<?= $twoFactorEnabled ? '1' : '0' ?>">
                                            <?= $twoFactorEnabled ? 'Disable 2FA' : __('enable_2fa') ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-body">
                                        <h6><?= __('active_sessions') ?></h6>
                                        <p class="text-muted small"><?= __('active_sessions_desc') ?></p>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle">
                                                <thead>
                                                    <tr>
                                                        <th><?= __('device') ?></th>
                                                        <th><?= __('location') ?></th>
                                                        <th><?= __('last_active') ?></th>
                                                        <th><?= __('action') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="sessionsTable">
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted py-3">Loading sessions...</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="button" class="btn btn-danger mt-2" id="logoutAllDevices"><?= __('logout_all_devices') ?></button>
                                    </div>
                                </div>
                            </div>

                            <!-- Connected Accounts Tab -->
                            <div class="tab-pane fade" id="connected" role="tabpanel">
                                <h5 class="mb-4"><?= __('connected_accounts') ?></h5>

                                <div id="connectedAccountsList"></div>
                                <div id="noConnectedAccounts" class="alert alert-info d-none">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <?= __('no_connected_accounts') ?>
                                </div>

                                <h5 class="mt-4"><?= __('add_new_account') ?></h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary connect-account-btn" data-provider="bank">
                                        <i class="bi bi-bank me-2"></i><?= __('connect_bank') ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary connect-account-btn" data-provider="paypal">
                                        <i class="bi bi-paypal me-2"></i><?= __('connect_paypal') ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary connect-account-btn" data-provider="card">
                                        <i class="bi bi-credit-card me-2"></i><?= __('connect_card') ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Preferences Tab -->
                            <div class="tab-pane fade" id="preferences" role="tabpanel">
                                <h5 class="mb-4"><?= __('preferences') ?></h5>
                                
                                <form id="preferencesForm" method="POST" action="account.php#preferences">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('preferred_currency') ?></label>
                                        <select class="form-select" name="currency">
                                            <option value="INR" <?= (isset($_SESSION['currency']) && $_SESSION['currency'] === 'INR') ? 'selected' : '' ?>>Indian Rupee (₹)</option>
                                            <option value="USD" <?= (isset($_SESSION['currency']) && $_SESSION['currency'] === 'USD') ? 'selected' : '' ?>>US Dollar ($)</option>
                                            <option value="EUR" <?= (isset($_SESSION['currency']) && $_SESSION['currency'] === 'EUR') ? 'selected' : '' ?>>Euro (€)</option>
                                            <option value="GBP" <?= (isset($_SESSION['currency']) && $_SESSION['currency'] === 'GBP') ? 'selected' : '' ?>>British Pound (£)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?= __('language') ?></label>
                                        <select class="form-select" name="language">
                                            <option value="en" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'en') ? 'selected' : '' ?>>English</option>
                                            <option value="hi" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'hi') ? 'selected' : '' ?>>हिन्दी (Hindi)</option>
                                            <option value="kn" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'kn') ? 'selected' : '' ?>>ಕನ್ನಡ (Kannada)</option>
                                            <option value="te" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'te') ? 'selected' : '' ?>>తెలుగు (Telugu)</option>
                                            <option value="ta" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'ta') ? 'selected' : '' ?>>தமிழ் (Tamil)</option>
                                            <option value="ml" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'ml') ? 'selected' : '' ?>>മലയാളം (Malayalam)</option>
                                            <option value="mr" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'mr') ? 'selected' : '' ?>>मराठी (Marathi)</option>
                                            <option value="gu" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'gu') ? 'selected' : '' ?>>ગુજરાતી (Gujarati)</option>
                                            <option value="bn" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'bn') ? 'selected' : '' ?>>বাংলা (Bengali)</option>
                                            <option value="es" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'es') ? 'selected' : '' ?>>Español (Spanish)</option>
                                            <option value="fr" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'fr') ? 'selected' : '' ?>>Français (French)</option>
                                            <option value="de" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'de') ? 'selected' : '' ?>>Deutsch (German)</option>
                                            <option value="ar" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'ar') ? 'selected' : '' ?>>العربية (Arabic)</option>
                                            <option value="th" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'th') ? 'selected' : '' ?>>ไทย (Thai)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?= __('theme') ?></label>
                                        <select class="form-select" name="theme" id="themeSelect">
                                            <option value="system" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'system') ? 'selected' : '' ?>><?= __('system_default') ?></option>
                                            <option value="light" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'light') ? 'selected' : '' ?>><?= __('light_mode') ?></option>
                                            <option value="dark" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') ? 'selected' : '' ?>><?= __('dark_mode') ?></option>
                                            <option value="read" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'read') ? 'selected' : '' ?>><?= __('read_mode') ?></option>
                                        </select>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="emailNotifications" name="emailNotifications" checked>
                                        <label class="form-check-label" for="emailNotifications"><?= __('email_notifications') ?></label>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="pushNotifications" name="pushNotifications">
                                        <label class="form-check-label" for="pushNotifications"><?= __('push_notifications') ?></label>
                                    </div>

                                    <button type="submit" name="update_preferences" class="btn btn-primary"><?= __('save_preferences') ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('change_password') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="mb-3">
                            <label class="form-label"><?= __('current_password') ?></label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('new_password') ?></label>
                            <input type="password" class="form-control" name="new_password" minlength="8" required>
                            <div class="form-text"><?= __('password_length_hint') ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('confirm_new_password') ?></label>
                            <input type="password" class="form-control" name="confirm_password" minlength="8" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-primary" id="savePasswordBtn"><?= __('change_password') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Two-Factor Modal -->
    <div class="modal fade" id="twoFactorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="twoFactorModalTitle"><?= __('enable_2fa') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="twoFactorForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="enable" id="twoFactorEnable" value="1">
                        <p id="twoFactorModalDesc" class="text-muted small"><?= __('two_factor_desc') ?></p>
                        <div class="mb-3" id="twoFactorPinGroup">
                            <label class="form-label">6-digit Security PIN</label>
                            <input type="password" class="form-control" name="pin" id="twoFactorPin" maxlength="6" pattern="\d{6}" inputmode="numeric" placeholder="000000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('current_password') ?></label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-primary" id="saveTwoFactorBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Connect Account Modal -->
    <div class="modal fade" id="connectAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="connectAccountModalTitle"><?= __('add_new_account') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="connectAccountForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="provider" id="connectProvider" value="">
                        <div class="mb-3">
                            <label class="form-label">Account name</label>
                            <input type="text" class="form-control" name="account_name" id="connectAccountName" required placeholder="e.g. HDFC Savings">
                        </div>
                        <div class="mb-3" id="connectIdentifierGroup">
                            <label class="form-label" id="connectIdentifierLabel">Account / Email</label>
                            <input type="text" class="form-control" name="account_identifier" id="connectAccountIdentifier" placeholder="Optional">
                        </div>
                        <div class="mb-3" id="connectLastFourGroup">
                            <label class="form-label">Last 4 digits</label>
                            <input type="text" class="form-control" name="last_four" id="connectLastFour" maxlength="4" pattern="\d{4}" inputmode="numeric" placeholder="1234">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-primary" id="saveConnectAccountBtn">Connect</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutConfirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('confirm_logout') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= __('confirm_logout_all_text') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmLogoutAll"><?= __('logout_all_devices') ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = '<?= isset($_SESSION['theme']) ? $_SESSION['theme'] : 'system' ?>';
            applyTheme(savedTheme);

            document.getElementById('themeSelect').addEventListener('change', function() {
                applyTheme(this.value);
            });

            const languageSelect = document.querySelector('select[name="language"]');
            if (languageSelect) {
                languageSelect.addEventListener('change', function() {
                    const form = document.getElementById('preferencesForm');
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'update_preferences';
                    hiddenInput.value = '1';
                    form.appendChild(hiddenInput);
                    form.submit();
                });
            }

            loadSessions();
            loadConnectedAccounts();

            document.querySelector('a[href="#security"]').addEventListener('shown.bs.tab', loadSessions);
            document.querySelector('a[href="#connected"]').addEventListener('shown.bs.tab', loadConnectedAccounts);

            document.getElementById('savePasswordBtn').addEventListener('click', savePassword);
            document.getElementById('toggle2faBtn').addEventListener('click', openTwoFactorModal);
            document.getElementById('saveTwoFactorBtn').addEventListener('click', saveTwoFactor);

            document.querySelectorAll('.connect-account-btn').forEach(btn => {
                btn.addEventListener('click', () => openConnectModal(btn.dataset.provider));
            });
            document.getElementById('saveConnectAccountBtn').addEventListener('click', saveConnectedAccount);

            document.getElementById('logoutAllDevices').addEventListener('click', function() {
                new bootstrap.Modal(document.getElementById('logoutConfirmationModal')).show();
            });

            document.getElementById('confirmLogoutAll').addEventListener('click', async function() {
                const data = await postAction('revoke_all_sessions', {});
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('logoutConfirmationModal')).hide();
                    showAlert('success', data.message);
                    loadSessions();
                } else {
                    showAlert('danger', data.message);
                }
            });

            if (window.location.hash === '#security') {
                document.querySelector('a[href="#security"]').click();
            } else if (window.location.hash === '#connected') {
                document.querySelector('a[href="#connected"]').click();
            }
        });

        async function postAction(action, fields) {
            const body = new FormData();
            body.append('action', action);
            body.append('csrf_token', CSRF_TOKEN);
            Object.entries(fields).forEach(([key, value]) => body.append(key, value));

            const response = await fetch('php/account_actions.php', { method: 'POST', body });
            return response.json();
        }

        async function loadSessions() {
            const tbody = document.getElementById('sessionsTable');
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>';

            const data = await postAction('list_sessions', {});
            if (!data.success || !data.sessions.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No active sessions found.</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            data.sessions.forEach(session => {
                const tr = document.createElement('tr');
                const lastActive = session.is_current ? '<?= __('now') ?>' : formatDateTime(session.last_active);
                tr.innerHTML = `
                    <td><i class="bi bi-laptop me-2"></i>${escapeHtml(session.device_name)}${session.is_current ? ' <span class="badge bg-success ms-1"><?= __('current_session') ?></span>' : ''}</td>
                    <td>${escapeHtml(session.location_label)}</td>
                    <td>${lastActive}</td>
                    <td>${session.is_current ? '<span class="text-muted small">This device</span>' : `<button type="button" class="btn btn-sm btn-outline-danger logout-device" data-session="${escapeHtml(session.session_id)}"><?= __('logout_device') ?></button>`}</td>
                `;
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.logout-device').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const sessionId = this.dataset.session;
                    const result = await postAction('revoke_session', { session_id: sessionId });
                    if (result.success) {
                        showAlert('success', result.message);
                        if (result.is_current) {
                            setTimeout(() => { window.location.href = 'logout.php'; }, 1000);
                        } else {
                            loadSessions();
                        }
                    } else {
                        showAlert('danger', result.message);
                    }
                });
            });
        }

        async function loadConnectedAccounts() {
            const list = document.getElementById('connectedAccountsList');
            const empty = document.getElementById('noConnectedAccounts');
            list.innerHTML = '';

            const data = await postAction('list_connected', {});
            if (!data.success || !data.accounts.length) {
                empty.classList.remove('d-none');
                return;
            }

            empty.classList.add('d-none');
            data.accounts.forEach(account => {
                const icon = account.provider === 'bank' ? 'bi-bank' : (account.provider === 'paypal' ? 'bi-paypal' : 'bi-credit-card');
                const detail = account.last_four ? `**** ${account.last_four}` : (account.account_identifier || '');
                const card = document.createElement('div');
                card.className = 'card mb-2';
                card.innerHTML = `
                    <div class="card-body d-flex justify-content-between align-items-center py-3">
                        <div>
                            <h6 class="mb-1"><i class="bi ${icon} me-2"></i>${escapeHtml(account.account_name)}</h6>
                            <small class="text-muted">${escapeHtml(account.provider.toUpperCase())}${detail ? ' · ' + escapeHtml(detail) : ''} · Connected ${formatDate(account.connected_at)}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger disconnect-account" data-id="${account.id}">Disconnect</button>
                    </div>
                `;
                list.appendChild(card);
            });

            list.querySelectorAll('.disconnect-account').forEach(btn => {
                btn.addEventListener('click', async function() {
                    if (!confirm('Disconnect this account?')) return;
                    const result = await postAction('disconnect_account', { account_id: this.dataset.id });
                    showAlert(result.success ? 'success' : 'danger', result.message);
                    if (result.success) loadConnectedAccounts();
                });
            });
        }

        async function savePassword() {
            const form = document.getElementById('changePasswordForm');
            const data = await postAction('change_password', {
                current_password: form.current_password.value,
                new_password: form.new_password.value,
                confirm_password: form.confirm_password.value
            });
            showAlert(data.success ? 'success' : 'danger', data.message);
            if (data.success) {
                form.reset();
                bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
            }
        }

        function openTwoFactorModal() {
            const enabling = document.getElementById('toggle2faBtn').dataset.enabled !== '1';
            document.getElementById('twoFactorEnable').value = enabling ? '1' : '0';
            document.getElementById('twoFactorModalTitle').textContent = enabling ? '<?= __('enable_2fa') ?>' : 'Disable 2FA';
            document.getElementById('twoFactorPinGroup').style.display = enabling ? 'block' : 'none';
            document.getElementById('twoFactorPin').required = enabling;
            document.getElementById('twoFactorForm').reset();
            new bootstrap.Modal(document.getElementById('twoFactorModal')).show();
        }

        async function saveTwoFactor() {
            const form = document.getElementById('twoFactorForm');
            const data = await postAction('toggle_2fa', {
                enable: form.querySelector('#twoFactorEnable').value,
                pin: form.pin.value,
                current_password: form.current_password.value
            });
            showAlert(data.success ? 'success' : 'danger', data.message);
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('twoFactorModal')).hide();
                setTimeout(() => location.reload(), 800);
            }
        }

        function openConnectModal(provider) {
            document.getElementById('connectProvider').value = provider;
            document.getElementById('connectAccountForm').reset();
            const titles = { bank: '<?= __('connect_bank') ?>', paypal: '<?= __('connect_paypal') ?>', card: '<?= __('connect_card') ?>' };
            document.getElementById('connectAccountModalTitle').textContent = titles[provider] || '<?= __('add_new_account') ?>';

            const idLabel = document.getElementById('connectIdentifierLabel');
            const lastFourGroup = document.getElementById('connectLastFourGroup');
            if (provider === 'paypal') {
                idLabel.textContent = 'PayPal email';
                lastFourGroup.style.display = 'none';
            } else if (provider === 'card') {
                idLabel.textContent = 'Card nickname';
                lastFourGroup.style.display = 'block';
            } else {
                idLabel.textContent = 'Bank account number (optional)';
                lastFourGroup.style.display = 'block';
            }
            new bootstrap.Modal(document.getElementById('connectAccountModal')).show();
        }

        async function saveConnectedAccount() {
            const form = document.getElementById('connectAccountForm');
            const data = await postAction('connect_account', {
                provider: form.provider.value,
                account_name: form.account_name.value,
                account_identifier: form.account_identifier.value,
                last_four: form.last_four.value
            });
            showAlert(data.success ? 'success' : 'danger', data.message);
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('connectAccountModal')).hide();
                loadConnectedAccounts();
            }
        }

        function formatDateTime(value) {
            const d = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return value;
            const pad = n => String(n).padStart(2, '0');
            return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }

        function formatDate(value) {
            const d = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return value;
            const pad = n => String(n).padStart(2, '0');
            return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }
        
        function applyTheme(theme) {
            // Remove all theme attributes first
            document.documentElement.removeAttribute('data-theme');
            
            if (theme === 'system') {
                // Use system preference
                const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (systemPrefersDark) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } else if (theme !== 'light') {
                // Apply selected theme (dark or read)
                document.documentElement.setAttribute('data-theme', theme);
            }
        }
        
        function showAlert(type, message) {
            // Remove any existing alerts
            const existingAlert = document.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            // Create alert element
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            alert.style.zIndex = '1100';
            alert.role = 'alert';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Add to body
            document.body.appendChild(alert);
            
            // Auto dismiss after 3 seconds
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 3000);
        }
    </script>
</body>
</html>