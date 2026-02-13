<?php
/**
 * Send Notification API (Admin only)
 * Sends notifications via FCM and stores in database
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/firebase.php';
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
    Auth::hasRole('admin');
    $user = Auth::user();

    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('title', $data['title'] ?? '', 'Title');
    $validator->required('message', $data['message'] ?? '', 'Message');
    $validator->required('type', $data['type'] ?? '', 'Notification Type');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    $validTypes = ['attendance_alert', 'low_attendance', 'system', 'schedule_change', 'face_reregister'];
    if (!in_array($data['type'], $validTypes)) {
        Response::error('Invalid notification type', 400);
    }

    // Insert notification
    $query = "INSERT INTO notifications (title, message, notification_type, target_role, target_user_id, target_department_id, data, created_by)
              VALUES (:title, :message, :type, :target_role, :target_user_id, :target_department_id, :data, :created_by)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':title', $data['title']);
    $stmt->bindParam(':message', $data['message']);
    $stmt->bindParam(':type', $data['type']);
    $targetRole = $data['target_role'] ?? null;
    $targetUserId = $data['target_user_id'] ?? null;
    $targetDeptId = $data['target_department_id'] ?? null;
    $notifData = isset($data['data']) ? json_encode($data['data']) : null;
    $stmt->bindParam(':target_role', $targetRole);
    $stmt->bindParam(':target_user_id', $targetUserId);
    $stmt->bindParam(':target_department_id', $targetDeptId);
    $stmt->bindParam(':data', $notifData);
    $stmt->bindParam(':created_by', $user['id']);
    $stmt->execute();

    $notificationId = $db->lastInsertId();

    // Get FCM tokens for target users
    $tokenQuery = "SELECT ft.token, ft.user_id FROM fcm_tokens ft
                   JOIN users u ON ft.user_id = u.id
                   WHERE ft.is_active = TRUE";
    
    $conditions = [];
    $params = [];

    if ($targetUserId) {
        $conditions[] = "ft.user_id = :user_id";
        $params[':user_id'] = $targetUserId;
    } elseif ($targetRole && $targetRole !== 'all') {
        $conditions[] = "u.role = :role";
        $params[':role'] = $targetRole;
    }

    if ($targetDeptId) {
        $tokenQuery .= " AND (
            (u.role = 'student' AND EXISTS (SELECT 1 FROM students s WHERE s.user_id = u.id AND s.department_id = :dept_id))
            OR (u.role = 'teacher' AND EXISTS (SELECT 1 FROM teachers t WHERE t.user_id = u.id AND t.primary_department_id = :dept_id2))
        )";
        $params[':dept_id'] = $targetDeptId;
        $params[':dept_id2'] = $targetDeptId;
    }

    if (!empty($conditions)) {
        $tokenQuery .= " AND " . implode(' AND ', $conditions);
    }

    $stmt = $db->prepare($tokenQuery);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Send FCM notifications
    $sentCount = 0;
    $failedCount = 0;

    if (!empty($tokens) && defined('FCM_SERVER_KEY') && FCM_SERVER_KEY) {
        $fcmTokens = array_column($tokens, 'token');
        
        foreach (array_chunk($fcmTokens, 500) as $tokenChunk) {
            $fcmPayload = [
                'registration_ids' => $tokenChunk,
                'notification' => [
                    'title' => $data['title'],
                    'body' => $data['message']
                ],
                'data' => [
                    'notification_id' => $notificationId,
                    'type' => $data['type'],
                    'custom_data' => $data['data'] ?? null
                ]
            ];

            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: key=' . FCM_SERVER_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmPayload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $result = json_decode($response, true);
            curl_close($ch);

            if ($result) {
                $sentCount += $result['success'] ?? 0;
                $failedCount += $result['failure'] ?? 0;
            }
        }

        // Update notification as sent
        $updateQuery = "UPDATE notifications SET is_sent = TRUE, sent_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':id', $notificationId);
        $stmt->execute();
    }

    Response::success([
        'notification_id' => (int)$notificationId,
        'fcm_sent' => $sentCount,
        'fcm_failed' => $failedCount,
        'total_tokens' => count($tokens)
    ], 'Notification sent successfully');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
