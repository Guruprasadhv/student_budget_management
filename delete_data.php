<?php
session_start();
require_once __DIR__ . '/php/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit();
}

$payload = json_decode(file_get_contents('php://input'), true);
if (empty($payload['confirm'])) {
    echo json_encode(['success' => false, 'message' => 'Confirmation required.']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("DELETE FROM transactions WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM categories WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET budget_goals = NULL WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'All financial data deleted successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to delete data.']);
}
