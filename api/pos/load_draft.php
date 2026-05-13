<?php
// api/pos/load_draft.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$draftId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$draftId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Draft ID required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $userId = $_SESSION['user_id'];

    // Fetch draft header
    $sql = "SELECT * FROM pos_drafts WHERE draft_id = :id AND user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $draftId, ':user_id' => $userId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Draft not found']);
        exit;
    }

    // Fetch draft items with current stock and full pricing info
    $itemSql = "SELECT 
                    di.*,
                    pv.price_retail,
                    pv.price_wholesale,
                    pv.price_dealer,
                    pv.sku,
                    (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE variation_id = di.variation_id AND store_id = 1) AS current_stock
                FROM pos_draft_items di
                LEFT JOIN product_variations pv ON di.variation_id = pv.variation_id
                WHERE di.draft_id = :draft_id
                ORDER BY di.item_id ASC";

    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->execute([':draft_id' => $draftId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format items for cart JS
    $cartItems = [];
    foreach ($items as $item) {
        $cartItems[] = [
            'variation_id' => (int) $item['variation_id'],
            'unit_id' => $item['unit_id'] ? (int) $item['unit_id'] : null,
            'multiplier' => (int) ($item['multiplier'] ?? 1) ?: 1,
            'name' => $item['product_name'],
            'brand' => $item['brand_name'],
            'variation' => $item['variation_name'],
            'unit_type' => $item['unit_type'],
            'barcode' => $item['barcode'],
            'sku' => $item['sku'] ?? '',
            'price' => floatval($item['price']), // The saved price
            'original_price' => floatval($item['original_price']),
            'item_discount' => floatval($item['item_discount'] ?? 0),
            'manual_discount' => floatval($item['manual_discount'] ?? 0),
            'manual_discount_type' => $item['manual_discount_type'] ?? 'fixed',
            'override_tier' => $item['override_tier'] ?: null,
            'qty' => (int) $item['qty'],
            'stock' => (int) $item['current_stock'],
            'stock_at_draft' => (int) $item['stock_at_draft'],
            // CRITICAL: Tiers for price recalculation when switching customer types
            'tiers' => [
                'retail' => floatval($item['price_retail'] ?? $item['price']),
                'wholesale' => floatval($item['price_wholesale'] ?? $item['price']),
                'dealer' => floatval($item['price_dealer'] ?? $item['price'])
            ]
        ];
    }

    // Format buyer info
    $buyer = [
        'buyer_id' => $draft['buyer_id'] ? (int) $draft['buyer_id'] : null,
        'buyer_name' => $draft['buyer_name'],
        'shop_name' => $draft['buyer_shop_name'],
        'shop' => $draft['buyer_shop_name'],  // Alias for JS compatibility
        'address' => $draft['buyer_address'],
        'contact_number' => $draft['buyer_contact'],
        'price_tier' => $draft['price_tier'],
        'is_walkin' => (bool) $draft['is_walkin']
    ];

    // Parse discount data
    $discountData = !empty($draft['discount_data']) ? json_decode($draft['discount_data'], true) : null;

    echo json_encode([
        'success' => true,
        'draft_id' => (int) $draft['draft_id'],
        'draft_label' => $draft['draft_label'],
        'total_amount' => floatval($draft['total_amount']),
        'created_at' => $draft['created_at'],
        'buyer' => $buyer,
        'items' => $cartItems,
        'discount_data' => $discountData
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
