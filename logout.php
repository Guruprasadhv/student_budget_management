<?php
session_start();
require_once __DIR__ . '/php/languages.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('logout') ?> - <?= __('student_budget_tracker') ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/bootstrap-icons-1.13.1/bootstrap-icons.css">
    <style>
        .logout-container {
            max-width: 500px;
            margin: 0 auto;
            padding-top: 100px;
        }
    </style>
</head>
<body>
    <div class="container logout-container">
        <div class="card shadow">
            <div class="card-body text-center p-5">
                <div class="mb-4">
                    <i class="bi bi-box-arrow-right text-primary" style="font-size: 3rem;"></i>
                </div>
                <h3 class="mb-3"><?= __('ready_to_leave') ?></h3>
                <p class="text-muted mb-4">
                    <?= __('logout_confirm') ?><br>
                    <?= __('logout_back') ?>
                </p>
                
                <div class="d-grid gap-3">
                    <button id="logoutBtn" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-2"></i><?= __('yes_logout') ?>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i><?= __('cancel') ?>
                    </a>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <p class="text-muted small">
                        <?= __('having_trouble') ?> <a href="contact.php" target="_blank"><?= __('contact_support') ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert CSS -->
    <link rel="stylesheet" href="assets/sweetalert2/sweetalert2.min.css">

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/sweetalert2/sweetalert2.all.min.js"></script>
    
    <script>
        document.getElementById('logoutBtn').addEventListener('click', function() {
            Swal.fire({
                title: 'Logging Out',
                text: 'You have been successfully logged out.',
                icon: 'success',
                confirmButtonText: 'OK',
                allowOutsideClick: false,
                timer: 3000,
                timerProgressBar: true,
                willClose: () => {
                    // Call backend logout script after alert is closed
                    window.location.href = 'php/logout.php';
                }
            }).then(() => {
                // Alternative redirect
                window.location.href = 'php/logout.php';
            });
        });
    </script>
</body>
</html> 