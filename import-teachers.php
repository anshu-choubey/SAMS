<?php
/**
 * Import teachers data for Heroku
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Importing teachers data...\n";

    // Clear existing teachers
    $db->exec("DELETE FROM teachers");

    // Import teachers data
    $teachers = [
        [1, 2, 'EMP888', 1, 'Computer Science', '2026-02-11 06:13:34', '2026-02-11 06:13:34'],
        [2, 3, '68686868', 1, 'Mathematics', '2026-02-11 14:07:51', '2026-02-11 14:07:51'],
        [3, 4, 'dsfdf', 1, 'Physics', '2026-02-11 15:25:25', '2026-02-11 15:25:25'],
        [4, 5, 'freer', 1, 'Chemistry', '2026-02-12 09:18:52', '2026-02-12 09:18:52']
    ];

    $stmt = $db->prepare("INSERT INTO teachers (id, user_id, employee_id, department_id, specialization, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($teachers as $teacher) {
        try {
            $stmt->execute($teacher);
            echo "Inserted teacher: {$teacher[2]} (User ID: {$teacher[1]})\n";
        } catch (Exception $e) {
            echo "Error inserting teacher {$teacher[2]}: " . $e->getMessage() . "\n";
        }
    }

    echo "\nTeachers import completed!\n";
    echo "Total teachers: " . count($teachers) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>