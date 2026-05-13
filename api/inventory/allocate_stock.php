<?php
/**
 * api/inventory/allocate_stock.php
 * Transfers stock between Physical Store (store_id=1) and Online Shop (store_id=2)
 */
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Security
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Auth check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$variationId = (int)($data['variation_id'] ?? 0);
$quantity = (int)($data['quantity'] ?? 0);
$direction = $data['direction'] ?? 'to_online'; // 'to_online' or 'to_physical'

// Validation
if ($variationId <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid variation ID or quantity']);
    exit;
}

// Determine source and destination store IDs
$fromStoreId = ($direction === 'to_online') ? 1 : 2;
$toStoreId = ($direction === 'to_online') ? 2 : 1;
$movementType = ($direction === 'to_online') ? 'allocation_to_online' : 'allocation_to_physical';

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // 1. Check source stock
    $checkStmt = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = ?");
    $checkStmt->execute([$variationId, $fromStoreId]);
    $sourceRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

    $sourceStock = $sourceRow ? (int)$sourceRow['quantity'] : 0;

    if ($sourceStock < $quantity) {
        throw new Exception("Insufficient stock in source location. Available: {$sourceStock}");
    }

    // 2. Deduct from source
    $deductStmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE variation_id = ? AND store_id = ?");
    $deductStmt->execute([$quantity, $variationId, $fromStoreId]);

    // 3. Add to destination (INSERT or UPDATE)
    $addStmt = $conn->prepare("
        INSERT INTO inventory (variation_id, store_id, quantity) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ");
    $addStmt->execute([$variationId, $toStoreId, $quantity]);

    // 4. Get new stock levels for audit
    $checkStmt->execute([$variationId, $fromStoreId]);
    $newSourceStock = (int)($checkStmt->fetch(PDO::FETCH_ASSOC)['quantity'] ?? 0);

    $checkStmt->execute([$variationId, $toStoreId]);
    $newDestStock = (int)($checkStmt->fetch(PDO::FETCH_ASSOC)['quantity'] ?? 0);

    // 5. Get Capital Price for Logging
    $stmtCap = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");
    $stmtCap->execute([$variationId]);
    $capital_cost = (float)($stmtCap->fetchColumn() ?? 0);

    // 6. Log stock movement for audit trail
    $movementStmt = $conn->prepare("
        INSERT INTO stock_movements 
        (variation_id, store_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Log outbound from source
    $movementStmt->execute([
        $variationId,
        $fromStoreId,
        $movementType,
        -$quantity,
        $sourceStock,
        $newSourceStock,
        'ALLOC-' . date('YmdHis'),
        ($direction === 'to_online') ? 'Allocated to Online Shop' : 'Returned to Physical Store',
        $_SESSION['user_id'],
        $capital_cost
    ]);

    // Log inbound to destination
    $movementStmt->execute([
        $variationId,
        $toStoreId,
        $movementType,
        $quantity,
        $newDestStock - $quantity,
        $newDestStock,
        'ALLOC-' . date('YmdHis'),
        ($direction === 'to_online') ? 'Received from Physical Store' : 'Received from Online Shop',
        $_SESSION['user_id'],
        $capital_cost
    ]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Successfully allocated {$quantity} units",
        'physical_stock' => ($direction === 'to_online') ? $newSourceStock : $newDestStock,
        'online_stock' => ($direction === 'to_online') ? $newDestStock : $newSourceStock
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
