<?php
/**
 * Student Get Active Attendance Checks API
 * Returns active attendance checks that student needs to respond to
 * 
 * SECURITY FEATURE: When hide_timing_from_students is enabled:
 * - Students see only check count and current active check
 * - Exact timing/scheduling is hidden
 * - Students must stay on attendance screen to respond
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Student.php';
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
    $user = Auth::user();

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get student profile
    $student = new Student($db);
    $studentData = $student->getByUserId($user['id']);

    if (!$studentData) {
        Response::error('Student profile not found', 404);
    }

    // Get active check points for student's classes that they haven't responded to yet
    // Now includes hide_timing_from_students from teacher_locations
    $query = "SELECT DISTINCT
                cp.id as check_point_id,
                cp.check_number,
                cp.check_time,
                cp.window_end_time,
                sub.name as subject_name,
                sub.code as subject_code,
                u.full_name as teacher_name,
                sc.classroom,
                sc.building,
                TIME_FORMAT(sc.start_time, '%H:%i:%s') as class_start_time,
                TIME_FORMAT(sc.end_time, '%H:%i:%s') as class_end_time,
                tl.id as session_id,
                tl.total_checks_planned,
                tl.checks_completed,
                tl.hide_timing_from_students,
                tl.random_intervals_enabled,
                CASE WHEN NOW() > cp.window_end_time THEN TRUE ELSE FALSE END as is_expired,
                TIMESTAMPDIFF(SECOND, NOW(), cp.window_end_time) as seconds_remaining
              FROM attendance_check_points cp
              JOIN teacher_locations tl ON cp.session_id = tl.id
              JOIN schedules sc ON cp.schedule_id = sc.id
              JOIN teacher_assignments ta ON sc.assignment_id = ta.id
              JOIN subjects sub ON ta.subject_id = sub.id
              JOIN teachers t ON ta.teacher_id = t.id
              JOIN users u ON t.user_id = u.id
              WHERE cp.is_active = TRUE
                AND tl.is_active = TRUE
                AND ta.department_id = :department_id
                AND ta.semester = :semester
                AND (ta.section IS NULL OR ta.section = :section)
                AND NOT EXISTS (
                    SELECT 1 FROM attendance_check_responses acr
                    WHERE acr.check_point_id = cp.id AND acr.student_id = :student_id
                )
              ORDER BY cp.check_time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department_id', $studentData['department_id']);
    $stmt->bindParam(':semester', $studentData['semester']);
    $stmt->bindParam(':section', $studentData['section']);
    $stmt->bindParam(':student_id', $studentData['id']);
    $stmt->execute();
    
    $activeChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also get info about active sessions (for "stay on screen" requirement)
    $activeSessionsQuery = "SELECT 
                               tl.id as session_id,
                               tl.total_checks_planned,
                               tl.checks_completed,
                               tl.hide_timing_from_students,
                               tl.random_intervals_enabled,
                               sub.name as subject_name,
                               sub.code as subject_code,
                               u.full_name as teacher_name,
                               sc.classroom,
                               (SELECT COUNT(*) FROM attendance_check_responses acr 
                                JOIN attendance_check_points cp2 ON acr.check_point_id = cp2.id
                                WHERE cp2.session_id = tl.id AND acr.student_id = :student_id2
                                AND acr.verification_status = 'success') as student_successful_checks
                           FROM teacher_locations tl
                           JOIN schedules sc ON tl.schedule_id = sc.id
                           JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                           JOIN subjects sub ON ta.subject_id = sub.id
                           JOIN teachers t ON tl.teacher_id = t.id
                           JOIN users u ON t.user_id = u.id
                           WHERE tl.is_active = TRUE
                           AND ta.department_id = :department_id2
                           AND ta.semester = :semester2
                           AND (ta.section IS NULL OR ta.section = :section2)";
    
    $stmt = $db->prepare($activeSessionsQuery);
    $stmt->bindParam(':student_id2', $studentData['id']);
    $stmt->bindParam(':department_id2', $studentData['department_id']);
    $stmt->bindParam(':semester2', $studentData['semester']);
    $stmt->bindParam(':section2', $studentData['section']);
    $stmt->execute();
    $activeSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response - hide timing if configured
    $formattedChecks = array_map(function($check) {
        $hideTiming = isset($check['hide_timing_from_students']) && 
                      ($check['hide_timing_from_students'] === '1' || $check['hide_timing_from_students'] === true);
        
        $checkData = [
            'check_point_id' => (int)$check['check_point_id'],
            'check_number' => (int)$check['check_number'],
            'session_id' => (int)$check['session_id'],
            'subject_name' => $check['subject_name'],
            'subject_code' => $check['subject_code'],
            'teacher_name' => $check['teacher_name'],
            'classroom' => $check['classroom'],
            'building' => $check['building'],
            'class_start_time' => $check['class_start_time'],
            'class_end_time' => $check['class_end_time'],
            'total_checks_planned' => (int)($check['total_checks_planned'] ?? 0),
            'checks_completed' => (int)($check['checks_completed'] ?? 0),
            'is_expired' => (bool)$check['is_expired'],
            'hide_timing' => $hideTiming,
            'random_intervals' => isset($check['random_intervals_enabled']) && 
                                  ($check['random_intervals_enabled'] === '1' || $check['random_intervals_enabled'] === true)
        ];
        
        // Only include timing info if NOT hidden
        if (!$hideTiming) {
            $checkData['check_time'] = $check['check_time'];
            $checkData['window_end_time'] = $check['window_end_time'];
            $checkData['seconds_remaining'] = max(0, (int)$check['seconds_remaining']);
        } else {
            // For hidden timing, indicate check is active but hide details
            $checkData['check_time'] = null;
            $checkData['window_end_time'] = null;
            $checkData['seconds_remaining'] = null;
            $checkData['message'] = 'Attendance check #' . $check['check_number'] . ' is active. Respond now!';
        }
        
        return $checkData;
    }, $activeChecks);
    
    // Format active sessions info (for "stay on screen" UI)
    $formattedSessions = array_map(function($session) {
        $hideTiming = isset($session['hide_timing_from_students']) && 
                      ($session['hide_timing_from_students'] === '1' || $session['hide_timing_from_students'] === true);
        
        $checksRemaining = max(0, (int)($session['total_checks_planned'] ?? 0) - (int)($session['checks_completed'] ?? 0));
        
        return [
            'session_id' => (int)$session['session_id'],
            'subject_name' => $session['subject_name'],
            'subject_code' => $session['subject_code'],
            'teacher_name' => $session['teacher_name'],
            'classroom' => $session['classroom'],
            'total_checks_planned' => (int)($session['total_checks_planned'] ?? 0),
            'checks_completed' => (int)($session['checks_completed'] ?? 0),
            'student_successful_checks' => (int)($session['student_successful_checks'] ?? 0),
            'checks_remaining' => $checksRemaining,
            'hide_timing' => $hideTiming,
            'random_intervals' => isset($session['random_intervals_enabled']) && 
                                  ($session['random_intervals_enabled'] === '1' || $session['random_intervals_enabled'] === true),
            'message' => $hideTiming 
                ? "Stay on this screen. $checksRemaining check(s) remaining at random times."
                : null
        ];
    }, $activeSessions);

    // Determine if student should stay on screen
    $shouldStayOnScreen = false;
    foreach ($activeSessions as $session) {
        if (isset($session['hide_timing_from_students']) && 
            ($session['hide_timing_from_students'] === '1' || $session['hide_timing_from_students'] === true)) {
            $shouldStayOnScreen = true;
            break;
        }
    }

    Response::success([
        'active_checks' => $formattedChecks,
        'total_pending' => count($formattedChecks),
        'active_sessions' => $formattedSessions,
        'stay_on_screen' => $shouldStayOnScreen,
        'has_random_intervals' => $shouldStayOnScreen
    ], count($formattedChecks) > 0 
        ? 'Active check found - respond now!' 
        : (count($activeSessions) > 0 
            ? 'No active check right now. Stay on screen for upcoming checks.' 
            : 'No active checks'));

} catch (Exception $e) {
    error_log('Get active attendance checks error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
