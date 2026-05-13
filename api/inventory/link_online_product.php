<?php
/**
 * api/inventory/link_online_product.php
 * Handles manual creation, retrieval, and deletion of platform links.
 */
declare(strict_types=1);
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($action === 'link') {
        $variation_id = (int) ($input['variation_id'] ?? 0);
        $platform = trim($input['platform'] ?? '');
        $online_variation_id = trim($input['online_variation_id'] ?? '');
        $online_product_id = trim($input['online_product_id'] ?? '');
        $platform_sku = trim($input['platform_sku'] ?? '');

        if (!$variation_id || !$platform || !$online_variation_id) {
            throw new Exception("Missing required fields for linking.");
        }

        $stmtAutoLink = $conn->prepare("
            INSERT INTO online_platform_links
                (variation_id, platform, online_product_id, online_variation_id, platform_sku, linked_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                variation_id      = VALUES(variation_id),
                online_product_id = VALUES(online_product_id),
                platform_sku      = VALUES(platform_sku),
                linked_by         = VALUES(linked_by),
                is_active         = 1
        ");

        $stmtAutoLink->execute([
            $variation_id,
            $platform,
            $online_product_id ?: null,
            $online_variation_id,
            $platform_sku ?: null,
            $_SESSION['user_id'] ?? 1
        ]);

        echo json_encode(['success' => true, 'message' => 'Platform link saved successfully.']);

    } elseif ($action === 'unlink') {
        $link_id = (int) ($input['link_id'] ?? 0);
        if (!$link_id)
            throw new Exception("Invalid Link ID");

        $stmt = $conn->prepare("UPDATE online_platform_links SET is_active = 0 WHERE link_id = ?");
        $stmt->execute([$link_id]);

        echo json_encode(['success' => true, 'message' => 'Platform link removed.']);

    } elseif ($action === 'list') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, (int) ($_GET['limit'] ?? 30));
        $offset = ($page - 1) * $limit;

        $search = trim($_GET['q'] ?? '');

        $sql = "
            SELECT 
                l.link_id, l.variation_id, l.platform, l.online_product_id, l.online_variation_id, l.platform_sku, l.linked_at,
                p.product_name, p.brand_name, v.variation_name, v.sku as internal_sku,
                u.full_name as linked_by_name
            FROM online_platform_links l
            JOIN product_variations v ON l.variation_id = v.variation_id
            JOIN products p ON v.product_id = p.product_id
            LEFT JOIN users u ON l.linked_by = u.id
            WHERE l.is_active = 1
        ";

        $params = [];

        if ($search) {
            $sql .= " AND (p.product_name LIKE ? OR v.variation_name LIKE ? OR l.online_variation_id LIKE ? OR v.sku LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
        }

        $sql .= " ORDER BY l.linked_at DESC LIMIT $limit OFFSET $offset";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // count total
        $countSql = "
            SELECT COUNT(*) FROM online_platform_links l
            JOIN product_variations v ON l.variation_id = v.variation_id
            JOIN products p ON v.product_id = p.product_id
            WHERE l.is_active = 1
        ";
        if ($search) {
            $countSql .= " AND (p.product_name LIKE ? OR v.variation_name LIKE ? OR l.online_variation_id LIKE ? OR v.sku LIKE ?)";
        }
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'links' => $links,
            'pagination' => [
                'total_items' => $total,
                'total_pages' => ceil($total / $limit),
                'current_page' => $page
            ]
        ]);

    } else {
        throw new Exception("Invalid action.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
