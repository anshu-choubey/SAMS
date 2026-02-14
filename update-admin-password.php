<?php
/**
 * Update admin password
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Updating admin password...\n";

    // Generate hash for admin@123
    $passwordHash = password_hash('admin@123', PASSWORD_DEFAULT);

    // Update admin user
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$passwordHash, 'admin@sams.com']);

    echo "Admin password updated successfully!\n";
    echo "Email: admin@sams.com\n";
    echo "Password: admin@123\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>