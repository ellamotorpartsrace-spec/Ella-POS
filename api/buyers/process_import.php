<?php
// api/buyers/process_import.php - Process CSV Import/Update for Buyers
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    header("Location: " . BASE_URL . "views/buyers/index.php?error=Invalid request");
    exit;
}

$file = $_FILES['csv_file']['tmp_name'];
if (!is_uploaded_file($file)) {
    header("Location: " . BASE_URL . "views/buyers/index.php?error=File upload failed");
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $handle = fopen($file, 'r');
    
    // Check for BOM and skip it
    $bom = fread($handle, 3);
    if ($bom != "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Read headers
    $headers = fgetcsv($handle);
    if (!$headers) {
        throw new Exception("Empty CSV file");
    }

    // Map headers to column indices (flexible mapping)
    $fieldMap = [
        'id' => array_search('Buyer ID', $headers),
        'name' => array_search('Buyer Name', $headers),
        'shop' => array_search('Shop Name', $headers),
        'contact' => array_search('Contact Number', $headers),
        'email' => array_search('Email', $headers),
        'address' => array_search('Address', $headers),
        'tier' => array_search('Price Tier', $headers),
        'walkin' => array_search('Is Walk-in (0/1)', $headers),
        'limit' => array_search('Credit Limit', $headers),
        'notes' => array_search('Credit Notes', $headers)
    ];

    // If 'Buyer Name' is not found, try index 0 (if 1st col is name in template)
    if ($fieldMap['name'] === false) $fieldMap['name'] = 0;

    $successCount = 0;
    $errorCount = 0;

    while (($data = fgetcsv($handle)) !== false) {
        // Skip empty rows
        if (empty(array_filter($data))) continue;

        $id      = $fieldMap['id'] !== false ? ($data[$fieldMap['id']] ?? '') : '';
        $name    = $fieldMap['name'] !== false ? trim($data[$fieldMap['name']] ?? '') : '';
        $shop    = $fieldMap['shop'] !== false ? trim($data[$fieldMap['shop']] ?? '') : '';
        $contact = $fieldMap['contact'] !== false ? trim($data[$fieldMap['contact']] ?? '') : '';
        $email   = $fieldMap['email'] !== false ? trim($data[$fieldMap['email']] ?? '') : '';
        $address = $fieldMap['address'] !== false ? trim($data[$fieldMap['address']] ?? '') : '';
        $tier    = $fieldMap['tier'] !== false ? strtolower(trim($data[$fieldMap['tier']] ?? 'retail')) : 'retail';
        $walkin  = $fieldMap['walkin'] !== false ? (int)($data[$fieldMap['walkin']] ?? 0) : 0;
        $limit   = $fieldMap['limit'] !== false && ($data[$fieldMap['limit']] ?? '') !== '' ? floatval($data[$fieldMap['limit']]) : null;
        $notes   = $fieldMap['notes'] !== false ? trim($data[$fieldMap['notes']] ?? '') : '';

        if (empty($name)) {
            $errorCount++;
            continue;
        }

        // Validate tier
        if (!in_array($tier, ['retail', 'wholesale', 'dealer'])) {
            $tier = 'retail';
        }

        if (!empty($id)) {
            // Check if ID exists
            $check = $conn->prepare("SELECT buyer_id FROM buyers WHERE buyer_id = ?");
            $check->execute([$id]);
            if ($check->fetch()) {
                // UPDATE
                $sql = "UPDATE buyers SET buyer_name=?, shop_name=?, contact_number=?, email=?, address=?, price_tier=?, is_walkin=?, credit_limit=?, credit_notes=? 
                        WHERE buyer_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$name, $shop, $contact, $email, $address, $tier, $walkin, $limit, $notes, $id]);
                $successCount++;
                continue;
            }
        }

        // INSERT (if ID not found or not provided)
        $sql = "INSERT INTO buyers (buyer_name, shop_name, contact_number, email, address, price_tier, is_walkin, credit_limit, credit_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $shop, $contact, $email, $address, $tier, $walkin, $limit, $notes]);
        $successCount++;
    }

    fclose($handle);
    
    $msg = "Successfully processed $successCount records.";
    if ($errorCount > 0) $msg .= " Skipped $errorCount invalid rows.";
    
    header("Location: " . BASE_URL . "views/buyers/index.php?success=1&msg=" . urlencode($msg));

} catch (Exception $e) {
    header("Location: " . BASE_URL . "views/buyers/index.php?error=" . urlencode($e->getMessage()));
}
exit;
