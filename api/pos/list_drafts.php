<?php
// api/pos/list_drafts.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $userId = $_SESSION['user_id'];

    // Fetch all drafts for current user with item count
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
                COUNT(di.item_id) AS item_count,
                SUM(
                    CASE WHEN di.variation_id IS NOT NULL AND 
                        (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE variation_id = di.variation_id AND store_id = 1) <= 0 
                    THEN 1 ELSE 0 END
                ) AS out_of_stock_count,
                SUM(
                    CASE WHEN di.variation_id IS NOT NULL AND 
                        (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE variation_id = di.variation_id AND store_id = 1) > 0 AND
                        (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE variation_id = di.variation_id AND store_id = 1) < di.qty
                    THEN 1 ELSE 0 END
                ) AS partial_stock_count
            FROM pos_drafts d
            LEFT JOIN pos_draft_items di ON d.draft_id = di.draft_id
            WHERE d.user_id = :user_id
            GROUP BY d.draft_id
            ORDER BY d.created_at DESC
            LIMIT 50";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for display
    $formatted = [];
    foreach ($drafts as $d) {
        $formatted[] = [
            'draft_id' => (int) $d['draft_id'],
            'buyer_name' => $d['buyer_name'],
            'shop_name' => $d['buyer_shop_name'],
            'price_tier' => $d['price_tier'],
            'is_walkin' => (bool) $d['is_walkin'],
            'total' => floatval($d['total_amount']),
            'label' => $d['draft_label'],
            'item_count' => (int) $d['item_count'],
            'out_of_stock_count' => (int) $d['out_of_stock_count'],
            'partial_stock_count' => (int) $d['partial_stock_count'],
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
