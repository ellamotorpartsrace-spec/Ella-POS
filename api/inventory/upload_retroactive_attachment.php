<?php
// api/inventory/upload_retroactive_attachment.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Only allow admin/manager/stockman
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'stockman'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$movement_id = $_POST['movement_id'] ?? null;
$ref = $_POST['reference_number'] ?? null;

if (!$movement_id && !$ref) {
    echo json_encode(['success' => false, 'error' => 'Reference Number or Movement ID is required']);
    exit;
}

if (empty($_FILES['reference_images']['name'][0])) {
    echo json_encode(['success' => false, 'error' => 'No images provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get the reference if not provided
    $wasEmptyRef = false;
    if (!$ref && $movement_id) {
        $stmt = $conn->prepare("SELECT reference FROM stock_movements WHERE movement_id = ?");
        $stmt->execute([$movement_id]);
        $movement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$movement) {
            echo json_encode(['success' => false, 'error' => 'Stock movement not found']);
            exit;
        }

        $ref = $movement['reference'];

        // 2. Generate a reference if it doesn't have one
        if (empty($ref)) {
            $ref = 'REF-RETRO-' . date('YmdHis') . '-' . $movement_id;
            $wasEmptyRef = true;
        }
    }

    // 3. Check existing attachments count
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM reference_attachments WHERE reference_number = ?");
    $stmtCount->execute([$ref]);
    $existingCount = $stmtCount->fetchColumn();

    $newFilesCount = count($_FILES['reference_images']['name']);
    if ($existingCount + $newFilesCount > 8) {
        echo json_encode(['success' => false, 'error' => "Limit reached. A reference can have a maximum of 8 images. (Already has $existingCount)"]);
        exit;
    }

    // 4. Upload the files
    $uploadDir = '../../assets/uploads/references/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $uploadedPaths = [];
    foreach ($_FILES['reference_images']['tmp_name'] as $key => $tmpName) {
        if ($_FILES['reference_images']['error'][$key] !== UPLOAD_ERR_OK) {
            continue;
        }

        $fileName = $_FILES['reference_images']['name'][$key];
        $newFileName = 'ref_retro_' . time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9.]/', '', $fileName);
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($tmpName, $destPath)) {
            $uploadedPaths[] = 'assets/uploads/references/' . $newFileName;
        }
    }

    if (empty($uploadedPaths)) {
        echo json_encode(['success' => false, 'error' => 'Failed to upload any files.']);
        exit;
    }

    // 5. Save to database
    $conn->beginTransaction();

    // Update movement reference if it was empty
    if ($wasEmptyRef) {
        $conn->prepare("UPDATE stock_movements SET reference = ? WHERE movement_id = ?")->execute([$ref, $movement_id]);
    }

    // Insert attachments
    $stmtInsert = $conn->prepare("INSERT INTO reference_attachments (reference_number, image_path) VALUES (?, ?)");
    foreach ($uploadedPaths as $path) {
        $stmtInsert->execute([$ref, $path]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => count($uploadedPaths) . ' image(s) attached successfully',
        'reference' => $ref,
        'was_empty' => $wasEmptyRef
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
