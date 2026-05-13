<?php
// Ensure no accidental whitespace before <?php to avoid JSON parse errors
require_once '../../config/config.php'; // Added this for base paths
require_once '../../config/database.php';

// Set header immediately
header('Content-Type: application/json');

// Get and sanitize ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Supplier ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get Supplier Info
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        echo json_encode(['success' => false, 'message' => 'Supplier not found']);
        exit;
    }

    // 2. Get Linked Products 
    // Optimization: We use variation_id as well to ensure uniqueness in the list
    $stmtItems = $conn->prepare("
        SELECT 
            p.product_name, 
            v.variation_name, 
            v.price_capital,
            v.sku
        FROM product_variations v
        INNER JOIN products p ON v.product_id = p.product_id
        WHERE p.supplier_id = ?
        ORDER BY p.product_name ASC
    ");
    $stmtItems->execute([$id]);
    $products = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Final Output
    // We explicitly return an empty array if no products found to avoid null errors in JS
    echo json_encode([
        'success' => true,
        'supplier' => $supplier,
        'products' => $products ?: []
    ]);

} catch (PDOException $e) {
    // Return specific Database error
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Return general error
    echo json_encode(['success' => false, 'message' => 'General Error: ' . $e->getMessage()]);
}