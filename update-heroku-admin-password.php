<?php
/**
 * Update Admin Password on Heroku
 */

$db_url = getenv('JAWSDB_URL') ?: getenv('DATABASE_URL');
$url_parts = parse_url($db_url);

$pdo = new PDO(
    "mysql:host=" . $url_parts['host'] . ";dbname=" . trim($url_parts['path'], '/') . ";charset=utf8mb4",
    $url_parts['user'],
    $url_parts['pass']
);

$new_hash = '$2y$12$s3blvAa6epcQAWruexnHt.UULDNaZKx3Ud0jiwwIYqz1TwrfoKom.';

$query = "UPDATE users SET password_hash = ? WHERE email = ?";
$stmt = $pdo->prepare($query);
$result = $stmt->execute([$new_hash, 'admin@sams.edu']);

if ($result) {
    echo "✅ Admin password updated successfully!\n";
    echo "\nLogin with:\n";
    echo "  Email: admin@sams.edu\n";
    echo "  Password: Admin@123\n";
} else {
    echo "❌ Failed to update admin password\n";
}

?>
