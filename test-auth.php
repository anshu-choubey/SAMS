<?php
/**
 * Test Authentication Script
 * Verify login credentials work locally
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/models/User.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!\n");
}

$user = new User($db);

// Test login
$email = 'admin@sams.edu';
$password = 'Admin@123';

echo "Testing login credentials:\n";
echo "Email: $email\n";
echo "Password: $password\n\n";

$result = $user->verifyPassword($email, $password);

if ($result) {
    echo "✅ LOGIN SUCCESSFUL!\n";
    echo "User Details:\n";
    echo "  ID: " . $result['id'] . "\n";
    echo "  Name: " . $result['full_name'] . "\n";
    echo "  Email: " . $result['email'] . "\n";
    echo "  Role: " . $result['role'] . "\n";
    echo "  Active: " . ($result['is_active'] ? 'Yes' : 'No') . "\n";
} else {
    echo "❌ LOGIN FAILED - Invalid credentials!\n";
}

// Verify password hash
echo "\nPassword Hash Verification:\n";
$userRecord = $user->getByEmail($email);
if ($userRecord) {
    $hashMatch = password_verify($password, $userRecord['password_hash']);
    echo "Hash: " . substr($userRecord['password_hash'], 0, 20) . "...\n";
    echo "Verification: " . ($hashMatch ? '✅ Pass' : '❌ Fail') . "\n";
}

?>
