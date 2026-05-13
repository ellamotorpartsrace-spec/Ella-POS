<?php
// api/pos/recalculate_profit.php - Recalculate profit based on current capital
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// 1. Auth & Permission Check
// Only Admin and Manager should be able to recalculate profit
requireLogin();
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);
$sale_id = $input['sale_id'] ?? null;

if (!$sale_id) {
    echo json_encode(['success' => false, 'error' => 'Missing Sale ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // 2. Fetch Sale Items
    // We need to know which variation each item corresponds to
    $stmtItems = $conn->prepare("
        SELECT sale_item_id, variation_id, quantity, cost_at_sale 
        FROM pos_sale_items 
        WHERE sale_id = ?
    ");
    $stmtItems->execute([$sale_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        throw new Exception("Sale not found or has no items");
    }

    $updatedCount = 0;

    foreach ($items as $item) {
        // 3. Get Current Capital for this Variation
        $stmtVar = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");
        $stmtVar->execute([$item['variation_id']]);
        $variation = $stmtVar->fetch(PDO::FETCH_ASSOC);

        if ($variation) {
            $currentCapital = (float)$variation['price_capital'];
            
            // Only update if different (to save writes, though logic remains same)
            // Actually, we should force update even if same to confirm "refreshed" action?
            // Let's just update.
            
            $stmtUpdate = $conn->prepare("
                UPDATE pos_sale_items 
                SET cost_at_sale = ? 
                WHERE sale_item_id = ?
            ");
            $stmtUpdate->execute([$currentCapital, $item['sale_item_id']]);
            $updatedCount++;
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Successfully recalculated profit for $updatedCount items.",
        'updated_count' => $updatedCount
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
