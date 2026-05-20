-- ══════════════════════════════════════════════
-- SHOPEE SYNC MODULE — DATABASE MIGRATION
-- Run this in phpMyAdmin on ella_parts_db
-- ══════════════════════════════════════════════

-- 1. Shopee API credentials and tokens
CREATE TABLE IF NOT EXISTS `shopee_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `environment` ENUM('test','live') NOT NULL DEFAULT 'test',
    `partner_id` VARCHAR(50) NOT NULL,
    `partner_key` VARCHAR(255) NOT NULL,
    `shop_id` VARCHAR(50) DEFAULT NULL,
    `shop_region` VARCHAR(10) NOT NULL DEFAULT 'PH',
    `access_token` TEXT DEFAULT NULL,
    `refresh_token` TEXT DEFAULT NULL,
    `token_expires_at` DATETIME DEFAULT NULL,
    `shop_name` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Shopee product mappings
CREATE TABLE IF NOT EXISTS `shopee_product_mappings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `shopee_item_id` BIGINT NOT NULL,
    `shopee_model_id` BIGINT DEFAULT NULL,
    `shopee_product_name` VARCHAR(500) DEFAULT NULL,
    `shopee_variation_name` VARCHAR(255) DEFAULT NULL,
    `shopee_parent_sku` VARCHAR(100) DEFAULT NULL,
    `shopee_variation_sku` VARCHAR(100) DEFAULT NULL,
    `has_variation` TINYINT(1) NOT NULL DEFAULT 0,
    `matched_pos_sku` VARCHAR(100) DEFAULT NULL,
    `pos_product_id` INT DEFAULT NULL,
    `mapping_status` ENUM('auto','manual','unmapped','duplicate','missing_sku') NOT NULL DEFAULT 'unmapped',
    `shopee_stock` INT NOT NULL DEFAULT 0,
    `shopee_price` DECIMAL(10,2) DEFAULT NULL,
    `shopee_image_url` VARCHAR(1000) DEFAULT NULL,
    `last_synced_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_shopee_item` (`shopee_item_id`),
    INDEX `idx_shopee_model` (`shopee_model_id`),
    INDEX `idx_pos_sku` (`matched_pos_sku`),
    INDEX `idx_mapping_status` (`mapping_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Sync logs
CREATE TABLE IF NOT EXISTS `shopee_sync_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_type` ENUM('product_import','stock_update','order_sync','allocation','sync_failed','token_refresh','mapping') NOT NULL,
    `shopee_item_id` BIGINT DEFAULT NULL,
    `product_name` VARCHAR(500) DEFAULT NULL,
    `sku` VARCHAR(100) DEFAULT NULL,
    `old_value` VARCHAR(100) DEFAULT NULL,
    `new_value` VARCHAR(100) DEFAULT NULL,
    `source` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('success','failed','pending') NOT NULL DEFAULT 'success',
    `error_message` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
