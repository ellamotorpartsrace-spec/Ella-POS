<?php
// api/pos/wallet_adjustment.php
// Manual wallet credit/debit tied to an existing sale reference (price correction)
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';
require_once '../../config/database.php';

requireLogin();

// Only admins or managers can make manual wallet adjustments
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$buyerId = intval($data['buyer_id'] ?? 0);
$saleRef = trim($data['sale_ref'] ?? '');
$amount = floatval($data['amount'] ?? 0);
$type = $data['type'] ?? 'credit';   // 'credit' or 'debit'
$reason = trim($data['reason'] ?? '');
$userId = $_SESSION['user_id'] ?? null;

// Validation
if (!$buyerId) {
    echo json_encode(['success' => false, 'message' => 'Invalid buyer.']);
    exit;
}
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be positive.']);
    exit;
}
if (!in_array($type, ['credit', 'debit'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid type.']);
    exit;
}
if (!$reason) {
    echo json_encode(['success' => false, 'message' => 'Reason is required.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $conn->beginTransaction();

    // Fetch current balance
    $stmt = $conn->prepare("SELECT wallet_balance, buyer_name FROM buyers WHERE buyer_id = ? FOR UPDATE");
    $stmt->execute([$buyerId]);
    $buyer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$buyer) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Buyer not found.']);
        exit;
    }

    $currentBalance = (float) $buyer['wallet_balance'];
    $newBalance = ($type === 'credit')
        ? $currentBalance + $amount
        : $currentBalance - $amount;

    // Update wallet
    $upd = $conn->prepare("UPDATE buyers SET wallet_balance = ? WHERE buyer_id = ?");
    $upd->execute([$newBalance, $buyerId]);

    // Log to buyer_wallet_logs
    $logRemarks = ($type === 'credit' ? '[CREDIT ADJ] ' : '[DEBIT ADJ] ') . $reason;
    $log = $conn->prepare("
        INSERT INTO buyer_wallet_logs
            (buyer_id, user_id, type, amount, balance_after, reference_type, reference_id, remarks)
        VALUES (?, ?, ?, ?, ?, 'sale', ?, ?)
    ");
    $log->execute([$buyerId, $userId, $type, $amount, $newBalance, $saleRef, $logRemarks]);

    // Activity log
    logActivity(
        $conn,
        $userId,
        'WALLET_ADJUSTMENT',
        'POS',
        "Manual wallet {$type} of ₱{$amount} for buyer #{$buyerId} ({$buyer['buyer_name']}) on sale {$saleRef}. Reason: {$reason}",
        null
    );

    $conn->commit();

    echo json_encode([
        'success' => true,
        'new_balance' => $newBalance,
        'message' => "Wallet {$type} of ₱" . number_format($amount, 2) . " applied successfully."
    ]);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
