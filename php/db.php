<?php
// Database configuration
$host = 'localhost';
$port = 3306;
$user = 'root';
$pass = '';
$db   = 'student_budget_management';

// Disable mysqli errors
mysqli_report(MYSQLI_REPORT_OFF);

// Create connection
$conn = @new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    $conn = null;
}
?>
