<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center" style="height: 100vh;">
        <div class="card shadow p-4" style="max-width: 400px; width: 100%;">
            <h4 class="mb-4 text-center">Forgot Password</h4>
            <form action="php/forgot_password.php" method="post">
                <div class="mb-3">
                    <label for="email" class="form-label">Registered Email</label>
                    <input type="email" class="form-control" name="email" id="email" placeholder="Enter your email" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
            </form>
            <div class="text-center mt-3">
                <a href="index.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
