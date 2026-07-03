<?php
/**
 * Admin Attendance Settings API
 * Configure multi-check attendance and face verification
 * 
 * SIMPLIFIED: Removed unused continuous monitoring settings
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
        // Get current settings
        $settings = [];
        
        $query = "SELECT `key`, value FROM system_settings 
                  WHERE `key` LIKE 'attendance_%' 
                     OR `key` IN ('liveness_detection_enabled', 'face_confidence_threshold', 'gps_proximity_radius')
                  ORDER BY `key`";
        $stmt = $db->query($query);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        
        $defaults = [
            // Multi-check attendance settings
            'attendance_multi_check_enabled' => 'true',
            'attendance_default_total_checks' => '3',
            'attendance_random_intervals_enabled' => 'true',
            'attendance_min_check_interval' => '10',
            'attendance_max_check_interval' => '25',
            'attendance_first_check_delay' => '10',
            'attendance_check_window_minutes' => '3',
            'attendance_hide_timing_from_students' => 'true',
            'attendance_auto_trigger_enabled' => 'true',
            'attendance_auto_schedule_enabled' => 'true',
            'attendance_response_window_minutes' => '3',
            
            // Face verification settings
            'liveness_detection_enabled' => 'true',
            'face_confidence_threshold' => '75',
            
            // GPS settings
            'gps_proximity_radius' => '50'
        ];
        
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
        
        Response::success($settings, 'Settings retrieved successfully');
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update settings
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data)) {
            Response::error('No settings provided', 400);
        }
        
        $validKeys = [
            // Multi-check attendance
            'attendance_multi_check_enabled',
            'attendance_default_total_checks',
            'attendance_random_intervals_enabled',
            'attendance_min_check_interval',
            'attendance_max_check_interval',
            'attendance_first_check_delay',
            'attendance_check_window_minutes',
            'attendance_hide_timing_from_students',
            'attendance_auto_trigger_enabled',
            'attendance_auto_schedule_enabled',
            'attendance_response_window_minutes',
            
            // Face verification
            'liveness_detection_enabled',
            'face_confidence_threshold',
            
            // GPS
            'gps_proximity_radius'
        ];
        
        $booleanKeys = [
            'attendance_multi_check_enabled', 'attendance_random_intervals_enabled',
            'attendance_hide_timing_from_students', 'attendance_auto_trigger_enabled',
            'attendance_auto_schedule_enabled', 'liveness_detection_enabled'
        ];
        $integerKeys = [
            'attendance_default_total_checks', 'attendance_min_check_interval',
            'attendance_max_check_interval', 'attendance_first_check_delay',
            'attendance_check_window_minutes', 'attendance_response_window_minutes',
            'face_confidence_threshold', 'gps_proximity_radius'
        ];
        
        $updated = 0;
        
        foreach ($data as $key => $value) {
            if (!in_array($key, $validKeys)) {
                continue;
            }
            
            $type = in_array($key, $booleanKeys) ? 'boolean' : (in_array($key, $integerKeys) ? 'integer' : 'string');
            
            $checkQuery = "SELECT id FROM system_settings WHERE `key` = :key";
            $stmt = $db->prepare($checkQuery);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $updateQuery = "UPDATE system_settings SET value = :value, type = :type WHERE `key` = :key";
                $stmt = $db->prepare($updateQuery);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':type', $type);
                $stmt->bindParam(':key', $key);
                $stmt->execute();
            } else {
                $insertQuery = "INSERT INTO system_settings (`key`, value, type) VALUES (:key, :value, :type)";
                $stmt = $db->prepare($insertQuery);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':type', $type);
                $stmt->execute();
            }
            
            $updated++;
        }
        
        Response::success([
            'updated_count' => $updated,
            'settings' => $data
        ], "Successfully updated $updated settings");
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log('Attendance settings error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
