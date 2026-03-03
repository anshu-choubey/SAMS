<?php
/**
 * Student Schedule API
 * Returns weekly schedule for the student based on department, semester, section
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
    $user = Auth::user();

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get student profile
    $studentQuery = "SELECT s.*, d.name as department_name, d.code as department_code
                     FROM students s
                     JOIN departments d ON s.department_id = d.id
                     WHERE s.user_id = :user_id";
    $stmt = $db->prepare($studentQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        Response::error('Student profile not found', 404);
    }

    // Optional day filter
    $dayFilter = $_GET['day'] ?? null;

    // Build query for schedules with session status
    $query = "SELECT 
                sc.id as schedule_id,
                sc.day_of_week,
                sc.start_time,
                sc.end_time,
                sc.classroom,
                sc.building,
                sc.is_active,
                sub.id as subject_id,
                sub.name as subject_name,
                sub.code as subject_code,
                sub.credits,
                t.id as teacher_id,
                u.full_name as teacher_name,
                ta.section,
                ta.semester,
                d.name as department_name,
                CASE 
                    WHEN tl.id IS NOT NULL AND tl.session_end IS NULL THEN 1 
                    ELSE 0 
                END as session_active,
                COALESCE(tl.latitude, 0) as teacher_latitude,
                COALESCE(tl.longitude, 0) as teacher_longitude,
                CASE 
                    WHEN att.id IS NOT NULL THEN 1 
                    ELSE 0 
                END as attendance_marked
              FROM schedules sc
              JOIN teacher_assignments ta ON sc.assignment_id = ta.id
              JOIN subjects sub ON ta.subject_id = sub.id
              JOIN teachers t ON ta.teacher_id = t.id
              JOIN users u ON t.user_id = u.id
              JOIN departments d ON ta.department_id = d.id
              LEFT JOIN teacher_locations tl ON sc.id = tl.schedule_id AND DATE(tl.session_start) = CURDATE() AND tl.session_end IS NULL AND tl.is_active = TRUE
              LEFT JOIN attendance att ON sc.id = att.schedule_id AND att.student_id = :student_id AND att.attendance_date = CURDATE()
              WHERE ta.department_id = :department_id
              AND ta.semester = :semester
              AND (ta.section IS NULL OR ta.section = :section)
              AND sc.is_active = TRUE
              AND ta.is_active = TRUE";

    $params = [
        ':student_id' => $student['id'],
        ':department_id' => $student['department_id'],
        ':semester' => $student['semester'],
        ':section' => $student['section']
    ];

    if ($dayFilter) {
        $query .= " AND sc.day_of_week = :day_of_week";
        $params[':day_of_week'] = $dayFilter;
    }

    $query .= " ORDER BY FIELD(sc.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), sc.start_time";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format schedules to match Android Schedule model exactly
    $formattedSchedules = array_map(function($schedule) {
        return [
            'id' => (int)$schedule['schedule_id'],
            'subject_id' => (int)$schedule['subject_id'],
            'subject_name' => $schedule['subject_name'],
            'subject_code' => $schedule['subject_code'],
            'teacher_name' => $schedule['teacher_name'],
            'day_of_week' => $schedule['day_of_week'],
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
            'classroom' => $schedule['classroom'],
            'building' => $schedule['building'],
            'is_active' => (bool)$schedule['is_active'],
            'session_active' => (bool)$schedule['session_active'],
            'teacher_latitude' => (float)$schedule['teacher_latitude'],
            'teacher_longitude' => (float)$schedule['teacher_longitude'],
            'attendance_marked' => (bool)$schedule['attendance_marked']
        ];
    }, $schedules);

    // Return ScheduleResponse structure matching Android model
    Response::success([
        'data' => [
            'schedules' => $formattedSchedules
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
