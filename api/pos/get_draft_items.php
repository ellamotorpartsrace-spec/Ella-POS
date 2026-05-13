<?php
// api/pos/get_draft_items.php
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

$draftId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$draftId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Draft ID required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch draft items
    $sql = "SELECT di.*, pv.sku 
            FROM pos_draft_items di
            LEFT JOIN product_variations pv ON di.variation_id = pv.variation_id
            WHERE di.draft_id = :draft_id
            ORDER BY di.item_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':draft_id' => $draftId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
