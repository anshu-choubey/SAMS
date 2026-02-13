<?php
/**
 * Mark Notification as Read API
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

    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['notification_id'])) {
        // Mark single notification as read
        $query = "UPDATE notifications SET is_read = TRUE, read_at = NOW() 
                  WHERE id = :id AND (target_user_id = :user_id OR target_role = :role OR target_role = 'all')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data['notification_id']);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':role', $user['role']);
        $stmt->execute();
    } elseif (isset($data['mark_all']) && $data['mark_all'] === true) {
        // Mark all as read
        $query = "UPDATE notifications SET is_read = TRUE, read_at = NOW() 
                  WHERE (target_user_id = :user_id OR target_role = :role OR target_role = 'all') 
                  AND is_read = FALSE";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':role', $user['role']);
        $stmt->execute();
    } else {
        Response::error('notification_id or mark_all required', 400);
    }

    Response::success(null, 'Notification(s) marked as read');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
