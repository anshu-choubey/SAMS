<?php
/**
 * FCM Setup Check API
 * Verify if FCM is properly configured
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

try {
    Auth::hasRole('admin');

    $database = new Database();
    $db = $database->getConnection();

    $hasServerKey = !empty(FCM_SERVER_KEY);
    $serverKeyPrefix = $hasServerKey ? substr(FCM_SERVER_KEY, 0, 20) . '...' : 'NOT SET';

    // Check registered FCM tokens
    $tokenQuery = "SELECT COUNT(*) as total_tokens, COUNT(DISTINCT user_id) as unique_users FROM fcm_tokens WHERE is_active = TRUE";
    $stmt = $db->prepare($tokenQuery);
    $stmt->execute();
    $tokenStats = $stmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'fcm_configured' => $hasServerKey,
        'fcm_server_key_set' => $hasServerKey,
        'fcm_server_key_preview' => $serverKeyPrefix,
        'registered_devices' => (int)$tokenStats['total_tokens'],
        'registered_users' => (int)$tokenStats['unique_users'],
        'status' => $hasServerKey ? 'READY' : 'NOT_CONFIGURED',
        'setup_instructions' => $hasServerKey ? 'FCM is properly configured' : 'Please set FCM Server Key via PUT /api/fcm/configure.php'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
