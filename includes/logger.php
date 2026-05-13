<?php
// includes/logger.php - Activity Logger
// Provides a unified function to log user actions across the system

/**
 * Log an activity to the database.
 *
 * @param PDO $conn Database connection
 * @param int|null $user_id ID of the user performing the action (null for system actions)
 * @param string $action_type e.g., 'LOGIN', 'SALE', 'VOID', 'CREATE', 'UPDATE', 'DELETE'
 * @param string $module e.g., 'Auth', 'POS', 'Inventory', 'Settings'
 * @param string $description Human-readable description of the action
 * @param int|null $item_id Optional ID of the affected item (e.g., sale_id, product_id)
 * @return bool True on success, false on failure
 */
function logActivity($conn, $user_id, $action_type, $module, $description, $item_id = null) {
    try {
        // Get client IP address
        $ip_address = $_SERVER['HTTP_CLIENT_IP'] 
            ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? '127.0.0.1';

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action_type, module, description, item_id, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user_id, 
            strtoupper($action_type), 
            ucfirst($module), 
            $description, 
            $item_id, 
            $ip_address
        ]);
    } catch (Exception $e) {
        // Silently fail logging rather than breaking the application flow,
        // but log to PHP error log for debugging
        error_log("Failed to insert activity log: " . $e->getMessage());
        return false;
    }
}
