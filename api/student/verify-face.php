<?php
/**
 * Verify Face API
 * GET: Retrieves stored face embedding for client-side verification
 * POST: (Legacy) Verifies uploaded face embedding against stored student face data
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Student.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

function getSystemSettingValue(PDO $db, string $settingKey, $defaultValue = null) {
    try {
        $valueColumnQuery = "SELECT COLUMN_NAME
                             FROM INFORMATION_SCHEMA.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE()
                               AND TABLE_NAME = 'system_settings'
                               AND COLUMN_NAME IN ('setting_value', 'value')
                             ORDER BY FIELD(COLUMN_NAME, 'setting_value', 'value')
                             LIMIT 1";
        $stmt = $db->query($valueColumnQuery);
        $valueColumn = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['COLUMN_NAME'] ?? 'setting_value') : 'setting_value';

        $query = "SELECT {$valueColumn} AS setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':setting_key', $settingKey);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['setting_value'] ?? $defaultValue;
    } catch (Exception $e) {
        return $defaultValue;
    }
}

// Handle CORS
CORS::handle();

// Support both GET (fetch stored embedding) and POST (verify)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    Auth::hasRole('student');
    $user = Auth::user();

    $database = new Database();
    $db = $database->getConnection();

    // Get student profile
    $studentModel = new Student($db);
    $studentData = $studentModel->getByUserId($user['id']);

    if (!$studentData) {
        Response::error('Student profile not found', 404);
    }

    // For GET requests, return decrypted embedding for client-side comparison
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$studentData['face_registered'] || !$studentData['face_data']) {
            // Return null embedding if not registered (app handles this)
            Response::success([
                'face_embedding' => null,
                'face_registered' => false
            ], 'Face not registered');
        }
        
        // Decrypt the face data before returning
        $decryptedEmbedding = $studentModel->getFaceData($studentData['id']);
        
        // Get threshold from database settings
        $threshold = (int)(getSystemSettingValue($db, 'face_confidence_threshold', FACE_CONFIDENCE_THRESHOLD ?? 95) ?: (FACE_CONFIDENCE_THRESHOLD ?? 95));
        
        Response::success([
            'face_embedding' => $decryptedEmbedding,
            'face_registered' => true,
            'threshold' => $threshold
        ], 'Face data retrieved for verification');
    }

    // For POST requests, validate incoming embedding
    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('face_embedding', $data['face_embedding'] ?? '', 'Face Embedding');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    if (!$studentData['face_registered'] || !$studentData['face_data']) {
        Response::error('Face not registered. Please register your face first.', 400);
    }

    // Decrypt the face data before returning
    $decryptedEmbedding = $studentModel->getFaceData($studentData['id']);

    // Return decrypted embedding for client-side comparison (ML Kit does the comparison)
    Response::success([
        'student_id' => (int)$studentData['id'],
        'face_registered' => true,
        'face_embedding' => $decryptedEmbedding,
        'threshold' => FACE_CONFIDENCE_THRESHOLD ?? 95
    ], 'Face data retrieved for verification');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
