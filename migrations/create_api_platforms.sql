-- Drop table if it exists to allow clean re-runs
DROP TABLE IF EXISTS `api_platforms`;

-- Create api_platforms table
CREATE TABLE `api_platforms` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `platform_name` VARCHAR(50) NOT NULL COMMENT 'e.g., shopee, lazada',
    `partner_id` VARCHAR(100) DEFAULT NULL COMMENT 'Partner ID or App Key',
    `partner_key` VARCHAR(255) DEFAULT NULL COMMENT 'Partner Key or App Secret',
    `shop_id` VARCHAR(100) DEFAULT NULL COMMENT 'The Shop ID for the specific seller authorized',
    `webhook_url` TEXT DEFAULT NULL COMMENT 'URL to send stock updates to',
    `is_test` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether to use sandbox/test mode',
    `access_token` TEXT DEFAULT NULL,
    `refresh_token` TEXT DEFAULT NULL,
    `token_expiry` INT(11) DEFAULT NULL COMMENT 'Unix timestamp when access_token expires',
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_platform_name` (`platform_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial placeholder for Shopee
INSERT IGNORE INTO `api_platforms` (`platform_name`, `is_active`) VALUES ('shopee', 0);
