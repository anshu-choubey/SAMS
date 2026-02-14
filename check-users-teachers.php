<?php
require_once __DIR__ . '/config/database.php';

try {
    putenv('DATABASE_URL=postgres://u6pa1o10ujrn7p:p2efc1037cad4f9b81fe10b2e2ddfc9bb1fff62ec3571e12677a166f6fe9c143c@chepvbj2ergru.cluster-czrs8kj4isg7.us-east-1.rds.amazonaws.com:5432/d7mv44je5e9cj5');

    $database = new Database();
    $db = $database->getConnection();

    echo "All Teachers in database:\n";
    $stmt = $db->query('SELECT t.id, t.employee_id, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY t.id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Teacher ID: {$row['id']}, Employee ID: {$row['employee_id']}, Name: {$row['full_name']}\n";
    }

    echo "\nAll Users:\n";
    $stmt = $db->query('SELECT id, full_name, role FROM users ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "User ID: {$row['id']}, Name: {$row['full_name']}, Role: {$row['role']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>