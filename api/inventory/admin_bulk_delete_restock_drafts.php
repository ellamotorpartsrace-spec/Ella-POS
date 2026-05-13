<?php
// api/inventory/admin_bulk_delete_restock_drafts.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Must be admin
requireLogin();
if (!hasPermission('manage_settings') && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ids = isset($data['ids']) ? $data['ids'] : [];

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No draft IDs provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Convert to placeholders
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    $stmt = $conn->prepare("DELETE FROM restock_drafts WHERE draft_id IN ($placeholders)");
    $stmt->execute($ids);

    echo json_encode([
        'success' => true,
        'message' => count($ids) . ' restock drafts deleted successfully'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
