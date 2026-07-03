<?php
/**
 * Cron: Auto-Trigger Scheduled Attendance Checks
 * 
 * This script should be run every minute via cron to:
 * 1. Find scheduled checks that are due
 * 2. Activate them so students can respond
 * 3. Deactivate expired checks
 * 4. Schedule next check time for active sessions
 * 
 * Cron setup: * * * * * curl -s https://your-app.herokuapp.com/api/cron/trigger-scheduled-checks.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

// Simple token-based authentication for cron
$authToken = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
$expectedToken = getenv('CRON_SECRET_TOKEN') ?: 'sams-cron-2026';

// Allow internal calls without token
$isInternal = isset($_SERVER['HTTP_X_INTERNAL_CALL']);

if (!$isInternal && $authToken !== $expectedToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $now = date('Y-m-d H:i:s');
    $stats = [
        'activated' => 0,
        'expired' => 0,
        'sessions_updated' => 0
    ];
    
    // 1. Find scheduled checks that are due (scheduled_time <= NOW and not yet active)
    $dueQuery = "SELECT acp.*, tl.teacher_id, tl.auto_trigger_checks, tl.hide_timing_from_students
                 FROM attendance_check_points acp
                 JOIN teacher_locations tl ON acp.session_id = tl.id
                 WHERE acp.is_scheduled = TRUE 
                 AND acp.is_active = FALSE
                 AND acp.was_auto_triggered = FALSE
                 AND acp.scheduled_time <= :now
                 AND acp.window_end_time > :now
                 AND tl.is_active = TRUE
                 AND tl.auto_trigger_checks = TRUE";
    
    $stmt = $db->prepare($dueQuery);
    $stmt->bindParam(':now', $now);
    $stmt->execute();
    $dueChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($dueChecks as $check) {
        // Activate this check point
        $activateQuery = "UPDATE attendance_check_points 
                          SET is_active = TRUE, was_auto_triggered = TRUE, check_time = :now
                          WHERE id = :id";
        $stmt = $db->prepare($activateQuery);
        $stmt->bindParam(':now', $now);
        $stmt->bindParam(':id', $check['id']);
        $stmt->execute();
        
        // Update session's checks_completed counter
        $updateSessionQuery = "UPDATE teacher_locations 
                               SET checks_completed = checks_completed + 1 
                               WHERE id = :session_id";
        $stmt = $db->prepare($updateSessionQuery);
        $stmt->bindParam(':session_id', $check['session_id']);
        $stmt->execute();
        
        $stats['activated']++;
        
        // TODO: Send push notification to students about active check
        // sendCheckNotification($check['session_id'], $check['check_number']);
    }
    
    // 2. Deactivate expired check points
    $expireQuery = "UPDATE attendance_check_points 
                    SET is_active = FALSE 
                    WHERE is_active = TRUE 
                    AND window_end_time < :now";
    $stmt = $db->prepare($expireQuery);
    $stmt->bindParam(':now', $now);
    $stmt->execute();
    $stats['expired'] = $stmt->rowCount();
    
    // 3. Auto-end sessions that have exceeded their expected end time by 30 minutes
    $autoEndQuery = "UPDATE teacher_locations 
                     SET is_active = FALSE, session_end = :now
                     WHERE is_active = TRUE 
                     AND session_start < DATE_SUB(:now2, INTERVAL 150 MINUTE)";
    $stmt = $db->prepare($autoEndQuery);
    $stmt->bindParam(':now', $now);
    $stmt->bindParam(':now2', $now);
    $stmt->execute();
    $stats['sessions_updated'] = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cron job completed',
        'timestamp' => $now,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    error_log('Cron trigger-scheduled-checks error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
