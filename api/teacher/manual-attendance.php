<?php
/**
 * Teacher Manual Attendance API
 * Allows teacher to manually mark attendance for a student
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

    // Get POST data (matching ManualAttendanceRequest)
    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('schedule_id', $data['schedule_id'] ?? '', 'Schedule ID');
    $validator->required('student_id', $data['student_id'] ?? '', 'Student ID');
    $validator->required('status', $data['status'] ?? '', 'Status');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    $scheduleId = (int)$data['schedule_id'];
    $studentId = (int)$data['student_id'];
    $status = $data['status'];

    // Validate status value
    if (!in_array($status, ['present', 'absent', 'late'])) {
        Response::error('Invalid status. Must be present, absent, or late', 400);
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

    // Verify schedule belongs to this teacher
    // First verify teacher is assigned to this schedule with explicit INNER JOIN
    $authQuery = "SELECT ta.id as assignment_id FROM schedules sc
                  JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                  WHERE sc.id = :schedule_id AND ta.teacher_id = :teacher_id AND sc.is_active = TRUE
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

    // Now get full schedule details
    $scheduleQuery = "SELECT sc.*, ta.teacher_id, ta.subject_id, ta.department_id, ta.semester, ta.section, ta.is_active as assignment_active
                      FROM schedules sc
                      LEFT JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                      WHERE sc.id = :schedule_id AND sc.is_active = TRUE";
    $stmt = $db->prepare($scheduleQuery);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->execute();
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        Response::error('Schedule not found or inactive', 404);
    }

    // Check if assignment is active
    if ((int)$schedule['assignment_active'] !== 1) {
        Response::error('Assignment is currently inactive', 403);
    }

    // Verify student exists and is in the class
    $studentQuery = "SELECT s.* FROM students s 
                     WHERE s.id = :student_id 
                     AND s.department_id = :department_id 
                     AND s.semester = :semester 
                     AND (s.section = :section OR :section2 IS NULL)";
    $stmt = $db->prepare($studentQuery);
    $stmt->bindParam(':student_id', $studentId);
    $stmt->bindParam(':department_id', $schedule['department_id']);
    $stmt->bindParam(':semester', $schedule['semester']);
    $stmt->bindParam(':section', $schedule['section']);
    $stmt->bindParam(':section2', $schedule['section']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        Response::error('Student not found or not in this class', 404);
    }

    $today = date('Y-m-d');
    $now = date('H:i:s');

    // Check if attendance already exists for today
    $checkQuery = "SELECT id FROM attendance 
                   WHERE student_id = :student_id 
                   AND schedule_id = :schedule_id 
                   AND attendance_date = :date";
    $stmt = $db->prepare($checkQuery);
    $stmt->bindParam(':student_id', $studentId);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->bindParam(':date', $today);
    $stmt->execute();
    $existingAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAttendance) {
        // Update existing attendance
        $updateQuery = "UPDATE attendance 
                        SET status = :status, 
                            attendance_time = NOW(), 
                            verification_status = 'success'
                        WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $existingAttendance['id']);
        $stmt->execute();
    } else {
        // Insert new attendance record
        $insertQuery = "INSERT INTO attendance 
                        (student_id, schedule_id, assignment_id, teacher_id, department_id, attendance_date, status, verification_status)
                        VALUES (:student_id, :schedule_id, :assignment_id, :teacher_id, :department_id, :date, :status, 'success')";
        $stmt = $db->prepare($insertQuery);
        $stmt->bindParam(':student_id', $studentId);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->bindParam(':assignment_id', $schedule['assignment_id']);
        $stmt->bindParam(':teacher_id', $teacher['id']);
        $stmt->bindParam(':department_id', $schedule['department_id']);
        $stmt->bindParam(':date', $today);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
    }

    Response::success(null, 'Attendance marked successfully');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
