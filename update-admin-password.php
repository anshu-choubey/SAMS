<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        echo "Database connection successful!\n";

        // Update admin password
        $newHash = '$2y$12$EfE9yo8DnNgdDAIohaOmUu.kGE8ghPFtfsyjpYmwJBjfF9bLUSb96';
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([$newHash, 'admin@sams.com']);

        echo "Admin password updated successfully!\n";
        echo "Email: admin@sams.com\n";
        echo "Password: admin@123\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>