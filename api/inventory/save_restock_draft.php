<?php
// api/inventory/save_restock_draft.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No items in batch']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $userId = $_SESSION['user_id'];
    $supplierId = !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null;
    $supplierName = $data['supplier_name'] ?? null;
    $reference = $data['reference'] ?? null;
    $paymentStatus = $data['payment_status'] ?? 'paid';
    $draftLabel = $data['draft_label'] ?? null;
    $totalAmount = floatval($data['total_amount'] ?? 0);
    
    // Convert arrays to JSON strings for storage
    $itemsJson = json_encode($data['items']);
    $termsJson = !empty($data['credit_terms']) ? json_encode($data['credit_terms']) : null;

    $sql = "INSERT INTO restock_drafts 
            (user_id, supplier_id, supplier_name, reference, payment_status, 
             items, credit_terms, draft_label, total_amount)
            VALUES 
            (:user_id, :supplier_id, :supplier_name, :reference, :payment_status,
             :items, :credit_terms, :draft_label, :total_amount)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':supplier_id' => $supplierId,
        ':supplier_name' => $supplierName,
        ':reference' => $reference,
        ':payment_status' => $paymentStatus,
        ':items' => $itemsJson,
        ':credit_terms' => $termsJson,
        ':draft_label' => $draftLabel,
        ':total_amount' => $totalAmount
    ]);

    $draftId = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'draft_id' => $draftId,
        'message' => 'Restock draft saved successfully'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
