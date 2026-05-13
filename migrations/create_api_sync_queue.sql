-- create_api_sync_queue.sql
-- Table to queue stock updates to be pushed to external platforms (Shopee, etc.)

CREATE TABLE IF NOT EXISTS `api_sync_queue` (
    `queue_id` INT(11) NOT NULL AUTO_INCREMENT,
    `variation_id` INT(11) NOT NULL,
    `platform` VARCHAR(50) NOT NULL COMMENT 'e.g., shopee',
    `new_quantity` INT(11) NOT NULL,
    `status` ENUM('pending', 'processing', 'success', 'failed') DEFAULT 'pending',
    `attempts` TINYINT(3) DEFAULT 0,
    `last_error` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`queue_id`),
    KEY `idx_status` (`status`),
    KEY `idx_platform` (`platform`),
    KEY `idx_variation` (`variation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
