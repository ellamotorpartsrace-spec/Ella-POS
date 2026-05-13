<?php
// api/pos/admin_list_drafts.php
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

    // Fetch all drafts with item counts and creator username
    $sql = "SELECT 
                d.draft_id,
                d.buyer_name,
                d.buyer_shop_name,
                d.price_tier,
                d.is_walkin,
                d.total_amount,
                d.draft_label,
                d.created_at,
                d.updated_at,
                u.username AS creator_name,
                COUNT(di.item_id) AS item_count
            FROM pos_drafts d
            LEFT JOIN users u ON d.user_id = u.id
            LEFT JOIN pos_draft_items di ON d.draft_id = di.draft_id
            GROUP BY d.draft_id
            ORDER BY d.created_at DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for display
    $formatted = [];
    foreach ($drafts as $d) {
        $formatted[] = [
            'draft_id' => (int) $d['draft_id'],
            'creator_name' => $d['creator_name'] ?? 'Unknown User',
            'buyer_name' => $d['buyer_name'],
            'shop_name' => $d['buyer_shop_name'],
            'price_tier' => $d['price_tier'],
            'is_walkin' => (bool) $d['is_walkin'],
            'total' => floatval($d['total_amount']),
            'label' => $d['draft_label'],
            'item_count' => (int) $d['item_count'],
            'created_at' => $d['created_at'],
            'updated_at' => $d['updated_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'drafts' => $formatted
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
