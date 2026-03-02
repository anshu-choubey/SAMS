<?php
/**
 * Teacher Dashboard API
 * Returns teacher's profile, stats, today's classes, and recent sessions
 * Matches Android app's TeacherDashboardData model
 */

// Start output buffering to prevent early header issues
ob_start();

// Capture fatal errors BEFORE any output
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean(); // Clear any buffered output
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        ob_end_clean(); // Clear any buffered output
        error_log("Fatal Error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line"]);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Server error',
            'error' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/constants.php';
    require_once __DIR__ . '/../../includes/middleware/CORS.php';
    require_once __DIR__ . '/../../includes/middleware/Auth.php';
    require_once __DIR__ . '/../../includes/helpers/Response.php';
} catch (Exception $e) {
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

// Only GET method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    // Get teacher profile
    $teacherQuery = "SELECT t.*, t.primary_department_id as department_id, d.name as department_name, d.code as department_code,
                            u.full_name, u.email, u.phone
                     FROM teachers t
                     LEFT JOIN departments d ON t.primary_department_id = d.id
                     JOIN users u ON t.user_id = u.id
                     WHERE t.user_id = :user_id";
    $stmt = $db->prepare($teacherQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Response::error('Teacher profile not found', 404);
    }

    // Get teacher stats
    $statsQuery = "SELECT 
                    (SELECT COUNT(DISTINCT ta.subject_id) FROM teacher_assignments ta WHERE ta.teacher_id = :teacher_id AND ta.is_active = TRUE) as total_subjects,
                    (SELECT COUNT(DISTINCT s.id) FROM students s 
                     JOIN teacher_assignments ta ON s.department_id = ta.department_id AND s.semester = ta.semester
                     WHERE ta.teacher_id = :teacher_id2 AND ta.is_active = TRUE) as total_students";
    $stmt = $db->prepare($statsQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->bindParam(':teacher_id2', $teacher['id']);
    $stmt->execute();
    $basicStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Count today's classes
    $dayOfWeek = date('l');
    $todayCountQuery = "SELECT COUNT(*) as count FROM schedules sc
                        JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                        WHERE ta.teacher_id = :teacher_id AND sc.day_of_week = :day AND sc.is_active = TRUE";
    $stmt = $db->prepare($todayCountQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->bindParam(':day', $dayOfWeek);
    $stmt->execute();
    $classesToday = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Count this week's classes
    $weekCountQuery = "SELECT COUNT(*) as count FROM schedules sc
                       JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                       WHERE ta.teacher_id = :teacher_id AND sc.is_active = TRUE";
    $stmt = $db->prepare($weekCountQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $classesThisWeek = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get average attendance percentage
    $avgAttendanceQuery = "SELECT AVG(
                            (SELECT COUNT(*) FROM attendance a2 WHERE a2.assignment_id = ta.id AND a2.status = 'present') * 100.0 /
                            NULLIF((SELECT COUNT(*) FROM attendance a3 WHERE a3.assignment_id = ta.id), 0)
                           ) as avg_attendance
                           FROM teacher_assignments ta
                           WHERE ta.teacher_id = :teacher_id AND ta.is_active = TRUE";
    $stmt = $db->prepare($avgAttendanceQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $avgAttendance = (float)($stmt->fetch(PDO::FETCH_ASSOC)['avg_attendance'] ?? 0);

    // Get today's classes
    $todayQuery = "SELECT 
                    sc.id,
                    sub.id as subject_id,
                    sub.name as subject_name,
                    sub.code as subject_code,
                    ta.section,
                    ta.semester,
                    TIME_FORMAT(sc.start_time, '%H:%i:%s') as start_time,
                    TIME_FORMAT(sc.end_time, '%H:%i:%s') as end_time,
                    sc.classroom,
                    sc.building,
                    (SELECT COUNT(*) FROM students s WHERE s.department_id = ta.department_id AND s.semester = ta.semester 
                     AND (ta.section IS NULL OR s.section = ta.section)) as total_students,
                    sc.is_active,
                    CASE WHEN tl.id IS NOT NULL AND tl.is_active = TRUE THEN TRUE ELSE FALSE END as session_started,
                    tl.id as session_id
                   FROM schedules sc
                   JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                   JOIN subjects sub ON ta.subject_id = sub.id
                   LEFT JOIN teacher_locations tl ON sc.id = tl.schedule_id AND tl.is_active = TRUE AND DATE(tl.session_start) = CURDATE()
                   WHERE ta.teacher_id = :teacher_id
                   AND sc.day_of_week = :day_of_week
                   AND sc.is_active = TRUE
                   ORDER BY sc.start_time";
    $stmt = $db->prepare($todayQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->bindParam(':day_of_week', $dayOfWeek);
    $stmt->execute();
    $todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format today's classes - matching TeacherScheduleItem model
    $currentTime = date('H:i:s');
    $activeSession = null;
    $formattedTodaySchedule = array_map(function($class) use ($currentTime, &$activeSession) {
        $startTime = $class['start_time'];
        $endTime = $class['end_time'];
        $isWithinTime = ($currentTime >= $startTime && $currentTime <= $endTime);
        $sessionActive = (bool)$class['session_started'];
        
        // Capture active session
        if ($sessionActive && $activeSession === null) {
            $activeSession = [
                'session_id' => (int)($class['session_id'] ?? 0),
                'schedule_id' => (int)$class['id'],
                'subject_id' => (int)$class['subject_id'],
                'subject_name' => $class['subject_name'],
                'subject_code' => $class['subject_code'],
                'classroom' => $class['classroom'],
                'present_count' => 0, // Will update below
                'total_students' => (int)$class['total_students']
            ];
        }
        
        return [
            'schedule_id' => (int)$class['id'],
            'subject_id' => (int)$class['subject_id'],
            'subject_name' => $class['subject_name'],
            'subject_code' => $class['subject_code'],
            'day_of_week' => date('l'),
            'start_time' => $class['start_time'],
            'end_time' => $class['end_time'],
            'semester' => (int)($class['semester'] ?? 1),
            'section' => $class['section'] ?? 'A',
            'classroom' => $class['classroom'],
            'session_active' => $sessionActive,
            'is_startable' => $isWithinTime && !$sessionActive,
            'is_completed' => !$isWithinTime && $currentTime > $endTime
        ];
    }, $todayClasses);
    
    // Get present count for active session
    if ($activeSession !== null) {
        $presentQuery = "SELECT COUNT(*) as present_count FROM attendance 
                         WHERE schedule_id = :schedule_id AND attendance_date = CURDATE() AND status = 'present'";
        $stmt = $db->prepare($presentQuery);
        $stmt->bindParam(':schedule_id', $activeSession['schedule_id']);
        $stmt->execute();
        $activeSession['present_count'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['present_count'];
    }

    // Get recent sessions (last 10)
    $recentQuery = "SELECT 
                        tl.id,
                        sub.name as subject_name,
                        sub.code as subject_code,
                        DATE(tl.session_start) as date,
                        (SELECT COUNT(*) FROM students s WHERE s.department_id = ta.department_id AND s.semester = ta.semester) as total_students,
                        (SELECT COUNT(*) FROM attendance a WHERE a.schedule_id = tl.schedule_id AND a.attendance_date = DATE(tl.session_start) AND a.status = 'present') as present,
                        ROUND(
                            (SELECT COUNT(*) FROM attendance a WHERE a.schedule_id = tl.schedule_id AND a.attendance_date = DATE(tl.session_start) AND a.status = 'present') * 100.0 /
                            NULLIF((SELECT COUNT(*) FROM students s WHERE s.department_id = ta.department_id AND s.semester = ta.semester), 0)
                        , 2) as attendance_percentage
                    FROM teacher_locations tl
                    JOIN schedules sc ON tl.schedule_id = sc.id
                    JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                    JOIN subjects sub ON ta.subject_id = sub.id
                    WHERE ta.teacher_id = :teacher_id
                    ORDER BY tl.session_start DESC
                    LIMIT 10";
    $stmt = $db->prepare($recentQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $recentSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format recent sessions
    $formattedRecentSessions = array_map(function($session) {
        return [
            'id' => (int)$session['id'],
            'subject_name' => $session['subject_name'],
            'subject_code' => $session['subject_code'],
            'date' => $session['date'],
            'total_students' => (int)$session['total_students'],
            'present' => (int)$session['present'],
            'attendance_percentage' => (float)($session['attendance_percentage'] ?? 0)
        ];
    }, $recentSessions);

    // Build response matching Android TeacherDashboardData model
    $response = [
        'profile' => [
            'id' => (int)$teacher['id'],
            'user_id' => (int)$teacher['user_id'],
            'full_name' => $teacher['full_name'],
            'email' => $teacher['email'],
            'phone' => $teacher['phone'],
            'employee_id' => $teacher['employee_id'],
            'department_id' => (int)$teacher['department_id'],
            'department_name' => $teacher['department_name'],
            'designation' => $teacher['designation'],
            'qualification' => $teacher['qualification']
        ],
        'subjects' => [], // Can be extended to include teacher's subjects
        'today_schedule' => $formattedTodaySchedule,
        'active_session' => $activeSession,
        'total_students' => (int)$basicStats['total_students']
    ];

    Response::success($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
