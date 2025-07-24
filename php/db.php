<?php
// Database configuration
$host = 'localhost';
$port = 3306; // Change if you're using a custom port like 3309
$user = 'root';
$pass = '';
$db   = 'student_budget_management';

// Create connection
$conn = new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Optional: Set character set to UTF-8 for international support
$conn->set_charset("utf8mb4");
?>
