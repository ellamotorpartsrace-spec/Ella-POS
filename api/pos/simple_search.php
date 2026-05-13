<?php
// api/pos/simple_search.php
// Progressive search: product_name + brand_name + variation_name + sku (any order)
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

$q = trim($_GET['q'] ?? '');
$catId = trim($_GET['category_id'] ?? 'all');

// Allow empty query and category to return all products up to the default limit limit

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Split search into individual words
    $words = preg_split('/\s+/', $q);
    $validWords = [];

    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) >= 1) {
            $validWords[] = $word;
        }
    }

    // Build WHERE clause: each word must match at least one of the 4 fields
    // Example: "Samsung Blue 128" becomes:
    // (product_name LIKE '%Samsung%' OR brand_name LIKE '%Samsung%' OR variation_name LIKE '%Samsung%' OR sku LIKE '%Samsung%')
    // AND (product_name LIKE '%Blue%' OR brand_name LIKE '%Blue%' OR variation_name LIKE '%Blue%' OR sku LIKE '%Blue%')
    // AND (product_name LIKE '%128%' OR brand_name LIKE '%128%' OR variation_name LIKE '%128%' OR sku LIKE '%128%')

    $wordConditions = [];
    $params = [':barcode' => $q];

    foreach ($validWords as $idx => $word) {
        $paramName = ":word{$idx}";
        $params[$paramName] = "%{$word}%";

        // Each word can match ANY of the 4 fields
        $wordConditions[] = "(
            p.product_name LIKE {$paramName}
            OR p.brand_name LIKE {$paramName}
            OR v.variation_name LIKE {$paramName}
            OR v.sku LIKE {$paramName}
        )";
    }

    // If no valid words, use the full query as one LIKE
    if (empty($wordConditions)) {
        $params[':like'] = "%{$q}%";
        $wordConditions[] = "(
            p.product_name LIKE :like
            OR p.brand_name LIKE :like
            OR v.variation_name LIKE :like
            OR v.sku LIKE :like
        )";
    }

    // All word conditions must be satisfied (AND logic)
    $searchCondition = !empty($wordConditions) ? implode(' AND ', $wordConditions) : "1=1";

    // Category Filter
    $categoryCondition = ($catId !== 'all') ? "AND p.category_id = :catId" : "";
    if ($catId !== 'all') {
        $params[':catId'] = $catId;
    }

    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $limit = 50;

    $sql = "
        SELECT 
            v.variation_id,
            p.product_name,
            p.brand_name,
            p.image_path,
            p.description,
            v.variation_name,
            v.sku,
            v.price_retail,
            v.price_wholesale,
            v.price_dealer,
            v.unit_type,
            v.barcode,
            COALESCE(inv.stock, 0) AS stock,
            CAST(1 AS UNSIGNED) AS multiplier,
            NULL AS unit_id,
            (
                CASE 
                    WHEN v.barcode = :barcode THEN 100
                    WHEN v.sku = :barcode THEN 90
                    WHEN p.product_name LIKE CONCAT(:barcode, '%') THEN 80
                    WHEN v.variation_name LIKE CONCAT(:barcode, '%') THEN 80
                    WHEN p.brand_name LIKE CONCAT(:barcode, '%') THEN 80
                    ELSE 10
                END
            ) AS relevance_score
        FROM product_variations v
        INNER JOIN products p ON v.product_id = p.product_id
        LEFT JOIN (
            SELECT variation_id, SUM(quantity) as stock 
            FROM inventory 
            WHERE store_id = 1 
            GROUP BY variation_id
        ) inv ON v.variation_id = inv.variation_id
        WHERE v.status = 'active'
        AND (
            v.barcode = :barcode
            OR ({$searchCondition})
        )
        {$categoryCondition}
        
        UNION ALL
        
        SELECT 
            v.variation_id,
            p.product_name,
            p.brand_name,
            p.image_path,
            COALESCE(u.description, p.description) AS description,
            v.variation_name,
            v.sku,
            u.price_retail,
            u.price_wholesale,
            u.price_dealer,
            u.unit_name AS unit_type,
            u.barcode,
            FLOOR(COALESCE(inv.stock, 0) / u.multiplier) AS stock,
            u.multiplier,
            u.id AS unit_id,
            (
                CASE 
                    WHEN u.barcode = :barcode THEN 100
                    WHEN v.sku = :barcode THEN 90
                    WHEN p.product_name LIKE CONCAT(:barcode, '%') THEN 80
                    WHEN v.variation_name LIKE CONCAT(:barcode, '%') THEN 80
                    WHEN p.brand_name LIKE CONCAT(:barcode, '%') THEN 80
                    ELSE 10
                END
            ) AS relevance_score
        FROM product_units u
        INNER JOIN product_variations v ON u.variation_id = v.variation_id
        INNER JOIN products p ON v.product_id = p.product_id
        LEFT JOIN (
            SELECT variation_id, SUM(quantity) as stock 
            FROM inventory 
            WHERE store_id = 1 
            GROUP BY variation_id
        ) inv ON v.variation_id = inv.variation_id
        WHERE v.status = 'active'
        AND (
            u.barcode = :barcode
            OR ({$searchCondition})
        )
        {$categoryCondition}
        
        ORDER BY 
            relevance_score DESC,
            (stock > 0) DESC,
            product_name ASC,
            variation_name ASC,
            multiplier ASC
        LIMIT {$limit} OFFSET {$offset}
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Image Path Fix
    foreach ($rows as &$row) {
        $row['image_url'] = !empty($row['image_path'])
            ? "../../" . $row['image_path']
            : "../../assets/img/products/no-image.png";
    }

    echo json_encode($rows ?: []);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
