<?php
/**
 * App Settings Configuration API
 * Returns public settings for mobile app configuration
 * No authentication required for public settings
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $type = $_GET['type'] ?? 'all';

        switch ($type) {
            case 'attendance':
                // Attendance settings for marking attendance
                $settings = [
                    'min_attendance_threshold' => getSetting($db, 'min_attendance_threshold', 75),
                    'gps_proximity_radius' => getSetting($db, 'gps_proximity_radius', 50),
                    'face_confidence_threshold' => getSetting($db, 'face_confidence_threshold', 85),
                    'enable_liveness_detection' => getSetting($db, 'enable_liveness_detection', true)
                ];
                Response::success($settings);
                break;

            case 'academic':
            case 'system':
                // System/Academic settings
                $settings = [
                    'session_timeout' => 3600,
                    'academic_year' => getSetting($db, 'academic_year', 2026),
                    'semester_duration_weeks' => getSetting($db, 'semester_duration_weeks', 16),
                    'current_semester' => 1,
                    'maintenance_mode' => false
                ];
                Response::success($settings);
                break;

            case 'app':
            case 'app_info':
                // App information
                $appInfo = [
                    'name' => 'SAMS',
                    'version' => '1.0.0',
                    'institution' => 'Your Institution',
                    'logo_url' => '',
                    'support_email' => 'support@sams.local'
                ];
                Response::success($appInfo);
                break;

            case 'all':
            default:
                // All public settings - matches AppSettingsConfig in Android
                $config = [
                    'attendance' => [
                        'min_attendance_threshold' => getSetting($db, 'min_attendance_threshold', 75),
                        'gps_proximity_radius' => getSetting($db, 'gps_proximity_radius', 50),
                        'face_confidence_threshold' => getSetting($db, 'face_confidence_threshold', 85),
                        'enable_liveness_detection' => getSetting($db, 'enable_liveness_detection', true)
                    ],
                    'system' => [
                        'session_timeout' => 3600,
                        'academic_year' => getSetting($db, 'academic_year', 2026),
                        'semester_duration_weeks' => getSetting($db, 'semester_duration_weeks', 16),
                        'current_semester' => 1,
                        'maintenance_mode' => false
                    ],
                    'app_info' => [
                        'name' => 'SAMS',
                        'version' => '1.0.0',
                        'institution' => 'Your Institution',
                        'logo_url' => '',
                        'support_email' => 'support@sams.local'
                    ]
                ];
                Response::success($config);
                break;
        }
    }

} catch (Exception $e) {
    error_log("Settings API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve settings',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get setting value from database with default fallback
 */
function getSetting($db, $key, $default = null) {
    try {
        $query = "SELECT `value`, `type` FROM system_settings WHERE `key` = :key LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['value'];
        
        // Cast based on type
        if ($result['type'] === 'integer') {
            return intval($value);
        } elseif ($result['type'] === 'float') {
            return floatval($value);
        } elseif ($result['type'] === 'boolean') {
            return $value === '1' || strtolower($value) === 'true';
        }
        
        return $value;
    } catch (Exception $e) {
        error_log("Error getting setting $key: " . $e->getMessage());
        return $default;
    }
}
?>
