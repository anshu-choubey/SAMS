<?php
/**
 * Database Connection Test Endpoint
 * For deployment verification only - remove in production
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Test query
    $stmt = $conn->query("SELECT VERSION() as version, DATABASE() as database_name");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get table count
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'data' => [
            'mysql_version' => $result['version'],
            'database_name' => $result['database_name'],
            'table_count' => count($tables),
            'tables' => $tables,
            'connection_info' => $database->getConnectionInfo()
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'connection_info' => isset($database) ? $database->getConnectionInfo() : null
    ], JSON_PRETTY_PRINT);
}
?>