<?php
/**
 * Teacher Location Update API
 * Updates teacher's GPS location during active class session
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
    
    if ($user['role'] !== 'teacher') {
        Response::error('Access restricted to teachers only', 403);
    }

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get POST data (matching UpdateLocationRequest)
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

    // Find active session for this schedule
    $sessionQuery = "SELECT tl.id FROM teacher_locations tl
                     WHERE tl.schedule_id = :schedule_id 
                     AND tl.teacher_id = :teacher_id 
                     AND tl.is_active = TRUE";
    $stmt = $db->prepare($sessionQuery);
    $stmt->bindParam(':schedule_id', $data['schedule_id']);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        Response::error('No active session found for this schedule', 404);
    }

    // Update location
    $updateQuery = "UPDATE teacher_locations 
                    SET latitude = :latitude, 
                        longitude = :longitude
                    WHERE id = :session_id";
    $stmt = $db->prepare($updateQuery);
    $stmt->bindParam(':latitude', $data['latitude']);
    $stmt->bindParam(':longitude', $data['longitude']);
    $stmt->bindParam(':session_id', $session['id']);
    $stmt->execute();

    Response::success(null, 'Location updated successfully');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
