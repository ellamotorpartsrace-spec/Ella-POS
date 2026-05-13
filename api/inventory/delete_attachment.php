<?php
// api/inventory/delete_attachment.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Only allow admin/manager
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin/Manager access required to delete attachments.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$id = $_POST['id'] ?? null;
$image_path = $_POST['image_path'] ?? null;

if (!$id && !$image_path) {
    echo json_encode(['success' => false, 'error' => 'ID or Image path is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Find the record
    if ($id) {
        $stmt = $conn->prepare("SELECT id, image_path FROM reference_attachments WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // Clean up path - remove leading slash if any
        $cleaned_path = ltrim($image_path, '/');
        $stmt = $conn->prepare("SELECT id, image_path FROM reference_attachments WHERE image_path = ? OR image_path = ?");
        $stmt->execute([$cleaned_path, '/' . $cleaned_path]);
    }

    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        echo json_encode(['success' => false, 'error' => 'Attachment record not found in database.']);
        exit;
    }

    $actual_path = $attachment['image_path'];
    $id_to_delete = $attachment['id'];

    // 2. Delete from database
    $stmtDelete = $conn->prepare("DELETE FROM reference_attachments WHERE id = ?");
    $stmtDelete->execute([$id_to_delete]);

    // 3. Delete physical file
    // Clean actual path for file system
    $file_path_on_disk = ltrim($actual_path, '/');
    $fullPath = '../../' . $file_path_on_disk;

    $file_deleted = false;
    if (file_exists($fullPath)) {
        if (unlink($fullPath)) {
            $file_deleted = true;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Attachment deleted successfully',
        'file_deleted' => $file_deleted
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
