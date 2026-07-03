<?php
/**
 * Teacher Trigger Attendance Check API
 * Triggers a random attendance check during an active session
 * Used for multi-check attendance to avoid geofencing issues
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

// Handle CORS
CORS::handle();

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check authentication and role
    $user = Auth::user();
    
    if (!$user) {
        Response::unauthorized('Please login to continue');
    }
    
    if ($user['role'] !== 'teacher') {
        Response::error('Access restricted to teachers only', 403);
    }

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('session_id', $data['session_id'] ?? '', 'Session ID');
    
    // Optional: window duration in minutes (default: 5 minutes)
    $windowDuration = isset($data['window_minutes']) ? (int)$data['window_minutes'] : 5;

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    // Get teacher profile
    $teacherQuery = "SELECT t.* FROM teachers t WHERE t.user_id = :user_id";
    $stmt = $db->prepare($teacherQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Response::error('Teacher profile not found', 404);
    }

    // Get session and verify ownership
    $sessionQuery = "SELECT tl.*, sc.id as schedule_id 
                     FROM teacher_locations tl
                     JOIN schedules sc ON tl.schedule_id = sc.id
                     WHERE tl.id = :session_id AND tl.teacher_id = :teacher_id";
    $stmt = $db->prepare($sessionQuery);
    $stmt->bindParam(':session_id', $data['session_id']);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        Response::error('Session not found or not owned by you', 404);
    }

    if (!$session['is_active']) {
        Response::error('Session is not active', 400);
    }

    // Get current check number
    $countQuery = "SELECT COUNT(*) as count FROM attendance_check_points 
                   WHERE session_id = :session_id";
    $stmt = $db->prepare($countQuery);
    $stmt->bindParam(':session_id', $data['session_id']);
    $stmt->execute();
    $checkNumber = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;

    // Create new check point
    $windowEndTime = date('Y-m-d H:i:s', strtotime("+{$windowDuration} minutes"));
    
    $insertQuery = "INSERT INTO attendance_check_points 
                    (session_id, schedule_id, check_number, window_end_time, is_active)
                    VALUES (:session_id, :schedule_id, :check_number, :window_end_time, TRUE)";
    $stmt = $db->prepare($insertQuery);
    $stmt->bindParam(':session_id', $data['session_id']);
    $stmt->bindParam(':schedule_id', $session['schedule_id']);
    $stmt->bindParam(':check_number', $checkNumber);
    $stmt->bindParam(':window_end_time', $windowEndTime);
    $stmt->execute();

    $checkPointId = $db->lastInsertId();

    // Update session checks_completed counter
    $updateQuery = "UPDATE teacher_locations 
                    SET checks_completed = checks_completed + 1 
                    WHERE id = :session_id";
    $stmt = $db->prepare($updateQuery);
    $stmt->bindParam(':session_id', $data['session_id']);
    $stmt->execute();

    // Get count of students who should respond
    $studentsQuery = "SELECT COUNT(*) as count FROM students s
                      WHERE s.department_id = :department_id 
                      AND s.semester = :semester";
    if (!empty($session['section'])) {
        $studentsQuery .= " AND s.section = :section";
    }
    
    $stmt = $db->prepare($studentsQuery);
    $stmt->bindParam(':department_id', $session['department_id']);
    $stmt->bindParam(':semester', $session['semester']);
    if (!empty($session['section'])) {
        $stmt->bindParam(':section', $session['section']);
    }
    $stmt->execute();
    $expectedStudents = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    Response::success([
        'check_point_id' => (int)$checkPointId,
        'check_number' => $checkNumber,
        'session_id' => (int)$data['session_id'],
        'triggered_at' => date('Y-m-d H:i:s'),
        'window_end_time' => $windowEndTime,
        'window_minutes' => $windowDuration,
        'expected_responses' => $expectedStudents
    ], 'Attendance check triggered successfully. Students have ' . $windowDuration . ' minutes to respond.');

} catch (Exception $e) {
    error_log('Trigger attendance check error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
