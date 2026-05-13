<?php
// api/receivables/ar_note.php — Save/update a note on a pos_sale_payments record
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $payment_id = isset($data['payment_id']) ? intval($data['payment_id']) : 0;
    $note       = isset($data['note']) ? trim($data['note']) : '';

    if ($payment_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid payment_id']);
        exit;
    }

    $db   = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("UPDATE pos_sale_payments SET notes = ? WHERE payment_id = ?");
    $stmt->execute([$note === '' ? null : $note, $payment_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Payment record not found']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Note saved']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
