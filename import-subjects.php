<?php
/**
 * Import subjects data from MySQL dump
 */

require_once __DIR__ . '/config/database.php';

try {
    putenv('DATABASE_URL=postgres://u6pa1o10ujrn7p:p2efc1037cad4f9b81fe10b2e2ddfc9bb1fff62ec3571e12677a166f6fe9c143c@chepvbj2ergru.cluster-czrs8kj4isg7.us-east-1.rds.amazonaws.com:5432/d7mv44je5e9cj5');

    $database = new Database();
    $db = $database->getConnection();

    echo "Importing subjects data...\n";

    // Clear existing subjects
    $db->exec("DELETE FROM subjects");

    // Insert subjects data from the dump
    $subjects = [
        [1, 'kjckkcs543345', '355335', 1, 3, 1, 'sfssfd', true, '2026-02-11 06:07:14', '2026-02-11 11:28:44'],
        [2, 'esfsdfsd', 'FSDFSD', 1, 3, 2, 'sfddsds', true, '2026-02-13 10:30:56', '2026-02-13 10:30:56'],
        [3, 'dgfr', 'RDGRD', 1, 3, 1, '', true, '2026-02-13 10:34:17', '2026-02-13 10:34:17']
    ];

    $stmt = $db->prepare("INSERT INTO subjects (id, name, code, department_id, credits, semester, description, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($subjects as $subject) {
        $stmt->execute($subject);
        echo "Inserted subject: {$subject[1]} ({$subject[2]})\n";
    }

    echo "\nSubjects import completed!\n";

    // Verify the import
    $stmt = $db->query("SELECT COUNT(*) as count FROM subjects");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total subjects: " . $result['count'] . "\n";

} catch (Exception $e) {
    echo "Import failed: " . $e->getMessage() . "\n";
}
?>