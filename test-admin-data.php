<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        // Test teacher assignments query
        $stmt = $db->query("SELECT ta.*, u.full_name, sub.name as subject_name
                           FROM teacher_assignments ta
                           LEFT JOIN teachers t ON ta.teacher_id = t.id
                           LEFT JOIN users u ON t.user_id = u.id
                           LEFT JOIN subjects sub ON ta.subject_id = sub.id
                           ORDER BY u.full_name");
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Teacher Assignments (" . count($assignments) . "):\n";
        foreach ($assignments as $assignment) {
            echo "- " . ($assignment['full_name'] ?? 'Unknown') . ": " . $assignment['subject_name'] . " (" . $assignment['class_name'] . ")\n";
        }

        echo "\n";

        // Test schedules query
        $stmt = $db->query("SELECT s.*, ta.class_name, u.full_name, sub.name as subject_name
                           FROM schedules s
                           LEFT JOIN teacher_assignments ta ON s.teacher_assignment_id = ta.id
                           LEFT JOIN teachers t ON ta.teacher_id = t.id
                           LEFT JOIN users u ON t.user_id = u.id
                           LEFT JOIN subjects sub ON ta.subject_id = sub.id
                           ORDER BY s.day_of_week, s.start_time");
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Schedules (" . count($schedules) . "):\n";
        foreach ($schedules as $schedule) {
            echo "- " . ($schedule['full_name'] ?? 'Unknown') . ": " . $schedule['subject_name'] . " (" . $schedule['class_name'] . ") - " . $schedule['day_of_week'] . " " . $schedule['start_time'] . "-" . $schedule['end_time'] . "\n";
        }

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database connection failed\n";
}
?>