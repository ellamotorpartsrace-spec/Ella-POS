<?php
// api/pos/recover_sale_data.php
// Returns sale data formatted for the POS Cart (similar to load_draft.php)
// Used when "Recovering" a voided sale to the cart

header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$saleId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sale ID required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Fetch Sale Header
    $sqlSale = "
        SELECT 
            s.*,
            COALESCE(s.walkin_name, b.buyer_name) as buyer_name_resolved,
            COALESCE(s.buyer_shop_name, b.shop_name) as shop_name_resolved,
            COALESCE(s.buyer_address, b.address) as address_resolved,
            COALESCE(s.buyer_contact, b.contact_number) as contact_resolved
        FROM pos_sales s
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE s.sale_id = ?
    ";
    $stmt = $conn->prepare($sqlSale);
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Sale not found']);
        exit;
    }

    // 2. Fetch Items with CURRENT Stock and Tiers
    // even though it's a past sale, we need current stock to validate new sale
    $sqlItems = "
        SELECT 
            si.*,
            pv.price_retail,
            pv.price_wholesale,
            pv.price_dealer,
            (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE variation_id = si.variation_id AND store_id = 1) as current_stock
        FROM pos_sale_items si
        LEFT JOIN product_variations pv ON si.variation_id = pv.variation_id
        WHERE si.sale_id = ?
        ORDER BY si.sale_item_id ASC
    ";

    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->execute([$saleId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format Items for Cart
    $cartItems = [];
    foreach ($items as $item) {
        $cartItems[] = [
            'variation_id' => (int) $item['variation_id'],
            'name' => $item['product_name'],
            'brand' => $item['brand_name'],
            'variation' => $item['variation_name'],
            'unit_type' => $item['unit_type'],
            'barcode' => $item['barcode'],
            'price' => floatval($item['price_at_sale']), // Use the price at original sale
            'original_price' => floatval($item['original_price'] ?? $item['price_at_sale']),
            'item_discount' => floatval($item['item_discount'] ?? 0),
            'manual_discount' => floatval($item['price_at_sale']),
            'manual_discount_type' => 'custom',
            'override_tier' => null,
            'qty' => (int) $item['quantity'],
            'stock' => (int) $item['current_stock'],
            // Include tiers so price switching works
            'tiers' => [
                'retail' => floatval($item['price_retail']),
                'wholesale' => floatval($item['price_wholesale']),
                'dealer' => floatval($item['price_dealer'])
            ]
        ];
    }

    // 4. Format Buyer for POS
    $buyer = [
        'buyer_id' => $sale['buyer_id'] ? (int) $sale['buyer_id'] : null,
        'buyer_name' => $sale['buyer_name_resolved'] ?? 'Walk-in',
        'shop_name' => $sale['shop_name_resolved'] ?? '',
        'shop' => $sale['shop_name_resolved'] ?? '',
        'address' => $sale['address_resolved'] ?? '',
        'contact_number' => $sale['contact_resolved'] ?? '',
        'price_tier' => $sale['price_tier'] ?? 'retail',
        'is_walkin' => empty($sale['buyer_id'])
    ];

    echo json_encode([
        'success' => true,
        'sale_id' => $sale['sale_id'],
        'sale_ref' => $sale['sale_ref'],
        'discount_amount' => floatval($sale['discount_amount'] ?? 0),
        'buyer' => $buyer,
        'items' => $cartItems
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
