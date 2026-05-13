<?php
// api/inventory/process_batch_restock.php - Process batch restock
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

requireLogin();

$input = $_POST;

$supplier_id = $input['supplier_id'] ?? null;
$supplier_name = $input['supplier_name'] ?? '';
$reference = $input['reference'] ?? '';
// Since we used FormData and JSON.stringify for items
$items = isset($input['items']) ? json_decode($input['items'], true) : [];

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'No items provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $user_id = $_SESSION['user_id'] ?? 1;
    $processed = 0;
    $totalCost = 0;

    // Generate a consistent reference if empty, especially for image linking
    $finalReference = !empty($reference) ? $reference : 'BATCH-' . date('YmdHis');

    foreach ($items as $item) {
        $variation_id = $item['variation_id'];
        $quantity = (int) $item['quantity'];
        $cost = (float) $item['cost'];
        $current_stock = (int) $item['current_stock'];

        if ($quantity <= 0)
            continue;

        // 1. Get actual current stock (fresh read from physical store)
        $stmt = $conn->prepare("SELECT COALESCE(quantity, 0) as qty FROM inventory WHERE variation_id = ? AND store_id = 1");
        $stmt->execute([$variation_id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        $actual_current = $inv ? (int) $inv['qty'] : 0;

        // 2. Update inventory (insert or update)
        $conn->prepare("
            INSERT INTO inventory (variation_id, quantity, store_id) 
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE quantity = quantity + ?
        ")->execute([$variation_id, $quantity, $quantity]);

        $new_stock = $actual_current + $quantity;

        // 3. Update capital price if provided
        if ($cost > 0) {
            // Get old price for history
            $stmtOld = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");
            $stmtOld->execute([$variation_id]);
            $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
            $old_capital = $oldData ? (float) $oldData['price_capital'] : 0;

            // Update if different
            if ($old_capital != $cost) {
                // Log price history FIRST (before updating the variation table)
                $conn->prepare("
                    INSERT INTO product_price_history 
                    (variation_id, user_id, old_capital, new_capital, old_retail, new_retail, old_wholesale, new_wholesale, old_dealer, new_dealer, changed_at)
                    SELECT ?, ?, price_capital, ?, price_retail, price_retail, price_wholesale, price_wholesale, price_dealer, price_dealer, NOW()
                    FROM product_variations WHERE variation_id = ?
                ")->execute([$variation_id, $user_id, $cost, $variation_id]);

                // NOW update the actual price
                $conn->prepare("UPDATE product_variations SET price_capital = ? WHERE variation_id = ?")
                    ->execute([$cost, $variation_id]);
            }
        }

        // 4. Log stock movement
        $isFree = !empty($item['is_free']) ? (bool) $item['is_free'] : false;
        $freeReason = isset($item['free_reason']) ? trim($item['free_reason']) : '';

        $remarks = "Batch Restock: " . ($supplier_name ?: 'Unknown Supplier');
        if ($isFree) {
            $remarks .= " [FREE ITEM" . ($freeReason ? " - $freeReason" : "") . "]";
        }

        $conn->prepare("
            INSERT INTO stock_movements 
            (variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, store_id, capital_cost)
            VALUES (?, 'stock_in', ?, ?, ?, ?, ?, ?, 1, ?)
        ")->execute([
                    $variation_id,
                    $quantity,
                    $actual_current,
                    $new_stock,
                    $finalReference,
                    $remarks,
                    $user_id,
                    $cost
                ]);

        $processed++;
        $totalCost += $quantity * $cost;
    }

    // Handle Multiple File Uploads
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

                $newFileName = 'batch_' . time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9.]/', '', $fileName);
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $dbPath = 'assets/uploads/references/' . $newFileName;
                    $conn->prepare("INSERT INTO reference_attachments (reference_number, image_path) VALUES (?, ?)")
                        ->execute([$finalReference, $dbPath]);
                }
            }
        }
    }

    // Handle Payment Obligation (if unpaid)
    $payment_status = trim($input['payment_status'] ?? 'paid');
    $credit_terms = isset($input['credit_terms']) ? json_decode($input['credit_terms'], true) : [];

    if ($payment_status === 'unpaid' && $supplier_id && $totalCost > 0) {
        // Create a simple PO reference if none exists
        $po_ref = $reference ?: 'BATCH-' . date('YmdHis');

        // Check if PO exists, if not create one
        $checkPO = $conn->prepare("SELECT po_id FROM purchase_orders WHERE po_ref = ?");
        $checkPO->execute([$po_ref]);
        $existingPO = $checkPO->fetch();

        // Use the first term's due date as the PO's primary due date, or fallback to +30 days
        $primary_due_date = (!empty($credit_terms) && isset($credit_terms[0]['due_date'])) ? $credit_terms[0]['due_date'] : date('Y-m-d', strtotime('+30 days'));

        if (!$existingPO) {
            // Create a quick purchase order entry
            $stmtPO = $conn->prepare("
                INSERT INTO purchase_orders 
                (supplier_id, po_ref, total_amount, status, payment_status, user_id, due_date) 
                VALUES (?, ?, ?, 'received', 'unpaid', ?, ?)
            ");
            $stmtPO->execute([
                $supplier_id,
                $po_ref,
                $totalCost,
                $user_id,
                $primary_due_date
            ]);
            $po_id = $conn->lastInsertId();
        } else {
            $po_id = $existingPO['po_id'];
            // Update existing PO total
            $conn->prepare("UPDATE purchase_orders SET total_amount = total_amount + ? WHERE po_id = ?")
                ->execute([$totalCost, $po_id]);
        }

        // Create supplier payment records
        if (!empty($credit_terms)) {
            $stmtPay = $conn->prepare("
                INSERT INTO supplier_payments 
                (po_id, supplier_id, amount, due_date, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $term_number = 1;
            $total_terms = count($credit_terms);

            foreach ($credit_terms as $term) {
                // If there's only 1 term, keep notes simple, else indicate term sequence
                $notes = "Batch Restock: $processed items" . ($total_terms > 1 ? " (Term $term_number of $total_terms)" : "");

                $stmtPay->execute([
                    $po_id,
                    $supplier_id,
                    $term['amount'],
                    $term['due_date'],
                    $notes,
                    $user_id
                ]);
                $term_number++;
            }
        } else {
            // Fallback for requests that might not send credit_terms (like old cached page)
            $stmtPay = $conn->prepare("
                INSERT INTO supplier_payments 
                (po_id, supplier_id, amount, due_date, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtPay->execute([
                $po_id,
                $supplier_id,
                $totalCost,
                $primary_due_date,
                "Batch Restock: " . $processed . " items",
                $user_id
            ]);
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Successfully restocked $processed items",
        'processed' => $processed
    ]);
} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
