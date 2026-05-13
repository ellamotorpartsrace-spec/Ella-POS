<?php
// api/pos/void_sale.php - Void a sale transaction
header("Content-Type: application/json");
require_once '../../config/database.php';
require_once '../../includes/logger.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../../includes/auth.php';
requirePermission('void_sales');

$user_id = $_SESSION['user_id'] ?? 1;

$input = json_decode(file_get_contents('php://input'), true);
$sale_id = $input['sale_id'] ?? null;
$reason = $input['reason'] ?? 'Voided by user';

if (!$sale_id) {
    echo json_encode(['success' => false, 'error' => 'Missing sale ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // 1. Check if sale exists and is not already voided
    $stmt = $conn->prepare("SELECT sale_id, status FROM pos_sales WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Sale not found");
    }

    if ($sale['status'] === 'voided') {
        throw new Exception("Sale is already voided");
    }

    // Capture the sale reference for logging
    $stmtRef = $conn->prepare("SELECT sale_ref, grand_total FROM pos_sales WHERE sale_id = ?");
    $stmtRef->execute([$sale_id]);
    $saleInfo = $stmtRef->fetch(PDO::FETCH_ASSOC);
    $sale_ref = $saleInfo['sale_ref'] ?? "ID:$sale_id";

    // 2. Update sale status to voided
    $stmt = $conn->prepare("
        UPDATE pos_sales 
        SET status = 'voided', 
            remarks = CONCAT(COALESCE(remarks, ''), ' [VOIDED: ', ?, ']')
        WHERE sale_id = ?
    ");
    $stmt->execute([$reason, $sale_id]);

    // 2b. Also void all associated payment records (removes from receivables)
    $stmt = $conn->prepare("
        UPDATE pos_sale_payments 
        SET payment_status = 'voided' 
        WHERE sale_id = ?
    ");
    $stmt->execute([$sale_id]);

    // 3. Restore inventory for each item
    $itemsStmt = $conn->prepare("SELECT variation_id, quantity, multiplier, cost_at_sale FROM pos_sale_items WHERE sale_id = ?");
    $itemsStmt->execute([$sale_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $multiplier = isset($item['multiplier']) && $item['multiplier'] > 0 ? (int) $item['multiplier'] : 1;
        $totalQtyToRestore = (int) $item['quantity'] * $multiplier;
        // Get current stock from Physical Store (store_id = 1)
        $invStmt = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
        $invStmt->execute([$item['variation_id']]);
        $inv = $invStmt->fetch(PDO::FETCH_ASSOC);
        $previousStock = $inv ? $inv['quantity'] : 0;
        $newStock = $previousStock + $totalQtyToRestore;

        // Update inventory
        $conn->prepare("
            INSERT INTO inventory (variation_id, quantity, store_id) 
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE quantity = quantity + ?
        ")->execute([$item['variation_id'], $totalQtyToRestore, $totalQtyToRestore]);

        // Log stock movement
        $conn->prepare("
            INSERT INTO stock_movements 
            (variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, store_id, capital_cost)
            VALUES (?, 'return', ?, ?, ?, ?, ?, ?, 1, ?)
        ")->execute([
                    $item['variation_id'],
                    $totalQtyToRestore,
                    $previousStock,
                    $newStock,
                    'VOID-' . $sale_id,
                    $reason,
                    $user_id,
                    $item['cost_at_sale'] ?? 0
                ]);
    }

    $conn->commit();

    // Log Activity
    logActivity($conn, $user_id, 'VOID_SALE', 'POS', "Voided sale $sale_ref. Reason: $reason", $sale_id);

    echo json_encode(['success' => true, 'message' => 'Sale voided and stock restored']);
} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
