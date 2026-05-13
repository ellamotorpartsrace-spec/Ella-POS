<?php
// api/pos/admin_copy_draft.php
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

$data = json_decode(file_get_contents('php://input'), true);
$originalDraftId = isset($data['draft_id']) ? (int) $data['draft_id'] : 0;

if (!$originalDraftId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Draft ID required']);
    exit;
}

// Release session lock
session_write_close();

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $userId = $_SESSION['user_id'];

    // 1. Fetch original draft
    $stmt = $conn->prepare("SELECT * FROM pos_drafts WHERE draft_id = :draft_id");
    $stmt->execute([':draft_id' => $originalDraftId]);
    $originalDraft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$originalDraft) {
        throw new Exception("Original draft not found.");
    }

    // 2. Insert new draft for current admin
    $newLabel = $originalDraft['draft_label'] ? $originalDraft['draft_label'] . ' (Copy)' : 'Admin Copy';
    $insertDraftSql = "INSERT INTO pos_drafts 
            (user_id, buyer_id, buyer_name, buyer_shop_name, buyer_address, 
             buyer_contact, price_tier, is_walkin, total_amount, discount_data, draft_label)
            VALUES 
            (:user_id, :buyer_id, :buyer_name, :buyer_shop, :buyer_address,
             :buyer_contact, :price_tier, :is_walkin, :total_amount, :discount_data, :draft_label)";
             
    $insertStmt = $conn->prepare($insertDraftSql);
    $insertStmt->execute([
        ':user_id' => $userId,
        ':buyer_id' => $originalDraft['buyer_id'],
        ':buyer_name' => $originalDraft['buyer_name'],
        ':buyer_shop' => $originalDraft['buyer_shop_name'],
        ':buyer_address' => $originalDraft['buyer_address'],
        ':buyer_contact' => $originalDraft['buyer_contact'],
        ':price_tier' => $originalDraft['price_tier'],
        ':is_walkin' => $originalDraft['is_walkin'],
        ':total_amount' => $originalDraft['total_amount'],
        ':discount_data' => $originalDraft['discount_data'],
        ':draft_label' => $newLabel
    ]);

    $newDraftId = $conn->lastInsertId();

    // 3. Duplicate draft items
    $itemSql = "SELECT * FROM pos_draft_items WHERE draft_id = :draft_id";
    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->execute([':draft_id' => $originalDraftId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($items)) {
        $insertItemSql = "INSERT INTO pos_draft_items 
                    (draft_id, variation_id, unit_id, multiplier, product_name, brand_name, variation_name,
                     unit_type, barcode, price, original_price, item_discount, manual_discount, manual_discount_type,
                     override_tier, price_tier, qty, stock_at_draft)
                    VALUES 
                    (:draft_id, :variation_id, :unit_id, :multiplier, :product_name, :brand_name, :variation_name,
                     :unit_type, :barcode, :price, :original_price, :item_discount, :manual_discount, :manual_discount_type,
                     :override_tier, :price_tier, :qty, :stock)";
        
        $insertItemStmt = $conn->prepare($insertItemSql);

        foreach ($items as $item) {
            $insertItemStmt->execute([
                ':draft_id' => $newDraftId,
                ':variation_id' => $item['variation_id'],
                ':unit_id' => $item['unit_id'],
                ':multiplier' => $item['multiplier'],
                ':product_name' => $item['product_name'],
                ':brand_name' => $item['brand_name'],
                ':variation_name' => $item['variation_name'],
                ':unit_type' => $item['unit_type'],
                ':barcode' => $item['barcode'],
                ':price' => $item['price'],
                ':original_price' => $item['original_price'],
                ':item_discount' => $item['item_discount'],
                ':manual_discount' => $item['manual_discount'],
                ':manual_discount_type' => $item['manual_discount_type'],
                ':override_tier' => $item['override_tier'],
                ':price_tier' => $item['price_tier'],
                ':qty' => $item['qty'],
                ':stock' => $item['stock_at_draft']
            ]);
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'new_draft_id' => $newDraftId,
        'message' => 'Draft copied successfully'
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
