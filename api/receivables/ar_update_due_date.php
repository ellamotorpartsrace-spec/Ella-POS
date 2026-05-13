<?php
// api/receivables/ar_update_due_date.php
// PATCH endpoint: updates the due_date of a pos_sale_payments record (pay_later type).
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

requireLogin();

// Only admin / manager allowed to reschedule due dates
if ($_SESSION['role'] !== 'admin' && !hasPermission('view_profit') && !in_array($_SESSION['role'], ['manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $paymentId = isset($data['payment_id']) ? intval($data['payment_id']) : 0;
    $newDueDate = $data['due_date'] ?? '';

    if ($paymentId <= 0) {
        throw new Exception('Invalid payment ID.');
    }

    if (empty($newDueDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDueDate)) {
        throw new Exception('Invalid date format. Expected YYYY-MM-DD.');
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Verify the payment record exists and is pay_later type
    $check = $conn->prepare("SELECT payment_id, sale_id, amount, due_date, payment_status, payment_type FROM pos_sale_payments WHERE payment_id = ?");
    $check->execute([$paymentId]);
    $record = $check->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception('Payment record not found.');
    }

    if ($record['payment_type'] !== 'pay_later') {
        throw new Exception('Only "Pay Later" type payment schedules can have their due date updated.');
    }

    if ($record['payment_status'] === 'paid') {
        throw new Exception('Cannot change the due date of an already-paid payment.');
    }

    $oldDueDate = $record['due_date'];
    $saleId = $record['sale_id'];
    $amount = number_format($record['amount'], 2);

    // 1. Update the payment record
    $stmt = $conn->prepare("UPDATE pos_sale_payments SET due_date = ? WHERE payment_id = ?");
    $stmt->execute([$newDueDate, $paymentId]);

    // 2. Sync with pos_sales.remarks if it contains the "Schedules:" pattern
    $getSale = $conn->prepare("SELECT remarks, sale_ref FROM pos_sales WHERE sale_id = ?");
    $getSale->execute([$saleId]);
    $sale = $getSale->fetch(PDO::FETCH_ASSOC);

    if ($sale && !empty($sale['remarks'])) {
        $oldSearch = "{$oldDueDate} (₱{$amount})";
        $newReplace = "{$newDueDate} (₱{$amount})";

        if (str_contains($sale['remarks'], $oldSearch)) {
            $updatedRemarks = str_replace($oldSearch, $newReplace, $sale['remarks']);
            $updRemarks = $conn->prepare("UPDATE pos_sales SET remarks = ? WHERE sale_id = ?");
            $updRemarks->execute([$updatedRemarks, $saleId]);
        }
    }

    // Log activity
    logActivity(
        $conn,
        $_SESSION['user_id'],
        'DUE_DATE_UPDATED',
        'Receivables',
        "Updated due date for payment_id #{$paymentId} (Sale: {$sale['sale_ref']}) from {$oldDueDate} to {$newDueDate}",
        $paymentId
    );

    echo json_encode([
        'success' => true,
        'message' => 'Due date updated successfully.',
        'payment_id' => $paymentId,
        'new_due_date' => $newDueDate,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
