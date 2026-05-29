<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "student_budget_management"
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>