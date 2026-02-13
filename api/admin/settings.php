<?php
/**
 * System Settings API
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

try {
    Auth::hasRole('admin');

    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'get_all';

        if ($action === 'quick_stats') {
            $stats = [
                'total_users' => getUserCount($db),
                'active_teachers' => getTeacherCount($db),
                'registered_students' => getStudentCount($db)
            ];
            Response::success($stats);
        } else {
            $query = "SELECT * FROM system_settings ORDER BY setting_key";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::success(['settings' => $settings]);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['action']) && $data['action'] === 'clear_sessions') {
            $query = "DELETE FROM sessions";
            $stmt = $db->prepare($query);
            $stmt->execute();
            Response::success([], 'All sessions cleared successfully');
        }

        if (isset($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                // Use INSERT ... ON DUPLICATE KEY UPDATE for proper upsert
                $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                          VALUES (:key, :value, 'string')
                          ON DUPLICATE KEY UPDATE setting_value = :value2";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':value2', $value);
                $stmt->execute();
            }
            Response::success([], 'Settings updated successfully');
        }

        Response::error('Invalid request', 400);
    }

} catch (Exception $e) {
    Response::error('Settings operation failed: ' . $e->getMessage(), 500);
}

function getUserCount($db) {
    $query = "SELECT COUNT(*) as count FROM users WHERE is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getTeacherCount($db) {
    $query = "SELECT COUNT(*) as count FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getStudentCount($db) {
    $query = "SELECT COUNT(*) as count FROM students s JOIN users u ON s.user_id = u.id WHERE u.is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>
