<?php
/**
 * Fix Admin Password
 * Generate correct hash for password "Admin@123"
 */

require_once __DIR__ . '/config/database.php';

$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Password: {$password}\n";
echo "Generated Hash: {$hash}\n\n";

// Connect to database
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!\n");
}

// Update admin user password
$query = "UPDATE users SET password_hash = :hash WHERE email = 'admin@sams.edu'";
$stmt = $db->prepare($query);
$stmt->bindParam(':hash', $hash);

if ($stmt->execute()) {
    echo "✅ Password updated successfully!\n";
    echo "\nLogin Credentials:\n";
    echo "  Email: admin@sams.edu\n";
    echo "  Password: Admin@123\n";
} else {
    echo "❌ Failed to update password\n";
}

?>
