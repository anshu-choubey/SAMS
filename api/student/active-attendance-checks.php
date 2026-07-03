<?php
/**
 * Student Get Active Attendance Checks API
 * Returns active attendance checks that student needs to respond to
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

    // Format the response
    $formattedChecks = array_map(function($check) {
        return [
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
            'check_time' => $check['check_time'],
            'window_end_time' => $check['window_end_time'],
            'is_expired' => (bool)$check['is_expired'],
            'seconds_remaining' => max(0, (int)$check['seconds_remaining'])
        ];
    }, $activeChecks);

    Response::success([
        'active_checks' => $formattedChecks,
        'total_pending' => count($formattedChecks)
    ]);

} catch (Exception $e) {
    error_log('Get active attendance checks error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
