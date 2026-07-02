<?php
/**
 * FCM Token Registration API
 * Register/Update device FCM token for push notifications
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    Auth::check();
    $user = Auth::user();

    $database = new Database();
    $db = $database->getConnection();

    $tokenColumn = 'token';
    try {
        $columnQuery = "SELECT COLUMN_NAME
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'fcm_tokens'
                          AND COLUMN_NAME IN ('token', 'device_token')";
        $stmt = $db->prepare($columnQuery);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('device_token', $columns, true)) {
            $tokenColumn = 'device_token';
        }
    } catch (Exception $e) {
        $tokenColumn = 'token';
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('token', $data['token'] ?? '', 'FCM Token');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    $deviceType = $data['device_type'] ?? 'android';
    $deviceName = $data['device_name'] ?? null;

    // Check if token already exists
    $checkQuery = "SELECT id, user_id FROM fcm_tokens WHERE {$tokenColumn} = :token";
    $stmt = $db->prepare($checkQuery);
    $stmt->bindParam(':token', $data['token']);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['user_id'] != $user['id']) {
            // Token belongs to different user, update it
            $updateQuery = "UPDATE fcm_tokens SET user_id = :user_id, device_type = :device_type, 
                           device_name = :device_name, is_active = TRUE, updated_at = NOW() 
                           WHERE id = :id";
            $stmt = $db->prepare($updateQuery);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->bindParam(':device_type', $deviceType);
            $stmt->bindParam(':device_name', $deviceName);
            $stmt->bindParam(':id', $existing['id']);
            $stmt->execute();
        } else {
            // Same user, just update timestamp
            $updateQuery = "UPDATE fcm_tokens SET is_active = TRUE, updated_at = NOW() WHERE id = :id";
            $stmt = $db->prepare($updateQuery);
            $stmt->bindParam(':id', $existing['id']);
            $stmt->execute();
        }
    } else {
        // Insert new token
        $insertQuery = "INSERT INTO fcm_tokens (user_id, {$tokenColumn}, device_type, device_name, is_active) 
                       VALUES (:user_id, :token, :device_type, :device_name, TRUE)";
        $stmt = $db->prepare($insertQuery);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':token', $data['token']);
        $stmt->bindParam(':device_type', $deviceType);
        $stmt->bindParam(':device_name', $deviceName);
        $stmt->execute();
    }

    Response::success(null, 'FCM token registered successfully');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
