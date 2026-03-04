<?php
/**
 * Teacher Access Verification API
 * Diagnostic endpoint to check what schedules a teacher has access to
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

try {
    $user = Auth::user();
    
    if (!$user) {
        Response::unauthorized('Please login to continue');
    }
    
    if ($user['role'] !== 'teacher') {
        Response::error('Access restricted to teachers only', 403);
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get teacher profile
    $teacherQuery = "SELECT t.* FROM teachers t WHERE t.user_id = :user_id";
    $stmt = $db->prepare($teacherQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Response::error('Teacher profile not found', 404);
    }

    // Get all teacher assignments
    $assignmentsQuery = "SELECT ta.id, ta.subject_id, ta.department_id, ta.semester, ta.section, 
                                ta.is_active, sub.name as subject_name, sub.code as subject_code,
                                dept.name as department_name
                         FROM teacher_assignments ta
                         JOIN subjects sub ON ta.subject_id = sub.id
                         JOIN departments dept ON ta.department_id = dept.id
                         WHERE ta.teacher_id = :teacher_id
                         ORDER BY ta.id";
    $stmt = $db->prepare($assignmentsQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each assignment, get its schedules
    $assignmentsData = [];
    foreach ($assignments as $assignment) {
        $schedulesQuery = "SELECT sc.id, sc.day_of_week, sc.start_time, sc.end_time, 
                                  sc.classroom, sc.building, sc.is_active
                           FROM schedules sc
                           WHERE sc.assignment_id = :assignment_id
                           ORDER BY sc.day_of_week, sc.start_time";
        $stmt = $db->prepare($schedulesQuery);
        $stmt->bindParam(':assignment_id', $assignment['id']);
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $assignmentsData[] = [
            'assignment_id' => (int)$assignment['id'],
            'subject_id' => (int)$assignment['subject_id'],
            'subject_name' => $assignment['subject_name'],
            'subject_code' => $assignment['subject_code'],
            'department' => $assignment['department_name'],
            'semester' => (int)$assignment['semester'],
            'section' => $assignment['section'],
            'is_active' => (bool)$assignment['is_active'],
            'schedules_count' => count($schedules),
            'schedules' => array_map(function($s) {
                return [
                    'schedule_id' => (int)$s['id'],
                    'day_of_week' => $s['day_of_week'],
                    'start_time' => $s['start_time'],
                    'end_time' => $s['end_time'],
                    'classroom' => $s['classroom'],
                    'building' => $s['building'],
                    'is_active' => (bool)$s['is_active']
                ];
            }, $schedules)
        ];
    }

    // Check the specific schedule if provided
    $checkSchedule = $_GET['schedule_id'] ?? null;
    $scheduleDetail = null;

    if ($checkSchedule) {
        $detailQuery = "SELECT sc.*, ta.teacher_id, ta.subject_id, ta.department_id, ta.semester, ta.section,
                               sub.name as subject_name, sub.code as subject_code
                        FROM schedules sc
                        LEFT JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                        LEFT JOIN subjects sub ON ta.subject_id = sub.id
                        WHERE sc.id = :schedule_id";
        $stmt = $db->prepare($detailQuery);
        $stmt->bindParam(':schedule_id', $checkSchedule);
        $stmt->execute();
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($schedule) {
            $scheduleDetail = [
                'schedule_id' => (int)$schedule['id'],
                'assignment_id' => $schedule['assignment_id'] ? (int)$schedule['assignment_id'] : null,
                'teacher_id' => $schedule['teacher_id'] ? (int)$schedule['teacher_id'] : null,
                'your_teacher_id' => (int)$teacher['id'],
                'teacher_match' => $schedule['teacher_id'] == $teacher['id'],
                'subject_name' => $schedule['subject_name'],
                'subject_code' => $schedule['subject_code'],
                'department_id' => (int)$schedule['department_id'],
                'semester' => (int)$schedule['semester'],
                'section' => $schedule['section'],
                'day_of_week' => $schedule['day_of_week'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'is_active' => (bool)$schedule['is_active']
            ];
        }
    }

    Response::success([
        'teacher_id' => (int)$teacher['id'],
        'teacher_name' => $user['full_name'],
        'total_assignments' => count($assignmentsData),
        'assignments' => $assignmentsData,
        'checked_schedule' => $scheduleDetail
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
