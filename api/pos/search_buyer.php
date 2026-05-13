<?php
// api/pos/search_buyer.php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([
        'success' => true,
        'data' => []
    ]);
    exit;
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $words = preg_split('/\\s+/', $q);
    $validWords = [];

    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) >= 1) {
            $validWords[] = $word;
        }
    }

    $wordConditions = [];
    $params = [':qForScore' => $q];

    foreach ($validWords as $idx => $word) {
        $paramName = ":word{$idx}";
        $params[$paramName] = "%{$word}%";
        
        $wordConditions[] = "(
            buyer_name LIKE {$paramName}
            OR shop_name LIKE {$paramName}
            OR contact_number LIKE {$paramName}
        )";
    }
    
    $searchCondition = !empty($wordConditions) ? implode(' AND ', $wordConditions) : "1=1";

    $sql = "
        SELECT
            buyer_id,
            buyer_name,
            shop_name,
            contact_number,
            address,
            wallet_balance,
            price_tier,
            (
                CASE
                    WHEN contact_number = :qForScore THEN 100
                    WHEN buyer_name LIKE CONCAT(:qForScore, '%') THEN 90
                    WHEN shop_name LIKE CONCAT(:qForScore, '%') THEN 80
                    ELSE 10
                END
            ) AS relevance_score
        FROM buyers
        WHERE
            is_walkin = 0
            AND ({$searchCondition})
        ORDER BY relevance_score DESC, buyer_name ASC
        LIMIT 10
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $buyers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $buyers
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}