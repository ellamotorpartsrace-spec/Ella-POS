<?php
// api/inventory/search_products.php - Progressive search for inventory list
header("Content-Type: application/json; charset=UTF-8");
require_once '../../config/database.php';

$query = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';
$status = $_GET['status'] ?? 'active';
$onlineOnly = !empty($_GET['online_only']) && $_GET['online_only'] == '1';
$category = trim($_GET['category'] ?? '');
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 100;
$offset = ($page - 1) * $limit;

// For inventory list search, allow empty query to show all products
try {
    $db = new Database();
    $conn = $db->getConnection();

    // Split search into individual words for progressive matching
    $words = preg_split('/\s+/', $query);
    $validWords = [];

    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) >= 1) {
            $validWords[] = $word;
        }
    }

    // Build WHERE clause: each word must match at least one of the searchable fields
    $wordConditions = [];
    $params = [];

    if (!empty($validWords)) {
        foreach ($validWords as $idx => $word) {
            $paramName = ":word{$idx}";
            $params[$paramName] = "%{$word}%";

            // Each word can match ANY of the searchable fields
            $wordConditions[] = "(
                p.product_name LIKE {$paramName}
                OR p.brand_name LIKE {$paramName}
                OR v.variation_name LIKE {$paramName}
                OR v.sku LIKE {$paramName}
                OR v.barcode LIKE {$paramName}
            )";
        }
    }

    // If query contains an exact barcode, prioritize barcode match
    $barcodeCondition = "";
    if (!empty($query)) {
        $params[':barcode'] = $query;
        $barcodeCondition = "v.barcode = :barcode OR ";
    }

    // All word conditions must be satisfied (AND logic)
    $searchCondition = !empty($wordConditions)
        ? implode(' AND ', $wordConditions)
        : "1=1"; // Match all if empty

    // Base FROM and WHERE clauses
    $baseSql = "
        FROM product_variations v
        JOIN products p ON v.product_id = p.product_id
        LEFT JOIN inventory i_phys ON v.variation_id = i_phys.variation_id AND i_phys.store_id = 1
        LEFT JOIN inventory i_online ON v.variation_id = i_online.variation_id AND i_online.store_id = 2
        WHERE v.status = :status
    ";

    // Add search condition if query is not empty
    if (!empty($query)) {
        $baseSql .= " AND ({$barcodeCondition}({$searchCondition}))";
    }

    // Add low stock filter if specified
    if ($filter === 'low_stock') {
        $baseSql .= " AND COALESCE(i_phys.quantity, 0) <= v.low_stock_threshold";
    }

    // Filter: only products with active online stock
    if ($onlineOnly) {
        $baseSql .= " AND COALESCE(i_online.quantity, 0) > 0";
    }

    // Filter: by brand/category
    if (!empty($category)) {
        $baseSql .= " AND p.brand_name = :category";
        $params[':category'] = $category;
    }

    // 1. Get Total Count (for pagination)
    $countSql = "SELECT COUNT(*) as total_items " . $baseSql;
    // We need to bind params twice, so let's just re-use $params
    $stmtCount = $conn->prepare($countSql);
    foreach ($params as $key => $val) {
        $stmtCount->bindValue($key, $val);
    }
    $stmtCount->bindValue(':status', $status);
    $stmtCount->execute();
    $totalItems = $stmtCount->fetch(PDO::FETCH_ASSOC)['total_items'];
    $totalPages = ceil($totalItems / $limit);

    $relevanceSelect = "";
    $orderClause = "ORDER BY p.product_name ASC";
    if (!empty($query)) {
        $relevanceSelect = ",
            (
                CASE 
                    WHEN v.barcode = :query_for_score THEN 100
                    WHEN v.sku = :query_for_score THEN 90
                    WHEN p.product_name LIKE CONCAT(:query_for_score, '%') THEN 80
                    WHEN v.variation_name LIKE CONCAT(:query_for_score, '%') THEN 80
                    WHEN p.brand_name LIKE CONCAT(:query_for_score, '%') THEN 80
                    ELSE 10
                END
            ) AS relevance_score";
        $orderClause = "ORDER BY relevance_score DESC, p.product_name ASC";
    }

    // 2. Main Query with LIMIT
    $sql = "
        SELECT 
            v.variation_id,
            v.variation_name,
            v.sku,
            v.barcode,
            v.unit_type,
            v.price_capital,
            v.price_retail,
            v.status,
            v.low_stock_threshold,
            p.product_name,
            p.brand_name,
            p.image_path,
            COALESCE(i_phys.quantity, 0) as physical_stock,
            COALESCE(i_online.quantity, 0) as online_stock,
            COALESCE(i_phys.quantity, 0) + COALESCE(i_online.quantity, 0) as current_stock
            {$relevanceSelect}
        " . $baseSql . "
        {$orderClause}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);

    // Bind all search params
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    if (!empty($query)) {
        $stmt->bindValue(':query_for_score', $query);
    }
    $stmt->bindValue(':status', $status);
    // Bind pagination params
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'products' => $results,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'limit' => $limit
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

