<?php
/**
 * FCM Configuration API (Admin only)
 * Set/Update FCM Server Key
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    Auth::hasRole('admin');

    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('fcm_server_key', $data['fcm_server_key'] ?? '', 'FCM Server Key');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    $serverKey = trim($data['fcm_server_key']);

    // Validate server key format (should start with 'AAAA')
    if (!preg_match('/^AAAA[A-Za-z0-9_-]+$/', $serverKey)) {
        Response::error('Invalid FCM Server Key format. It should start with "AAAA"', 400);
    }

    // Update setting
    $updateQuery = "UPDATE system_settings SET setting_value = :value, updated_at = NOW() 
                   WHERE setting_key = 'fcm_server_key'";
    $stmt = $db->prepare($updateQuery);
    $stmt->bindParam(':value', $serverKey);
    
    if (!$stmt->execute()) {
        Response::error('Failed to update FCM Server Key', 500);
    }

    // Test the connection to FCM
    $testPayload = [
        'registration_ids' => ['invalid_test_token'],
        'notification' => [
            'title' => 'FCM Configuration Test',
            'body' => 'Testing FCM connection'
        ]
    ];

    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $testResult = json_decode($response, true);
    
    // 200 or 400/401 errors indicate connectivity - 401 means invalid key
    $connectionValid = in_array($httpCode, [200, 400, 401]);

    $message = 'FCM Server Key configured successfully';
    if (!$connectionValid) {
        $message = 'Key configured but connection test failed. Please verify the key is correct.';
    } elseif ($httpCode === 401) {
        $message = 'Key configured but appears to be invalid. Please verify with Firebase Console.';
    }

    Response::success([
        'fcm_server_key_set' => true,
        'connection_test_status' => $httpCode === 200 ? 'SUCCESS' : ($httpCode === 401 ? 'KEY_INVALID' : 'FAILED'),
        'http_code' => $httpCode,
        'message' => $message
    ], 'FCM Server Key updated');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
