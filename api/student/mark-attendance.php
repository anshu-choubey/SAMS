<?php
/**
 * Student Mark Attendance API
 * Marks attendance with dual verification (GPS + Face)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Student.php';
require_once __DIR__ . '/../../includes/models/Teacher.php';
require_once __DIR__ . '/../../includes/models/Schedule.php';
require_once __DIR__ . '/../../includes/models/Attendance.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

function getSystemSettingValue(PDO $db, string $settingKey, $defaultValue = null) {
    try {
        $queries = [
            "SELECT value FROM system_settings WHERE `key` = :k LIMIT 1",
            "SELECT setting_value AS value FROM system_settings WHERE setting_key = :k LIMIT 1"
        ];
        foreach ($queries as $query) {
            try {
                $stmt = $db->prepare($query);
                $stmt->bindParam(':k', $settingKey);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && isset($result['value']) && $result['value'] !== null) {
                    return $result['value'];
                }
            } catch (Exception $e) { continue; }
        }
        return $defaultValue;
    } catch (Exception $e) {
        return $defaultValue;
    }
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
    // Check authentication and role
    Auth::hasRole('student');

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get face confidence threshold from settings
    $faceConfidenceThreshold = (int)(getSystemSettingValue($db, 'face_confidence_threshold', 60) ?: 60);

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();

    // Validate
    $validator->required('schedule_id', $data['schedule_id'] ?? '', 'Schedule ID');
    $validator->required('latitude', $data['latitude'] ?? '', 'Latitude');
    $validator->required('longitude', $data['longitude'] ?? '', 'Longitude');
    $validator->required('face_confidence', $data['face_confidence'] ?? '', 'Face Confidence');
    $validator->numeric('latitude', $data['latitude'] ?? '', 'Latitude');
    $validator->numeric('longitude', $data['longitude'] ?? '', 'Longitude');
    $validator->numeric('face_confidence', $data['face_confidence'] ?? '', 'Face Confidence');
    $validator->range('face_confidence', $data['face_confidence'] ?? 0, $faceConfidenceThreshold - 10, 100, 'Face Confidence');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    // Ensure face confidence is above minimum threshold
    if ($data['face_confidence'] < $faceConfidenceThreshold) {
        Response::error('Face verification failed. Confidence: ' . round($data['face_confidence']) . '% (minimum required: ' . $faceConfidenceThreshold . '%)', 400);
    }

    // Get current user
    $user = Auth::user();

    // Get student profile
    $student = new Student($db);
    $studentData = $student->getByUserId($user['id']);

    if (!$studentData) {
        Response::error('Student profile not found', 404);
    }

    // Check if face registered
    $faceRegistered = isset($studentData['face_registered'])
        ? (bool)$studentData['face_registered']
        : !empty($studentData['face_data']);

    if (!$faceRegistered) {
        Response::error('Please register your face first before marking attendance', 400);
    }

    // Get schedule details
    $schedule = new Schedule($db);
    $scheduleData = $schedule->getById($data['schedule_id']);

    if (!$scheduleData) {
        Response::error('Invalid schedule', 404);
    }

    // Get teacher's current location
    $teacher = new Teacher($db);
    $teacher->id = $scheduleData['teacher_id'];
    $teacherLocation = $teacher->getCurrentLocation();

    if (!$teacherLocation || !$teacherLocation['is_active']) {
        Response::error('Teacher has not started the attendance session yet', 400);
    }

    // If multi-check is enabled, don't mark attendance directly
    $multiCheckEnabled = getSystemSettingValue($db, 'attendance_multi_check_enabled', 'true');
    if ($multiCheckEnabled === 'true' || $multiCheckEnabled === '1') {
        Response::error('Multi-check attendance is enabled. Complete all attendance checks instead.', 400);
    }

    // Mark attendance (only when multi-check is disabled)
    $attendance = new Attendance($db);
    $attendance->student_id = $studentData['id'];
    $attendance->schedule_id = $data['schedule_id'];
    $attendance->assignment_id = $scheduleData['assignment_id'];
    $attendance->teacher_id = $scheduleData['teacher_id'];
    $attendance->department_id = $scheduleData['department_id'];
    $attendance->attendance_date = date('Y-m-d');
    $attendance->student_latitude = $data['latitude'];
    $attendance->student_longitude = $data['longitude'];
    $attendance->teacher_latitude = $teacherLocation['latitude'];
    $attendance->teacher_longitude = $teacherLocation['longitude'];
    $attendance->face_confidence_score = $data['face_confidence'];
    $attendance->gps_accuracy = isset($data['gps_accuracy']) ? (float)$data['gps_accuracy'] : 0;

    $result = $attendance->mark();

    if ($result['success']) {
        Response::success($result, $result['message']);
    }

    Response::error($result['message'], 400);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
