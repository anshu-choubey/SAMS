<?php
/**
 * Initialize Heroku Database Schema
 * Access: https://sams-backend-73451-bca7cff1a531.herokuapp.com/init-heroku-schema.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Read schema file
$schema = file_get_contents(__DIR__ . '/config/schema.sql');

if (!$schema) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not read schema file']);
    exit;
}

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $schema)));
$successCount = 0;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement)) {
        continue;
    }
    
    try {
        $db->exec($statement);
        $successCount++;
    } catch (PDOException $e) {
        // Ignore "table already exists" errors
        if (strpos($e->getMessage(), 'already exists') === false) {
            $errors[] = $e->getMessage();
        }
        $successCount++;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Schema initialization complete',
    'statements_executed' => $successCount,
    'errors' => count($errors) > 0 ? $errors : 'None'
]);
?>
