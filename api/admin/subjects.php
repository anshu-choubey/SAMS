<?php
/**
 * Subjects Management API (Admin Only)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Subject.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

CORS::handle();

try {
    Auth::hasRole('admin');

    $database = new Database();
    $db = $database->getConnection();

    $subject = new Subject($db);
    $validator = new Validator();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['id'])) {
                $result = $subject->getById($_GET['id']);
                if (!$result) {
                    Response::notFound('Subject not found');
                }
                Response::success(['subject' => $result]);
            } else {
                $filters = [
                    'department_id' => $_GET['department_id'] ?? null,
                    'semester' => $_GET['semester'] ?? null,
                    'is_active' => isset($_GET['is_active']) ? (bool)$_GET['is_active'] : null
                ];
                $subjects = $subject->getAll($filters);
                Response::success(['subjects' => $subjects]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $validator->required('name', $data['name'] ?? '', 'Subject Name');
            $validator->required('code', $data['code'] ?? '', 'Subject Code');
            $validator->required('department_id', $data['department_id'] ?? '', 'Department');
            $validator->required('semester', $data['semester'] ?? '', 'Semester');

            if ($validator->hasErrors()) {
                Response::validationError($validator->getErrors());
            }

            if ($subject->codeExists($data['code'])) {
                Response::error('Subject code already exists', 400);
            }

            $subject->name = $data['name'];
            $subject->code = strtoupper($data['code']);
            $subject->department_id = $data['department_id'];
            $subject->semester = $data['semester'];
            $subject->credits = $data['credits'] ?? 3;
            $subject->description = $data['description'] ?? null;
            $subject->is_active = $data['is_active'] ?? true;

            if ($subject->create()) {
                Response::success([
                    'subject_id' => $subject->id
                ], 'Subject created successfully', 201);
            }

            Response::error('Failed to create subject');
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                Response::error('Subject ID is required', 400);
            }

            $existing = $subject->getById($data['id']);
            if (!$existing) {
                Response::notFound('Subject not found');
            }

            if (isset($data['code']) && $subject->codeExists($data['code'], $data['id'])) {
                Response::error('Subject code already exists', 400);
            }

            $subject->id = $data['id'];
            $subject->name = $data['name'] ?? $existing['name'];
            $subject->code = isset($data['code']) ? strtoupper($data['code']) : $existing['code'];
            $subject->department_id = $data['department_id'] ?? $existing['department_id'];
            $subject->semester = $data['semester'] ?? $existing['semester'];
            $subject->credits = $data['credits'] ?? $existing['credits'];
            $subject->description = $data['description'] ?? $existing['description'];
            $subject->is_active = $data['is_active'] ?? $existing['is_active'];

            if ($subject->update()) {
                Response::success([], 'Subject updated successfully');
            }

            Response::error('Failed to update subject');
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                Response::error('Subject ID is required', 400);
            }

            $subject->id = $data['id'];

            if ($subject->delete()) {
                Response::success([], 'Subject deleted successfully');
            }

            Response::error('Failed to delete subject');
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
