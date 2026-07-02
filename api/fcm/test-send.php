<?php
/**
 * Send Test FCM Notification
 * Used to test if FCM is working
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    Auth::hasRole('admin');
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

    $firebaseConfig = new FirebaseConfig();

    if (!$firebaseConfig->isConfigured()) {
        Response::error('Firebase service account not configured. Please set FIREBASE_SERVICE_ACCOUNT_JSON and FIREBASE_PROJECT_ID', 400);
    }

    // Get a sample FCM token to test with
    $tokenQuery = "SELECT {$tokenColumn} AS token FROM fcm_tokens WHERE is_active = TRUE LIMIT 1";
    $stmt = $db->prepare($tokenQuery);
    $stmt->execute();
    $tokenResult = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenResult) {
        Response::error('No active FCM tokens found. Ensure users have logged in and their tokens are registered.', 400);
    }

    $testToken = $tokenResult['token'];

    // Create test notification record
    $insertQuery = "INSERT INTO notifications (title, message, notification_type, target_role, created_by)
                   VALUES ('FCM Test Notification', 'This is a test notification', 'system', 'all', :admin_id)";
    $stmt = $db->prepare($insertQuery);
    $stmt->bindParam(':admin_id', $user['id']);
    $stmt->execute();
    $notificationId = $db->lastInsertId();

    $result = $firebaseConfig->sendNotification(
        [$testToken],
        'FCM Test Notification',
        'If you see this, FCM is working correctly!',
        [
            'notification_id' => (string)$notificationId,
            'type' => 'system',
            'test' => 'true'
        ]
    );

    if (!empty($result['success'])) {
        $updateQuery = "UPDATE notifications SET is_sent = TRUE, sent_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':id', $notificationId);
        $stmt->execute();

        Response::success([
            'notification_id' => $notificationId,
            'test_token' => substr($testToken, 0, 20) . '...',
            'fcm_response' => $result,
            'status' => 'SUCCESS',
            'message' => 'Test notification sent successfully! Check your device.'
        ]);
    } else {
        Response::error(
            'FCM Send Failed: ' . ($result['message'] ?? 'Unknown FCM error'),
            500
        );
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
