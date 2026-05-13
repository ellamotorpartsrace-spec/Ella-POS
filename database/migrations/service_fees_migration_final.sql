-- =============================================================
-- Migration: Service Fee Expenses Module (Final)
-- Date: 2026-04-29
-- Description: Consolidated structure, permissions, and view
--              for the Service Fees module.
-- =============================================================

-- 1. Create Tables
CREATE TABLE IF NOT EXISTS `service_fees` (
  `fee_id` INT(11) NOT NULL AUTO_INCREMENT,
  `fee_ref` VARCHAR(50) NOT NULL,
  `buyer_id` INT(11) DEFAULT NULL,
  `buyer_name` VARCHAR(150) DEFAULT NULL,
  `fee_type` ENUM('shipping','delivery','handling','service','other') NOT NULL DEFAULT 'shipping',
  `description` TEXT DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('pending','partial','paid','voided') NOT NULL DEFAULT 'pending',
  `due_date` DATE DEFAULT NULL,
  `sale_ref` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`fee_id`),
  KEY `idx_buyer` (`buyer_id`),
  KEY `idx_status` (`payment_status`),
  KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `service_fee_payments` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `fee_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash','gcash','bank','check','card','other') NOT NULL DEFAULT 'cash',
  `reference_no` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `collected_by` INT(11) NOT NULL,
  `paid_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_fee` (`fee_id`),
  CONSTRAINT `fk_sfp_fee_final` FOREIGN KEY (`fee_id`) REFERENCES `service_fees` (`fee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `service_fee_attachments` (
  `attachment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `fee_id` INT(11) NOT NULL,
  `history_id` INT(11) DEFAULT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) DEFAULT NULL,
  `uploaded_by` INT(11) DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  KEY `idx_fee_attach` (`fee_id`),
  CONSTRAINT `fk_sfa_fee_final` FOREIGN KEY (`fee_id`) REFERENCES `service_fees` (`fee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Permissions Registration
-- Insert permission if it doesn't exist
INSERT IGNORE INTO `permissions` (`slug`, `name`, `description`, `module`) 
VALUES ('manage_service_fees', 'Manage Service Fees', 'Allows creating, editing, and voiding service/shipping fee records.', 'Receivables');

-- 3. Role-Permission Mapping (Grant to Manager)
-- Check if the mapping already exists before inserting
INSERT IGNORE INTO `role_permissions` (`role`, `permission_slug`) 
VALUES ('manager', 'manage_service_fees');

-- 4. Audit: Fix any missing columns in service_fees (e.g. if 'voided' status was missing)
-- This is a safety measure for existing tables
ALTER TABLE `service_fees` MODIFY COLUMN `payment_status` ENUM('pending','partial','paid','voided') NOT NULL DEFAULT 'pending';

-- 5. Create Dashboard View
CREATE OR REPLACE VIEW `v_pending_service_fees` AS
SELECT 
    sf.fee_id, sf.fee_ref, sf.buyer_id, sf.buyer_name,
    COALESCE(b.buyer_name, sf.buyer_name) AS display_name,
    b.shop_name, b.contact_number,
    sf.fee_type, sf.description, sf.amount,
    sf.paid_amount, (sf.amount - sf.paid_amount) AS balance,
    sf.payment_status, sf.due_date, sf.sale_ref, sf.notes,
    sf.created_at,
    CASE 
        WHEN sf.due_date < CURDATE() AND sf.payment_status IN ('pending', 'partial') THEN 'overdue'
        WHEN sf.due_date = CURDATE() AND sf.payment_status IN ('pending', 'partial') THEN 'due_today'
        ELSE sf.payment_status
    END AS status_label,
    DATEDIFF(sf.due_date, CURDATE()) AS days_until_due,
    u.full_name AS created_by_name
FROM service_fees sf
LEFT JOIN buyers b ON sf.buyer_id = b.buyer_id
LEFT JOIN users u ON sf.created_by = u.id
WHERE sf.payment_status IN ('pending', 'partial')
ORDER BY sf.due_date ASC;
