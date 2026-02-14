<?php
/**
 * Check teacher assignments and schedules data
 */

require_once __DIR__ . '/config/database.php';

try {
    putenv('DATABASE_URL=postgres://u6pa1o10ujrn7p:p2efc1037cad4f9b81fe10b2e2ddfc9bb1fff62ec3571e12677a166f6fe9c143c@chepvbj2ergru.cluster-czrs8kj4isg7.us-east-1.rds.amazonaws.com:5432/d7mv44je5e9cj5');

    $database = new Database();
    $db = $database->getConnection();

    echo "Checking Teacher Assignments and Schedules Data:\n\n";

    $tables = ['teacher_assignments', 'schedules', 'attendance'];
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo ucfirst(str_replace('_', ' ', $table)) . ': ' . $result['count'] . "\n";
    }

    echo "\nTeacher Assignments:\n";
    $stmt = $db->query("SELECT ta.*, t.employee_id, s.name as subject_name FROM teacher_assignments ta JOIN teachers t ON ta.teacher_id = t.id JOIN subjects s ON ta.subject_id = s.id ORDER BY ta.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- ID: {$row['id']}, Teacher: {$row['employee_id']}, Subject: {$row['subject_name']}, Year: {$row['academic_year']}\n";
    }

    echo "\nSchedules:\n";
    $stmt = $db->query("SELECT s.*, ta.class_name, sub.name as subject_name FROM schedules s JOIN teacher_assignments ta ON s.teacher_assignment_id = ta.id JOIN subjects sub ON ta.subject_id = sub.id ORDER BY s.id LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- ID: {$row['id']}, Class: {$row['class_name']}, Subject: {$row['subject_name']}, Day: {$row['day_of_week']}, Time: {$row['start_time']}-{$row['end_time']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>