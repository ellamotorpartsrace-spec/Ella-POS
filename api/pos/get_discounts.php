<?php
// api/pos/get_discounts.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../../config/config.php';
require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $today = date('Y-m-d');

    $sql = "SELECT * FROM discount_rules 
            WHERE is_active = 1 
            AND (start_date IS NULL OR start_date <= :today1)
            AND (end_date IS NULL OR end_date >= :today2)
            ORDER BY discount_type ASC"; // Apply fixed then percentage usually, but mostly just fetching here.

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':today1' => $today,
        ':today2' => $today
    ]);
    
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'rules' => $rules
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
