<?php
// api/expenses/bulk_delete.php

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
$ids = $data['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'No IDs provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Begin transaction for safety
    $conn->beginTransaction();

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id IN ($placeholders)");
    $result = $stmt->execute($ids);

    if ($result) {
        $conn->commit();
        logActivity($conn, $_SESSION['user_id'], 'EXPENSE_BULK_DELETED', 'EXPENSE', "Bulk deleted " . count($ids) . " expense records", 0);
        echo json_encode(['success' => true, 'message' => count($ids) . ' expenses deleted successfully']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete expenses']);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
