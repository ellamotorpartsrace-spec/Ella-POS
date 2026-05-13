<?php

/**
 * One-time script to sync inventory table with stock_movements
 * Run this ONCE to fix products with logged stock movements but missing inventory rows
 * 
 * HOW TO RUN: Navigate to this file in your browser
 * Example: http://localhost/ella-pos/api/inventory/sync_inventory.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html><html><head><title>Inventory Sync</title></head><body>";
echo "<h2>🔄 Inventory Synchronization Tool</h2>";
echo "<p>This script will sync the inventory table with stock_movements...</p>";
echo "<hr>";

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Find all variation_ids that have stock movements
    $sqlVariations = "SELECT DISTINCT variation_id FROM stock_movements ORDER BY variation_id";
    $stmtVar = $conn->query($sqlVariations);
    $variations = $stmtVar->fetchAll(PDO::FETCH_COLUMN);

    echo "<p><strong>Found " . count($variations) . " products with stock movements.</strong></p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Variation ID</th><th>Calculated Stock</th><th>Inventory Before</th><th>Action</th><th>Result</th></tr>";

    $fixedCount = 0;
    $alreadyCorrect = 0;

    foreach ($variations as $var_id) {
        // Calculate what the stock SHOULD be based on movements
        // Group by true delta of new_stock vs previous_stock
        $sqlCalc = "
            SELECT 
                SUM(new_stock - previous_stock) as calculated_stock
            FROM stock_movements 
            WHERE variation_id = :id AND store_id = 1
        ";
        $stmtCalc = $conn->prepare($sqlCalc);
        $stmtCalc->execute([':id' => $var_id]);
        $result = $stmtCalc->fetch(PDO::FETCH_ASSOC);
        $calculatedStock = (int) ($result['calculated_stock'] ?? 0);

        // Prevent negative stock levels
        $wasNegative = false;
        if ($calculatedStock < 0) {
            $calculatedStock = 0;
            $wasNegative = true;
        }

        // Check current inventory for physical store
        $sqlInv = "SELECT quantity FROM inventory WHERE variation_id = :id AND store_id = 1";
        $stmtInv = $conn->prepare($sqlInv);
        $stmtInv->execute([':id' => $var_id]);
        $invRow = $stmtInv->fetch(PDO::FETCH_ASSOC);
        $currentStock = $invRow ? (int) $invRow['quantity'] : null;

        // Display and fix if needed
        echo "<tr>";
        echo "<td>$var_id</td>";
        if ($wasNegative) {
            echo "<td style='text-align:center;'><strong>$calculatedStock</strong> <br><small style='color:orange; font-size:11px;'>(floored from negative)</small></td>";
        } else {
            echo "<td style='text-align:center;'><strong>$calculatedStock</strong></td>";
        }
        echo "<td style='text-align:center;'>" . ($currentStock !== null ? $currentStock : '<span style="color:red;">NULL (missing)</span>') . "</td>";

        if ($currentStock === null) {
            // Missing row - INSERT
            $sqlInsert = "INSERT INTO inventory (variation_id, quantity, store_id) VALUES (:id, :qty, 1)";
            $stmtIns = $conn->prepare($sqlInsert);
            $stmtIns->execute([':id' => $var_id, ':qty' => $calculatedStock]);
            echo "<td style='color:blue;'>INSERT</td>";
            echo "<td style='color:green;'>✓ Fixed</td>";
            $fixedCount++;
        } elseif ($currentStock != $calculatedStock) {
            // Wrong quantity - UPDATE
            $sqlUpdate = "UPDATE inventory SET quantity = :qty WHERE variation_id = :id";
            $stmtUpd = $conn->prepare($sqlUpdate);
            $stmtUpd->execute([':id' => $var_id, ':qty' => $calculatedStock]);
            echo "<td style='color:orange;'>UPDATE</td>";
            echo "<td style='color:green;'>✓ Corrected</td>";
            $fixedCount++;
        } else {
            // Already correct
            echo "<td style='color:gray;'>-</td>";
            echo "<td style='color:gray;'>Already OK</td>";
            $alreadyCorrect++;
        }

        echo "</tr>";
    }

    echo "</table>";
    echo "<hr>";
    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Total Products:</strong> " . count($variations) . "</li>";
    echo "<li><strong style='color:green;'>Fixed/Updated:</strong> $fixedCount</li>";
    echo "<li><strong style='color:gray;'>Already Correct:</strong> $alreadyCorrect</li>";
    echo "</ul>";
    echo "<p><strong>✅ Sync Complete!</strong></p>";
    echo "<p><a href='../../views/inventory/index.php'>← Back to Inventory</a></p>";
} catch (Exception $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
