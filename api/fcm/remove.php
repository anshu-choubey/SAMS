<?php
/**
 * Remove FCM Token API
 * Called on logout to disable push notifications
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

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

    if (isset($data['token'])) {
        // Deactivate specific token
        $query = "UPDATE fcm_tokens SET is_active = FALSE WHERE {$tokenColumn} = :token AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $data['token']);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->execute();
    } else {
        // Deactivate all tokens for user
        $query = "UPDATE fcm_tokens SET is_active = FALSE WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->execute();
    }

    Response::success(null, 'FCM token removed successfully');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
