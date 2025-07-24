<?php
// Start session for CSRF protection
session_start();

// Set security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sanitize email input
$email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' https://stackpath.bootstrapcdn.com; style-src 'self' https://stackpath.bootstrapcdn.com; img-src 'self' data:;">
    <title>Reset Password - Student Budget Tracker</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .password-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">

    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card password-card p-4" style="width: 100%; max-width: 400px;">
            <div class="text-center mb-4">
                <h4>Reset Your Password</h4>
                <p class="text-muted">Enter a new password for your account</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <form id="resetForm" action="php/reset_password.php" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" 
                           placeholder="Enter new password" required minlength="8">
                    <div class="password-strength">
                        <div class="password-strength-fill" id="password-strength"></div>
                    </div>
                    <small class="form-text password-requirements">
                        Must be at least 8 characters with numbers and symbols
                    </small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" 
                           placeholder="Confirm new password" required minlength="8">
                    <small id="password-match-feedback" class="form-text"></small>
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-4" id="submit-btn">
                    Reset Password
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="login.php" class="text-primary">Back to Login</a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" integrity="sha384-LtrjvnR4Twt/qOuYxE721u19sVFLVSA4hf/rRt6PrZTmiPltdZcI7q7PXQBYTKyf" crossorigin="anonymous"></script>
    
    <script>
        $(document).ready(function() {
            // Password strength indicator
            $('#new_password').on('input', function() {
                const password = $(this).val();
                const strengthMeter = $('#password-strength');
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 1;
                // Lowercase check
                if (password.match(/[a-z]/)) strength += 1;
                // Uppercase check
                if (password.match(/[A-Z]/)) strength += 1;
                // Number check
                if (password.match(/[0-9]/)) strength += 1;
                // Special char check
                if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
                
                // Update meter
                const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#218838'];
                const width = (strength / 5) * 100;
                strengthMeter.css('width', width + '%');
                strengthMeter.css('background-color', colors[strength - 1] || '#e9ecef');
            });

            // Password match checker
            $('#confirm_password').on('input', function() {
                const password = $('#new_password').val();
                const confirmPassword = $(this).val();
                const feedback = $('#password-match-feedback');
                
                if (confirmPassword === '') {
                    feedback.text('').removeClass('text-danger text-success');
                } else if (password === confirmPassword) {
                    feedback.text('Passwords match').removeClass('text-danger').addClass('text-success');
                } else {
                    feedback.text('Passwords do not match').removeClass('text-success').addClass('text-danger');
                }
            });

            // Form validation
            $('#resetForm').on('submit', function(e) {
                const password = $('#new_password').val();
                const confirmPassword = $('#confirm_password').val();
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>