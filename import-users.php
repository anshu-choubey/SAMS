<?php
/**
 * Import basic users data for Heroku
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Importing users data...\n";

    // Clear existing users
    $db->exec("DELETE FROM users");

    // Import basic users from the original setup
    $users = [
        [1, 'Admin User', 'admin@sams.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+1234567890', null, true, '2026-02-11 06:05:06', '2026-02-11 11:26:47'],
        [2, 'EMP888', 'teacher1@sams.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', null, null, true, '2026-02-11 06:13:34', '2026-02-11 06:13:34'],
        [3, '68686868', 'teacher2@sams.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', null, null, true, '2026-02-11 14:07:51', '2026-02-11 14:07:51'],
        [4, 'dsfdf', 'teacher3@sams.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', null, null, true, '2026-02-11 15:25:25', '2026-02-11 15:25:25'],
        [5, 'freer', 'teacher4@sams.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', null, null, true, '2026-02-12 09:18:52', '2026-02-12 09:18:52'],
        [6, 'Student One', 'student1@sams.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', null, null, true, '2026-02-11 06:13:34', '2026-02-11 06:13:34']
    ];

    $stmt = $db->prepare("INSERT INTO users (id, full_name, email, password_hash, role, phone, profile_image, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($users as $user) {
        try {
            $stmt->execute($user);
            echo "Inserted user: {$user[1]} ({$user[2]}) - {$user[4]}\n";
        } catch (Exception $e) {
            echo "Error inserting user {$user[1]}: " . $e->getMessage() . "\n";
        }
    }

    echo "\nUsers import completed!\n";
    echo "Total users: " . count($users) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>