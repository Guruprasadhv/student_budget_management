<?php

$host = "sql308.infinityfree.com";
$user = "if0_41968547";
$pass = "101NaIiF6b";
$db   = "if0_41968547_student_budget";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>