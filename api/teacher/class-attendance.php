<?php
/**
 * Teacher Class Attendance API
 * Returns detailed attendance report for a class session
 * Matches Android app's ClassAttendanceReport model
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

    // Validate schedule_id parameter (Android sends schedule_id)
    if (!isset($_GET['schedule_id'])) {
        Response::error('Schedule ID is required', 400);
    }

    $scheduleId = (int)$_GET['schedule_id'];
    $date = $_GET['date'] ?? date('Y-m-d');

    // Get teacher profile
    $teacherQuery = "SELECT t.* FROM teachers t WHERE t.user_id = :user_id";
    $stmt = $db->prepare($teacherQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Response::error('Teacher profile not found', 404);
    }

    // Get schedule info and verify ownership
    // First verify teacher is assigned to this schedule
    $authQuery = "SELECT ta.id as assignment_id FROM schedules sc
                  JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                  WHERE sc.id = :schedule_id AND ta.teacher_id = :teacher_id
                  LIMIT 1";
    $stmt = $db->prepare($authQuery);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $authorization = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$authorization) {
        // Check if schedule exists at all
        $checkSchedule = "SELECT id FROM schedules WHERE id = :schedule_id LIMIT 1";
        $stmt = $db->prepare($checkSchedule);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->execute();
        if (!$stmt->fetch()) {
            Response::error('Schedule not found', 404);
        }
        // Schedule exists but not assigned to this teacher
        Response::error('This schedule is not assigned to you', 403);
    }

    // Now get full schedule details with soft joins
    $scheduleQuery = "SELECT sc.*, ta.teacher_id, ta.department_id, ta.semester, ta.section, ta.is_active as assignment_active,
                             sub.name as subject_name, sub.code as subject_code, sub.id as subject_id
                      FROM schedules sc
                      LEFT JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                      LEFT JOIN subjects sub ON ta.subject_id = sub.id
                      WHERE sc.id = :schedule_id";
    $stmt = $db->prepare($scheduleQuery);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->execute();
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if assignment is active
    if ((int)$schedule['assignment_active'] !== 1) {
        Response::error('Assignment is currently inactive', 403);
    }

    // Check if subject exists
    if (!$schedule['subject_id']) {
        Response::error('Subject not found for this schedule', 404);
    }

    // Validate required schedule fields
    if (!$schedule['department_id'] || !$schedule['semester']) {
        Response::error('Incomplete schedule configuration', 500);
    }

    // Get active session for this schedule if any
    $sessionQuery = "SELECT id, is_active, session_start, session_end 
                     FROM teacher_locations 
                     WHERE schedule_id = :schedule_id AND DATE(session_start) = :date
                     ORDER BY session_start DESC LIMIT 1";
    $stmt = $db->prepare($sessionQuery);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    // Session info
    $sessionInfo = [
        'session_id' => $session ? (int)$session['id'] : null,
        'schedule_id' => $scheduleId,
        'subject_name' => $schedule['subject_name'],
        'subject_code' => $schedule['subject_code'],
        'date' => $date,
        'start_time' => $schedule['start_time'],
        'end_time' => $schedule['end_time'],
        'is_active' => $session ? (bool)$session['is_active'] : false
    ];

    // Get all students in the class
    $studentsQuery = "SELECT s.id as student_id, s.roll_number, u.full_name
                      FROM students s
                      JOIN users u ON s.user_id = u.id
                      WHERE s.department_id = :department_id
                      AND s.semester = :semester
                      AND (s.section = :section OR :section2 IS NULL)
                      ORDER BY s.roll_number";
    $stmt = $db->prepare($studentsQuery);
    $stmt->bindParam(':department_id', $schedule['department_id']);
    $stmt->bindParam(':semester', $schedule['semester']);
    $stmt->bindParam(':section', $schedule['section']);
    $stmt->bindParam(':section2', $schedule['section']);
    $stmt->execute();
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get attendance records for this date and schedule
    $attendanceQuery = "SELECT a.student_id, a.status, a.attendance_time as marked_at,
                               a.verification_status, a.face_confidence_score
                        FROM attendance a
                        WHERE a.schedule_id = :schedule_id
                        AND a.attendance_date = :date";
    $stmt = $db->prepare($attendanceQuery);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create attendance lookup
    $attendanceLookup = [];
    foreach ($attendanceRecords as $record) {
        $attendanceLookup[$record['student_id']] = $record;
    }

    // Build student details
    $students = [];
    $presentCount = 0;
    $absentCount = 0;

    foreach ($allStudents as $student) {
        $attendance = $attendanceLookup[$student['student_id']] ?? null;
        $status = $attendance ? $attendance['status'] : 'absent';
        
        if ($status === 'present') {
            $presentCount++;
        } else {
            $absentCount++;
        }

        $students[] = [
            'student_id' => (int)$student['student_id'],
            'student_name' => $student['full_name'],
            'roll_number' => $student['roll_number'],
            'status' => $status,
            'marked_at' => $attendance ? $attendance['marked_at'] : null,
            'face_confidence' => $attendance ? (float)$attendance['face_confidence_score'] : null
        ];
    }

    $totalStudents = count($allStudents);

    // Return flat response matching Android ClassAttendanceResponse model
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'session_id' => $session ? (int)$session['id'] : null,
        'schedule_id' => $scheduleId,
        'subject_name' => $schedule['subject_name'],
        'subject_code' => $schedule['subject_code'],
        'semester' => (int)$schedule['semester'],
        'section' => $schedule['section'],
        'start_time' => $schedule['start_time'],
        'end_time' => $schedule['end_time'],
        'session_active' => $session ? (bool)$session['is_active'] : false,
        'total_students' => $totalStudents,
        'present_count' => $presentCount,
        'absent_count' => $absentCount,
        'students' => $students,
        'message' => 'Success'
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
