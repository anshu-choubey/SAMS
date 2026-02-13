<?php
/**
 * Departments Management API (Admin Only)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Department.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

// Handle CORS
CORS::handle();

try {
    // Check authentication and role
    Auth::hasRole('admin');

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    $department = new Department($db);
    $validator = new Validator();

    // Handle different HTTP methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single department
                $result = $department->getById($_GET['id']);
                if (!$result) {
                    Response::notFound('Department not found');
                }
                Response::success(['department' => $result]);
            } else {
                // Get all departments
                $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
                $departments = $department->getAll($activeOnly);
                Response::success(['departments' => $departments]);
            }
            break;

        case 'POST':
            // Create new department
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate
            $validator->required('name', $data['name'] ?? '', 'Department Name');
            $validator->required('code', $data['code'] ?? '', 'Department Code');

            if ($validator->hasErrors()) {
                Response::validationError($validator->getErrors());
            }

            // Check if code exists
            if ($department->codeExists($data['code'])) {
                Response::error('Department code already exists', 400);
            }

            // Create department
            $department->name = $data['name'];
            $department->code = strtoupper($data['code']);
            $department->description = $data['description'] ?? null;
            $department->hod_id = $data['hod_id'] ?? null;
            $department->is_active = $data['is_active'] ?? true;

            if ($department->create()) {
                Response::success([
                    'department_id' => $department->id
                ], 'Department created successfully', 201);
            }

            Response::error('Failed to create department');
            break;

        case 'PUT':
            // Update department
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                Response::error('Department ID is required', 400);
            }

            // Check if exists
            $existing = $department->getById($data['id']);
            if (!$existing) {
                Response::notFound('Department not found');
            }

            // Validate
            if (isset($data['code']) && $department->codeExists($data['code'], $data['id'])) {
                Response::error('Department code already exists', 400);
            }

            // Update
            $department->id = $data['id'];
            $department->name = $data['name'] ?? $existing['name'];
            $department->code = isset($data['code']) ? strtoupper($data['code']) : $existing['code'];
            $department->description = $data['description'] ?? $existing['description'];
            $department->hod_id = $data['hod_id'] ?? $existing['hod_id'];
            $department->is_active = $data['is_active'] ?? $existing['is_active'];

            if ($department->update()) {
                Response::success([], 'Department updated successfully');
            }

            Response::error('Failed to update department');
            break;

        case 'DELETE':
            // Delete department
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                Response::error('Department ID is required', 400);
            }

            $department->id = $data['id'];

            if ($department->delete()) {
                Response::success([], 'Department deleted successfully');
            }

            Response::error('Failed to delete department');
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
