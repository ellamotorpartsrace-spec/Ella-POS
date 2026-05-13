<?php
// database/seed_receipt_settings.php
// Run once to add receipt toggle settings to system_settings table
require_once '../config/config.php';
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $settings = [
        ['receipt_show_store_name',     '1', 'Show store name on receipt'],
        ['receipt_show_address',        '1', 'Show store address on receipt'],
        ['receipt_show_contact',        '1', 'Show contact number on receipt'],
        ['receipt_show_facebook',       '1', 'Show Facebook page on receipt'],
        ['receipt_show_tax_id',         '1', 'Show tax ID on receipt'],
        ['receipt_show_cashier',        '1', 'Show cashier name on receipt'],
        ['receipt_show_customer',       '1', 'Show customer name on receipt'],
        ['receipt_show_item_discount',  '1', 'Show per-item discount details'],
        ['receipt_show_payment_method', '1', 'Show payment method on receipt'],
        ['receipt_header_text',         '',  'Extra header text below store name'],
        ['receipt_footer_note',         '',  'Additional footer note above main footer']
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");

    foreach ($settings as $s) {
        $stmt->execute($s);
    }

    echo "✅ Receipt template settings seeded successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
