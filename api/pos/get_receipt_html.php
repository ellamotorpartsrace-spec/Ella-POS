<?php
// api/pos/get_receipt_html.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/settings_helper.php';

if (!isset($_GET['id'])) {
    die("Invalid Receipt ID");
}

$sale_id = $_GET['id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Load store settings
    $settings = getSettings($conn);
    $store_name = $settings['store_name'] ?? 'ELLA MOTOR PARTS';
    $store_address = $settings['store_address'] ?? 'Cauayan City, Isabela';
    $store_contact = $settings['store_contact'] ?? '';
    $receipt_footer = $settings['receipt_footer'] ?? 'Thank you for your purchase!';
    
    // Receipt template toggle settings
    $show_store_name    = ($settings['receipt_show_store_name'] ?? '1') === '1';
    $show_address       = ($settings['receipt_show_address'] ?? '1') === '1';
    $show_contact       = ($settings['receipt_show_contact'] ?? '1') === '1';
    $show_facebook      = ($settings['receipt_show_facebook'] ?? '1') === '1';
    $show_tax_id        = ($settings['receipt_show_tax_id'] ?? '1') === '1';
    $show_cashier       = ($settings['receipt_show_cashier'] ?? '1') === '1';
    $show_customer      = ($settings['receipt_show_customer'] ?? '1') === '1';
    $show_item_discount = ($settings['receipt_show_item_discount'] ?? '1') === '1';
    $show_payment       = ($settings['receipt_show_payment_method'] ?? '1') === '1';
    $header_text        = $settings['receipt_header_text'] ?? '';
    $footer_note        = $settings['receipt_footer_note'] ?? '';
    $store_facebook     = $settings['store_facebook'] ?? '';
    $store_tax_id       = $settings['store_tax_id'] ?? '';

    // 1. Fetch Sale Header Info
    $sql = "
        SELECT s.*, 
               u.full_name as cashier,
               b.buyer_name, b.shop_name, b.address as buyer_address
        FROM pos_sales s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE s.sale_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        die("Receipt not found.");
    }

    // 2. Fetch Sale Items
    $stmtItems = $conn->prepare("SELECT * FROM pos_sale_items WHERE sale_id = ?");
    $stmtItems->execute([$sale_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Determine Customer Display Name
    $customer_display = $sale['walkin_name'] ?? 'Walk-in Customer';
    if ($sale['buyer_id']) {
        $customer_display = $sale['buyer_name'];
        if ($sale['shop_name']) {
            $customer_display .= " (" . $sale['shop_name'] . ")";
        }
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<style>
    /* Inline CSS to ensure styles carry over to print window */
    .receipt-wrapper {
        width: 100%;
        max-width: 300px; /* Standard 80mm width roughly */
        margin: 0 auto;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        color: #000;
        line-height: 1.2;
    }
    .text-center { text-align: center; }
    .text-end { text-align: right; }
    .fw-bold { font-weight: bold; }
    .border-bottom-dashed { border-bottom: 1px dashed #000; margin: 5px 0; }
    .item-row td { vertical-align: top; padding-bottom: 4px; }
    .totals-row td { padding-top: 4px; }
    .sale-meta { font-size: 11px; color: #333; margin-bottom: 5px; }
</style>

<div class="receipt-wrapper">
    
    <div class="text-center">
        <?php if ($show_store_name): ?>
            <h4 style="margin: 0; font-weight: bold;"><?= htmlspecialchars($store_name) ?></h4>
        <?php endif; ?>
        <?php if ($show_address): ?>
            <div><?= htmlspecialchars($store_address) ?></div>
        <?php endif; ?>
        <?php if ($show_contact && $store_contact): ?>
            <div>Tel: <?= htmlspecialchars($store_contact) ?></div>
        <?php endif; ?>
        <?php if ($show_facebook && $store_facebook): ?>
            <div>Follow Us: <?= htmlspecialchars($store_facebook) ?></div>
        <?php endif; ?>
        <?php if ($show_tax_id && $store_tax_id): ?>
            <div>Non-VAT Reg: <?= htmlspecialchars($store_tax_id) ?></div>
        <?php endif; ?>
        <?php if ($header_text): ?>
            <div style="font-size: 10px;"><?= htmlspecialchars($header_text) ?></div>
        <?php endif; ?>
    </div>

    <div class="border-bottom-dashed"></div>

    <div class="sale-meta">
        <div><strong>Date:</strong> <?= date('m/d/Y h:i A', strtotime($sale['created_at'])) ?></div>
        <div><strong>Ref #:</strong> <?= $sale['sale_ref'] ?></div>
        <?php if ($show_cashier): ?>
            <div><strong>Cashier:</strong> <?= $sale['cashier'] ?></div>
        <?php endif; ?>
        <?php if ($show_customer): ?>
            <div><strong>Cust:</strong> <?= htmlspecialchars($customer_display) ?></div>
        <?php endif; ?>
    </div>

    <div class="border-bottom-dashed"></div>

    <table width="100%" cellspacing="0">
        <?php foreach ($items as $item): ?>
        <tr class="item-row">
            <td colspan="2">
                <?= htmlspecialchars($item['product_name']) ?> <?= $item['unit_type'] && $item['unit_type'] !== 'pc' ? '(' . htmlspecialchars($item['unit_type']) . ')' : '' ?>
            </td>
        </tr>
        <tr class="item-row">
            <td width="60%" style="padding-left: 10px;">
                <div style="display: flex; justify-content: space-between;">
                    <span><?= $item['quantity'] ?> x <?= number_format($item['price_at_sale'], 2) ?></span>
                    <?php if ($show_item_discount && !empty($item['item_discount']) && $item['item_discount'] > 0): ?>
                        <span style="text-decoration: line-through; font-size: 10px;">
                            <?= number_format($item['original_price'], 2) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </td>
            <td width="40%" class="text-end">
                <?= number_format($item['subtotal'], 2) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="border-bottom-dashed"></div>

    <table width="100%" cellspacing="0">
        <tr class="totals-row">
            <td>Subtotal:</td>
            <td class="text-end"><?= number_format($sale['subtotal'], 2) ?></td>
        </tr>
        <?php if ($sale['discount_amount'] > 0): ?>
        <tr class="totals-row">
            <td>Discount:</td>
            <td class="text-end">-<?= number_format($sale['discount_amount'], 2) ?></td>
        </tr>
        <?php endif; ?>
        
        <?php if ($sale['tax_amount'] > 0): ?>
        <tr class="totals-row" style="font-size: 10px;">
            <td>VAT (12%):</td>
            <td class="text-end"><?= number_format($sale['tax_amount'], 2) ?></td>
        </tr>
        <?php endif; ?>

        <tr class="totals-row" style="font-size: 14px; font-weight: bold;">
            <td>TOTAL:</td>
            <td class="text-end">₱<?= number_format($sale['grand_total'], 2) ?></td>
        </tr>
    </table>

    <div class="border-bottom-dashed"></div>

    <table width="100%" cellspacing="0">
        <?php if ($sale['payment_method'] === 'mix'): ?>
            <?php
            // Fetch multiple payments for Mix Payment
            $stmtMix = $conn->prepare("SELECT payment_type, amount FROM pos_sale_payments WHERE sale_id = ?");
            $stmtMix->execute([$sale_id]);
            $mixPayments = $stmtMix->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <tr>
                <td colspan="2" class="fw-bold">Mixed Payments:</td>
            </tr>
            <?php foreach ($mixPayments as $mixPay): ?>
            <tr>
                <td style="padding-left: 10px;"><?= ucfirst(str_replace('_', ' ', $mixPay['payment_type'])) ?>:</td>
                <td class="text-end"><?= number_format($mixPay['amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td>Total Tendered:</td>
                <td class="text-end"><?= number_format($sale['amount_tendered'], 2) ?></td>
            </tr>
        <?php else: ?>
            <tr>
                <td>Method:</td>
                <td class="text-end fw-bold"><?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?></td>
            </tr>
            <tr>
                <td>Tendered:</td>
                <td class="text-end"><?= number_format($sale['amount_tendered'], 2) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>Change:</td>
            <td class="text-end"><?= number_format($sale['change_due'], 2) ?></td>
        </tr>
    </table>

    <div class="border-bottom-dashed"></div>

    <div class="text-center" style="margin-top: 10px;">
        <?php if ($footer_note): ?>
            <div style="font-size: 10px;"><?= htmlspecialchars($footer_note) ?></div>
        <?php endif; ?>
        <div><?= htmlspecialchars($receipt_footer) ?></div>
    </div>
</div>