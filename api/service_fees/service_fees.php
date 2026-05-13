<?php
// api/service_fees/service_fees.php
// Main API for Service Fee Expenses (Shipping/Delivery/Service Charges)
// Mirrors payables.php pattern for buyer-side fee tracking

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Permission Check
if (!hasPermission('manage_service_fees')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: You do not have manage_service_fees permission']);
    exit;
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();

    // =====================================================
    // GET Request: List Service Fees or Get Details/History
    // =====================================================
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Mode 1: Get Payment History for a specific service fee
        if (isset($_GET['fee_id'])) {
            $fee_id = intval($_GET['fee_id']);

            $stmt = $conn->prepare("
                SELECT h.*, u.full_name as collector_name,
                       (SELECT GROUP_CONCAT(a.image_path) 
                        FROM service_fee_attachments a 
                        WHERE a.history_id = h.history_id) as attachments
                FROM service_fee_payments h
                LEFT JOIN users u ON h.collected_by = u.id
                WHERE h.fee_id = ?
                ORDER BY h.paid_at DESC
            ");
            $stmt->execute([$fee_id]);
            $history = $stmt->fetchAll();

            echo json_encode(['status' => 'success', 'data' => $history]);
            exit;
        }

        // Mode 2: Get a single fee's full details
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("
                SELECT sf.*, 
                       COALESCE(b.buyer_name, sf.buyer_name) AS display_name,
                       b.shop_name, b.contact_number,
                       (sf.amount - sf.paid_amount) as balance,
                       u.full_name as created_by_name
                FROM service_fees sf
                LEFT JOIN buyers b ON sf.buyer_id = b.buyer_id
                LEFT JOIN users u ON sf.created_by = u.id
                WHERE sf.fee_id = ?
            ");
            $stmt->execute([$id]);
            $fee = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fee) {
                throw new Exception("Service fee record not found");
            }

            echo json_encode(['status' => 'success', 'data' => $fee]);
            exit;
        }

        // Mode 3: List Service Fees
        $showAll = isset($_GET['all']) && $_GET['all'] === '1';

        if ($showAll) {
            $stmt = $conn->prepare("
                SELECT sf.fee_id, sf.fee_ref, sf.buyer_id, sf.buyer_name,
                       COALESCE(b.buyer_name, sf.buyer_name) AS display_name,
                       b.shop_name, b.contact_number,
                       sf.fee_type, sf.description,
                       sf.amount, sf.paid_amount,
                       (sf.amount - sf.paid_amount) as balance,
                       sf.payment_status, sf.due_date, sf.sale_ref, sf.notes,
                       sf.created_at,
                       CASE 
                           WHEN sf.due_date < CURDATE() AND sf.payment_status IN ('pending', 'partial') THEN 'overdue'
                           WHEN sf.due_date = CURDATE() AND sf.payment_status IN ('pending', 'partial') THEN 'due_today'
                           ELSE sf.payment_status
                       END AS status_label,
                       DATEDIFF(sf.due_date, CURDATE()) AS days_until_due,
                       u.full_name AS created_by_name
                FROM service_fees sf
                LEFT JOIN buyers b ON sf.buyer_id = b.buyer_id
                LEFT JOIN users u ON sf.created_by = u.id
                ORDER BY sf.due_date ASC
            ");
        } else {
            $stmt = $conn->prepare("SELECT * FROM v_pending_service_fees ORDER BY due_date ASC");
        }

        $stmt->execute();
        $fees = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $fees]);
        exit;
    }

    // =====================================================
    // POST Request: Create Fee, Record Payment, or Update
    // =====================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);

        // ---- Action: Create New Service Fee ----
        if (isset($data['action']) && $data['action'] === 'create') {
            if (empty($data['amount']) || floatval($data['amount']) <= 0) {
                throw new Exception("Amount must be greater than 0");
            }

            $feeRef = 'SVC-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $buyerId = !empty($data['buyer_id']) ? intval($data['buyer_id']) : null;
            $buyerName = $data['buyer_name'] ?? null;
            $feeType = $data['fee_type'] ?? 'shipping';
            $description = $data['description'] ?? null;
            $amount = floatval($data['amount']);
            $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;
            $saleRef = $data['sale_ref'] ?? null;
            $notes = $data['notes'] ?? null;
            $userId = $_SESSION['user_id'];

            $stmt = $conn->prepare("
                INSERT INTO service_fees 
                (fee_ref, buyer_id, buyer_name, fee_type, description, amount, due_date, sale_ref, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $feeRef,
                $buyerId,
                $buyerName,
                $feeType,
                $description,
                $amount,
                $dueDate,
                $saleRef,
                $notes,
                $userId
            ]);

            $feeId = $conn->lastInsertId();

            echo json_encode([
                'status' => 'success',
                'message' => 'Service fee created successfully',
                'fee_id' => $feeId,
                'fee_ref' => $feeRef
            ]);
            exit;
        }

        // ---- Action: Update Due Date ----
        if (isset($data['action']) && $data['action'] === 'update_due_date') {
            if (!isset($data['fee_id'], $data['due_date'])) {
                throw new Exception("Missing required fields for date update");
            }

            $feeId = intval($data['fee_id']);
            $dueDate = $data['due_date'];

            $stmt = $conn->prepare("UPDATE service_fees SET due_date = ?, updated_at = NOW() WHERE fee_id = ?");
            $stmt->execute([$dueDate, $feeId]);

            echo json_encode(['status' => 'success', 'message' => 'Due date updated successfully']);
            exit;
        }

        // ---- Action: Void Service Fee ----
        if (isset($data['action']) && $data['action'] === 'void') {
            if (!isset($data['fee_id'])) {
                throw new Exception("Missing required field: fee_id");
            }

            $feeId = intval($data['fee_id']);

            // Check if there are already payments
            $stmt = $conn->prepare("SELECT paid_amount FROM service_fees WHERE fee_id = ?");
            $stmt->execute([$feeId]);
            $fee = $stmt->fetch();

            if ($fee && floatval($fee['paid_amount']) > 0) {
                throw new Exception("Cannot void a fee that has existing payments. Reset payments first if you wish to void.");
            }

            $stmt = $conn->prepare("UPDATE service_fees SET payment_status = 'voided', updated_at = NOW() WHERE fee_id = ?");
            $stmt->execute([$feeId]);

            echo json_encode(['status' => 'success', 'message' => 'Service fee voided successfully']);
            exit;
        }

        // ---- Action: Edit Amount ----
        if (isset($data['action']) && $data['action'] === 'edit_amount') {
            if (!isset($data['fee_id'], $data['amount'])) {
                throw new Exception("Missing required fields: fee_id and amount");
            }

            $feeId = intval($data['fee_id']);
            $newAmount = floatval($data['amount']);

            if ($newAmount <= 0) {
                throw new Exception("Amount must be greater than 0");
            }

            // Check if new amount is less than already paid amount
            $stmt = $conn->prepare("SELECT paid_amount FROM service_fees WHERE fee_id = ?");
            $stmt->execute([$feeId]);
            $fee = $stmt->fetch();

            if ($fee && $newAmount < floatval($fee['paid_amount'])) {
                throw new Exception("New amount cannot be less than the already paid amount (₱" . number_format($fee['paid_amount'], 2) . ")");
            }

            // Update amount and status if needed
            $newStatus = 'pending';
            if (floatval($fee['paid_amount']) > 0) {
                $newStatus = ($newAmount <= floatval($fee['paid_amount']) + 0.01) ? 'paid' : 'partial';
            }

            $stmt = $conn->prepare("UPDATE service_fees SET amount = ?, payment_status = ?, updated_at = NOW() WHERE fee_id = ?");
            $stmt->execute([$newAmount, $newStatus, $feeId]);

            echo json_encode(['status' => 'success', 'message' => 'Amount updated successfully']);
            exit;
        }

        // ---- Action: Record Payment (Settlement) ----
        if (!isset($data['fee_id'], $data['amount'])) {
            throw new Exception("Missing required fields: fee_id and amount");
        }

        $feeId = intval($data['fee_id']);
        $amountPaying = floatval($data['amount']);
        $method = $data['payment_method'] ?? 'cash';
        $refNo = $data['reference_no'] ?? null;
        $notes = $data['notes'] ?? null;
        $userId = $_SESSION['user_id'];

        if ($amountPaying <= 0) {
            throw new Exception("Amount must be greater than 0");
        }

        $conn->beginTransaction();

        try {
            // 1. Get Current Fee Info
            $stmt = $conn->prepare("SELECT * FROM service_fees WHERE fee_id = ?");
            $stmt->execute([$feeId]);
            $feeRecord = $stmt->fetch();

            if (!$feeRecord) {
                throw new Exception("Service fee record not found");
            }

            if ($feeRecord['payment_status'] === 'voided') {
                throw new Exception("Cannot record payment for a voided service fee.");
            }

            if ($feeRecord['payment_status'] === 'paid') {
                throw new Exception("This service fee has already been fully paid.");
            }

            $currentPaid = floatval($feeRecord['paid_amount']);
            $totalDue = floatval($feeRecord['amount']);
            $newPaidTotal = $currentPaid + $amountPaying;

            // 2. Insert into service_fee_payments (history)
            $stmt = $conn->prepare("
                INSERT INTO service_fee_payments 
                (fee_id, amount, payment_method, reference_no, notes, collected_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$feeId, $amountPaying, $method, $refNo, $notes, $userId]);
            $historyId = $conn->lastInsertId();

            // 3. Update service_fees
            $newStatus = 'partial';
            if ($newPaidTotal >= $totalDue - 0.01) { // Floating point tolerance
                $newStatus = 'paid';
            }

            $stmt = $conn->prepare("
                UPDATE service_fees 
                SET paid_amount = paid_amount + ?, 
                    payment_status = ?,
                    updated_at = NOW()
                WHERE fee_id = ?
            ");
            $stmt->execute([$amountPaying, $newStatus, $feeId]);

            $conn->commit();
            echo json_encode([
                'status' => 'success',
                'message' => 'Payment recorded successfully',
                'history_id' => $historyId,
                'new_balance' => $totalDue - $newPaidTotal,
                'new_status' => $newStatus
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
