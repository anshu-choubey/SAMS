<?php
/**
 * Get Continuous Monitoring Configuration for Student
 * Returns settings for ContinuousAttendanceScreen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

// Handle CORS
CORS::handle();

// Only GET method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check authentication and role
    Auth::hasRole('student');

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Get session_id from query params
    $sessionId = $_GET['session_id'] ?? null;
    
    if (!$sessionId) {
        Response::error('Session ID required', 400);
    }
    
    // Get session details
    $sessionQuery = "SELECT tl.*, sc.start_time, sc.end_time,
                            sub.name as subject_name, sub.code as subject_code,
                            u.full_name as teacher_name
                     FROM teacher_locations tl
                     JOIN schedules sc ON tl.schedule_id = sc.id
                     JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                     JOIN subjects sub ON ta.subject_id = sub.id
                     JOIN teachers t ON ta.teacher_id = t.id
                     JOIN users u ON t.user_id = u.id
                     WHERE tl.id = :session_id AND tl.is_active = TRUE";
    $stmt = $db->prepare($sessionQuery);
    $stmt->bindParam(':session_id', $sessionId);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        Response::error('Session not found or ended', 404);
    }
    
    // Get system settings
    $settingsQuery = "SELECT setting_key, setting_value FROM system_settings 
                      WHERE setting_key IN (
                          'continuous_monitoring_enabled',
                          'continuous_monitoring_required',
                          'continuous_auto_response_enabled',
                          'continuous_face_detection_interval',
                          'liveness_detection_enabled',
                          'liveness_min_score',
                          'face_confidence_threshold',
                          'attendance_check_window_minutes'
                      )";
    $stmt = $db->query($settingsQuery);
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Calculate expected end time
    $sessionStart = strtotime($session['session_start']);
    $expectedEnd = date('Y-m-d H:i:s', strtotime($session['end_time'], $sessionStart));
    
    // Get scheduled checks
    $checksQuery = "SELECT check_number, check_time, window_end_time, is_active
                    FROM attendance_check_points
                    WHERE session_id = :session_id
                    ORDER BY check_number";
    $stmt = $db->prepare($checksQuery);
    $stmt->bindParam(':session_id', $sessionId);
    $stmt->execute();
    $scheduledChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success([
        'session' => [
            'session_id' => (int)$session['id'],
            'schedule_id' => (int)$session['schedule_id'],
            'subject_name' => $session['subject_name'],
            'subject_code' => $session['subject_code'],
            'teacher_name' => $session['teacher_name'],
            'started_at' => $session['session_start'],
            'expected_end' => $expectedEnd,
            'multi_check_enabled' => (bool)$session['multi_check_enabled'],
            'total_checks_planned' => (int)$session['total_checks_planned'],
            'auto_schedule' => (bool)$session['auto_schedule']
        ],
        'scheduled_checks' => array_map(function($check) {
            return [
                'check_number' => (int)$check['check_number'],
                'check_time' => $check['check_time'],
                'window_end_time' => $check['window_end_time'],
                'is_active' => (bool)$check['is_active']
            ];
        }, $scheduledChecks),
        'settings' => [
            'continuous_monitoring_enabled' => ($settings['continuous_monitoring_enabled'] ?? 'true') === 'true',
            'continuous_monitoring_required' => ($settings['continuous_monitoring_required'] ?? 'false') === 'true',
            'auto_response_enabled' => ($settings['continuous_auto_response_enabled'] ?? 'true') === 'true',
            'face_detection_interval_seconds' => (int)($settings['continuous_face_detection_interval'] ?? 30),
            'liveness_detection_enabled' => ($settings['liveness_detection_enabled'] ?? 'true') === 'true',
            'liveness_min_score' => (int)($settings['liveness_min_score'] ?? 60),
            'face_confidence_threshold' => (int)($settings['face_confidence_threshold'] ?? 75),
            'check_window_minutes' => (int)($settings['attendance_check_window_minutes'] ?? 5)
        ]
    ]);

} catch (Exception $e) {
    error_log('Continuous monitoring config error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
