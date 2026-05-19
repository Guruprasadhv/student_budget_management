<?php

mysqli_report(MYSQLI_REPORT_OFF);

// Railway MySQL credentials
$host = "YOUR_HOST";
$port = YOUR_PORT;
$user = "YOUR_USER";
$pass = "YOUR_PASSWORD";
$db   = "YOUR_DATABASE";

// Create connection
$conn = new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

?>
