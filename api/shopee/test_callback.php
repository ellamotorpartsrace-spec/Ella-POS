<?php
require_once '../../config/config.php';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isHttps ? "https://" : "http://";

$host = $_SERVER['HTTP_HOST'];
$callbackUrl = $protocol . $host . BASE_URL . 'api/shopee/callback.php';

echo json_encode([
    'callbackUrl' => $callbackUrl,
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
    'HTTPS' => $_SERVER['HTTPS'] ?? null,
    'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
]);
