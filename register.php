<?php
session_start();

require_once 'php/db.php';

if ($conn === null) {
    $_SESSION['register_error'] = "Database not connected.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student Budget Tracker</title>

    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/bootstrap-icons-1.13.1/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        /* ... your styles ... */
    </style>
</head>

<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center" style="height: 100vh;">

    <div class="card shadow p-4" style="width: 100%; max-width: 400px;">

        <h4 class="text-center mb-4">Create an Account</h4>

        <?php
        if (isset($_SESSION['register_error'])) {
            echo "<div class='alert alert-danger'>" . $_SESSION['register_error'] . "</div>";
            unset($_SESSION['register_error']);
        }

        if (isset($_SESSION['register_success'])) {
            echo "<div class='alert alert-success'>" . $_SESSION['register_success'] . "</div>";
            unset($_SESSION['register_success']);
        }
        ?>

        <form action="php/register.php" method="post">

            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>

                <input type="text"
                       class="form-control"
                       id="name"
                       name="name"
                       placeholder="Enter your name"
                       required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>

                <input type="email"
                       class="form-control"
                       id="email"
                       name="email"
                       placeholder="Enter email"
                       required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Create Password</label>

                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
                       placeholder="Enter password"
                       required>
            </div>

            <button type="submit" class="btn btn-success w-100 mt-2">
                Register
            </button>

        </form>

        <div class="text-center mt-3">
            <a href="index.php">Back to Login</a>
        </div>

    </div>

</div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
