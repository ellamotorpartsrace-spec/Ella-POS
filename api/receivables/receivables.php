<?php
// api/receivables.php
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

    // GET Request: List Receivables or Get History
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Mode 1: Get Payment History for a specific sale/payment
        if (isset($_GET['payment_id'])) {
            $payment_id = intval($_GET['payment_id']);

            $stmt = $conn->prepare("
                SELECT h.*, u.full_name as collector_name 
                FROM payment_history h
                LEFT JOIN users u ON h.collected_by = u.id
                WHERE h.payment_id = ?
                ORDER BY h.paid_at DESC
            ");
            $stmt->execute([$payment_id]);
            $history = $stmt->fetchAll();

            echo json_encode(['status' => 'success', 'data' => $history]);
            exit;
        }

        // Mode 1.5: Get a single payment's details and items
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);

            // Get payment details
            $stmt = $conn->prepare("
                SELECT p.*, s.sale_ref,
                       COALESCE(s.walkin_name, b.buyer_name) AS customer_name,
                       (p.amount - p.paid_amount) AS balance
                FROM pos_sale_payments p
                JOIN pos_sales s ON p.sale_id = s.sale_id
                LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
                WHERE p.payment_id = ?
            ");
            $stmt->execute([$id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Payment record not found']);
                exit;
            }

            // Get items for this sale
            $itemsStmt = $conn->prepare("
                SELECT i.quantity, i.price_at_sale as price, i.subtotal, p.product_name, pv.variation_name
                FROM pos_sale_items i
                JOIN product_variations pv ON i.variation_id = pv.variation_id
                JOIN products p ON pv.product_id = p.product_id
                WHERE i.sale_id = ?
            ");
            $itemsStmt->execute([$payment['sale_id']]);
            $payment['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $payment]);
            exit;
        }

        // Mode 2: List Pending Payments (Receivables)
        // Uses the view v_pending_payments
        $stmt = $conn->prepare("SELECT * FROM v_pending_payments ORDER BY due_date ASC");
        $stmt->execute();
        $receivables = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $receivables]);
        exit;
    }

    // POST Request: Add Payment
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);

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
            // 1. Get Current Payment Info to verify logic
            $stmt = $conn->prepare("SELECT * FROM pos_sale_payments WHERE payment_id = ?");
            $stmt->execute([$payment_id]);
            $payment_record = $stmt->fetch();

            if (!$payment_record) {
                throw new Exception("Payment record not found");
            }

            $current_paid = floatval($payment_record['paid_amount']);
            $total_due = floatval($payment_record['amount']);
            $new_paid_total = $current_paid + $amount_paying;

            // Optional: Prevent overpayment? 
            // For now, allow it but maybe warn? Or strictly cap it at total_due?
            // Usually valid to overpay (change), but for debt tracking, we might just cap it to close the debt.
            // Let's stick to exact recording.

            // 2. Insert into payment_history
            $stmt = $conn->prepare("
                INSERT INTO payment_history 
                (payment_id, amount, payment_method, reference_no, notes, collected_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$payment_id, $amount_paying, $method, $ref_no, $notes, $user_id]);

            // 3. Update pos_sale_payments
            // check status
            $new_status = 'partial';
            if ($new_paid_total >= $total_due - 0.01) { // Floating point tolerance
                $new_status = 'paid';
            }

            $stmt = $conn->prepare("
                UPDATE pos_sale_payments 
                SET paid_amount = paid_amount + ?, 
                    payment_status = ?,
                    paid_at = NOW()
                WHERE payment_id = ?
            ");
            $stmt->execute([$amount_paying, $new_status, $payment_id]);

            // 4. Synchronization with pos_sales table
            // We need to check if ALL payments for this sale are paid?
            // Usually there is only 1 'pay_later' payment record per sale, but theoretically there could be splits.
            // Let's assume strict 1-1 for now or check the sale status.

            // If the payment is legally "paid", we check the parent sale.
            if ($new_status === 'paid') {
                $sale_id = $payment_record['sale_id'];

                // Check if there are ANY other pending payments for this sale
                $check_stmt = $conn->prepare("
                    SELECT count(*) as pending_count 
                    FROM pos_sale_payments 
                    WHERE sale_id = ? AND payment_status != 'paid' AND payment_id != ?
                ");
                $check_stmt->execute([$sale_id, $payment_id]);
                $pending = $check_stmt->fetch();

                if ($pending['pending_count'] == 0) {
                    // All payments are cleared, mark sale as completed/paid
                    $update_sale = $conn->prepare("
                        UPDATE pos_sales 
                        SET payment_status = 'paid', status = 'completed' 
                        WHERE sale_id = ?
                    ");
                    $update_sale->execute([$sale_id]);
                }
            } else {
                // Even if partial, update sale to partial?
                // It might already be partial.
                $sale_id = $payment_record['sale_id'];
                $update_sale = $conn->prepare("
                    UPDATE pos_sales 
                    SET payment_status = 'partial' 
                    WHERE sale_id = ? AND payment_status = 'unpaid'
                ");
                $update_sale->execute([$sale_id]);
            }

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Payment recorded successfully', 'new_balance' => $total_due - $new_paid_total]);
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
