<?php
/**
 * Admin Attendance Settings API
 * Configure multi-check attendance and continuous monitoring
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
        
        $query = "SELECT setting_key, setting_value FROM system_settings 
                  WHERE setting_key LIKE 'attendance_%' OR setting_key LIKE 'continuous_%'
                  ORDER BY setting_key";
        $stmt = $db->query($query);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Default values if not set
        $defaults = [
            'attendance_multi_check_enabled' => 'true',
            'attendance_default_total_checks' => '3',
            'attendance_auto_schedule_enabled' => 'true',
            'attendance_first_check_delay' => '20',
            'attendance_min_check_interval' => '15',
            'attendance_max_check_interval' => '30',
            'attendance_check_window_minutes' => '5',
            'continuous_monitoring_enabled' => 'true',
            'continuous_monitoring_required' => 'false',
            'continuous_auto_response_enabled' => 'true',
            'continuous_face_detection_interval' => '30',
            'liveness_detection_enabled' => 'true',
            'liveness_min_score' => '60',
            'face_confidence_threshold' => '75'
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
        
        // Valid setting keys
        $validKeys = [
            'attendance_multi_check_enabled',
            'attendance_default_total_checks',
            'attendance_auto_schedule_enabled',
            'attendance_first_check_delay',
            'attendance_min_check_interval',
            'attendance_max_check_interval',
            'attendance_check_window_minutes',
            'continuous_monitoring_enabled',
            'continuous_monitoring_required',
            'continuous_auto_response_enabled',
            'continuous_face_detection_interval',
            'liveness_detection_enabled',
            'liveness_min_score',
            'face_confidence_threshold'
        ];
        
        $updated = 0;
        
        foreach ($data as $key => $value) {
            if (!in_array($key, $validKeys)) {
                continue;
            }
            
            // Check if setting exists
            $checkQuery = "SELECT id FROM system_settings WHERE setting_key = :key";
            $stmt = $db->prepare($checkQuery);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                // Update existing
                $updateQuery = "UPDATE system_settings SET setting_value = :value WHERE setting_key = :key";
                $stmt = $db->prepare($updateQuery);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':key', $key);
                $stmt->execute();
            } else {
                // Insert new
                $insertQuery = "INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :value)";
                $stmt = $db->prepare($insertQuery);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
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
