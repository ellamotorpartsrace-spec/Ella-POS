<?php
// api/inventory/admin_list_restock_drafts.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Must be logged in and admin
requireLogin();
if (!hasPermission('manage_settings') && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
    exit;
}

// Release session lock to allow concurrent requests
session_write_close();

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "SELECT 
                d.draft_id,
                d.draft_label,
                d.total_amount,
                d.created_at,
                d.supplier_name,
                d.items,
                u.username AS creator_name
            FROM restock_drafts d
            LEFT JOIN users u ON d.user_id = u.id
            ORDER BY d.created_at DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($drafts as &$draft) {
        $items = json_decode($draft['items'], true);
        $draft['item_count'] = is_array($items) ? count($items) : 0;
        // Keep the items for previewing if needed, or we can fetch separately. 
        // For restock, they are already in the main table so we can just decode them.
    }

    echo json_encode([
        'success' => true,
        'drafts' => $drafts
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
