<?php
// api/service_fees/upload_proof.php
// Handles multiple image uploads for service fee payment proofs
// Mirrors payables/upload_payment_proof.php

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
    if (!isset($_POST['fee_id'])) {
        throw new Exception("Missing fee_id");
    }

    $fee_id = intval($_POST['fee_id']);
    $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : null;
    $user_id = $_SESSION['user_id'];

    // Check if files were uploaded
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        throw new Exception("No images uploaded");
    }

    $database = new Database();
    $conn = $database->getConnection();

    // Verify the fee exists
    $stmt = $conn->prepare("SELECT fee_id FROM service_fees WHERE fee_id = ?");
    $stmt->execute([$fee_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Service fee record not found");
    }

    // If history_id provided, verify it exists
    if ($history_id) {
        $stmt = $conn->prepare("SELECT history_id FROM service_fee_payments WHERE history_id = ?");
        $stmt->execute([$history_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Payment history record not found");
        }
    }

    // Setup upload directory
    $upload_dir = '../../assets/uploads/service_fees/';
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
            $new_filename = 'svc_proof_' . $fee_id . '_' . time() . '_' . $i . '.' . $ext;
            $destination = $upload_dir . $new_filename;

            // Move uploaded file
            if (move_uploaded_file($tmp_name, $destination)) {
                // Store relative path for database
                $relative_path = 'assets/uploads/service_fees/' . $new_filename;

                // Insert into database
                $stmt = $conn->prepare("
                    INSERT INTO service_fee_attachments 
                    (fee_id, history_id, image_path, original_filename, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $fee_id,
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
