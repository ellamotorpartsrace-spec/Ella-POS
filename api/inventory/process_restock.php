<?php
// api/inventory/process_restock.php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

// 1. Security
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/inventory/restock.php");
    exit;
}
requireLogin();

// 2. Get Data
$var_id = $_POST['variation_id'];
$qty_added = (int) $_POST['quantity_added'];
$current_qty = (int) $_POST['current_stock'];
$new_capital = $_POST['new_capital'];
$supplier = trim($_POST['supplier']);
$ref = trim($_POST['reference']);

// Validate
if ($qty_added <= 0) {
    header("Location: " . BASE_URL . "views/inventory/restock.php?id=$var_id&error=Invalid Quantity");
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $conn->beginTransaction();

    // A. Update Stock Quantity (Increase)
    // Use INSERT ON DUPLICATE KEY UPDATE to handle cases where inventory row doesn't exist yet
    // This fixes the issue where products created without initial stock can't be restocked
    $stmtUpd = $conn->prepare("
        INSERT INTO inventory (variation_id, quantity, store_id) 
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ");
    $stmtUpd->execute([$var_id, $qty_added]);

    // B. Handle Price Change & History Log
    // 1. Fetch Old Data First (Audit Trail)
    $stmtOld = $conn->prepare("SELECT price_capital, price_retail, price_wholesale, price_dealer FROM product_variations WHERE variation_id = ?");
    $stmtOld->execute([$var_id]);
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

    if ($oldData && hasPermission('view_profit')) {
        $old_capital = (float) $oldData['price_capital'];
        $old_retail = (float) $oldData['price_retail'];
        $old_wholesale = (float) $oldData['price_wholesale'];
        $old_dealer = (float) $oldData['price_dealer'];
        $current_new_capital = (float) $new_capital;

        // 2. Detect Change
        if ($old_capital != $current_new_capital) {

            // 3. Record History
            $sqlHistory = "INSERT INTO product_price_history 
                (variation_id, user_id, 
                 old_capital, new_capital, 
                 old_retail, new_retail, 
                 old_wholesale, new_wholesale, 
                 old_dealer, new_dealer,
                 changed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmtHist = $conn->prepare($sqlHistory);
            $stmtHist->execute([
                $var_id,
                $_SESSION['user_id'] ?? 1,
                $old_capital,
                $current_new_capital,
                $old_retail,
                $old_retail,       // Retail unchanged
                $old_wholesale,
                $old_wholesale, // Wholesale unchanged
                $old_dealer,
                $old_dealer        // Dealer unchanged
            ]);
        }
    }

    // C. Update Capital Price (If changed and permitted)
    if (hasPermission('view_profit')) {
        $stmtPrice = $conn->prepare("UPDATE product_variations SET price_capital = ? WHERE variation_id = ?");
        $stmtPrice->execute([$new_capital, $var_id]);
    }

    // C. Create Audit Log (Stock Movement)
    $new_total = $current_qty + $qty_added;
    $remarks = "Restock: " . ($supplier ?: 'Manual Entry');

    // Ensure we have a reference if an image is uploaded but ref is empty
    if (empty($ref) && !empty($_FILES['reference_image']['name'])) {
        $ref = 'REF-' . date('YmdHis') . '-' . $var_id;
    }

    $sqlLog = "INSERT INTO stock_movements 
               (variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost, store_id) 
               VALUES (?, 'stock_in', ?, ?, ?, ?, ?, ?, ?, 1)";

    $stmtLog = $conn->prepare($sqlLog);
    // Use the $new_capital variable which was just posted (and possibly updated above) to lock in the price.
    $stmtLog->execute([$var_id, $qty_added, $current_qty, $new_total, $ref, $remarks, $_SESSION['user_id'], (float) $new_capital]);

    // D. Handle Reference Image Upload (Multiple)
    if (!empty($_FILES['reference_images']['name'][0])) {
        $uploadDir = '../../assets/uploads/references/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $files = $_FILES['reference_images'];
        $fileCount = count($files['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmpPath = $files['tmp_name'][$i];
                $fileName = $files['name'][$i];

                // Use the $ref we established above (either user input or generated)
                $finalRef = $ref ?: 'REF-NOS-' . time();
                $newFileName = 'ref_' . time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9.]/', '', $fileName);
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $dbPath = 'assets/uploads/references/' . $newFileName;
                    $stmtAtt = $conn->prepare("INSERT INTO reference_attachments (reference_number, image_path) VALUES (?, ?)");
                    $stmtAtt->execute([$finalRef, $dbPath]);

                    // If ref was empty and we didn't generate one earlier (rare), update the movement
                    if (empty($ref)) {
                        $conn->prepare("UPDATE stock_movements SET reference = ? WHERE movement_id = ?")
                            ->execute([$finalRef, $conn->lastInsertId()]);
                    }
                }
            }
        }
    }

    // E. Handle Payment Obligation (if unpaid)
    $payment_status = trim($_POST['payment_status'] ?? 'paid');
    $due_date = $_POST['due_date'] ?? null;

    if ($payment_status === 'unpaid' && !empty($supplier)) {
        // Calculate total cost for this restock
        $total_cost = $qty_added * (float) $new_capital;

        // Get supplier_id from supplier name
        $stmtSupp = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_name = ? LIMIT 1");
        $stmtSupp->execute([$supplier]);
        $suppData = $stmtSupp->fetch(PDO::FETCH_ASSOC);

        if ($suppData && $total_cost > 0) {
            // Create a simple PO reference if none exists
            $po_ref = $ref ?: 'RESTOCK-' . date('YmdHis');

            // Check if PO exists, if not create one
            $checkPO = $conn->prepare("SELECT po_id FROM purchase_orders WHERE po_ref = ?");
            $checkPO->execute([$po_ref]);
            $existingPO = $checkPO->fetch();

            if (!$existingPO) {
                // Create a quick purchase order entry
                $stmtPO = $conn->prepare("
                    INSERT INTO purchase_orders 
                    (supplier_id, po_ref, total_amount, status, payment_status, user_id, due_date) 
                    VALUES (?, ?, ?, 'received', 'unpaid', ?, ?)
                ");
                $stmtPO->execute([
                    $suppData['supplier_id'],
                    $po_ref,
                    $total_cost,
                    $_SESSION['user_id'],
                    $due_date ?: date('Y-m-d', strtotime('+30 days'))
                ]);
                $po_id = $conn->lastInsertId();
            } else {
                $po_id = $existingPO['po_id'];
                // Update existing PO total
                $conn->prepare("UPDATE purchase_orders SET total_amount = total_amount + ? WHERE po_id = ?")
                    ->execute([$total_cost, $po_id]);
            }

            // Create supplier payment record
            $stmtPay = $conn->prepare("
                INSERT INTO supplier_payments 
                (po_id, supplier_id, amount, due_date, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtPay->execute([
                $po_id,
                $suppData['supplier_id'],
                $total_cost,
                $due_date ?: date('Y-m-d', strtotime('+30 days')),
                "Restock: " . $qty_added . " units",
                $_SESSION['user_id']
            ]);
        }
    }

    $conn->commit();

    // Log Activity
    logActivity($conn, $_SESSION['user_id'], 'RESTOCK', 'Inventory', "Restocked +$qty_added units for variation ID: $var_id", $var_id);

    // Redirect with success
    header("Location: " . BASE_URL . "views/inventory/restock.php?success=1");
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: " . BASE_URL . "views/inventory/restock.php?id=$var_id&error=" . urlencode($e->getMessage()));
}
