<?php
// api/pos/print_direct.php
// Receives JSON payload of print commands and sends them directly to an ESC/POS printer via Network or USB Share
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || empty($input['commands'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty print commands']);
    exit;
}

$commands = $input['commands'];
$connectionType = $input['printer_connection'] ?? 'network';
$printerAddress = $input['printer_address'] ?? '';

if (empty($printerAddress)) {
    echo json_encode(['success' => false, 'error' => 'Printer address is not configured']);
    exit;
}

// ESC/POS Byte Constants
define('ESC', "\x1b");
define('GS', "\x1d");
define('LF', "\x0a");

$escposData = "";

// 1. Initialize Printer
$escposData .=  ESC . "@"; 

// 2. Process Commands
foreach ($commands as $cmd) {
    if (!isset($cmd['type'])) continue;

    // Apply Alignment
    if (isset($cmd['align'])) {
        $alignBytes = "\x00"; // Left default
        if ($cmd['align'] === 'center') $alignBytes = "\x01";
        if ($cmd['align'] === 'right') $alignBytes = "\x02";
        $escposData .= ESC . "a" . $alignBytes;
    } else {
        $escposData .= ESC . "a" . "\x00"; // Reset to left
    }

    // Apply Bold
    if (isset($cmd['bold']) && $cmd['bold']) {
        $escposData .= ESC . "E" . "\x01";
    } else {
        $escposData .= ESC . "E" . "\x00";
    }
    
    // Apply Size (Standard is 0x00, Double Height/Width is 0x11)
    if (isset($cmd['size']) && $cmd['size'] === 'large') {
        $escposData .= GS . "!" . "\x11";
    } elseif (isset($cmd['size']) && $cmd['size'] === 'tall') {
        $escposData .= GS . "!" . "\x01"; // Double height only
    } else {
        $escposData .= GS . "!" . "\x00"; // Standard
    }

    switch ($cmd['type']) {
        case 'text':
            // Output text followed by Line Feed
            $text = isset($cmd['text']) ? $cmd['text'] : '';
            // Convert utf8 to extended ascii if needed here (simplified for now)
            $escposData .= $text . LF;
            break;
            
        case 'feed':
            $lines = isset($cmd['lines']) ? (int)$cmd['lines'] : 1;
            $escposData .= ESC . "d" . chr($lines);
            break;
            
        case 'cut':
            // Partial cut (feed paper and cut)
            $escposData .= GS . "V" . "\x42" . "\x03"; 
            break;
            
        case 'drawer':
            // Kick cash drawer 1 (pin 2)
            $escposData .= ESC . "p" . "\x00" . "\x19" . "\xff";
            break;
            
        case 'raw':
            // Completely raw bytes if needed
            if (isset($cmd['bytes'])) {
                $escposData .= $cmd['bytes'];
            }
            break;
    }
}

// 3. Final Reset just in case
$escposData .= ESC . "@";

// 4. Send to Printer
try {
    if ($connectionType === 'network') {
        // Network Printer (IP Address, usually port 9100)
        $port = 9100;
        $ip = $printerAddress;
        
        // Timeout 3 seconds
        $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
        if (!$fp) {
            throw new Exception("Could not connect to network printer at $ip:$port ($errstr)");
        }
        
        fwrite($fp, $escposData);
        fclose($fp);
        
    } elseif ($connectionType === 'usb_shared') {
        // Windows Shared USB Printer (e.g. smb://COMPUTER/ReceiptPrinter or \\COMPUTER\ReceiptPrinter)
        // Using standard file copy method in PHP
        $path = $printerAddress;
        
        $fp = @fopen($path, "wb");
        if (!$fp) {
            throw new Exception("Could not open shared printer stream at $path");
        }
        
        fwrite($fp, $escposData);
        fclose($fp);
    } else {
        throw new Exception("Unknown print connection type: $connectionType");
    }

    echo json_encode(['success' => true, 'message' => 'Print job sent successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
