<?php
/**
 * Import teacher assignments data
 */

require_once __DIR__ . '/config/database.php';

try {
    putenv('DATABASE_URL=postgres://u6pa1o10ujrn7p:p2efc1037cad4f9b81fe10b2e2ddfc9bb1fff62ec3571e12677a166f6fe9c143c@chepvbj2ergru.cluster-czrs8kj4isg7.us-east-1.rds.amazonaws.com:5432/d7mv44je5e9cj5');

    $database = new Database();
    $db = $database->getConnection();

    echo "Importing teacher assignments...\n";

    // Clear existing assignments
    $db->exec("DELETE FROM teacher_assignments");

    // Import assignments for teachers we have (IDs 2,3,4,6)
    // From dump: (2,3,'Monday','12:32:00','12:34:00','fdssfds','fdsdffd',1,'2026-02-11 14:16:36','2026-02-11 14:16:36')
    // This references teacher_id 2, subject_id 3 (but we only have subject_id 3)
    $assignments = [
        [1, 2, 3, 'Class A', 1, '2024-2025', '2026-02-11 14:16:36'], // teacher 2, subject 3
        [2, 3, 3, 'Class B', 1, '2025-2026', '2026-02-11 15:26:50'], // teacher 3, subject 3
        [3, 4, 3, 'Class C', 2, '2025-2026', '2026-02-12 09:00:58'], // teacher 4, subject 3
        [4, 6, 3, 'Class D', 1, '2025-2026', '2026-02-12 09:19:26'], // teacher 6, subject 3
    ];

    $stmt = $db->prepare("INSERT INTO teacher_assignments (id, teacher_id, subject_id, class_name, semester, academic_year, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($assignments as $assignment) {
        try {
            // Remove the updated_at from the data array
            $data = array_slice($assignment, 0, 7);
            $stmt->execute($data);
            echo "Inserted assignment: Teacher {$assignment[1]} -> Subject {$assignment[2]}\n";
        } catch (Exception $e) {
            echo "Error inserting assignment: " . $e->getMessage() . "\n";
        }
    }

    echo "\nTeacher assignments import completed!\n";

    // Verify the import
    $stmt = $db->query("SELECT COUNT(*) as count FROM teacher_assignments");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total assignments: " . $result['count'] . "\n";

} catch (Exception $e) {
    echo "Import failed: " . $e->getMessage() . "\n";
}
?>