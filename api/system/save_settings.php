<?php
// api/system/save_settings.php - Save system settings (Admin only)
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

requireLogin();
requirePermission('manage_settings');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !is_array($input)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Allowed keys (whitelist)
$allowedKeys = [
    'store_name',
    'store_address',
    'store_contact',
    'store_facebook',
    'store_tax_id',
    'receipt_footer',
    'default_low_stock_threshold',
    'default_price_tier',
    'currency_symbol',
    'maintenance_mode',

    // Receipt Template toggles & texts
    'receipt_show_store_name',
    'receipt_show_address',
    'receipt_show_contact',
    'receipt_show_facebook',
    'receipt_show_tax_id',
    'receipt_show_cashier',
    'receipt_show_customer',
    'receipt_show_item_discount',
    'receipt_show_payment_method',
    'receipt_header_text',
    'receipt_footer_note',

    // Printer Settings
    'printer_mode',
    'printer_connection',
    'printer_address',
    'printer_paper_width',
    'enable_pos_preview'
];

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    $updated = 0;
    foreach ($input as $key => $value) {
        if (in_array($key, $allowedKeys)) {
            $stmt->execute([$key, trim($value)]);
            $updated++;
        }
    }

    $conn->commit();

    // Log the activity
    logActivity($conn, $_SESSION['user_id'], 'UPDATE_SETTINGS', 'Settings', "Updated system settings");

    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully',
        'updated' => $updated
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
