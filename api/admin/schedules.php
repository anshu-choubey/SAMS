<?php
/**
 * Schedules API (Admin Only)
 * Manages class scheduling with conflict detection
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
    // Check authentication and role
    Auth::hasRole('admin');

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    $schedule = new Schedule($db);
    $validator = new Validator();

    // Handle different HTTP methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['teacher_id'])) {
                // Get schedules for specific teacher
                $schedules = $schedule->getByTeacherId($_GET['teacher_id']);
                Response::success(['schedules' => $schedules]);
            } elseif (isset($_GET['id'])) {
                // Get single schedule
                $result = $schedule->getById($_GET['id']);
                if (!$result) {
                    Response::notFound('Schedule not found');
                }
                Response::success(['schedule' => $result]);
            } else {
                // Get all schedules
                $schedules = $schedule->getAll();
                Response::success(['schedules' => $schedules]);
            }
            break;

        case 'POST':
            // Create new schedule
            $data = json_decode(file_get_contents('php://input'), true);

            // Log incoming data for debugging
            error_log('Schedule POST data: ' . json_encode($data));

            // Validate required fields
            $validator->required('assignment_id', $data['assignment_id'] ?? '', 'Assignment');
            $validator->required('day_of_week', $data['day_of_week'] ?? '', 'Day');
            $validator->required('start_time', $data['start_time'] ?? '', 'Start Time');
            $validator->required('end_time', $data['end_time'] ?? '', 'End Time');

            if ($validator->hasErrors()) {
                Response::validationError($validator->getErrors());
            }

            // Validate time format
            if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $data['start_time'])) {
                Response::error('Invalid start time format. Use HH:MM or HH:MM:SS', 400);
            }
            if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $data['end_time'])) {
                Response::error('Invalid end time format. Use HH:MM or HH:MM:SS', 400);
            }

            // Normalize times to add seconds if not present
            if (strlen($data['start_time']) === 5) {
                $data['start_time'] .= ':00';
            }
            if (strlen($data['end_time']) === 5) {
                $data['end_time'] .= ':00';
            }

            // Validate end time > start time
            if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
                Response::error('End time must be after start time', 400);
            }

            // Verify assignment exists
            try {
                $assignmentStmt = $db->prepare("SELECT id, teacher_id FROM teacher_assignments WHERE id = :id");
                $assignmentStmt->bindParam(':id', $data['assignment_id']);
                $assignmentStmt->execute();
                $assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$assignment) {
                    Response::error('Selected assignment does not exist', 400);
                }
            } catch (Exception $e) {
                error_log('Assignment lookup error: ' . $e->getMessage());
                Response::error('Failed to verify assignment', 400);
            }

            // Set schedule properties
            $schedule->assignment_id = $data['assignment_id'];
            $schedule->day_of_week = $data['day_of_week'];
            $schedule->start_time = $data['start_time'];
            $schedule->end_time = $data['end_time'];
            $schedule->classroom = $data['classroom'] ?? null;
            $schedule->building = $data['building'] ?? null;
            $schedule->is_active = 1;

            // Create schedule
            try {
                $result = $schedule->create();
                if ($result['success']) {
                    Response::success([], 'Schedule created successfully', 201);
                } else {
                    Response::error($result['message'] ?? 'Failed to create schedule', 400);
                }
            } catch (Exception $e) {
                error_log('Schedule creation error: ' . $e->getMessage());
                Response::error('Failed to create schedule: ' . $e->getMessage(), 500);
            }
            break;

        case 'PUT':
            // Update schedule
            $data = json_decode(file_get_contents('php://input'), true);

            $scheduleId = $_GET['id'] ?? $data['id'] ?? null;
            if (!$scheduleId) {
                Response::error('Schedule ID is required', 400);
            }

            $schedule->id = $scheduleId;
            $result = $schedule->getById($schedule->id);
            if (!$result) {
                Response::notFound('Schedule not found');
            }

            // Update fields
            if (isset($data['assignment_id'])) $schedule->assignment_id = $data['assignment_id'];
            if (isset($data['day_of_week'])) $schedule->day_of_week = $data['day_of_week'];
            if (isset($data['start_time'])) {
                $data['start_time'] = strlen($data['start_time']) === 5 ? $data['start_time'] . ':00' : $data['start_time'];
                $schedule->start_time = $data['start_time'];
            }
            if (isset($data['end_time'])) {
                $data['end_time'] = strlen($data['end_time']) === 5 ? $data['end_time'] . ':00' : $data['end_time'];
                $schedule->end_time = $data['end_time'];
            }
            if (isset($data['classroom'])) $schedule->classroom = $data['classroom'];
            if (isset($data['building'])) $schedule->building = $data['building'];
            if (isset($data['is_active'])) $schedule->is_active = $data['is_active'];

            // Update schedule
            if ($schedule->update()) {
                Response::success([], 'Schedule updated successfully');
            } else {
                Response::error('Failed to update schedule', 400);
            }
            break;

        case 'DELETE':
            // Delete schedule
            $data = json_decode(file_get_contents('php://input'), true);
            $scheduleId = $_GET['id'] ?? $data['id'] ?? null;
            
            if (!$scheduleId) {
                Response::error('Schedule ID is required', 400);
            }

            $schedule->id = $scheduleId;
            $result = $schedule->getById($schedule->id);
            if (!$result) {
                Response::notFound('Schedule not found');
            }

            if ($schedule->delete()) {
                Response::success([], 'Schedule deleted successfully');
            } else {
                Response::error('Failed to delete schedule', 400);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Schedules API Error: ' . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
?>
