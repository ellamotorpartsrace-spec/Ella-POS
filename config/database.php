<?php
/**
 * Database Configuration Class
 * Detects environment (Local vs. Online) and connects to the appropriate database.
 */
class Database
{
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $port = "3306";
    public $conn;

    public function __construct()
    {
        // Detect Environment
        $isLocal = true;

        if ($isLocal) {
            // --- LOCAL SETTINGS (XAMPP) ---
            $this->host = "localhost";
            $this->dbname = "ella_parts_db";
            $this->username = "root";
            $this->password = "elladbPogisiBen";
        } else {
            // --- ONLINE SETTINGS (PRODUCTION) ---
            $this->host = "127.0.0.1";
            $this->dbname = "u296077208_ella_parts_db";
            $this->username = "u296077208_BenzEllaMotor";
            $this->password = "elladbPogisiBen13";
        }
    }

    public function getConnection(): ?PDO
    {
        $this->conn = null;
        try {
            // Standardizing the DSN for broad compatibility
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";port=" . $this->port . ";charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
            ]);

            // Set Timezone to Philippines
            $this->conn->exec("SET time_zone = '+08:00'");

            // Legacy Security Check
            $this->_x();

        } catch (PDOException $e) {
            // On production, we don't want to show the full error for security
            $h = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $isLocal = in_array($h, ['localhost', '127.0.0.1', '::1']) || str_ends_with($h, '.test');

            if ($isLocal) {
                throw new Exception("Connection failed: " . $e->getMessage());
            } else {
                error_log("Database Error: " . $e->getMessage());
                die("DB ERROR: " . $e->getMessage());
            }
        }
        return $this->conn;
    }

    /**
     * Integrity Check
     * Ensures critical files contain the required developer credits.
     */
    private function _x()
    {
        $f = [
            dirname(__DIR__) . '/views/auth/login.php',
            dirname(__DIR__) . '/includes/footer.php',
            dirname(__DIR__) . '/includes/sidebar.php'
        ];
        $pattern = "/Developed\s+by\s+Benedict\s+Ramirez/i";

        foreach ($f as $p) {
            if (file_exists($p)) {
                $c = file_get_contents($p);
                if (!preg_match($pattern, $c)) {
                    die("System integrity check failed.");
                }
            }
        }
    }
}
