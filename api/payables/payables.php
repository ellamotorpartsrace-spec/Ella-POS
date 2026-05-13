<?php
// api/payables.php
// Main API for Supplier Payments (Accounts Payable)

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();

    // =====================================================
    // GET Request: List Payables or Get History
    // =====================================================
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Mode 1: Get Payment History for a specific supplier payment
        if (isset($_GET['payment_id'])) {
            $payment_id = intval($_GET['payment_id']);

            $stmt = $conn->prepare("
                SELECT h.*, u.full_name as paid_by_name,
                       (SELECT GROUP_CONCAT(a.image_path) 
                        FROM supplier_payment_attachments a 
                        WHERE a.history_id = h.history_id) as attachments
                FROM supplier_payment_history h
                LEFT JOIN users u ON h.paid_by = u.id
                WHERE h.supplier_payment_id = ?
                ORDER BY h.paid_at DESC
            ");
            $stmt->execute([$payment_id]);
            $history = $stmt->fetchAll();

            // Also get attachments linked directly to the payment (not history)
            $attachStmt = $conn->prepare("
                SELECT * FROM supplier_payment_attachments 
                WHERE supplier_payment_id = ? AND history_id IS NULL
                ORDER BY uploaded_at DESC
            ");
            $attachStmt->execute([$payment_id]);
            $generalAttachments = $attachStmt->fetchAll();

            echo json_encode([
                'status' => 'success',
                'data' => $history,
                'general_attachments' => $generalAttachments
            ]);
            exit;
        }

        // Mode 2: Get a single payment details
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("
                SELECT sp.*, 
                       po.po_ref,
                       s.supplier_name, s.contact_person, s.phone as supplier_phone,
                       (sp.amount - sp.paid_amount) as balance
                FROM supplier_payments sp
                JOIN purchase_orders po ON sp.po_id = po.po_id
                JOIN suppliers s ON sp.supplier_id = s.supplier_id
                WHERE sp.payment_id = ?
            ");
            $stmt->execute([$id]);
            $payment = $stmt->fetch();

            if (!$payment) {
                throw new Exception("Payment record not found");
            }

            // Also get reference images (from restock)
            $refStmt = $conn->prepare("
                SELECT image_path FROM reference_attachments 
                WHERE reference_number = ?
                ORDER BY created_at DESC
            ");
            $refStmt->execute([$payment['po_ref']]);
            $refImages = $refStmt->fetchAll(PDO::FETCH_COLUMN);

            $payment['reference_images'] = $refImages;

            // Get receipt items matching the PO ref (Physical Store only)
            $itemsStmt = $conn->prepare("
                SELECT sm.quantity, sm.remarks, p.product_name, pv.variation_name, pv.price_capital as current_cost
                FROM stock_movements sm
                JOIN product_variations pv ON sm.variation_id = pv.variation_id
                JOIN products p ON pv.product_id = p.product_id
                WHERE sm.reference = ? AND sm.type = 'stock_in' AND sm.store_id = 1
            ");
            $itemsStmt->execute([$payment['po_ref']]);
            $payment['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            // If it's empty, try getting them from purchase_order_items as fallback
            if (empty($payment['items'])) {
                $itemsStmt2 = $conn->prepare("
                    SELECT poi.quantity, poi.cost_price as current_cost, p.product_name, pv.variation_name, 'Mass Stock-In' as remarks
                    FROM purchase_order_items poi
                    JOIN product_variations pv ON poi.variation_id = pv.variation_id
                    JOIN products p ON pv.product_id = p.product_id
                    WHERE poi.po_id = ?
                ");
                $itemsStmt2->execute([$payment['po_id']]);
                $payment['items'] = $itemsStmt2->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['status' => 'success', 'data' => $payment]);
            exit;
        }

        // Mode 3: List All Pending Payables (Suppliers we owe money to)
        $showAll = isset($_GET['all']) && $_GET['all'] === '1';

        if ($showAll) {
            // Show all including paid
            $stmt = $conn->prepare("
                SELECT sp.payment_id, sp.po_id, po.po_ref, sp.supplier_id,
                       s.supplier_name, s.contact_person, s.phone as supplier_phone,
                       sp.amount as amount_due, sp.paid_amount,
                       (sp.amount - sp.paid_amount) as balance,
                       sp.due_date, sp.payment_status,
                       CASE 
                           WHEN sp.due_date < CURDATE() AND sp.payment_status IN ('pending', 'partial') THEN 'overdue'
                           WHEN sp.due_date = CURDATE() AND sp.payment_status IN ('pending', 'partial') THEN 'due_today'
                           ELSE sp.payment_status
                       END AS status_label,
                       DATEDIFF(sp.due_date, CURDATE()) AS days_until_due,
                       sp.notes, sp.created_at
                FROM supplier_payments sp
                JOIN purchase_orders po ON sp.po_id = po.po_id
                JOIN suppliers s ON sp.supplier_id = s.supplier_id
                ORDER BY sp.due_date ASC
            ");
        } else {
            // Only pending/partial
            $stmt = $conn->prepare("SELECT * FROM v_pending_supplier_payments ORDER BY due_date ASC");
        }

        $stmt->execute();
        $payables = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $payables]);
        exit;
    }

    // =====================================================
    // POST Request: Add Payment or Update Date
    // =====================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);

        // Update Due Date
        if (isset($data['action']) && $data['action'] === 'update_due_date') {
            if (!isset($data['payment_id'], $data['due_date'])) {
                throw new Exception("Missing required fields for date update");
            }
            
            $payment_id = intval($data['payment_id']);
            $due_date = $data['due_date'];
            
            $stmt = $conn->prepare("UPDATE supplier_payments SET due_date = ?, updated_at = NOW() WHERE payment_id = ?");
            $stmt->execute([$due_date, $payment_id]);
            
            echo json_encode(['status' => 'success', 'message' => 'Due date updated successfully']);
            exit;
        }

        if (!isset($data['payment_id'], $data['amount'])) {
            throw new Exception("Missing required fields");
        }

        $payment_id = intval($data['payment_id']);
        $amount_paying = floatval($data['amount']);
        $method = $data['payment_method'] ?? 'cash';
        $ref_no = $data['reference_no'] ?? null;
        $notes = $data['notes'] ?? null;
        $user_id = $_SESSION['user_id'];

        if ($amount_paying <= 0) {
            throw new Exception("Amount must be greater than 0");
        }

        $conn->beginTransaction();

        try {
            // 1. Get Current Payment Info
            $stmt = $conn->prepare("SELECT * FROM supplier_payments WHERE payment_id = ?");
            $stmt->execute([$payment_id]);
            $payment_record = $stmt->fetch();

            if (!$payment_record) {
                throw new Exception("Payment record not found");
            }

            $current_paid = floatval($payment_record['paid_amount']);
            $total_due = floatval($payment_record['amount']);
            $new_paid_total = $current_paid + $amount_paying;

            // 2. Insert into supplier_payment_history
            $stmt = $conn->prepare("
                INSERT INTO supplier_payment_history 
                (supplier_payment_id, amount, payment_method, reference_no, notes, paid_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$payment_id, $amount_paying, $method, $ref_no, $notes, $user_id]);
            $history_id = $conn->lastInsertId();

            // 3. Update supplier_payments
            $new_status = 'partial';
            if ($new_paid_total >= $total_due - 0.01) { // Floating point tolerance
                $new_status = 'paid';
            }

            $stmt = $conn->prepare("
                UPDATE supplier_payments 
                SET paid_amount = paid_amount + ?, 
                    payment_status = ?,
                    updated_at = NOW()
                WHERE payment_id = ?
            ");
            $stmt->execute([$amount_paying, $new_status, $payment_id]);

            // 4. Update Purchase Order status if fully paid
            if ($new_status === 'paid') {
                $po_id = $payment_record['po_id'];

                // Check if there are any other pending payments for this PO
                $check_stmt = $conn->prepare("
                    SELECT count(*) as pending_count 
                    FROM supplier_payments 
                    WHERE po_id = ? AND payment_status != 'paid' AND payment_id != ?
                ");
                $check_stmt->execute([$po_id, $payment_id]);
                $pending = $check_stmt->fetch();

                if ($pending['pending_count'] == 0) {
                    // All payments cleared, mark PO as paid
                    $update_po = $conn->prepare("
                        UPDATE purchase_orders 
                        SET payment_status = 'paid' 
                        WHERE po_id = ?
                    ");
                    $update_po->execute([$po_id]);
                }
            } else {
                // Update PO to partial if not already
                $po_id = $payment_record['po_id'];
                $update_po = $conn->prepare("
                    UPDATE purchase_orders 
                    SET payment_status = 'partial' 
                    WHERE po_id = ? AND payment_status = 'unpaid'
                ");
                $update_po->execute([$po_id]);
            }

            $conn->commit();
            echo json_encode([
                'status' => 'success',
                'message' => 'Payment recorded successfully',
                'history_id' => $history_id,
                'new_balance' => $total_due - $new_paid_total,
                'new_status' => $new_status
            ]);
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
