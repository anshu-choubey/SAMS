<?php
/**
 * Teacher Assignments API (Admin Only)
 * Multi-branch teacher assignment management
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/TeacherAssignment.php';
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

    $assignment = new TeacherAssignment($db);
    $validator = new Validator();

    // Handle different HTTP methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['teacher_id'])) {
                // Get assignments for specific teacher
                if (isset($_GET['grouped']) && $_GET['grouped'] === 'true') {
                    $assignments = $assignment->getByTeacherIdGrouped($_GET['teacher_id']);
                } else {
                    $assignments = $assignment->getByTeacherId($_GET['teacher_id']);
                }
                Response::success(['assignments' => $assignments]);
            } elseif (isset($_GET['id'])) {
                // Get single assignment
                $result = $assignment->getById($_GET['id']);
                if (!$result) {
                    Response::notFound('Assignment not found');
                }
                Response::success(['assignment' => $result]);
            } else {
                // Get all assignments with filters
                $filters = [
                    'teacher_id' => $_GET['teacher_id'] ?? null,
                    'department_id' => $_GET['department_id'] ?? null,
                    'subject_id' => $_GET['subject_id'] ?? null,
                    'is_active' => isset($_GET['is_active']) ? (bool)$_GET['is_active'] : null
                ];
                $assignments = $assignment->getAll($filters);
                Response::success(['assignments' => $assignments]);
            }
            break;

        case 'POST':
            // Create new assignment(s)
            $data = json_decode(file_get_contents('php://input'), true);

            // Check if bulk assignment
            if (isset($data['assignments']) && is_array($data['assignments'])) {
                // Bulk assignment
                if ($assignment->createBulk($data['assignments'])) {
                    Response::success([], 'Assignments created successfully', 201);
                }
                Response::error('Failed to create assignments');
            } else {
                // Single assignment
                $validator->required('teacher_id', $data['teacher_id'] ?? '', 'Teacher');
                $validator->required('subject_id', $data['subject_id'] ?? '', 'Subject');
                $validator->required('department_id', $data['department_id'] ?? '', 'Department');
                $validator->required('academic_year', $data['academic_year'] ?? '', 'Academic Year');
                $validator->required('semester', $data['semester'] ?? '', 'Semester');

                if ($validator->hasErrors()) {
                    Response::validationError($validator->getErrors());
                }

                // Check for duplicate
                if ($assignment->assignmentExists(
                    $data['teacher_id'],
                    $data['subject_id'],
                    $data['department_id'],
                    $data['section'] ?? null,
                    $data['semester'],
                    $data['academic_year']
                )) {
                    Response::error('This assignment already exists', 400);
                }

                $assignment->teacher_id = $data['teacher_id'];
                $assignment->subject_id = $data['subject_id'];
                $assignment->department_id = $data['department_id'];
                $assignment->section = $data['section'] ?? null;
                $assignment->academic_year = $data['academic_year'];
                $assignment->semester = $data['semester'];
                $assignment->is_active = $data['is_active'] ?? true;

                if ($assignment->create()) {
                    Response::success([
                        'assignment_id' => $assignment->id
                    ], 'Assignment created successfully', 201);
                }

                Response::error('Failed to create assignment');
            }
            break;

        case 'PUT':
            // Update assignment
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                Response::error('Assignment ID is required', 400);
            }

            $existing = $assignment->getById($data['id']);
            if (!$existing) {
                Response::notFound('Assignment not found');
            }

            $assignment->id = $data['id'];
            $assignment->subject_id = $data['subject_id'] ?? $existing['subject_id'];
            $assignment->department_id = $data['department_id'] ?? $existing['department_id'];
            $assignment->section = $data['section'] ?? $existing['section'];
            $assignment->academic_year = $data['academic_year'] ?? $existing['academic_year'];
            $assignment->semester = $data['semester'] ?? $existing['semester'];
            $assignment->is_active = $data['is_active'] ?? $existing['is_active'];

            if ($assignment->update()) {
                Response::success([], 'Assignment updated successfully');
            }

            Response::error('Failed to update assignment');
            break;

        case 'DELETE':
            // Delete assignment
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                Response::error('Assignment ID is required', 400);
            }

            $assignment->id = $data['id'];

            if ($assignment->delete()) {
                Response::success([], 'Assignment deleted successfully');
            }

            Response::error('Failed to delete assignment');
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
