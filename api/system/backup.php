<?php
// api/system/backup.php - Backup Control Panel API (Hosting Compatible)
// Uses pure PHP PDO dump — no exec(), no shell commands, no Windows paths.

error_reporting(0);
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Admin check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Admin login required.']);
    exit;
}

// Backup directory — inside the project, protected by .htaccess
define('BACKUP_DIR', dirname(__DIR__, 2) . '/backups');

// Ensure backup directory exists
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
    // Write .htaccess to block direct browser access
    file_put_contents(BACKUP_DIR . '/.htaccess', "Deny from all\n");
}

$action = $_GET['action'] ?? '';

// For download action, we stream a file — no JSON header
if ($action === 'download') {
    downloadBackup();
    exit;
}

header('Content-Type: application/json');
ob_clean();

switch ($action) {
    case 'status':
        getSystemStatus();
        break;
    case 'list_files':
        listBackupFiles();
        break;
    case 'run_backup':
        runBackup();
        break;
    case 'run_test':
        runRestoreTest();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

// ──────────────────────────────────────────────
// STATUS
// ──────────────────────────────────────────────
function getSystemStatus()
{
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Binary logging
        $stmt = $conn->query("SHOW VARIABLES LIKE 'log_bin'");
        $binlog = $stmt->fetch();
        $binlogEnabled = ($binlog && $binlog['Value'] === 'ON');

        // DB size & table count
        $stmt = $conn->query("
            SELECT SUM(data_length + index_length) AS size, COUNT(*) AS table_count
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
        ");
        $dbInfo = $stmt->fetch();

        // Last backup
        $lastBackup = null;
        $hoursSinceBackup = 999;
        $files = glob(BACKUP_DIR . '/*.zip');
        if (!empty($files)) {
            usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
            $latest = $files[0];
            $lastBackup = [
                'name' => basename($latest),
                'size' => filesize($latest),
                'time' => filemtime($latest),
            ];
            $hoursSinceBackup = (time() - filemtime($latest)) / 3600;
        }

        echo json_encode([
            'success' => true,
            'binlog_enabled' => $binlogEnabled,
            'db_size' => (int) ($dbInfo['size'] ?? 0),
            'table_count' => (int) ($dbInfo['table_count'] ?? 0),
            'last_backup' => $lastBackup,
            'hours_since_backup' => round($hoursSinceBackup, 1),
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ──────────────────────────────────────────────
// LIST FILES
// ──────────────────────────────────────────────
function listBackupFiles()
{
    $files = [];
    $items = glob(BACKUP_DIR . '/*.zip');
    if (!empty($items)) {
        usort($items, fn($a, $b) => filemtime($b) - filemtime($a));
        foreach ($items as $item) {
            $files[] = [
                'name' => basename($item),
                'size' => filesize($item),
                'date' => date('Y-m-d H:i:s', filemtime($item)),
            ];
        }
    }
    echo json_encode(['success' => true, 'files' => $files]);
}

// ──────────────────────────────────────────────
// RUN BACKUP  (pure-PHP SQL dump → zip)
// ──────────────────────────────────────────────
function runBackup()
{
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Collect all table names
        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            echo json_encode(['success' => false, 'error' => 'No tables found in database.']);
            return;
        }

        $sql = "-- Ella POS Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Structure
            $createStmt = $conn->query("SHOW CREATE TABLE `$table`")->fetch();
            $sql .= "-- Table: $table\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createStmt['Create Table'] . ";\n\n";

            // Data
            $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $chunks = array_chunk($rows, 100);
                foreach ($chunks as $chunk) {
                    $values = [];
                    foreach ($chunk as $row) {
                        $escaped = array_map(function ($v) use ($conn) {
                            if ($v === null)
                                return 'NULL';
                            return $conn->quote($v);
                        }, array_values($row));
                        $values[] = '(' . implode(', ', $escaped) . ')';
                    }
                    $sql .= "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $values) . ";\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Write SQL file
        $filename = 'ella_backup_' . date('Ymd_His') . '.sql';
        $sqlPath = BACKUP_DIR . '/' . $filename;
        file_put_contents($sqlPath, $sql);

        // Compress to zip
        $zipFilename = str_replace('.sql', '.zip', $filename);
        $zipPath = BACKUP_DIR . '/' . $zipFilename;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                $zip->addFile($sqlPath, $filename);
                $zip->close();
                unlink($sqlPath);  // Remove raw SQL, keep zip only
                $finalFile = $zipFilename;
                $finalPath = $zipPath;
            } else {
                // If zip fails, keep .sql
                $finalFile = $filename;
                $finalPath = $sqlPath;
            }
        } else {
            // ZipArchive not available — serve raw SQL
            $finalFile = $filename;
            $finalPath = $sqlPath;
        }

        // Keep only latest 10 backups to avoid disk bloat
        pruneOldBackups(10);

        echo json_encode([
            'success' => true,
            'filename' => $finalFile,
            'size' => filesize($finalPath),
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ──────────────────────────────────────────────
// RESTORE TEST  (read-only validation)
// ──────────────────────────────────────────────
function runRestoreTest()
{
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $files = glob(BACKUP_DIR . '/*.{zip,sql}', GLOB_BRACE);
        if (empty($files)) {
            echo json_encode(['success' => false, 'error' => 'No backup files found. Run a backup first.']);
            return;
        }

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $latestFile = $files[0];

        $results = [];
        $results[] = 'Latest backup: ' . basename($latestFile);
        $results[] = 'Size: ' . formatBytesServer(filesize($latestFile));
        $results[] = 'Date: ' . date('Y-m-d H:i:s', filemtime($latestFile));

        // Check it's a valid zip
        if (pathinfo($latestFile, PATHINFO_EXTENSION) === 'zip' && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($latestFile) === true) {
                $results[] = 'PASS: ZIP archive is valid';
                $numFiles = $zip->numFiles;
                $results[] = "PASS: Archive contains $numFiles file(s)";

                // Read SQL content for basic validation
                $sqlContent = $zip->getFromIndex(0);
                $zip->close();

                if ($sqlContent !== false) {
                    $tableCount = substr_count($sqlContent, 'CREATE TABLE');
                    $results[] = "PASS: SQL contains $tableCount CREATE TABLE statements";
                    $insertCount = substr_count($sqlContent, 'INSERT INTO');
                    $results[] = "PASS: SQL contains $insertCount INSERT INTO blocks";
                } else {
                    $results[] = 'WARNING: Could not read SQL from archive';
                }
            } else {
                $results[] = 'FAIL: ZIP archive could not be opened';
            }
        } elseif (pathinfo($latestFile, PATHINFO_EXTENSION) === 'sql') {
            $sqlContent = file_get_contents($latestFile, false, null, 0, 65536);
            if (strpos($sqlContent, 'CREATE TABLE') !== false) {
                $results[] = 'PASS: SQL file is readable';
            } else {
                $results[] = 'WARNING: SQL file may be empty or invalid';
            }
        }

        // Verify live DB is accessible
        $tableCount = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
        $results[] = "PASS: Live database accessible ($tableCount tables)";

        echo json_encode(['success' => true, 'results' => $results]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ──────────────────────────────────────────────
// DOWNLOAD
// ──────────────────────────────────────────────
function downloadBackup()
{
    $filename = $_GET['file'] ?? '';

    // Security: prevent path traversal
    if (empty($filename) || strpos($filename, '..') !== false || preg_match('/[\/\\\\]/', $filename)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid filename']);
        return;
    }

    // Allow only .zip and .sql files
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['zip', 'sql'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid file type']);
        return;
    }

    $filepath = BACKUP_DIR . '/' . $filename;
    if (!file_exists($filepath) || !is_file($filepath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'File not found']);
        return;
    }

    ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($filepath);
    exit;
}

// ──────────────────────────────────────────────
// HELPERS
// ──────────────────────────────────────────────
function pruneOldBackups(int $keep = 10)
{
    $files = glob(BACKUP_DIR . '/*.{zip,sql}', GLOB_BRACE);
    if (count($files) <= $keep)
        return;
    usort($files, fn($a, $b) => filemtime($a) - filemtime($b)); // oldest first
    $toDelete = array_slice($files, 0, count($files) - $keep);
    foreach ($toDelete as $f) {
        @unlink($f);
    }
}

function formatBytesServer(int $bytes): string
{
    if ($bytes === 0)
        return '0 Bytes';
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}
