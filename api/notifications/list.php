<?php
/**
 * Notifications List API
 * Get user notifications
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

// Handle CORS
CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    Auth::check();
    $user = Auth::user();

    $database = new Database();
    $db = $database->getConnection();

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

    $query = "SELECT n.* FROM notifications n
              WHERE (n.target_user_id = :user_id 
                     OR n.target_role = :role 
                     OR n.target_role = 'all')";
    
    if ($unreadOnly) {
        $query .= " AND n.is_read = FALSE";
    }
    
    $query .= " ORDER BY n.created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':role', $user['role']);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread count
    $countQuery = "SELECT COUNT(*) as unread_count FROM notifications n
                   WHERE (n.target_user_id = :user_id 
                          OR n.target_role = :role 
                          OR n.target_role = 'all')
                   AND n.is_read = FALSE";
    $stmt = $db->prepare($countQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':role', $user['role']);
    $stmt->execute();
    $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

    $formattedNotifications = array_map(function($n) {
        return [
            'id' => (int)$n['id'],
            'title' => $n['title'],
            'message' => $n['message'],
            'type' => $n['notification_type'],
            'data' => $n['data'] ? json_decode($n['data'], true) : null,
            'is_read' => (bool)$n['is_read'],
            'created_at' => $n['created_at'],
            'read_at' => $n['read_at']
        ];
    }, $notifications);

    Response::success([
        'notifications' => $formattedNotifications,
        'unread_count' => (int)$unreadCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
