<?php
/**
 * FCM Configuration API (Admin only)
 * Set/Update Firebase service account credentials for FCM API v1
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/firebase.php';
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
    $validator->required('service_account_json', $data['service_account_json'] ?? '', 'Service Account JSON');
    $validator->required('project_id', $data['project_id'] ?? '', 'Project ID');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    $serviceAccountJson = trim($data['service_account_json']);
    $projectId = trim($data['project_id']);

    $serviceAccount = json_decode($serviceAccountJson, true);
    if (!is_array($serviceAccount) || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
        Response::error('Invalid Firebase service account JSON', 400);
    }

    // Update setting
    $valueColumn = getSystemSettingsValueColumn($db) ?: 'setting_value';
    $settings = [
        'firebase_service_account_json' => $serviceAccountJson,
        'firebase_project_id' => $projectId,
    ];

    foreach ($settings as $settingKey => $settingValue) {
        $upsertQuery = "INSERT INTO system_settings (setting_key, {$valueColumn}, setting_type)
                        VALUES (:setting_key, :value, 'string')
                        ON DUPLICATE KEY UPDATE {$valueColumn} = VALUES({$valueColumn}), updated_at = NOW()";
        $stmt = $db->prepare($upsertQuery);
        $stmt->bindValue(':setting_key', $settingKey);
        $stmt->bindValue(':value', $settingValue);

        if (!$stmt->execute()) {
            Response::error('Failed to update Firebase configuration', 500);
        }
    }

    $message = 'Firebase service account configured successfully';

    Response::success([
        'service_account_configured' => true,
        'project_id' => $projectId,
        'message' => $message
    ], 'Firebase configuration updated');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
