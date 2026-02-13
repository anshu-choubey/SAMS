<?php
/**
 * Users Management API (Admin Only)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/controllers/UserController.php';

// Handle CORS
CORS::handle();

try {
    // Check authentication and role
    Auth::hasRole('admin');

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Initialize controller
    $userController = new UserController($db);

    // Handle different HTTP methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single user
                $userController->getById($_GET['id']);
            } else {
                // Get all users with filters
                $filters = [
                    'role' => $_GET['role'] ?? null,
                    'department_id' => $_GET['department_id'] ?? null,
                    'is_active' => isset($_GET['is_active']) ? (bool)$_GET['is_active'] : null,
                    'search' => $_GET['search'] ?? null,
                    'page' => $_GET['page'] ?? 1,
                    'limit' => $_GET['limit'] ?? ITEMS_PER_PAGE,
                    'offset' => (($_GET['page'] ?? 1) - 1) * ($_GET['limit'] ?? ITEMS_PER_PAGE)
                ];
                $userController->getAll($filters);
            }
            break;

        case 'POST':
            // Create new user
            $data = json_decode(file_get_contents('php://input'), true);
            $userController->create($data);
            break;

        case 'PUT':
            // Update user
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id'])) {
                Response::error('User ID is required', 400);
            }
            $userController->update($data['id'], $data);
            break;

        case 'DELETE':
            // Delete user
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id'])) {
                Response::error('User ID is required', 400);
            }
            $userController->delete($data['id']);
            break;

        case 'PATCH':
            // Update password
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id']) || !isset($data['password'])) {
                Response::error('User ID and password are required', 400);
            }
            if (strlen($data['password']) < 6) {
                Response::error('Password must be at least 6 characters', 400);
            }
            $userController->updatePassword($data['id'], $data['password']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
