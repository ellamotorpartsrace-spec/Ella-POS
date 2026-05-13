<?php
// api/payables/create_payment.php
// Creates a new supplier payment record when receiving stock/PO

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    $data = json_decode(file_get_contents("php://input"), true);

    // Validate required fields
    if (!isset($data['po_id'], $data['amount'])) {
        throw new Exception("Missing required fields: po_id and amount");
    }

    $po_id = intval($data['po_id']);
    $amount = floatval($data['amount']);
    $due_date = $data['due_date'] ?? null;
    $notes = $data['notes'] ?? null;
    $user_id = $_SESSION['user_id'];

    if ($amount <= 0) {
        throw new Exception("Amount must be greater than 0");
    }

    $database = new Database();
    $conn = $database->getConnection();

    // Verify PO exists and get supplier_id
    $stmt = $conn->prepare("SELECT po_id, supplier_id, total_amount FROM purchase_orders WHERE po_id = ?");
    $stmt->execute([$po_id]);
    $po = $stmt->fetch();

    if (!$po) {
        throw new Exception("Purchase Order not found");
    }

    // Check if payment record already exists for this PO
    $stmt = $conn->prepare("SELECT payment_id FROM supplier_payments WHERE po_id = ?");
    $stmt->execute([$po_id]);
    if ($stmt->fetch()) {
        throw new Exception("Payment record already exists for this Purchase Order");
    }

    // Create supplier payment record
    $stmt = $conn->prepare("
        INSERT INTO supplier_payments 
        (po_id, supplier_id, amount, due_date, notes, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $po_id,
        $po['supplier_id'],
        $amount,
        $due_date,
        $notes,
        $user_id
    ]);

    $payment_id = $conn->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'Supplier payment record created',
        'payment_id' => $payment_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
