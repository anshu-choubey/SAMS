<?php
/**
 * Student Face Registration API
 * Registers encrypted face embedding
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Student.php';
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
    Auth::hasRole('student');

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();

    // Validate
    $validator->required('face_embedding', $data['face_embedding'] ?? '', 'Face Embedding');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    // Get current user
    $user = Auth::user();

    // Get student profile
    $student = new Student($db);
    $studentData = $student->getByUserId($user['id']);

    if (!$studentData) {
        Response::error('Student profile not found', 404);
    }

    $student->id = $studentData['id'];
    
    // Get optional face photo (base64 encoded)
    $facePhoto = $data['face_photo'] ?? null;

    // Register face with optional photo
    if ($student->registerFace($data['face_embedding'], $facePhoto)) {
        Response::success([
            'student_id' => $student->id,
            'face_registered' => true
        ], 'Face registered successfully');
    }

    Response::error('Failed to register face');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
