<?php
// api/inventory/process_adjustment.php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

// 1. Security
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/inventory/adjustment.php");
    exit;
}
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    $_SESSION['error'] = 'Permission Denied';
    header("Location: ../../views/inventory/adjustment.php");
    exit;
}

// 2. Get Data
$var_id = $_POST['variation_id'];
$qty_adjust = (int) $_POST['quantity_adjustment']; // Can be positive or negative
$reason = trim($_POST['reason']);
$remarks = trim($_POST['remarks']);

// Validate
if (empty($var_id) || $qty_adjust == 0) {
    header("Location: " . BASE_URL . "views/inventory/adjustment.php?error=Invalid Product or Quantity");
    exit;
}

if (empty($reason)) {
    header("Location: " . BASE_URL . "views/inventory/adjustment.php?error=Reason is required");
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $conn->beginTransaction();

    // A. Check current stock first
    $stmtCheck = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
    $stmtCheck->execute([$var_id]);
    $current_qty = $stmtCheck->fetchColumn();

    if ($current_qty === false) {
        $current_qty = 0; // Should exist if selecting from list, but handle just in case
        // If it doesn't exist, we might need to insert it first if the adjustment is positive. 
        // If negative, we can't subtract from 0 (or undefined).
    }

    $new_total = $current_qty + $qty_adjust;

    if ($new_total < 0) {
        throw new Exception("Adjustment leads to negative stock ($new_total). Cannot proceed.");
    }

    // B. Update Stock Quantity
    // Using ON DUPLICATE KEY UPDATE to ensure record exists
    $stmtUpd = $conn->prepare("
        INSERT INTO inventory (variation_id, quantity, store_id) 
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ");
    $stmtUpd->execute([$var_id, $qty_adjust]);

    // C. Get Capital Price for Logging
    $stmtCap = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");
    $stmtCap->execute([$var_id]);
    $capital_cost = (float)($stmtCap->fetchColumn() ?? 0);

    // D. Create Audit Log (Stock Movement)
    $full_remarks = "Adjustment: $reason" . ($remarks ? " - $remarks" : "");

    $sqlLog = "INSERT INTO stock_movements 
               (variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, store_id, capital_cost) 
               VALUES (?, 'adjustment', ?, ?, ?, '', ?, ?, 1, ?)";

    $stmtLog = $conn->prepare($sqlLog);
    $stmtLog->execute([$var_id, $qty_adjust, $current_qty, $new_total, $full_remarks, $_SESSION['user_id'], $capital_cost]);

    $conn->commit();

    // Log Activity
    $actionType = $qty_adjust > 0 ? 'ADJUST_POSITIVE' : 'ADJUST_NEGATIVE';
    logActivity($conn, $_SESSION['user_id'], $actionType, 'Inventory', "Adjusted stock by $qty_adjust units. Reason: $reason", $var_id);

    // Redirect with success
    header("Location: " . BASE_URL . "views/inventory/adjustment.php?success=1");

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: " . BASE_URL . "views/inventory/adjustment.php?error=" . urlencode($e->getMessage()));
}
