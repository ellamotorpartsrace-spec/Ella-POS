<?php
/**
 * POS Preview Receipt Feature Migration
 * 
 * 1. Adds 'enable_pos_preview' to system_settings (Global Toggle)
 * 2. Adds 'pos_preview_receipt' to permissions (Granular Control)
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    echo "Starting migration...\n";

    // 1. Add Global Toggle to system_settings
    $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('enable_pos_preview', '1')");
    $stmt->execute();
    echo "- Global Setting 'enable_pos_preview' added to system_settings (Default: Enabled).\n";

    // 2. Add Permission to permissions table
    $stmt = $conn->prepare("INSERT IGNORE INTO permissions (slug, name, description, module) VALUES ('pos_preview_receipt', 'Preview Receipt', 'Allow users to preview receipt in Point of Sale checkout.', 'Sales')");
    $stmt->execute();
    echo "- Permission 'pos_preview_receipt' added to permissions (Module: Sales).\n";

    echo "\nMigration completed successfully!\n";
    echo "You can now manage the global toggle in System Settings and granular access in Roles & Permissions.\n";

} catch (Exception $e) {
    echo "\nError during migration: " . $e->getMessage() . "\n";
}
