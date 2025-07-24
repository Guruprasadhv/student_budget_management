<?php
session_start();
// Destroy session and log out user immediately
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Student Budget Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
                <h3 class="mb-3">Ready to Leave?</h3>
                <p class="text-muted mb-4">
                    Are you sure you want to logout from your account?<br>
                    You can always log back in later.
                </p>
                
                <div class="d-grid gap-3">
                    <button id="logoutBtn" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-2"></i>Yes, Logout
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <p class="text-muted small">
                        Having trouble? <a href="contact.html">Contact support</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
                    // Redirect to login page after alert is closed
                    window.location.href = 'index.php';
                }
            }).then(() => {
                // Alternative redirect if user clicks OK
                window.location.href = 'index.php';
            });
        });
    </script>
</body>
</html> 