-- POS Preview Receipt Migration SQL
-- Run this in your database manager (e.g., phpMyAdmin) to enable the POS Preview Receipt feature controls.

-- 1. Add Global Toggle to system_settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) 
VALUES ('enable_pos_preview', '1');

-- 2. Add Permission to permissions table (categorized under Sales)
INSERT IGNORE INTO permissions (slug, name, description, module) 
VALUES ('pos_preview_receipt', 'Preview Receipt', 'Allow users to preview receipt in Point of Sale checkout.', 'Sales');
