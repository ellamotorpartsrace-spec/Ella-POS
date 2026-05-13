<?php
// api/pos/save_draft.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No items in cart']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // Extract buyer info
    $buyer = $data['buyer'] ?? [];
    $buyerId = !empty($buyer['buyer_id']) ? (int) $buyer['buyer_id'] : null;
    $buyerName = $buyer['buyer_name'] ?? 'Walk-in Customer';
    $buyerShop = $buyer['shop_name'] ?? $buyer['shop'] ?? null;
    $buyerAddress = $buyer['address'] ?? null;
    $buyerContact = $buyer['contact_number'] ?? null;
    $priceTier = $buyer['price_tier'] ?? 'retail';
    $isWalkin = !empty($buyer['is_walkin']) ? 1 : ($buyerId ? 0 : 1);

    $totalAmount = floatval($data['total_amount'] ?? 0);
    $draftLabel = $data['draft_label'] ?? null;
    $discountData = !empty($data['discount_data']) ? json_encode($data['discount_data']) : null;
    $userId = $_SESSION['user_id'];

    // Insert draft header
    $sql = "INSERT INTO pos_drafts 
            (user_id, buyer_id, buyer_name, buyer_shop_name, buyer_address, 
             buyer_contact, price_tier, is_walkin, total_amount, discount_data, draft_label)
            VALUES 
            (:user_id, :buyer_id, :buyer_name, :buyer_shop, :buyer_address,
             :buyer_contact, :price_tier, :is_walkin, :total_amount, :discount_data, :draft_label)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':buyer_id' => $buyerId,
        ':buyer_name' => $buyerName,
        ':buyer_shop' => $buyerShop,
        ':buyer_address' => $buyerAddress,
        ':buyer_contact' => $buyerContact,
        ':price_tier' => $priceTier,
        ':is_walkin' => $isWalkin,
        ':total_amount' => $totalAmount,
        ':discount_data' => $discountData,
        ':draft_label' => $draftLabel
    ]);

    $draftId = $conn->lastInsertId();

    // Insert each cart item
    $itemSql = "INSERT INTO pos_draft_items 
                (draft_id, variation_id, unit_id, multiplier, product_name, brand_name, variation_name,
                 unit_type, barcode, price, original_price, item_discount, manual_discount, manual_discount_type,
                 override_tier, price_tier, qty, stock_at_draft)
                VALUES 
                (:draft_id, :variation_id, :unit_id, :multiplier, :product_name, :brand_name, :variation_name,
                 :unit_type, :barcode, :price, :original_price, :item_discount, :manual_discount, :manual_discount_type,
                 :override_tier, :price_tier, :qty, :stock)";

    $itemStmt = $conn->prepare($itemSql);

    foreach ($data['items'] as $item) {
        $itemStmt->execute([
            ':draft_id' => $draftId,
            ':variation_id' => (int) $item['variation_id'],
            ':unit_id' => !empty($item['unit_id']) ? (int) $item['unit_id'] : null,
            ':multiplier' => (int) ($item['multiplier'] ?? 1) ?: 1,
            ':product_name' => $item['name'] ?? $item['product_name'] ?? '',
            ':brand_name' => $item['brand'] ?? $item['brand_name'] ?? null,
            ':variation_name' => $item['variation'] ?? $item['variation_name'] ?? null,
            ':unit_type' => $item['unit_type'] ?? 'pc',
            ':barcode' => $item['barcode'] ?? null,
            ':price' => floatval($item['price']),
            ':original_price' => floatval($item['original_price'] ?? $item['price']),
            ':item_discount' => floatval($item['item_discount'] ?? 0),
            ':manual_discount' => floatval($item['manual_discount'] ?? 0),
            ':manual_discount_type' => $item['manual_discount_type'] ?? 'fixed',
            ':override_tier' => !empty($item['override_tier']) ? $item['override_tier'] : null,
            ':price_tier' => $priceTier,
            ':qty' => (int) $item['qty'],
            ':stock' => (int) ($item['stock'] ?? 0)
        ]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'draft_id' => $draftId,
        'message' => 'Draft saved successfully'
    ]);
} catch (Throwable $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
