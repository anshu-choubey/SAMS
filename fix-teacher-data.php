<?php
/**
 * Fix teacher assignments and schedules data
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Fixing teacher assignments and schedules...\n\n";

    // Check current assignments
    echo "Current teacher assignments:\n";
    $stmt = $db->query("SELECT ta.id, ta.teacher_id, t.id as teacher_exists FROM teacher_assignments ta LEFT JOIN teachers t ON ta.teacher_id = t.id ORDER BY ta.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Assignment {$row['id']}: teacher_id={$row['teacher_id']}, exists=" . ($row['teacher_exists'] ? 'YES' : 'NO') . "\n";
    }

    // Fix assignment 4 - change teacher_id from 6 to 5 (which exists)
    echo "\nFixing assignment 4 (changing teacher_id from 6 to 5)...\n";
    $db->exec("UPDATE teacher_assignments SET teacher_id = 5 WHERE id = 4");

    // Verify the fix
    echo "\nAfter fix:\n";
    $stmt = $db->query("SELECT ta.id, ta.teacher_id, t.id as teacher_exists, u.full_name FROM teacher_assignments ta LEFT JOIN teachers t ON ta.teacher_id = t.id LEFT JOIN users u ON t.user_id = u.id ORDER BY ta.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Assignment {$row['id']}: teacher_id={$row['teacher_id']}, exists=" . ($row['teacher_exists'] ? 'YES' : 'NO') . ", name=" . ($row['full_name'] ?? 'Unknown') . "\n";
    }

    // Check schedules
    echo "\nChecking schedules:\n";
    $stmt = $db->query("SELECT s.id, s.teacher_assignment_id, ta.id as assignment_exists FROM schedules s LEFT JOIN teacher_assignments ta ON s.teacher_assignment_id = ta.id ORDER BY s.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Schedule {$row['id']}: assignment_id={$row['teacher_assignment_id']}, exists=" . ($row['assignment_exists'] ? 'YES' : 'NO') . "\n";
    }

    echo "\nAll data should now be consistent!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>