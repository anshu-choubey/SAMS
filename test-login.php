<?php
require 'config/database.php';
require 'includes/models/User.php';

$db = (new Database())->getConnection();
$user = new User($db);
$result = $user->verifyPassword('admin@sams.edu', 'Admin@123');

if ($result) {
    echo "✅ LOGIN SUCCESSFUL!\n";
    echo "User: {$result['full_name']}\n";
    echo "Email: {$result['email']}\n";
    echo "Role: {$result['role']}\n";
} else {
    echo "❌ LOGIN FAILED\n";
}
?>
