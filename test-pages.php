<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        // Test assignments query (simplified for minimal schema)
        $stmt = $db->query("SELECT ta.*, u.full_name, sub.name as subject_name
                           FROM teacher_assignments ta
                           LEFT JOIN teachers t ON ta.teacher_id = t.id
                           LEFT JOIN users u ON t.user_id = u.id
                           LEFT JOIN subjects sub ON ta.subject_id = sub.id
                           ORDER BY u.full_name");
        $assignments = $stmt->fetchAll();
        echo "Assignments found: " . count($assignments) . "\n";

        // Test schedules query (simplified for minimal schema)
        $stmt = $db->query("SELECT s.*, ta.class_name, u.full_name, sub.name as subject_name
                           FROM schedules s
                           LEFT JOIN teacher_assignments ta ON s.teacher_assignment_id = ta.id
                           LEFT JOIN teachers t ON ta.teacher_id = t.id
                           LEFT JOIN users u ON t.user_id = u.id
                           LEFT JOIN subjects sub ON ta.subject_id = sub.id
                           ORDER BY s.day_of_week, s.start_time");
        $schedules = $stmt->fetchAll();
        echo "Schedules found: " . count($schedules) . "\n";

        echo "Assignment and schedule pages should work correctly!\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database connection failed\n";
}
?>