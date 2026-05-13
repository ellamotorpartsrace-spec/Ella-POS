<?php
/**
 * api/inventory/update_online_stock.php
 * Directly set/adjust the online stock (store_id=2) for a product variation.
 * Records an audit entry in stock_movements as type 'online_adjustment'.
 */
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$variationId = (int) ($data['variation_id'] ?? 0);
$newQuantity = (int) ($data['new_quantity'] ?? -1);
$reason = trim($data['reason'] ?? 'Manual online stock adjustment');

if ($variationId <= 0 || $newQuantity < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid variation ID or quantity']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // Get current online stock
    $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 2");
    $stmt->execute([$variationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentQty = $row ? (int) $row['quantity'] : 0;

    // Upsert online inventory record
    $upsert = $conn->prepare("
        INSERT INTO inventory (variation_id, store_id, quantity)
        VALUES (?, 2, ?)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
    ");
    $upsert->execute([$variationId, $newQuantity]);

    // Fetch cost price for logging
    $costStmt = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");
    $costStmt->execute([$variationId]);
    $capital_cost = (float)($costStmt->fetchColumn() ?? 0);

    // Log to stock_movements
    $diff = $newQuantity - $currentQty;
    $movStmt = $conn->prepare("
        INSERT INTO stock_movements
            (variation_id, store_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost)
        VALUES (?, 2, 'online_adjustment', ?, ?, ?, ?, ?, ?, ?)
    ");
    $movStmt->execute([
        $variationId,
        $diff,
        $currentQty,
        $newQuantity,
        'ADJ-ONLINE-' . date('YmdHis'),
        $reason,
        $_SESSION['user_id'],
        $capital_cost
    ]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Online stock updated successfully',
        'previous_stock' => $currentQty,
        'new_stock' => $newQuantity,
        'difference' => $diff,
    ]);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
