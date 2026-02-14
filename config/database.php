<?php
/**
 * Database Configuration
 * PDO-based database connection with error handling
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    private $conn;

    public function __construct() {
        // Check for JAWSDB_URL (JawsDB on Heroku) or DATABASE_URL (Heroku style)
        $databaseUrl = getenv('JAWSDB_URL') ?: getenv('DATABASE_URL');
        if ($databaseUrl) {
            $this->parseDatabaseUrl($databaseUrl);
        } else {
            // Local defaults
            $this->host = getenv('MYSQL_HOST') ?: 'localhost';
            $this->db_name = getenv('MYSQL_DATABASE') ?: 'sams_db';
            $this->username = getenv('MYSQL_USER') ?: 'root';
            $this->password = getenv('MYSQL_PASSWORD') ?: '';
        }
    }

    private function parseDatabaseUrl($url) {
        $parsed = parse_url($url);
        $this->host = $parsed['host'];
        $this->db_name = ltrim($parsed['path'], '/');
        $this->username = $parsed['user'];
        $this->password = $parsed['pass'];
    }

    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            return null;
        }

        return $this->conn;
    }

    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
}
