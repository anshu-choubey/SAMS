<?php
ob_start();
header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $errstr,
        'error' => $errstr
    ]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        error_log("Fatal Error: " . $error['message']);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $error['message'],
            'error' => $error['message']
        ]);
    }
});

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/constants.php';
    require_once __DIR__ . '/../../includes/middleware/CORS.php';
    require_once __DIR__ . '/../../includes/middleware/Auth.php';
    require_once __DIR__ . '/../../includes/helpers/Response.php';
    require_once __DIR__ . '/../../includes/helpers/Validator.php';
    require_once __DIR__ . '/../../includes/models/Attendance.php';
} catch (Exception $e) {
    ob_end_clean();
    error_log("Include error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error - could not load required files',
        'error' => $e->getMessage()
    ]);
    exit;
}

// Handle CORS
CORS::handle();

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $user = Auth::user();

    if (!$user) {
        ob_end_clean();
        Response::unauthorized('Please login to continue');
    }

    if ($user['role'] !== 'teacher') {
        ob_end_clean();
        Response::error('Access restricted to teachers only', 403);
    }

    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('session_id', $data['session_id'] ?? '', 'Session ID');

    if ($validator->hasErrors()) {
        ob_end_clean();
        Response::validationError($validator->getErrors());
    }

    // Get teacher profile
    $teacherQuery = "SELECT t.* FROM teachers t WHERE t.user_id = :user_id";
    $stmt = $db->prepare($teacherQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        ob_end_clean();
        Response::error('Teacher profile not found', 404);
    }

    // Get session and verify ownership
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
        ob_end_clean();
        Response::error('Session not found or not owned by you', 404);
    }

    // ✅ Cast to int — DB may return "0"/"1" string instead of boolean
    if ((int)$session['is_active'] !== 1) {
        ob_end_clean();
        Response::error('Session already ended', 400);
    }

    // End the session
    $updateQuery = "UPDATE teacher_locations SET is_active = FALSE, session_end = NOW() WHERE id = :session_id";
    $stmt = $db->prepare($updateQuery);
    $stmt->bindParam(':session_id', $data['session_id']);
    $stmt->execute();

    // ✅ Wrap absent marking — don't let it crash the whole request
    $absentResult = ['absent_marked' => 0, 'message' => ''];
    try {
        $attendanceModel = new Attendance($db);
        $absentResult = $attendanceModel->markAbsentForEndedClass(
            $session['schedule_id'],
            $teacher['id'],
            $session['department_id'],
            $session['semester'],
            $session['section']
        );
    } catch (Exception $absentEx) {
        error_log('Auto-absent marking failed: ' . $absentEx->getMessage());
        // Session already ended — don't fail the response
    }

    $today = date('Y-m-d');

    // ✅ Fixed: unique PDO param names — :section and :section2
    $totalQuery = "SELECT COUNT(*) as total FROM students s 
                   WHERE s.department_id = :department_id 
                   AND s.semester = :semester
                   AND (:section IS NULL OR s.section = :section2)";
    $stmt = $db->prepare($totalQuery);
    $stmt->bindParam(':department_id', $session['department_id']);
    $stmt->bindParam(':semester', $session['semester']);
    $stmt->bindParam(':section', $session['section']);
    $stmt->bindParam(':section2', $session['section']);
    $stmt->execute();
    $totalStudents = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Present students
    $presentQuery = "SELECT COUNT(*) as present FROM attendance a 
                     WHERE a.schedule_id = :schedule_id 
                     AND a.attendance_date = :date 
                     AND a.status = 'present'";
    $stmt = $db->prepare($presentQuery);
    $stmt->bindParam(':schedule_id', $session['schedule_id']);
    $stmt->bindParam(':date', $today);
    $stmt->execute();
    $present = (int)$stmt->fetch(PDO::FETCH_ASSOC)['present'];

    $absent = $totalStudents - $present;
    $percentage = $totalStudents > 0 ? round(($present / $totalStudents) * 100, 2) : 0;

    ob_end_clean();
    Response::success([
        'session_id'           => (int)$data['session_id'],
        'ended_at'             => date('Y-m-d H:i:s'),
        'total_students'       => $totalStudents,
        'present'              => $present,
        'absent'               => $absent,
        'auto_marked_absent'   => $absentResult['absent_marked'] ?? 0,
        'attendance_percentage'=> $percentage
    ], 'Class session ended successfully. ' . ($absentResult['message'] ?? ''));

} catch (Exception $e) {
    ob_end_clean();
    error_log('End class error: ' . $e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error'   => $e->getMessage()
    ]);
    exit;
}
?>
