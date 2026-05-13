<?php
// api/inventory/admin_copy_restock_draft.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Must be logged in and admin
requireLogin();
if (!hasPermission('manage_settings') && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$originalDraftId = isset($data['draft_id']) ? (int) $data['draft_id'] : 0;

if (!$originalDraftId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Draft ID required']);
    exit;
}

// Release session lock
session_write_close();

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $userId = $_SESSION['user_id'];

    // 1. Fetch original draft
    $stmt = $conn->prepare("SELECT * FROM restock_drafts WHERE draft_id = :draft_id");
    $stmt->execute([':draft_id' => $originalDraftId]);
    $originalDraft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$originalDraft) {
        throw new Exception("Original restock draft not found.");
    }

    // 2. Insert new draft for current admin
    $newLabel = ($originalDraft['draft_label'] ?: 'Restock Draft') . ' (Copy)';
    $sql = "INSERT INTO restock_drafts 
            (user_id, supplier_id, supplier_name, reference, payment_status, 
             items, credit_terms, draft_label, total_amount)
            VALUES 
            (:user_id, :supplier_id, :supplier_name, :reference, :payment_status,
             :items, :credit_terms, :draft_label, :total_amount)";

    $insertStmt = $conn->prepare($sql);
    $insertStmt->execute([
        ':user_id' => $userId,
        ':supplier_id' => $originalDraft['supplier_id'],
        ':supplier_name' => $originalDraft['supplier_name'],
        ':reference' => $originalDraft['reference'],
        ':payment_status' => $originalDraft['payment_status'],
        ':items' => $originalDraft['items'],
        ':credit_terms' => $originalDraft['credit_terms'],
        ':draft_label' => $newLabel,
        ':total_amount' => $originalDraft['total_amount']
    ]);

    $newDraftId = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'new_draft_id' => $newDraftId,
        'message' => 'Restock draft copied successfully'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
