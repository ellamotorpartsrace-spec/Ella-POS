<?php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Admin or manager access required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$queueId = $data['queue_id'] ?? null;
$status = $data['status'] ?? 'completed'; // completed or failed

if (!$queueId) {
    echo json_encode(['success' => false, 'error' => 'No queue_id provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("UPDATE shopee_sync_queues SET status = ?, completed_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $queueId]);

    // Automatically execute conflict detection to update shopee_error_logs and stats in real-time
    if ($status === 'completed') {
        require_once __DIR__ . '/detect_conflicts.php';
        runConflictDetection($conn);

        // Fetch cumulative statistics for a single unified sync log entry
        $qStmt = $conn->prepare("SELECT * FROM shopee_sync_queues WHERE id = ?");
        $qStmt->execute([$queueId]);
        $queue = $qStmt->fetch(PDO::FETCH_ASSOC);

        if ($queue) {
            $modeLabel = ucfirst($queue['sync_mode'] ?? 'smart') . ' Sync';
            $conn->prepare("
                INSERT INTO shopee_sync_logs (event_type, product_name, source, status, new_value, created_by, created_at)
                VALUES ('product_import', ?, 'Shopee API Import', 'success', ?, ?, NOW())
            ")->execute([
                "Shopee Catalog Synchronization ({$modeLabel})",
                "Processed: {$queue['processed_items']} items (Inserted: {$queue['inserted_count']}, Updated: {$queue['updated_count']}, Skipped: {$queue['skipped_count']})",
                $_SESSION['user_id'] ?? null
            ]);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
