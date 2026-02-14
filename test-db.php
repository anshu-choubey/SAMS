<?php
/**
 * Test script to check database tables
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check departments details
    $stmt = $db->query("SELECT id, name, code, is_active FROM departments");
    $depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Departments:\n";
    foreach ($depts as $dept) {
        echo "  - {$dept['name']} ({$dept['code']}) - Active: " . ($dept['is_active'] ? 'Yes' : 'No') . "\n";
    }

    // Check subjects
    $stmt = $db->query("SELECT COUNT(*) as count FROM subjects");
    $subjects = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Subjects count: " . $subjects['count'] . "\n";

    // Check if subjects have semester column
    $stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'subjects' AND column_name = 'semester'");
    $semester = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Semester column exists: " . ($semester ? 'Yes' : 'No') . "\n";

    // Check if subjects have is_active column
    $stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'subjects' AND column_name = 'is_active'");
    $is_active = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Is_active column exists: " . ($is_active ? 'Yes' : 'No') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>