<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$db = (new Database())->getConnection();

if (!$db) {
    echo json_encode(['error' => 'No DB connection']);
    exit;
}

try {
    // Check users table
    $result = $db->query("SELECT id, email, full_name, role, password_hash FROM users LIMIT 10");
    $users = $result->fetchAll();
    
    echo json_encode([
        'users_count' => count($users),
        'users' => $users
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
