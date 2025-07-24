<?php
session_start();
include('php/db.php'); // Adjust if db.php is in another folder

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    echo "<div class='alert alert-danger'>User not found. Please log in again.</div>";
    exit();
}

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
        
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: account.php");
        exit();
    }
    
    if (isset($_POST['update_preferences'])) {
        // Handle preferences update
        $_SESSION['theme'] = $_POST['theme'];
        $_SESSION['currency'] = $_POST['currency'];
        $_SESSION['language'] = $_POST['language'];
        
        $_SESSION['success_message'] = "Preferences updated successfully!";
        header("Location: account.php#preferences");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Student Budget Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Default light theme */
        :root {
            --primary-bg-color: #f8f9fa;
            --secondary-bg-color: #ffffff;
            --text-color: #212529;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
        }
        
        /* Dark theme */
        [data-theme="dark"] {
            --primary-bg-color: #212529;
            --secondary-bg-color: #343a40;
            --text-color: #f8f9fa;
            --card-bg: #343a40;
            --border-color: #495057;
        }
        
        /* Read theme */
        [data-theme="read"] {
            --primary-bg-color: #f8f5f2;
            --secondary-bg-color: #f1e9e0;
            --text-color: #3a3a3a;
            --card-bg: #f1e9e0;
            --border-color: #d9d1c7;
        }
        
        body {
            background-color: var(--primary-bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .card, .list-group-item, .modal-content, .table {
            background-color: var(--card-bg);
            color: var(--text-color);
            border-color: var(--border-color);
        }
        
        .table th, .table td {
            border-color: var(--border-color);
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .form-control, .form-select {
            background-color: var(--secondary-bg-color);
            color: var(--text-color);
            border-color: var(--border-color);
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--secondary-bg-color);
            color: var(--text-color);
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
                        <a class="nav-link" href="history.php">History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="account.php">My Account</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Settings</a>
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
                        <h5 class="mb-0">Account Menu</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                            <i class="bi bi-person me-2"></i>Profile
                        </a>
                        <a href="#security" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-shield-lock me-2"></i>Security
                        </a>
                        <a href="#connected" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-wallet2 me-2"></i>Connected Accounts
                        </a>
                        <a href="#preferences" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="bi bi-sliders me-2"></i>Preferences
                        </a>
                    </div>
                </div>
            </div>

            <!-- Account Content -->
            <div class="col-md-9">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">My Account</h4>
                            <span class="badge bg-light text-primary">Student Plan</span>
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
                                    <h4 class="mt-3"><?= htmlspecialchars($user['name']) ?></h4>
                                    <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
                                    <button class="btn btn-outline-primary btn-sm">Change Photo</button>
                                </div>

                                <form method="POST" action="account.php">
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" placeholder="Enter phone number">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Student ID</label>
                                        <input type="text" class="form-control" name="student_id" placeholder="Enter student ID">
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>

                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <h5 class="mb-4">Security Settings</h5>
                                
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h6>Password</h6>
                                        <p>Last changed <?= date('F j, Y', strtotime($user['created_at'])) ?></p>
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                            Change Password
                                        </button>
                                    </div>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h6>Two-Factor Authentication</h6>
                                        <p class="text-danger">Not enabled</p>
                                        <button class="btn btn-outline-primary">Enable 2FA</button>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-body">
                                        <h6>Active Sessions</h6>
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Device</th>
                                                        <th>Location</th>
                                                        <th>Last Active</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="sessionsTable">
                                                    <tr>
                                                        <td>
                                                            <i class="bi bi-laptop me-2"></i>
                                                            Windows Chrome
                                                        </td>
                                                        <td>New York, US</td>
                                                        <td>Now</td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-danger logout-device">Logout</button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button class="btn btn-danger mt-2" id="logoutAllDevices">Logout All Devices</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Connected Accounts Tab -->
                            <div class="tab-pane fade" id="connected" role="tabpanel">
                                <h5 class="mb-4">Connected Accounts</h5>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No connected accounts yet. Connect your bank or payment methods to get started.
                                </div>

                                <h5 class="mt-4">Add New Account</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-primary">
                                        <i class="bi bi-bank me-2"></i>Connect Bank
                                    </button>
                                    <button class="btn btn-outline-primary">
                                        <i class="bi bi-paypal me-2"></i>Connect PayPal
                                    </button>
                                    <button class="btn btn-outline-primary">
                                        <i class="bi bi-credit-card me-2"></i>Connect Card
                                    </button>
                                </div>
                            </div>

                            <!-- Preferences Tab -->
                            <div class="tab-pane fade" id="preferences" role="tabpanel">
                                <h5 class="mb-4">Preferences</h5>
                                
                                <form id="preferencesForm" method="POST" action="account.php#preferences">
                                    <div class="mb-3">
                                        <label class="form-label">Preferred Currency</label>
                                        <select class="form-select" name="currency">
                                            <option value="INR" <?= (isset($_SESSION['currency']) && $_SESSION['currency'] === 'INR') ? 'selected' : '' ?>>Indian Rupee (₹)</option>
                                            <option value="USD" <?= (isset($_SESSION['currency']) && $_SESSION['currency'] === 'USD') ? 'selected' : '' ?>>US Dollar ($)</option>
                                            <option value="EUR" <?= (isset($_SESSION['currency']) && $_SESSION['currency'] === 'EUR') ? 'selected' : '' ?>>Euro (€)</option>
                                            <option value="GBP" <?= (isset($_SESSION['currency']) && $_SESSION['currency'] === 'GBP') ? 'selected' : '' ?>>British Pound (£)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Language</label>
                                        <select class="form-select" name="language">
                                            <option value="en" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'en') ? 'selected' : '' ?>>English</option>
                                            <option value="hi" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'hi') ? 'selected' : '' ?>>Hindi</option>
                                            <option value="es" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'es') ? 'selected' : '' ?>>Spanish</option>
                                            <option value="fr" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'fr') ? 'selected' : '' ?>>French</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Theme</label>
                                        <select class="form-select" name="theme" id="themeSelect">
                                            <option value="system" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'system') ? 'selected' : '' ?>>System Default</option>
                                            <option value="light" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'light') ? 'selected' : '' ?>>Light Mode</option>
                                            <option value="dark" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') ? 'selected' : '' ?>>Dark Mode</option>
                                            <option value="read" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'read') ? 'selected' : '' ?>>Read Mode</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="emailNotifications" name="emailNotifications" checked>
                                        <label class="form-check-label" for="emailNotifications">Email Notifications</label>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="pushNotifications" name="pushNotifications">
                                        <label class="form-check-label" for="pushNotifications">Push Notifications</label>
                                    </div>

                                    <button type="submit" name="update_preferences" class="btn btn-primary">Save Preferences</button>
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
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="change-password.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutConfirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to log out all devices? You will need to log in again on this device.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmLogoutAll">Logout All Devices</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply saved theme
            const savedTheme = '<?= isset($_SESSION['theme']) ? $_SESSION['theme'] : 'system' ?>';
            applyTheme(savedTheme);
            
            // Theme selector change handler
            document.getElementById('themeSelect').addEventListener('change', function() {
                applyTheme(this.value);
            });
            
            // Logout single device handler
            document.querySelectorAll('.logout-device').forEach(button => {
                button.addEventListener('click', function() {
                    const row = this.closest('tr');
                    row.remove();
                    showAlert('success', 'Device logged out successfully');
                    
                    // If this was the current device, redirect to login
                    if (row.querySelector('td:nth-child(3)').textContent === 'Now') {
                        setTimeout(() => {
                            window.location.href = 'logout.php';
                        }, 1500);
                    }
                });
            });
            
            // Logout all devices handler
            document.getElementById('logoutAllDevices').addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('logoutConfirmationModal'));
                modal.show();
            });
            
            // Confirm logout all devices
            document.getElementById('confirmLogoutAll').addEventListener('click', function() {
                // Clear all sessions from the table
                document.getElementById('sessionsTable').innerHTML = '';
                
                // Show success message
                showAlert('success', 'All devices have been logged out');
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('logoutConfirmationModal'));
                modal.hide();
                
                // Redirect to logout page after a delay
                setTimeout(() => {
                    window.location.href = 'logout.php';
                }, 1500);
            });
        });
        
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