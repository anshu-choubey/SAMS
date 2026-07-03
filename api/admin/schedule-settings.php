<?php
/**
 * Admin Schedule Settings API
 * Manage per-schedule interval and timing settings
 * 
 * GET /api/admin/schedule-settings.php - List all schedules with settings
 * GET /api/admin/schedule-settings.php?id=123 - Get specific schedule settings
 * PUT /api/admin/schedule-settings.php - Update schedule settings
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

// Handle CORS
CORS::handle();

try {
    // Check authentication and admin role
    $user = Auth::user();
    
    if (!$user) {
        Response::unauthorized('Please login to continue');
    }
    
    if ($user['role'] !== 'admin') {
        Response::error('Access restricted to administrators only', 403);
    }

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $scheduleId = $_GET['id'] ?? null;
        
        if ($scheduleId) {
            // Get specific schedule with settings
            $query = "SELECT 
                        sc.id,
                        sc.day_of_week,
                        TIME_FORMAT(sc.start_time, '%H:%i') as start_time,
                        TIME_FORMAT(sc.end_time, '%H:%i') as end_time,
                        sc.classroom,
                        sc.building,
                        sc.is_active,
                        sc.total_checks,
                        sc.min_interval_minutes,
                        sc.max_interval_minutes,
                        sc.response_window_minutes,
                        sc.hide_timing_from_students,
                        sc.random_intervals_enabled,
                        sc.auto_trigger_enabled,
                        sc.duration_minutes,
                        sub.name as subject_name,
                        sub.code as subject_code,
                        u.full_name as teacher_name,
                        ta.semester,
                        ta.section,
                        d.name as department_name
                      FROM schedules sc
                      JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                      JOIN subjects sub ON ta.subject_id = sub.id
                      JOIN teachers t ON ta.teacher_id = t.id
                      JOIN users u ON t.user_id = u.id
                      JOIN departments d ON ta.department_id = d.id
                      WHERE sc.id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $scheduleId);
            $stmt->execute();
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$schedule) {
                Response::error('Schedule not found', 404);
            }
            
            Response::success([
                'schedule' => formatSchedule($schedule)
            ], 'Schedule settings retrieved');
            
        } else {
            // List all schedules with settings
            $departmentId = $_GET['department_id'] ?? null;
            $dayOfWeek = $_GET['day_of_week'] ?? null;
            
            $query = "SELECT 
                        sc.id,
                        sc.day_of_week,
                        TIME_FORMAT(sc.start_time, '%H:%i') as start_time,
                        TIME_FORMAT(sc.end_time, '%H:%i') as end_time,
                        sc.classroom,
                        sc.building,
                        sc.is_active,
                        sc.total_checks,
                        sc.min_interval_minutes,
                        sc.max_interval_minutes,
                        sc.response_window_minutes,
                        sc.hide_timing_from_students,
                        sc.random_intervals_enabled,
                        sc.auto_trigger_enabled,
                        sc.duration_minutes,
                        sub.name as subject_name,
                        sub.code as subject_code,
                        u.full_name as teacher_name,
                        ta.semester,
                        ta.section,
                        d.name as department_name,
                        d.id as department_id
                      FROM schedules sc
                      JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                      JOIN subjects sub ON ta.subject_id = sub.id
                      JOIN teachers t ON ta.teacher_id = t.id
                      JOIN users u ON t.user_id = u.id
                      JOIN departments d ON ta.department_id = d.id
                      WHERE sc.is_active = TRUE";
            
            $params = [];
            
            if ($departmentId) {
                $query .= " AND d.id = :department_id";
                $params[':department_id'] = $departmentId;
            }
            
            if ($dayOfWeek) {
                $query .= " AND sc.day_of_week = :day_of_week";
                $params[':day_of_week'] = $dayOfWeek;
            }
            
            $query .= " ORDER BY sc.day_of_week, sc.start_time";
            
            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by day
            $grouped = [];
            foreach ($schedules as $schedule) {
                $day = $schedule['day_of_week'];
                if (!isset($grouped[$day])) {
                    $grouped[$day] = [];
                }
                $grouped[$day][] = formatSchedule($schedule);
            }
            
            Response::success([
                'schedules' => array_map('formatSchedule', $schedules),
                'grouped_by_day' => $grouped,
                'total' => count($schedules)
            ], 'Schedule settings retrieved');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update schedule settings
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['schedule_id'])) {
            Response::error('Schedule ID is required', 400);
        }
        
        // Validate schedule exists
        $checkQuery = "SELECT id FROM schedules WHERE id = :id";
        $stmt = $db->prepare($checkQuery);
        $stmt->bindParam(':id', $data['schedule_id']);
        $stmt->execute();
        if (!$stmt->fetch()) {
            Response::error('Schedule not found', 404);
        }
        
        // Build update query dynamically
        $updates = [];
        $params = [':id' => $data['schedule_id']];
        
        $allowedFields = [
            'total_checks' => 'integer',
            'min_interval_minutes' => 'integer',
            'max_interval_minutes' => 'integer',
            'response_window_minutes' => 'integer',
            'hide_timing_from_students' => 'boolean',
            'random_intervals_enabled' => 'boolean',
            'auto_trigger_enabled' => 'boolean',
            'duration_minutes' => 'integer',
            'classroom' => 'string',
            'building' => 'string',
            'start_time' => 'time',
            'end_time' => 'time'
        ];
        
        foreach ($allowedFields as $field => $type) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                
                switch ($type) {
                    case 'integer':
                        $params[":$field"] = (int)$data[$field];
                        break;
                    case 'boolean':
                        $params[":$field"] = $data[$field] ? 1 : 0;
                        break;
                    case 'time':
                        $params[":$field"] = $data[$field];
                        break;
                    default:
                        $params[":$field"] = $data[$field];
                }
            }
        }
        
        if (empty($updates)) {
            Response::error('No valid fields to update', 400);
        }
        
        $updateQuery = "UPDATE schedules SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        Response::success([
            'schedule_id' => (int)$data['schedule_id'],
            'updated_fields' => array_keys(array_intersect_key($data, $allowedFields))
        ], 'Schedule settings updated successfully');
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log('Schedule settings error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function formatSchedule($schedule) {
    return [
        'id' => (int)$schedule['id'],
        'day_of_week' => $schedule['day_of_week'],
        'start_time' => $schedule['start_time'],
        'end_time' => $schedule['end_time'],
        'classroom' => $schedule['classroom'],
        'building' => $schedule['building'],
        'is_active' => (bool)($schedule['is_active'] ?? true),
        'subject_name' => $schedule['subject_name'],
        'subject_code' => $schedule['subject_code'],
        'teacher_name' => $schedule['teacher_name'],
        'semester' => (int)($schedule['semester'] ?? 1),
        'section' => $schedule['section'],
        'department_name' => $schedule['department_name'] ?? null,
        'department_id' => (int)($schedule['department_id'] ?? 0),
        // Interval settings
        'interval_settings' => [
            'total_checks' => (int)($schedule['total_checks'] ?? 3),
            'min_interval_minutes' => (int)($schedule['min_interval_minutes'] ?? 10),
            'max_interval_minutes' => (int)($schedule['max_interval_minutes'] ?? 25),
            'response_window_minutes' => (int)($schedule['response_window_minutes'] ?? 3),
            'hide_timing_from_students' => (bool)($schedule['hide_timing_from_students'] ?? true),
            'random_intervals_enabled' => (bool)($schedule['random_intervals_enabled'] ?? true),
            'auto_trigger_enabled' => (bool)($schedule['auto_trigger_enabled'] ?? true),
            'duration_minutes' => (int)($schedule['duration_minutes'] ?? 60)
        ]
    ];
}
?>
