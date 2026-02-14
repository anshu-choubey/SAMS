<?php
/**
 * Check what data the teacher assignments and schedules pages show
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "=== TEACHER ASSIGNMENTS PAGE DATA ===\n\n";

    // Query used by teacher-assignments.php (adapted for our schema)
    $stmt = $db->query("SELECT ta.*, u.full_name, sub.name as subject_name
                       FROM teacher_assignments ta
                       LEFT JOIN teachers t ON ta.teacher_id = t.id
                       LEFT JOIN users u ON t.user_id = u.id
                       LEFT JOIN subjects sub ON ta.subject_id = sub.id
                       ORDER BY u.full_name");

    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total assignments: " . count($assignments) . "\n\n";

    foreach ($assignments as $assignment) {
        echo "Assignment ID: {$assignment['id']}\n";
        echo "Teacher: " . ($assignment['full_name'] ?? 'Unknown') . "\n";
        echo "Subject: {$assignment['subject_name']}\n";
        echo "Class: " . ($assignment['class_name'] ?? 'N/A') . "\n";
        echo "Semester: {$assignment['semester']}\n";
        echo "Academic Year: {$assignment['academic_year']}\n";
        echo "---\n";
    }

    echo "\n=== SCHEDULES PAGE DATA ===\n\n";

    // Query used by schedules.php (adapted for our schema)
    $stmt = $db->query("SELECT s.*, ta.class_name, u.full_name, sub.name as subject_name
                       FROM schedules s
                       LEFT JOIN teacher_assignments ta ON s.teacher_assignment_id = ta.id
                       LEFT JOIN teachers t ON ta.teacher_id = t.id
                       LEFT JOIN users u ON t.user_id = u.id
                       LEFT JOIN subjects sub ON ta.subject_id = sub.id
                       ORDER BY s.day_of_week, s.start_time");

    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total schedules: " . count($schedules) . "\n\n";

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    foreach ($schedules as $schedule) {
        echo "Schedule ID: {$schedule['id']}\n";
        echo "Teacher: " . ($schedule['full_name'] ?? 'Unknown') . "\n";
        echo "Subject: {$schedule['subject_name']}\n";
        echo "Class: {$schedule['class_name']}\n";
        echo "Day: " . ($days[$schedule['day_of_week'] - 1] ?? 'Unknown') . " ({$schedule['day_of_week']})\n";
        echo "Time: {$schedule['start_time']} - {$schedule['end_time']}\n";
        echo "Room: {$schedule['room']}\n";
        echo "---\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>