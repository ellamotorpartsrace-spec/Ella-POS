-- =====================================================
-- migrations/create_wallet_full_system.sql
-- Run this file manually in phpMyAdmin or MySQL CLI
-- to set up the complete wallet system.
--
-- Covers:
--   1. buyers.wallet_balance column
--   2. buyer_wallet_logs table
--   3. pos_sale_payments ENUM fix (add 'wallet' and 'credit' types)
--   4. v_pending_payments VIEW update (include credits + wallet balance)
-- =====================================================

-- ---------------------------------------------------
-- STEP 1: Add wallet_balance to buyers table
-- (Safe — only runs if column doesn't exist)
-- ---------------------------------------------------
DROP PROCEDURE IF EXISTS _AddWalletBalance;
DELIMITER //
CREATE PROCEDURE _AddWalletBalance()
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'buyers'
          AND COLUMN_NAME  = 'wallet_balance'
    ) THEN
        ALTER TABLE `buyers`
            ADD COLUMN `wallet_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00
            COMMENT 'Customer prepaid wallet balance';
    END IF;
END//
DELIMITER ;
CALL _AddWalletBalance();
DROP PROCEDURE _AddWalletBalance;

-- ---------------------------------------------------
-- STEP 2: Create buyer_wallet_logs table
-- (Audit trail for every wallet credit/debit)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `buyer_wallet_logs` (
    `id`             INT(11)          NOT NULL AUTO_INCREMENT,
    `buyer_id`       INT(11)          NOT NULL,
    `user_id`        INT(11)          DEFAULT NULL,
    `type`           ENUM('credit','debit') NOT NULL,
    `amount`         DECIMAL(10,2)    NOT NULL,
    `balance_after`  DECIMAL(10,2)    NOT NULL,
    `reference_type` VARCHAR(50)      DEFAULT NULL  COMMENT 'e.g. sale, adjustment',
    `reference_id`   VARCHAR(50)      DEFAULT NULL  COMMENT 'e.g. POS sale_ref',
    `remarks`        TEXT             DEFAULT NULL,
    `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_buyer_wallet_logs_buyer` (`buyer_id`),
    KEY `idx_buyer_wallet_logs_type`  (`type`),
    KEY `idx_buyer_wallet_logs_ref`   (`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------
-- STEP 3: Extend pos_sale_payments.payment_type ENUM
--
-- The current ENUM is:
--   cash, gcash, bank_transfer, check, card, terms,
--   pay_later, home_credit, mix, financing
--
-- We need to add:
--   'wallet'  — for wallet supplement or full wallet payment
--   'credit'  — for shortfall recorded as a receivable (pending)
--
-- NOTE: ALTER TABLE MODIFY will rewrite the enum safely.
-- ---------------------------------------------------
ALTER TABLE `pos_sale_payments`
    MODIFY COLUMN `payment_type`
        ENUM(
            'cash','gcash','bank_transfer','check','card',
            'terms','pay_later','home_credit','mix','financing',
            'wallet','credit'
        ) NOT NULL;

-- ---------------------------------------------------
-- STEP 4: Update v_pending_payments VIEW
--
-- Adds support for:
--   - 'credit' payment_type (shortfall as receivable)
--   - customer_wallet column so staff can see if
--     the buyer's wallet can cover the balance
-- ---------------------------------------------------
DROP VIEW IF EXISTS `v_pending_payments`;

CREATE VIEW `v_pending_payments` AS
SELECT
    p.payment_id,
    p.sale_id,
    s.sale_ref,
    s.buyer_id,
    COALESCE(s.walkin_name, b.buyer_name)          AS customer_name,
    COALESCE(s.buyer_shop_name, b.shop_name)        AS shop_name,
    COALESCE(s.buyer_contact, b.contact_number)     AS contact,
    p.amount                                        AS amount_due,
    p.paid_amount,
    (p.amount - p.paid_amount)                      AS balance,
    COALESCE(b.wallet_balance, 0)                   AS customer_wallet,
    p.payment_type,
    p.due_date,
    p.payment_status,
    p.payment_status                                AS status_label,
    s.created_at                                    AS sale_date
FROM `pos_sale_payments` p
JOIN `pos_sales` s ON p.sale_id = s.sale_id
LEFT JOIN `buyers` b ON s.buyer_id = b.buyer_id
WHERE
    p.payment_type IN ('pay_later', 'credit')
    AND p.payment_status IN ('pending', 'partial')
    AND s.status <> 'voided'
ORDER BY p.due_date ASC, s.created_at ASC;
