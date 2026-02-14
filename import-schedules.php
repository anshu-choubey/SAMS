<?php
/**
 * Import schedules data
 */

require_once __DIR__ . '/config/database.php';

try {
    putenv('DATABASE_URL=postgres://u6pa1o10ujrn7p:p2efc1037cad4f9b81fe10b2e2ddfc9bb1fff62ec3571e12677a166f6fe9c143c@chepvbj2ergru.cluster-czrs8kj4isg7.us-east-1.rds.amazonaws.com:5432/d7mv44je5e9cj5');

    $database = new Database();
    $db = $database->getConnection();

    echo "Importing schedules...\n";

    // Clear existing schedules
    $db->exec("DELETE FROM schedules");

    // Import schedules for the teacher assignments we created
    $schedules = [
        [1, 1, 1, '09:00:00', '10:00:00', 'Room 101', null, null, null, true, '2026-02-11 10:28:35', '2026-02-11 10:28:35'], // Monday class for assignment 1
        [2, 2, 1, '10:00:00', '11:00:00', 'Room 102', null, null, null, true, '2026-02-11 14:16:36', '2026-02-11 14:16:36'], // Monday class for assignment 2
        [3, 3, 2, '11:00:00', '12:00:00', 'Room 103', null, null, null, true, '2026-02-11 15:26:50', '2026-02-11 15:26:50'], // Tuesday class for assignment 3
        [4, 4, 3, '14:00:00', '15:00:00', 'Room 104', null, null, null, true, '2026-02-12 09:00:58', '2026-02-12 09:00:58'], // Wednesday class for assignment 4
        [5, 1, 3, '15:00:00', '16:00:00', 'Room 105', null, null, null, true, '2026-02-12 09:19:26', '2026-02-12 09:19:26'], // Wednesday class for assignment 1
        [6, 2, 5, '16:00:00', '17:00:00', 'Lab 1', null, null, null, true, '2026-02-13 11:35:08', '2026-02-13 11:35:08'], // Friday class for assignment 2
    ];

    $stmt = $db->prepare("INSERT INTO schedules (id, teacher_assignment_id, day_of_week, start_time, end_time, room, latitude, longitude, qr_code, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($schedules as $schedule) {
        try {
            $stmt->execute($schedule);
            echo "Inserted schedule: Assignment {$schedule[1]}, Day {$schedule[2]}, {$schedule[3]}-{$schedule[4]}\n";
        } catch (Exception $e) {
            echo "Error inserting schedule: " . $e->getMessage() . "\n";
        }
    }

    echo "\nSchedules import completed!\n";

    // Verify the import
    $stmt = $db->query("SELECT COUNT(*) as count FROM schedules");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total schedules: " . $result['count'] . "\n";

} catch (Exception $e) {
    echo "Import failed: " . $e->getMessage() . "\n";
}
?>