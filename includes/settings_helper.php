<?php
// includes/settings_helper.php - System Settings Helper
// Provides functions to read settings from the database

/**
 * Get all system settings as key => value array.
 * Results are cached for the duration of the request.
 */
function getSettings($conn) {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $cache = $rows ?: [];
    } catch (Exception $e) {
        $cache = [];
    }
    return $cache;
}

/**
 * Get a single setting value.
 * @param PDO $conn
 * @param string $key
 * @param string $default
 * @return string
 */
function getSetting($conn, $key, $default = '') {
    $settings = getSettings($conn);
    return $settings[$key] ?? $default;
}

/**
 * Get all settings as a JSON-safe array for injecting into JavaScript.
 */
function getSettingsForJS($conn) {
    $settings = getSettings($conn);
    // Only expose safe keys to JS (no sensitive data)
    $jsKeys = [
        'store_name', 'store_address', 'store_contact',
        'store_facebook', 'store_tax_id', 'receipt_footer',
        'currency_symbol',
        // Receipt template toggles
        'receipt_show_store_name', 'receipt_show_address', 'receipt_show_contact',
        'receipt_show_facebook', 'receipt_show_tax_id', 'receipt_show_cashier',
        'receipt_show_customer', 'receipt_show_item_discount', 'receipt_show_payment_method',
        'receipt_header_text', 'receipt_footer_note',
        // Printer settings
        'printer_mode', 'printer_connection', 'printer_address', 'printer_paper_width',
        'enable_pos_preview'
    ];
    $jsSettings = [];
    foreach ($jsKeys as $k) {
        $jsSettings[$k] = $settings[$k] ?? '';
    }
    return $jsSettings;
}
