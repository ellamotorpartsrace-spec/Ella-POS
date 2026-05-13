<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$ref = 'POS-20260430191019-348';
$stmt = $conn->prepare("SELECT sale_id, sale_ref, grand_total, status, payment_status FROM pos_sales WHERE sale_ref = ?");
$stmt->execute([$ref]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

echo "SALE HEADER:\n";
print_r($sale);

if ($sale) {
    $stmt2 = $conn->prepare("SELECT payment_id, payment_type, amount, paid_amount, payment_status, reference_no FROM pos_sale_payments WHERE sale_id = ?");
    $stmt2->execute([$sale['sale_id']]);
    $payments = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "\nPAYMENTS:\n";
    print_r($payments);
}
