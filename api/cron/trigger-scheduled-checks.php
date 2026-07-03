<?php
/**
 * Cron Job: Auto-trigger scheduled attendance checks
 * Run this every minute: * * * * * php /path/to/trigger-scheduled-checks.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $now = date('Y-m-d H:i:s');
    $oneMinuteAgo = date('Y-m-d H:i:s', strtotime('-1 minute'));
    
    // Find scheduled checks that should be triggered now
    $query = "SELECT cp.id, cp.session_id, cp.schedule_id, cp.check_number, cp.check_time, cp.window_end_time
              FROM attendance_check_points cp
              JOIN teacher_locations tl ON cp.session_id = tl.id
              WHERE cp.is_active = FALSE
              AND tl.is_active = TRUE
              AND tl.auto_schedule = TRUE
              AND cp.check_time BETWEEN :one_minute_ago AND :now";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':one_minute_ago', $oneMinuteAgo);
    $stmt->bindParam(':now', $now);
    $stmt->execute();
    
    $checksToTrigger = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($checksToTrigger as $check) {
        // Activate the check
        $updateQuery = "UPDATE attendance_check_points 
                        SET is_active = TRUE, check_time = NOW() 
                        WHERE id = :check_id";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':check_id', $check['id']);
        $stmt->execute();
        
        // Update session checks_completed counter
        $updateSessionQuery = "UPDATE teacher_locations 
                               SET checks_completed = checks_completed + 1 
                               WHERE id = :session_id";
        $stmt = $db->prepare($updateSessionQuery);
        $stmt->bindParam(':session_id', $check['session_id']);
        $stmt->execute();
        
        // Send push notifications to students (if FCM is configured)
        // This would call your FCM service
        sendPushNotification($db, $check['schedule_id'], $check['check_number']);
        
        echo "✓ Triggered check #{$check['check_number']} for session #{$check['session_id']}\n";
    }
    
    if (empty($checksToTrigger)) {
        echo "No checks to trigger at this time.\n";
    }
    
} catch (Exception $e) {
    error_log("Cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

function sendPushNotification($db, $scheduleId, $checkNumber) {
    // Get students for this schedule
    $query = "SELECT DISTINCT s.id, s.user_id, u.full_name
              FROM students s
              JOIN teacher_assignments ta ON s.department_id = ta.department_id AND s.semester = ta.semester
              JOIN schedules sc ON ta.id = sc.assignment_id
              JOIN users u ON s.user_id = u.id
              WHERE sc.id = :schedule_id AND (ta.section IS NULL OR s.section = ta.section)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get FCM tokens for these students
    foreach ($students as $student) {
        $tokenQuery = "SELECT token FROM fcm_tokens WHERE user_id = :user_id AND is_active = TRUE";
        $stmt = $db->prepare($tokenQuery);
        $stmt->bindParam(':user_id', $student['user_id']);
        $stmt->execute();
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tokens as $token) {
            // Send FCM notification
            // You would implement this based on your FCM setup
            // sendFCMNotification($token, "Attendance Check #$checkNumber", "Mark your attendance now!");
        }
    }
}
?>
