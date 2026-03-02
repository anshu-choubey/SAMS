<?php
/**
 * Teacher Start Class API
 * Starts a class session and enables attendance marking
 * Matches Android app's ClassSession response model
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

    // Get POST data (matching StartClassRequest)
    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('schedule_id', $data['schedule_id'] ?? '', 'Schedule ID');
    $validator->required('latitude', $data['latitude'] ?? '', 'Latitude');
    $validator->required('longitude', $data['longitude'] ?? '', 'Longitude');
    $validator->numeric('latitude', $data['latitude'] ?? '', 'Latitude');
    $validator->numeric('longitude', $data['longitude'] ?? '', 'Longitude');

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

    // Verify schedule belongs to this teacher
    $scheduleQuery = "SELECT sc.*, ta.teacher_id, ta.subject_id, ta.department_id, ta.semester, ta.section
                      FROM schedules sc
                      JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                      WHERE sc.id = :schedule_id AND ta.teacher_id = :teacher_id AND sc.is_active = TRUE";
    $stmt = $db->prepare($scheduleQuery);
    $stmt->bindParam(':schedule_id', $data['schedule_id']);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        Response::error('Schedule not found or not assigned to you', 404);
    }

    // Check if session already started
    $existingQuery = "SELECT id FROM teacher_locations 
                      WHERE schedule_id = :schedule_id AND is_active = TRUE";
    $stmt = $db->prepare($existingQuery);
    $stmt->bindParam(':schedule_id', $data['schedule_id']);
    $stmt->execute();
    if ($stmt->fetch()) {
        Response::error('Class session already started', 400);
    }

    // Get duration (default 60 minutes)
    $durationMinutes = isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : 60;
    $notes = $data['notes'] ?? null;

    // Start class session - insert into teacher_locations
    $insertQuery = "INSERT INTO teacher_locations 
                    (teacher_id, schedule_id, assignment_id, department_id, latitude, longitude, is_active, session_start)
                    VALUES (:teacher_id, :schedule_id, :assignment_id, :department_id, :latitude, :longitude, TRUE, NOW())";
    $stmt = $db->prepare($insertQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->bindParam(':schedule_id', $data['schedule_id']);
    $stmt->bindParam(':assignment_id', $schedule['assignment_id']);
    $stmt->bindParam(':department_id', $schedule['department_id']);
    $stmt->bindParam(':latitude', $data['latitude']);
    $stmt->bindParam(':longitude', $data['longitude']);
    $stmt->execute();

    $sessionId = $db->lastInsertId();

    // Calculate expected end time
    $startedAt = date('Y-m-d H:i:s');
    $expectedEnd = date('Y-m-d H:i:s', strtotime("+{$durationMinutes} minutes"));

    // Generate QR code data (optional - can be used for quick check-in)
    $qrData = json_encode([
        'session_id' => $sessionId,
        'schedule_id' => $data['schedule_id'],
        'timestamp' => time()
    ]);
    $qrCode = base64_encode($qrData);

    // Return ClassSession response
    Response::success([
        'session_id' => (int)$sessionId,
        'schedule_id' => (int)$data['schedule_id'],
        'started_at' => $startedAt,
        'expected_end' => $expectedEnd,
        'qr_code' => $qrCode,
        'attendance_window_minutes' => $durationMinutes
    ], 'Class session started successfully');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
