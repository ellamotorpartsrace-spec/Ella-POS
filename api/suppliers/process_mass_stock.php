<?php
// api/pos/process_mass_stock.php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// 1. Check Permissions
requireLogin();
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// 2. Parse Incoming Data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items provided for stock-in']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    $supplier_id = $data['supplier_id'];
    $po_ref      = $data['po_ref'] ?? 'PO-' . time();
    $user_id     = $_SESSION['user_id'];
    $grand_total = 0;

    // --- STEP 1: CREATE THE PURCHASE ORDER HEADER ---
    $sqlPO = "INSERT INTO purchase_orders (po_ref, supplier_id, user_id, status, created_at, received_at) 
              VALUES (?, ?, ?, 'received', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
    $stmtPO = $conn->prepare($sqlPO);
    $stmtPO->execute([$po_ref, $supplier_id, $user_id]);
    $po_id = (int)$conn->lastInsertId();

    // Prepare statements for the loop
    $sqlItem = "INSERT INTO purchase_order_items (po_id, variation_id, cost_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?)";
    $stmtItem = $conn->prepare($sqlItem);

    $sqlMove = "INSERT INTO stock_movements (store_id, variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost) 
                VALUES (1, ?, 'stock_in', ?, ?, ?, ?, ?, ?, ?)";
    $stmtMove = $conn->prepare($sqlMove);

    $sqlInv = "UPDATE inventory SET quantity = quantity + ? WHERE variation_id = ? AND store_id = 1";
    $stmtInv = $conn->prepare($sqlInv);

    // --- STEP 2: PROCESS EACH ITEM ---
    foreach ($data['items'] as $item) {
        $var_id = $item['variation_id'];
        $qty    = (int)$item['qty'];
        $cost   = (float)$item['cost'];
        $subtotal = $qty * $cost;
        $grand_total += $subtotal;

        // A. Insert into PO Items
        $stmtItem->execute([$po_id, $var_id, $cost, $qty, $subtotal]);

        // B. Get Current Stock for the Movement Log (Physical Store only)
        $stmtCurr = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
        $stmtCurr->execute([$var_id]);
        $prev_stock = (int)($stmtCurr->fetchColumn() ?: 0);
        $new_stock  = $prev_stock + $qty;

        // C. Update Inventory Table
        $stmtInv->execute([$qty, $var_id]);

        // D. Log Stock Movement
        $remarks = "Mass Stock-In from Supplier ID: " . $supplier_id;
        $stmtMove->execute([$var_id, $qty, $prev_stock, $new_stock, $po_ref, $remarks, $user_id, $cost]);
        
        // E. Update Product Variation Capital Price (Optional: keeping your cost up to date)
        $stmtUpdateCost = $conn->prepare("UPDATE product_variations SET price_capital = ? WHERE variation_id = ?");
        $stmtUpdateCost->execute([$cost, $var_id]);
    }

    // --- STEP 3: UPDATE PO TOTAL ---
    $stmtUpdatePO = $conn->prepare("UPDATE purchase_orders SET total_amount = ? WHERE po_id = ?");
    $stmtUpdatePO->execute([$grand_total, $po_id]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Inventory updated and PO recorded']);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}