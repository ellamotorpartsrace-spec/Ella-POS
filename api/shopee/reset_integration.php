<?php
// api/shopee/reset_integration.php — Securely wipe all imported Shopee data and start fresh
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Secure: check login and role
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Disable foreign key checks for clean truncation
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // 1. Wipe imported Shopee product mappings
    $conn->exec("TRUNCATE TABLE shopee_product_mappings");
    
    // 2. Wipe open and closed conflict error logs
    $conn->exec("TRUNCATE TABLE shopee_error_logs");
    
    // 3. Wipe sync process queues
    $conn->exec("TRUNCATE TABLE shopee_sync_queues");
    
    // 4. Wipe operation history logs
    $conn->exec("TRUNCATE TABLE shopee_sync_logs");
    
    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 5. Add a fresh audit log entry to record the system wipe
    $stmt = $conn->prepare("
        INSERT INTO shopee_sync_logs (event_type, source, status, new_value, created_by, created_at)
        VALUES ('system_reset', 'Database Administrator', 'success', 'All imported products, mappings, conflict logs, and sync queues cleared to start fresh.', ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'] ?? null]);
    
    echo json_encode(['success' => true, 'message' => 'Integration data successfully wiped. You can now perform a fresh sync!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Wipe failed: ' . $e->getMessage()]);
}
