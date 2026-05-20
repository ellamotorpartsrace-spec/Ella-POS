<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Create shopee_sync_queues
    $conn->exec("
        CREATE TABLE IF NOT EXISTS shopee_sync_queues (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sync_mode VARCHAR(50) NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            total_items INT DEFAULT 0,
            processed_items INT DEFAULT 0,
            error_count INT DEFAULT 0,
            inserted_count INT DEFAULT 0,
            updated_count INT DEFAULT 0,
            skipped_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created shopee_sync_queues.\n";

    // Dynamic self-healing migration for existing shopee_sync_queues tables
    $colsQueue = $conn->query("SHOW COLUMNS FROM shopee_sync_queues LIKE 'inserted_count'")->fetchAll();
    if (empty($colsQueue)) {
        $conn->exec("ALTER TABLE shopee_sync_queues 
            ADD COLUMN inserted_count INT DEFAULT 0 AFTER error_count,
            ADD COLUMN updated_count INT DEFAULT 0 AFTER inserted_count,
            ADD COLUMN skipped_count INT DEFAULT 0 AFTER updated_count
        ");
        echo "Added count columns to shopee_sync_queues.\n";
    }

    // 2. Create shopee_audit_logs
    $conn->exec("
        CREATE TABLE IF NOT EXISTS shopee_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action_type VARCHAR(100) NOT NULL,
            target_type VARCHAR(100) NULL,
            target_id VARCHAR(100) NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created shopee_audit_logs.\n";

    // 3. Create shopee_error_logs
    $conn->exec("
        CREATE TABLE IF NOT EXISTS shopee_error_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            error_type VARCHAR(50) NOT NULL,
            shopee_item_id BIGINT NULL,
            shopee_model_id BIGINT NULL,
            sku VARCHAR(100) NULL,
            error_message TEXT NOT NULL,
            status ENUM('open', 'resolved', 'ignored') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created shopee_error_logs.\n";

    // 4. Create shopee_saved_filters
    $conn->exec("
        CREATE TABLE IF NOT EXISTS shopee_saved_filters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            page_name VARCHAR(50) NOT NULL,
            filter_name VARCHAR(100) NOT NULL,
            filter_config JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created shopee_saved_filters.\n";

    // 5. Modify shopee_product_mappings
    $cols = $conn->query("SHOW COLUMNS FROM shopee_product_mappings LIKE 'last_stock_sync_at'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE shopee_product_mappings ADD COLUMN last_stock_sync_at TIMESTAMP NULL AFTER last_synced_at");
        echo "Added last_stock_sync_at to shopee_product_mappings.\n";
    }

    $cols2 = $conn->query("SHOW COLUMNS FROM shopee_product_mappings LIKE 'last_price_sync_at'")->fetchAll();
    if (empty($cols2)) {
        $conn->exec("ALTER TABLE shopee_product_mappings ADD COLUMN last_price_sync_at TIMESTAMP NULL AFTER last_stock_sync_at");
        echo "Added last_price_sync_at to shopee_product_mappings.\n";
    }

    $cols3 = $conn->query("SHOW COLUMNS FROM shopee_product_mappings LIKE 'sync_hash'")->fetchAll();
    if (empty($cols3)) {
        $conn->exec("ALTER TABLE shopee_product_mappings ADD COLUMN sync_hash VARCHAR(50) NULL AFTER last_price_sync_at");
        echo "Added sync_hash to shopee_product_mappings.\n";
    }

    // Check if idx_parent_sku index exists
    $indexes = $conn->query("SHOW INDEX FROM shopee_product_mappings WHERE Key_name = 'idx_parent_sku'")->fetchAll();
    if (empty($indexes)) {
        $conn->exec("ALTER TABLE shopee_product_mappings ADD INDEX idx_parent_sku (shopee_parent_sku)");
        echo "Added index idx_parent_sku to shopee_product_mappings.\n";
    }

    // Check if idx_variation_sku index exists
    $indexes2 = $conn->query("SHOW INDEX FROM shopee_product_mappings WHERE Key_name = 'idx_variation_sku'")->fetchAll();
    if (empty($indexes2)) {
        $conn->exec("ALTER TABLE shopee_product_mappings ADD INDEX idx_variation_sku (shopee_variation_sku)");
        echo "Added index idx_variation_sku to shopee_product_mappings.\n";
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
