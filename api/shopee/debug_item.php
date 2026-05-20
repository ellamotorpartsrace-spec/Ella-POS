<?php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/ShopeeApi.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $configStmt = $conn->query("SELECT * FROM shopee_config WHERE is_active = 1 LIMIT 1");
    $config = $configStmt->fetch(PDO::FETCH_ASSOC);
    if (!$config || empty($config['access_token'])) {
        throw new Exception("Active Shopee config/token not found");
    }

    $isTest = $config['environment'] === 'test';
    $shopee = new ShopeeAPI($config['partner_id'], $config['partner_key'], $isTest);

    $itemId = 47956302788;

    // Fetch base info
    $infoResult = $shopee->get('/api/v2/product/get_item_base_info', [
        'item_id_list' => (string)$itemId
    ], $config['access_token'], $config['shop_id']);

    $itemInfo = $infoResult['response']['item_list'][0] ?? null;
    if (!$itemInfo) {
        throw new Exception("Item not found on Shopee");
    }

    $isExtended = false;
    if (isset($itemInfo['description_info']['extended_description']['field_list']) && is_array($itemInfo['description_info']['extended_description']['field_list'])) {
        $isExtended = true;
    }

    $updateParams = ['item_id' => (int)$itemId];
    $padText = " - 🏍️ Premium quality motorcycle parts for performance, durability & everyday reliability. Premium quality guaranteed.";

    if ($isExtended) {
        $fieldList = $itemInfo['description_info']['extended_description']['field_list'];

        $totalTextLen = 0;
        $firstTextIdx = -1;
        foreach ($fieldList as $idx => $field) {
            if (isset($field['field_type']) && $field['field_type'] === 'text') {
                if ($firstTextIdx === -1) {
                    $firstTextIdx = $idx;
                }
                $totalTextLen += mb_strlen(trim($field['text'] ?? ''), 'UTF-8');
            }
        }

        if ($totalTextLen < 150) { // Safety threshold boosted to 150!
            $needed = 155 - $totalTextLen;
            $computedPad = $padText;
            while (mb_strlen($computedPad, 'UTF-8') < $needed) {
                $computedPad .= " Premium parts.";
            }

            if ($firstTextIdx !== -1) {
                $fieldList[$firstTextIdx]['text'] = trim($fieldList[$firstTextIdx]['text'] ?? '') . $computedPad;
            } else {
                array_unshift($fieldList, [
                    'field_type' => 'text',
                    'text' => trim($itemInfo['item_name'] ?? 'Product') . $computedPad
                ]);
            }
        }

        $updateParams['description_info'] = [
            'extended_description' => [
                'field_list' => $fieldList
            ]
        ];
        $updateParams['description_type'] = 'extended';
    } else {
        // Standard plain description
        $originalDesc = '';
        if (isset($itemInfo['description']) && is_string($itemInfo['description'])) {
            $originalDesc = trim($itemInfo['description']);
        }

        $newDesc = $originalDesc;
        if (empty($newDesc)) {
            $itemName = isset($itemInfo['item_name']) ? trim($itemInfo['item_name']) : '';
            $newDesc = !empty($itemName) ? $itemName : 'Product';
        }
        if (mb_strlen($newDesc, 'UTF-8') < 150) { // Safety threshold boosted to 150!
            $newDesc .= $padText;
        }
        if (mb_strlen($newDesc, 'UTF-8') < 150) {
            $newDesc = str_pad($newDesc, 155, ".");
        }

        $updateParams['description'] = $newDesc;
        $updateParams['description_type'] = 'normal';
    }

    // Call update_item to save description
    $descResponse = $shopee->post('/api/v2/product/update_item', $updateParams, $config['access_token'], $config['shop_id']);

    echo json_encode([
        'success' => true,
        'updateParams' => $updateParams,
        'descResponse' => $descResponse
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
