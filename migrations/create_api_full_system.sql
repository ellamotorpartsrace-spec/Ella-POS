-- =====================================================
-- migrations/create_api_full_system.sql
-- Run this file manually in phpMyAdmin or MySQL CLI
-- to set up the API and Online Platform Sync system.
--
-- Covers:
--   1. api_platforms (Storage for platform credentials)
--   2. api_sync_queue (Waitlist for outgoing stock updates)
--   3. online_platform_links (Linking local variations to online IDs)
-- =====================================================

-- ---------------------------------------------------
-- STEP 1: Create api_platforms table
-- (Stores partner keys, shop IDs, and OAuth tokens)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_platforms` (
    `id`             INT(11)          NOT NULL AUTO_INCREMENT,
    `platform_name`  VARCHAR(50)      NOT NULL COMMENT 'e.g., shopee, lazada',
    `partner_id`     VARCHAR(100)     DEFAULT NULL COMMENT 'Partner ID or App Key',
    `partner_key`    VARCHAR(255)     DEFAULT NULL COMMENT 'Partner Key or App Secret',
    `shop_id`        VARCHAR(100)     DEFAULT NULL COMMENT 'The Shop ID for the specific seller authorized',
    `access_token`   TEXT             DEFAULT NULL,
    `refresh_token`  TEXT             DEFAULT NULL,
    `token_expiry`   INT(11)          DEFAULT NULL COMMENT 'Unix timestamp when access_token expires',
    `is_active`      TINYINT(1)       NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_platform_name` (`platform_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial record for Shopee if not exists
INSERT IGNORE INTO `api_platforms` (`platform_name`, `is_active`) VALUES ('shopee', 0);

-- ---------------------------------------------------
-- STEP 2: Create api_sync_queue table
-- (Queues stock changes to be pushed to Shopee/Lazada)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_sync_queue` (
    `queue_id`       INT(11)          NOT NULL AUTO_INCREMENT,
    `variation_id`   INT(11)          NOT NULL,
    `platform`       VARCHAR(50)      NOT NULL COMMENT 'e.g., shopee',
    `new_quantity`   INT(11)          NOT NULL,
    `status`         ENUM('pending', 'processing', 'success', 'failed') DEFAULT 'pending',
    `attempts`       TINYINT(3)       DEFAULT 0,
    `last_error`     TEXT             DEFAULT NULL,
    `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`queue_id`),
    KEY `idx_status` (`status`),
    KEY `idx_platform` (`platform`),
    KEY `idx_variation` (`variation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- STEP 3: Create online_platform_links table
-- (Maps local product_variations to online platform IDs)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `online_platform_links` (
  `link_id`             INT(11)          NOT NULL AUTO_INCREMENT,
  `variation_id`        INT(11)          NOT NULL,
  `platform`            VARCHAR(50)      NOT NULL COMMENT 'Shopee, Lazada, TikTok, Facebook, Other',
  `online_product_id`   VARCHAR(100)     DEFAULT NULL COMMENT 'Optional parent product ID',
  `online_variation_id` VARCHAR(100)     NOT NULL COMMENT 'Platform assigned ID',
  `platform_sku`        VARCHAR(100)     DEFAULT NULL COMMENT 'SKU alias on the platform',
  `is_active`           TINYINT(1)       NOT NULL DEFAULT 1,
  `linked_at`           TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `linked_by`           INT(11)          NOT NULL,
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `idx_platform_online_var` (`platform`,`online_variation_id`),
  KEY `idx_variation_id` (`variation_id`),
  KEY `idx_linked_by` (`linked_by`),
  CONSTRAINT `fk_opl_variation` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`variation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_opl_user` FOREIGN KEY (`linked_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
