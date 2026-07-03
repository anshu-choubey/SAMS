<?php
/**
 * Teacher Finalize Attendance API
 * Finalizes attendance after all checks are complete
 * Calculates final status based on multi-check responses
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

    // Get session details
    $sessionQuery = "SELECT tl.*, sc.assignment_id, ta.department_id, ta.semester, ta.section
                     FROM teacher_locations tl
                     JOIN schedules sc ON tl.schedule_id = sc.id
                     JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                     WHERE tl.id = :session_id AND tl.teacher_id = :teacher_id";
    $stmt = $db->prepare($sessionQuery);
    $stmt->bindParam(':session_id', $data['session_id']);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        Response::error('Session not found or not owned by you', 404);
    }

    $today = date('Y-m-d');
    
    // Get all students in this class
    $studentsQuery = "SELECT s.id FROM students s
                      WHERE s.department_id = :department_id AND s.semester = :semester";
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
    $allStudents = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalChecks = (int)$session['total_checks_planned'];
    $requiredSuccessfulChecks = max(1, floor($totalChecks * 0.6)); // 60% threshold
    
    $presentCount = 0;
    $absentCount = 0;
    $partialCount = 0;

    // Process each student
    foreach ($allStudents as $studentId) {
        // Get student's check responses for this session
        $responsesQuery = "SELECT 
                            COUNT(*) as total_responses,
                            SUM(CASE WHEN verification_status = 'success' THEN 1 ELSE 0 END) as successful_checks,
                            AVG(distance_meters) as avg_distance,
                            AVG(face_confidence_score) as avg_face_confidence,
                            MAX(student_latitude) as last_lat,
                            MAX(student_longitude) as last_lon
                           FROM attendance_check_responses
                           WHERE session_id = :session_id AND student_id = :student_id";
        $stmt = $db->prepare($responsesQuery);
        $stmt->bindParam(':session_id', $data['session_id']);
        $stmt->bindParam(':student_id', $studentId);
        $stmt->execute();
        $studentStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $successfulChecks = (int)$studentStats['successful_checks'];
        $totalResponses = (int)$studentStats['total_responses'];

        // Determine final status
        if ($successfulChecks >= $requiredSuccessfulChecks) {
            $status = 'present';
            $verificationStatus = 'success';
            $presentCount++;
        } elseif ($totalResponses > 0 && $successfulChecks > 0) {
            $status = 'partial';
            $verificationStatus = 'partial';
            $partialCount++;
        } else {
            $status = 'absent';
            $verificationStatus = $totalResponses > 0 ? 'both_failed' : 'success';
            $absentCount++;
        }

        // Insert or update final attendance record
        $attendanceQuery = "INSERT INTO attendance 
                            (student_id, schedule_id, assignment_id, teacher_id, department_id, session_id,
                             attendance_date, total_checks_required, successful_checks,
                             student_latitude, student_longitude, teacher_latitude, teacher_longitude,
                             distance_meters, face_confidence_score, verification_status, status)
                            VALUES (:student_id, :schedule_id, :assignment_id, :teacher_id, :department_id, :session_id,
                                    :date, :total_checks, :successful_checks,
                                    :student_lat, :student_lon, :teacher_lat, :teacher_lon,
                                    :distance, :face_confidence, :verification_status, :status)
                            ON DUPLICATE KEY UPDATE
                                total_checks_required = :total_checks2,
                                successful_checks = :successful_checks2,
                                distance_meters = :distance2,
                                face_confidence_score = :face_confidence2,
                                verification_status = :verification_status2,
                                status = :status2";
        
        $stmt = $db->prepare($attendanceQuery);
        $stmt->bindParam(':student_id', $studentId);
        $stmt->bindParam(':schedule_id', $session['schedule_id']);
        $stmt->bindParam(':assignment_id', $session['assignment_id']);
        $stmt->bindParam(':teacher_id', $teacher['id']);
        $stmt->bindParam(':department_id', $session['department_id']);
        $stmt->bindParam(':session_id', $data['session_id']);
        $stmt->bindParam(':date', $today);
        $stmt->bindParam(':total_checks', $totalChecks);
        $stmt->bindParam(':successful_checks', $successfulChecks);
        $stmt->bindParam(':student_lat', $studentStats['last_lat']);
        $stmt->bindParam(':student_lon', $studentStats['last_lon']);
        $stmt->bindParam(':teacher_lat', $session['latitude']);
        $stmt->bindParam(':teacher_lon', $session['longitude']);
        $stmt->bindParam(':distance', $studentStats['avg_distance']);
        $stmt->bindParam(':face_confidence', $studentStats['avg_face_confidence']);
        $stmt->bindParam(':verification_status', $verificationStatus);
        $stmt->bindParam(':status', $status);
        
        // For ON DUPLICATE KEY UPDATE
        $stmt->bindParam(':total_checks2', $totalChecks);
        $stmt->bindParam(':successful_checks2', $successfulChecks);
        $stmt->bindParam(':distance2', $studentStats['avg_distance']);
        $stmt->bindParam(':face_confidence2', $studentStats['avg_face_confidence']);
        $stmt->bindParam(':verification_status2', $verificationStatus);
        $stmt->bindParam(':status2', $status);
        
        $stmt->execute();
    }

    $totalStudents = count($allStudents);
    $attendancePercentage = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100, 2) : 0;

    Response::success([
        'session_id' => (int)$data['session_id'],
        'total_students' => $totalStudents,
        'present' => $presentCount,
        'absent' => $absentCount,
        'partial' => $partialCount,
        'attendance_percentage' => $attendancePercentage,
        'total_checks_conducted' => (int)$session['checks_completed'],
        'required_successful_checks' => $requiredSuccessfulChecks
    ], 'Attendance finalized successfully');

} catch (Exception $e) {
    error_log('Finalize attendance error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
