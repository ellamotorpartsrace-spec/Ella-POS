<?php
/**
 * api/inventory/process_online_sale.php
 * Records an online sale — deducts from online stock (store_id=2) and
 * logs a stock_movements entry of type 'online_sale'.
 */
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/SyncHelper.php';

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
$quantity = (int) ($data['quantity'] ?? 0);
$platform = trim($data['platform'] ?? 'Other');
$reference = trim($data['reference'] ?? '');
$price = (float) ($data['price'] ?? 0.0);
$notes = trim($data['notes'] ?? '');

if ($variationId <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid variation ID or quantity']);
    exit;
}

$allowedPlatforms = ['Shopee', 'Lazada', 'Facebook', 'TikTok', 'Other'];
if (!in_array($platform, $allowedPlatforms)) {
    $platform = 'Other';
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // Check current online stock
    $checkStmt = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 2");
    $checkStmt->execute([$variationId]);
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $currentOnlineStock = $row ? (int) $row['quantity'] : 0;

    if ($currentOnlineStock < $quantity) {
        throw new Exception("Insufficient online stock. Available: {$currentOnlineStock}");
    }

    // Deduct from online inventory
    $deductStmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE variation_id = ? AND store_id = 2");
    $deductStmt->execute([$quantity, $variationId]);

    $newStock = $currentOnlineStock - $quantity;

    // Compose reference string: include platform and order ref
    $refStr = 'OSALE-' . date('YmdHis');
    if (!empty($reference)) {
        $refStr = $platform . '-' . $reference;
    }

    // Build remarks
    $remarks = "Online sale via {$platform}";
    if ($price > 0) {
        $total = $price * $quantity;
        $remarks .= " | Price: ₱" . number_format($price, 2) . " | Total: ₱" . number_format($total, 2);
    }
    if (!empty($notes)) {
        $remarks .= ' | ' . $notes;
    }

    // Fetch cost price for logging
    $costStmt = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");
    $costStmt->execute([$variationId]);
    $capital_cost = (float)($costStmt->fetchColumn() ?? 0);

    // Log to stock_movements
    $movStmt = $conn->prepare("
        INSERT INTO stock_movements
            (variation_id, store_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost)
        VALUES (?, 2, 'online_sale', ?, ?, ?, ?, ?, ?, ?)
    ");
    $movStmt->execute([
        $variationId,
        -$quantity,
        $currentOnlineStock,
        $newStock,
        $refStr,
        $remarks,
        $_SESSION['user_id'],
        $capital_cost
    ]);

    $conn->commit();

    // Trigger Sync Queue for this variation
    SyncHelper::queueStockUpdate($conn, $variationId);

    echo json_encode([
        'success' => true,
        'message' => "Online sale recorded: {$quantity} unit(s) deducted from online stock",
        'previous_stock' => $currentOnlineStock,
        'new_stock' => $newStock,
        'platform' => $platform,
        'reference' => $refStr,
    ]);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
