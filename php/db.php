<?php

// Disable MySQL fatal errors
mysqli_report(MYSQLI_REPORT_OFF);

// Database configuration
$host = "localhost";
$port = 3306;
$user = "root";
$pass = "";
$db   = "student_budget_management";

// Create connection
$conn = @new mysqli($host, $user, $pass, $db, $port);

// If database fails, stop crashing
if ($conn->connect_error) {
    $conn = null;
}

?>
