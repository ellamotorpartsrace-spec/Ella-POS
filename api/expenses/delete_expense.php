<?php
// api/expenses/delete_expense.php

header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/logger.php';

requireLogin();

// Only admin/manager can delete
if (!hasPermission('manage_settings') && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int) $data['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid expense ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT amount, category FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit;
    }

    $delStmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $result = $delStmt->execute([$id]);

    if ($result) {
        logActivity($conn, $_SESSION['user_id'], 'EXPENSE_DELETED', 'EXPENSE', "Deleted expense record #$id: ₱" . number_format($expense['amount'], 2) . " (" . $expense['category'] . ")", $id);
        echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete expense']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
