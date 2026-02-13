<?php
/**
 * Production Database Configuration for Azure
 * Uses environment variables for security
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $ssl_mode;
    public $conn;

    public function __construct() {
        // Use environment variables for production
        $this->host = getenv('MYSQL_HOST') ?: 'sams-mysql-server.mysql.database.azure.com';
        $this->db_name = getenv('MYSQL_DATABASE') ?: 'sams_db';
        $this->username = getenv('MYSQL_USER') ?: 'samsadmin@sams-mysql-server';
        $this->password = getenv('MYSQL_PASSWORD') ?: '';
        $this->port = getenv('MYSQL_PORT') ?: 3306;
        $this->ssl_mode = getenv('MYSQL_SSL') ?: 'PREFERRED'; // Azure requires SSL
    }

    public function getConnection() {
        $this->conn = null;

        try {
            // Azure MySQL connection string
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            // Add SSL options for Azure
            if ($this->ssl_mode !== 'DISABLED') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt'; // For Linux
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            // Test connection
            $this->conn->query('SELECT 1');

        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }

        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }

    // Get database info for debugging (remove in production)
    public function getConnectionInfo() {
        return [
            'host' => $this->host,
            'database' => $this->db_name,
            'username' => $this->username,
            'port' => $this->port,
            'ssl_mode' => $this->ssl_mode
        ];
    }
}
?>