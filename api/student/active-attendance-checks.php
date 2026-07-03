<?php
/**
 * Student Get Active Attendance Checks API
 * Returns active attendance checks that student needs to respond to
 * 
 * SECURITY FEATURE: When hide_timing_from_students is enabled:
 * - Students see only check count and current active check
 * - Exact timing/scheduling is hidden
 * - Students must stay on attendance screen to respond
 * 
 * LAZY ACTIVATION: Also triggers due scheduled checks (no cron needed)
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
    
    // Check if multi-check system is enabled
    $multiCheckSetting = "SELECT value FROM system_settings WHERE `key` = 'attendance_multi_check_enabled' LIMIT 1";
    $stmt = $db->query($multiCheckSetting);
    $settingRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $multiCheckEnabled = !$settingRow || $settingRow['value'] === 'true' || $settingRow['value'] === '1';
    
    if (!$multiCheckEnabled) {
        Response::success([
            'active_checks' => [],
            'total_pending' => 0,
            'active_sessions' => [],
            'stay_on_screen' => false,
            'has_random_intervals' => false
        ], 'Multi-check attendance is disabled');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LAZY ACTIVATION: Trigger any due scheduled checks
    // This replaces the need for a cron job
    // ═══════════════════════════════════════════════════════════════════════
    $now = date('Y-m-d H:i:s');
    
    // Find and activate due scheduled checks
    // Removed auto_trigger_checks requirement — always activate due checks
    $dueQuery = "SELECT acp.id, acp.session_id, acp.check_number
                 FROM attendance_check_points acp
                 JOIN teacher_locations tl ON acp.session_id = tl.id
                 WHERE acp.is_scheduled = TRUE 
                 AND acp.is_active = FALSE
                 AND acp.was_auto_triggered = FALSE
                 AND acp.scheduled_time <= :now
                 AND acp.window_end_time > :now2
                 AND tl.is_active = TRUE
                 LIMIT 10";
    
    $stmt = $db->prepare($dueQuery);
    $stmt->bindParam(':now', $now);
    $stmt->bindParam(':now2', $now);
    $stmt->execute();
    $dueChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($dueChecks as $check) {
        // Activate this check point
        $activateQuery = "UPDATE attendance_check_points 
                          SET is_active = TRUE, was_auto_triggered = TRUE, check_time = :now
                          WHERE id = :id AND is_active = FALSE";
        $stmt = $db->prepare($activateQuery);
        $stmt->bindParam(':now', $now);
        $stmt->bindParam(':id', $check['id']);
        $stmt->execute();
        
        // Update session's next check time
        $updateSessionQuery = "UPDATE teacher_locations 
                               SET checks_completed = checks_completed + 1,
                                   next_check_time = (
                                       SELECT MIN(scheduled_time) 
                                       FROM attendance_check_points 
                                       WHERE session_id = :session_id 
                                       AND is_scheduled = TRUE 
                                       AND is_active = FALSE 
                                       AND was_auto_triggered = FALSE
                                   )
                               WHERE id = :session_id";
        $stmt = $db->prepare($updateSessionQuery);
        $stmt->bindParam(':session_id', $check['session_id']);
        $stmt->execute();
    }
    
    // Also expire old checks
    $expireQuery = "UPDATE attendance_check_points 
                    SET is_active = FALSE 
                    WHERE is_active = TRUE 
                    AND window_end_time < :now";
    $stmt = $db->prepare($expireQuery);
    $stmt->bindParam(':now', $now);
    $stmt->execute();

    // Get student profile
    $student = new Student($db);
    $studentData = $student->getByUserId($user['id']);

    if (!$studentData) {
        Response::error('Student profile not found', 404);
    }

    // Get active check points for student's classes that they haven't responded to yet
    // Now includes hide_timing_from_students from teacher_locations AND teacher GPS coordinates
    $query = "SELECT DISTINCT
                cp.id as check_point_id,
                cp.check_number,
                cp.check_time,
                cp.window_end_time,
                cp.schedule_id,
                sub.name as subject_name,
                sub.code as subject_code,
                u.full_name as teacher_name,
                sc.classroom,
                sc.building,
                TIME_FORMAT(sc.start_time, '%H:%i:%s') as class_start_time,
                TIME_FORMAT(sc.end_time, '%H:%i:%s') as class_end_time,
                tl.id as session_id,
                tl.latitude as teacher_latitude,
                tl.longitude as teacher_longitude,
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
            'schedule_id' => (int)($check['schedule_id'] ?? 0),
            'subject_name' => $check['subject_name'],
            'subject_code' => $check['subject_code'],
            'teacher_name' => $check['teacher_name'],
            'classroom' => $check['classroom'],
            'building' => $check['building'],
            'class_start_time' => $check['class_start_time'],
            'class_end_time' => $check['class_end_time'],
            'teacher_latitude' => (float)($check['teacher_latitude'] ?? 0),
            'teacher_longitude' => (float)($check['teacher_longitude'] ?? 0),
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
        
        $totalPlanned = (int)($session['total_checks_planned'] ?? 0);
        $studentSuccess = (int)($session['student_successful_checks'] ?? 0);
        $checksRemaining = max(0, $totalPlanned - $studentSuccess);
        
        return [
            'session_id' => (int)$session['session_id'],
            'subject_name' => $session['subject_name'],
            'subject_code' => $session['subject_code'],
            'teacher_name' => $session['teacher_name'],
            'classroom' => $session['classroom'],
            'total_checks_planned' => $totalPlanned,
            'checks_completed' => (int)($session['checks_completed'] ?? 0),
            'student_successful_checks' => $studentSuccess,
            'checks_remaining' => $checksRemaining,
            'hide_timing' => $hideTiming,
            'random_intervals' => isset($session['random_intervals_enabled']) && 
                                  ($session['random_intervals_enabled'] === '1' || $session['random_intervals_enabled'] === true),
            'message' => $hideTiming 
                ? "Stay on this screen. $checksRemaining check(s) remaining at random times."
                : null
        ];
    }, $activeSessions);

    // Retroactive auto-mark: if student completed all checks but attendance wasn't recorded
    foreach ($activeSessions as $session) {
        $totalPlanned = (int)($session['total_checks_planned'] ?? 0);
        $studentSuccess = (int)($session['student_successful_checks'] ?? 0);
        
        if ($totalPlanned > 0 && $studentSuccess >= $totalPlanned) {
            // Check if attendance already exists
            $existsQuery = "SELECT id FROM attendance 
                            WHERE student_id = :sid AND schedule_id = (SELECT schedule_id FROM teacher_locations WHERE id = :sess_id)
                            AND attendance_date = CURDATE()";
            $stmt = $db->prepare($existsQuery);
            $stmt->bindParam(':sid', $studentData['id']);
            $stmt->bindParam(':sess_id', $session['session_id']);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                try {
                    $sessQuery = "SELECT schedule_id, assignment_id, department_id, teacher_id, latitude, longitude 
                                  FROM teacher_locations WHERE id = :sess_id";
                    $stmt = $db->prepare($sessQuery);
                    $stmt->bindParam(':sess_id', $session['session_id']);
                    $stmt->execute();
                    $sessInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($sessInfo) {
                        $markQuery = "INSERT INTO attendance 
                                      (student_id, schedule_id, assignment_id, teacher_id, department_id,
                                       attendance_date, status, face_confidence_score,
                                       student_latitude, student_longitude,
                                       teacher_latitude, teacher_longitude, distance_meters,
                                       verification_status)
                                      VALUES (:sid, :sched, :assign, :tid, :did,
                                              CURDATE(), 'present', 0,
                                              0, 0,
                                              :tlat, :tlon, 0,
                                              'success')";
                        $stmt = $db->prepare($markQuery);
                        $stmt->bindParam(':sid', $studentData['id']);
                        $stmt->bindParam(':sched', $sessInfo['schedule_id']);
                        $stmt->bindParam(':assign', $sessInfo['assignment_id']);
                        $stmt->bindParam(':tid', $sessInfo['teacher_id']);
                        $stmt->bindParam(':did', $sessInfo['department_id']);
                        $stmt->bindParam(':tlat', $sessInfo['latitude']);
                        $stmt->bindParam(':tlon', $sessInfo['longitude']);
                        $stmt->execute();
                        error_log("Retroactive auto-mark: student {$studentData['id']} session {$session['session_id']}");
                    }
                } catch (Exception $e) {
                    error_log("Retroactive auto-mark failed: " . $e->getMessage());
                }
            }
        }
    }

    // Derive flags from session data (which uses admin settings applied at start-class time)
    $shouldStayOnScreen = false;
    $hasRandomIntervals = false;
    foreach ($activeSessions as $session) {
        if (isset($session['hide_timing_from_students']) && 
            ($session['hide_timing_from_students'] === '1' || $session['hide_timing_from_students'] === true)) {
            $shouldStayOnScreen = true;
        }
        if (isset($session['random_intervals_enabled']) && 
            ($session['random_intervals_enabled'] === '1' || $session['random_intervals_enabled'] === true)) {
            $hasRandomIntervals = true;
        }
    }

    Response::success([
        'active_checks' => $formattedChecks,
        'total_pending' => count($formattedChecks),
        'active_sessions' => $formattedSessions,
        'stay_on_screen' => $shouldStayOnScreen,
        'has_random_intervals' => $hasRandomIntervals
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
