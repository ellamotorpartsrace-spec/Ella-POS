<?php
// api/payables/upload_payment_proof.php
// Handles multiple image uploads for payment proofs

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Validate required fields
    if (!isset($_POST['supplier_payment_id'])) {
        throw new Exception("Missing supplier_payment_id");
    }

    $supplier_payment_id = intval($_POST['supplier_payment_id']);
    $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : null;
    $user_id = $_SESSION['user_id'];

    // Check if files were uploaded
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        throw new Exception("No images uploaded");
    }

    $database = new Database();
    $conn = $database->getConnection();

    // Verify the payment exists
    $stmt = $conn->prepare("SELECT payment_id FROM supplier_payments WHERE payment_id = ?");
    $stmt->execute([$supplier_payment_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Payment record not found");
    }

    // If history_id provided, verify it exists
    if ($history_id) {
        $stmt = $conn->prepare("SELECT history_id FROM supplier_payment_history WHERE history_id = ?");
        $stmt->execute([$history_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Payment history record not found");
        }
    }

    // Setup upload directory
    $upload_dir = '../../assets/uploads/payment_proofs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Allowed file types
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $uploaded_files = [];
    $errors = [];

    // Process each uploaded file
    $files = $_FILES['images'];
    $file_count = count($files['name']);

    $conn->beginTransaction();

    try {
        for ($i = 0; $i < $file_count; $i++) {
            $name = $files['name'][$i];
            $type = $files['type'][$i];
            $tmp_name = $files['tmp_name'][$i];
            $error = $files['error'][$i];
            $size = $files['size'][$i];

            // Skip empty slots
            if (empty($name)) continue;

            // Check for upload errors
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = "File '$name' upload error: $error";
                continue;
            }

            // Validate file type
            if (!in_array($type, $allowed_types)) {
                $errors[] = "File '$name' is not an allowed image type";
                continue;
            }

            // Validate file size
            if ($size > $max_size) {
                $errors[] = "File '$name' exceeds maximum size of 5MB";
                continue;
            }

            // Generate unique filename
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $new_filename = 'proof_' . $supplier_payment_id . '_' . time() . '_' . $i . '.' . $ext;
            $destination = $upload_dir . $new_filename;

            // Move uploaded file
            if (move_uploaded_file($tmp_name, $destination)) {
                // Store relative path for database
                $relative_path = 'assets/uploads/payment_proofs/' . $new_filename;

                // Insert into database
                $stmt = $conn->prepare("
                    INSERT INTO supplier_payment_attachments 
                    (supplier_payment_id, history_id, image_path, original_filename, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $supplier_payment_id,
                    $history_id,
                    $relative_path,
                    $name,
                    $user_id
                ]);

                $uploaded_files[] = [
                    'id' => $conn->lastInsertId(),
                    'path' => $relative_path,
                    'original_name' => $name
                ];
            } else {
                $errors[] = "Failed to save file '$name'";
            }
        }

        $conn->commit();

        $response = [
            'status' => 'success',
            'message' => count($uploaded_files) . ' file(s) uploaded successfully',
            'uploaded' => $uploaded_files
        ];

        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }

        echo json_encode($response);

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
