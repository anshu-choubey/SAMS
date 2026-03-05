<?php
/**
 * Send Test FCM Notification
 * Used to test if FCM is working
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
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

    // Get FCM Server Key
    $keyQuery = "SELECT setting_value FROM system_settings WHERE setting_key = 'fcm_server_key' LIMIT 1";
    $stmt = $db->prepare($keyQuery);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $serverKey = $result['setting_value'] ?? '';

    if (empty($serverKey)) {
        Response::error('FCM Server Key not configured. Please set it via PUT /api/fcm/configure.php', 400);
    }

    // Get a sample FCM token to test with
    $tokenQuery = "SELECT token FROM fcm_tokens WHERE is_active = TRUE LIMIT 1";
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

    // Prepare FCM payload
    $fcmPayload = [
        'registration_ids' => [$testToken],
        'notification' => [
            'title' => 'FCM Test Notification',
            'body' => 'If you see this, FCM is working correctly!'
        ],
        'data' => [
            'notification_id' => (string)$notificationId,
            'type' => 'system',
            'test' => 'true'
        ],
        'priority' => 'high'
    ];

    // Send to FCM
    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $fcmResponse = json_decode($response, true);

    // Mark notification as sent if successful
    if ($httpCode === 200 && ($fcmResponse['success'] ?? 0) > 0) {
        $updateQuery = "UPDATE notifications SET is_sent = TRUE, sent_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':id', $notificationId);
        $stmt->execute();

        Response::success([
            'notification_id' => $notificationId,
            'test_token' => substr($testToken, 0, 20) . '...',
            'fcm_response' => $fcmResponse,
            'http_code' => $httpCode,
            'status' => 'SUCCESS',
            'message' => 'Test notification sent successfully! Check your device.'
        ]);
    } else {
        $errorMsg = $curlError ?: ($fcmResponse['error'] ?? 'Unknown FCM error');
        
        Response::error(
            'FCM Send Failed - HTTP ' . $httpCode . ': ' . $errorMsg,
            $httpCode
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
