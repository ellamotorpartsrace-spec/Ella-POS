<?php
// api/inventory/get_stockin_records.php - Get stock-in records filtered by supplier
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

$supplier_id = $_GET['supplier_id'] ?? null;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

if (!$supplier_id) {
    echo json_encode(['success' => false, 'error' => 'No supplier selected']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get supplier name
    $stmtSupp = $conn->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id = ?");
    $stmtSupp->execute([$supplier_id]);
    $supplier = $stmtSupp->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        echo json_encode(['success' => false, 'error' => 'Supplier not found']);
        exit;
    }

    $supplier_name = $supplier['supplier_name'];

    // Build query - match by remarks containing supplier name
    $sql = "
        SELECT 
            sm.movement_id,
            sm.type,
            sm.quantity,
            sm.previous_stock,
            sm.new_stock,
            sm.reference,
            sm.remarks,
            sm.created_at,
            pv.variation_name,
            pv.sku,
            pv.barcode,
            COALESCE(sm.capital_cost, pv.price_capital) as price_capital,
            pv.unit_type,
            p.product_name,
            p.brand_name,
            p.image_path,
            u.full_name as created_by_name,
            ra.attachment_count as has_attachment,
            ra.all_images_data as reference_image
        FROM stock_movements sm
        JOIN product_variations pv ON sm.variation_id = pv.variation_id
        JOIN products p ON pv.product_id = p.product_id
        LEFT JOIN users u ON sm.created_by = u.id
        LEFT JOIN (
            SELECT reference_number, 
                   GROUP_CONCAT(CONCAT(id, ':', image_path) ORDER BY id ASC) as all_images_data,
                   COUNT(*) as attachment_count 
            FROM reference_attachments 
            GROUP BY reference_number
        ) ra ON sm.reference = ra.reference_number
        WHERE sm.type = 'stock_in'
        AND (
            sm.remarks LIKE ? 
            OR sm.remarks LIKE ?
            OR sm.reference IN (SELECT po_ref FROM purchase_orders WHERE supplier_id = ?)
        )
    ";

    $params = [
        "%Restock: {$supplier_name}%",
        "%Batch Restock: {$supplier_name}%",
        $supplier_id
    ];

    if (!empty($date_from)) {
        $sql .= " AND DATE(sm.created_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $sql .= " AND DATE(sm.created_at) <= ?";
        $params[] = $date_to;
    }

    // Count total
    $countSql = "SELECT COUNT(*) FROM (" . $sql . ") as sub";
    $stmtCount = $conn->prepare($countSql);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // Get paginated results
    $sql .= " ORDER BY sm.created_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary stats
    $statsSql = "
        SELECT 
            COUNT(*) as total_records,
            COALESCE(SUM(sm.quantity), 0) as total_quantity,
            COALESCE(SUM(sm.quantity * COALESCE(sm.capital_cost, pv.price_capital)), 0) as total_cost,
            COUNT(DISTINCT sm.reference) as unique_references
        FROM stock_movements sm
        JOIN product_variations pv ON sm.variation_id = pv.variation_id
        WHERE sm.type = 'stock_in'
        AND (
            sm.remarks LIKE ? 
            OR sm.remarks LIKE ?
            OR sm.reference IN (SELECT po_ref FROM purchase_orders WHERE supplier_id = ?)
        )
    ";

    $statsParams = [
        "%Restock: {$supplier_name}%",
        "%Batch Restock: {$supplier_name}%",
        $supplier_id
    ];

    if (!empty($date_from)) {
        $statsSql .= " AND DATE(sm.created_at) >= ?";
        $statsParams[] = $date_from;
    }
    if (!empty($date_to)) {
        $statsSql .= " AND DATE(sm.created_at) <= ?";
        $statsParams[] = $date_to;
    }

    $stmtStats = $conn->prepare($statsSql);
    $stmtStats->execute($statsParams);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'supplier_name' => $supplier_name,
        'records' => $records,
        'stats' => $stats,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => (int) $total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
