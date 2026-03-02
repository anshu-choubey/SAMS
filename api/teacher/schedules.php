<?php
/**
 * Schedules Management API (Admin Only)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Schedule.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

// Handle CORS
CORS::handle();

try {
    // Check authentication and get user info
    // Teachers can access their own schedules, admin can access all
    $user = Auth::user();
    
    $userRole = $user['role'];
    $userId = $user['id'];

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    $schedule = new Schedule($db);
    $validator = new Validator();

    // Handle different HTTP methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single schedule
                $result = $schedule->getById($_GET['id']);
                if (!$result) {
                    Response::notFound('Schedule not found');
                }
                // Teachers can only view their own schedules
                if ($userRole === 'teacher' && $result['teacher_id'] != $userId) {
                    Response::error('Access denied - can only view your own schedules', 403);
                }
                Response::success(['schedule' => $result]);
            } else {
                // Get all schedules with filters
                $filters = [
                    'teacher_id' => $_GET['teacher_id'] ?? null,
                    'department_id' => $_GET['department_id'] ?? null,
                    'day_of_week' => $_GET['day_of_week'] ?? null,
                    'is_active' => isset($_GET['is_active']) ? (bool)$_GET['is_active'] : null
                ];
                
                // If teacher role, filter to only their schedules
                if ($userRole === 'teacher') {
                    $filters['teacher_id'] = $userId;
                }
                
                $schedules = $schedule->getAll($filters);
                Response::success(['schedules' => $schedules]);
            }
            break;

        case 'POST':
            // Create new schedule - admin only
            if ($userRole !== 'admin') {
                Response::error('Only admin can create schedules', 403);
            }
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate
            $validator->required('assignment_id', $data['assignment_id'] ?? '', 'Assignment');
            $validator->required('day_of_week', $data['day_of_week'] ?? '', 'Day of Week');
            $validator->required('start_time', $data['start_time'] ?? '', 'Start Time');
            $validator->required('end_time', $data['end_time'] ?? '', 'End Time');
            $validator->enum('day_of_week', $data['day_of_week'] ?? '', 
                ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'Day of Week');

            if ($validator->hasErrors()) {
                Response::validationError($validator->getErrors());
            }

            // Validate time
            if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                Response::error('End time must be after start time', 400);
            }

            $schedule->assignment_id = $data['assignment_id'];
            $schedule->day_of_week = $data['day_of_week'];
            $schedule->start_time = $data['start_time'];
            $schedule->end_time = $data['end_time'];
            $schedule->classroom = $data['classroom'] ?? null;
            $schedule->building = $data['building'] ?? null;
            $schedule->is_active = $data['is_active'] ?? true;

            $result = $schedule->create();

            if ($result['success']) {
                Response::success([
                    'schedule_id' => $result['id']
                ], 'Schedule created successfully', 201);
            }

            Response::error($result['message'] ?? 'Failed to create schedule');
            break;

        case 'PUT':
            // Update schedule - admin only
            if ($userRole !== 'admin') {
                Response::error('Only admin can update schedules', 403);
            }
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                Response::error('Schedule ID is required', 400);
            }

            $existing = $schedule->getById($data['id']);
            if (!$existing) {
                Response::notFound('Schedule not found');
            }

            // Validate time if provided
            $startTime = $data['start_time'] ?? $existing['start_time'];
            $endTime = $data['end_time'] ?? $existing['end_time'];

            if (strtotime($startTime) >= strtotime($endTime)) {
                Response::error('End time must be after start time', 400);
            }

            $schedule->id = $data['id'];
            $schedule->assignment_id = $data['assignment_id'] ?? $existing['assignment_id'];
            $schedule->day_of_week = $data['day_of_week'] ?? $existing['day_of_week'];
            $schedule->start_time = $startTime;
            $schedule->end_time = $endTime;
            $schedule->classroom = $data['classroom'] ?? $existing['classroom'];
            $schedule->building = $data['building'] ?? $existing['building'];
            $schedule->is_active = $data['is_active'] ?? $existing['is_active'];

            $result = $schedule->update();

            if ($result['success']) {
                Response::success([], 'Schedule updated successfully');
            }

            Response::error($result['message'] ?? 'Failed to update schedule');
            break;

        case 'DELETE':
            // Delete schedule - admin only
            if ($userRole !== 'admin') {
                Response::error('Only admin can delete schedules', 403);
            }
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                Response::error('Schedule ID is required', 400);
            }

            $schedule->id = $data['id'];

            if ($schedule->delete()) {
                Response::success([], 'Schedule deleted successfully');
            }

            Response::error('Failed to delete schedule');
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
