<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$db = (new Database())->getConnection();

if (!$db) {
    echo json_encode(['error' => 'No DB connection']);
    exit;
}

try {
    // Update admin user password hash for "Admin@123"
    // Hash: $2y$12$s3blvAa6epcQAWruexnHt.UULDNaZKx3Ud0jiwwIYqz1TwrfoKom.
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $result = $stmt->execute(['$2y$12$s3blvAa6epcQAWruexnHt.UULDNaZKx3Ud0jiwwIYqz1TwrfoKom.', 'admin@sams.edu']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Admin password hash updated',
        'rows_affected' => $stmt->rowCount()
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
