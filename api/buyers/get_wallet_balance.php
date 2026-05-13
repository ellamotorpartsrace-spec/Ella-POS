<?php
// api/buyers/get_wallet_balance.php
// Returns the current wallet balance for a buyer (used in real-time modal display)
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

requireLogin();

$buyerId = intval($_GET['buyer_id'] ?? 0);

if (!$buyerId) {
    echo json_encode(['success' => false, 'message' => 'Invalid buyer ID.']);
    exit;
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT buyer_name, wallet_balance FROM buyers WHERE buyer_id = ?");
    $stmt->execute([$buyerId]);
    $buyer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$buyer) {
        echo json_encode(['success' => false, 'message' => 'Buyer not found.']);
        exit;
    }

    echo json_encode([
        'success'        => true,
        'buyer_name'     => $buyer['buyer_name'],
        'balance'        => (float) $buyer['wallet_balance'],
        'wallet_balance' => (float) $buyer['wallet_balance']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
