<?php
// database/seed_printer_settings.php
// Run once to add ESC/POS printer settings to system_settings table
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $settings = [
        ['printer_mode',          'browser'],
        ['printer_connection',    'network'],
        ['printer_address',       ''],
        ['printer_paper_width',   '80']
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($settings as $s) {
        $stmt->execute($s);
    }

    echo "✅ ESC/POS printer settings seeded successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
