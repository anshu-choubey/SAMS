<?php
/**
 * App Settings Configuration API
 * Returns public settings for mobile app configuration
 * No authentication required for public settings
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/SettingsHelper.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    SettingsHelper::setDatabase($db);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $type = $_GET['type'] ?? 'all';

        switch ($type) {
            case 'attendance':
                // Attendance settings for marking attendance
                $settings = [
                    'gps_radius' => SettingsHelper::get('gps_proximity_radius', 50),
                    'face_confidence_threshold' => SettingsHelper::get('face_confidence_threshold', 75),
                    'enable_face_verification' => SettingsHelper::get('enable_face_verification', true),
                    'enable_gps_verification' => SettingsHelper::get('enable_gps_verification', true),
                    'minimum_threshold' => SettingsHelper::get('attendance_warning_threshold', 75),
                    'allow_late_attendance' => SettingsHelper::get('allow_late_attendance', true),
                    'late_threshold_minutes' => SettingsHelper::get('late_threshold_minutes', 15)
                ];
                Response::success($settings);
                break;

            case 'system':
                // System configuration for app
                $settings = [
                    'session_timeout' => SettingsHelper::get('session_lifetime', 604800),
                    'academic_year' => SettingsHelper::get('academic_year', '2025-26'),
                    'current_semester' => SettingsHelper::get('current_semester', 2),
                    'maintenance_mode' => SettingsHelper::get('maintenance_mode', false)
                ];
                Response::success($settings);
                break;

            case 'app':
                // App information and branding
                $settings = [
                    'app_name' => SettingsHelper::get('app_name', 'SAMS'),
                    'app_version' => SettingsHelper::get('app_version', '1.0.0'),
                    'institution_name' => SettingsHelper::get('institution_name', 'Your Institution'),
                    'institution_logo' => SettingsHelper::get('institution_logo', ''),
                    'support_email' => SettingsHelper::get('support_email', 'support@sams.edu')
                ];
                Response::success($settings);
                break;

            case 'all':
            default:
                // All public settings
                $config = [
                    'attendance' => [
                        'gps_radius' => SettingsHelper::get('gps_proximity_radius', 50),
                        'face_confidence_threshold' => SettingsHelper::get('face_confidence_threshold', 75),
                        'enable_face_verification' => SettingsHelper::get('enable_face_verification', true),
                        'enable_gps_verification' => SettingsHelper::get('enable_gps_verification', true),
                        'minimum_threshold' => SettingsHelper::get('attendance_warning_threshold', 75),
                        'allow_late_attendance' => SettingsHelper::get('allow_late_attendance', true),
                        'late_threshold_minutes' => SettingsHelper::get('late_threshold_minutes', 15)
                    ],
                    'system' => [
                        'session_timeout' => SettingsHelper::get('session_lifetime', 604800),
                        'academic_year' => SettingsHelper::get('academic_year', '2025-26'),
                        'current_semester' => SettingsHelper::get('current_semester', 2),
                        'maintenance_mode' => SettingsHelper::get('maintenance_mode', false)
                    ],
                    'app_info' => [
                        'name' => SettingsHelper::get('app_name', 'SAMS'),
                        'version' => SettingsHelper::get('app_version', '1.0.0'),
                        'institution' => SettingsHelper::get('institution_name', 'Your Institution'),
                        'logo_url' => SettingsHelper::get('institution_logo', ''),
                        'support_email' => SettingsHelper::get('support_email', 'support@sams.edu')
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
?>
