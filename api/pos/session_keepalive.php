<?php
// api/pos/session_keepalive.php
// Lightweight endpoint to check session status and optionally extend it
header("Content-Type: application/json");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is still logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['alive' => false, 'message' => 'Session expired']);
    exit;
}

// Touch the session to reset the timeout
$_SESSION['last_activity'] = time();

// Return session info
$maxLifetime = (int) ini_get('session.gc_maxlifetime'); // usually 1440 (24 min)
echo json_encode([
    'alive' => true,
    'max_lifetime' => $maxLifetime,
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'] ?? null
]);
