-- migrations/update_receivables_view_with_wallet.sql
-- Updates v_pending_payments to include the customer's wallet balance
-- This allows staff to see if a debt can be covered by existing wallet credit.

DROP VIEW IF EXISTS `v_pending_payments`;

CREATE VIEW `v_pending_payments` AS 
SELECT 
    `p`.`payment_id`,
    `p`.`sale_id`,
    `s`.`sale_ref`,
    `s`.`buyer_id`,
    COALESCE(`s`.`walkin_name`, `b`.`buyer_name`) AS `customer_name`,
    COALESCE(`s`.`buyer_shop_name`, `b`.`shop_name`) AS `shop_name`,
    COALESCE(`s`.`buyer_contact`, `b`.`contact_number`) AS `contact`,
    `p`.`amount` AS `amount_due`,
    `p`.`paid_amount` AS `paid_amount`,
    (`p`.`amount` - `p`.`paid_amount`) AS `balance`,
    `b`.`wallet_balance` AS `customer_wallet`,
    `p`.`due_date`,
    `p`.`payment_status`,
    `p`.`payment_status` AS `status_label` 
FROM `pos_sale_payments` `p` 
JOIN `pos_sales` `s` ON `p`.`sale_id` = `s`.`sale_id`
LEFT JOIN `buyers` `b` ON `s`.`buyer_id` = `b`.`buyer_id`
WHERE 
    `p`.`payment_type` = 'pay_later' 
    AND `p`.`payment_status` IN ('pending', 'partial') 
    AND `s`.`status` <> 'voided' 
ORDER BY `p`.`due_date` ASC;
