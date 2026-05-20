<?php

mysqli_report(MYSQLI_REPORT_OFF);

// InfinityFree database details
$host = "sql308.infinityfree.com";
$port = 3306;

$user = "if0_41968547";
$pass = "YOUR_DATABASE_PASSWORD";

$db = "if0_41968547_studentbudget";

// Create connection
$conn = new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>
