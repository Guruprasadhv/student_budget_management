<?php
// Simple test page to verify database connection.
// Place this file in a non-public location or remove it after testing.
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    if ($conn->connect_error) {
        echo "Connection failed: " . $conn->connect_error;
        exit(1);
    }

    // Run a safe lightweight query
    $res = $conn->query("SELECT 1 as ok");
    if ($res && $row = $res->fetch_assoc()) {
        echo "OK - connected to database and query returned: " . $row['ok'] . "\n";
        echo "Host: " . getenv('DB_HOST') . "\n";
        echo "Database: " . getenv('DB_NAME') . "\n";
    } else {
        echo "Connected but test query failed.\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

?>
