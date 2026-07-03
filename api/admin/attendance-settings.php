<?php
/**
 * Admin Attendance Settings API
 * Configure attendance checks, face verification, and GPS
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

try {
    $user = Auth::user();
    if (!$user) { Response::unauthorized('Please login to continue'); }
    if ($user['role'] !== 'admin') { Response::error('Access restricted to administrators only', 403); }

    $database = new Database();
    $db = $database->getConnection();

    $settingsConfig = [
        'attendance_multi_check_enabled'    => ['type' => 'boolean', 'default' => 'true',  'label' => 'Enable multi-check attendance'],
        'attendance_default_total_checks'   => ['type' => 'integer', 'default' => '2',     'label' => 'Number of checks per class'],
        'attendance_random_intervals_enabled' => ['type' => 'boolean', 'default' => 'true', 'label' => 'Random interval timing'],
        'attendance_min_check_interval'     => ['type' => 'integer', 'default' => '10',    'label' => 'Min minutes between checks'],
        'attendance_max_check_interval'     => ['type' => 'integer', 'default' => '25',    'label' => 'Max minutes between checks'],
        'attendance_check_window_minutes'   => ['type' => 'integer', 'default' => '3',     'label' => 'Response window (minutes)'],
        'attendance_hide_timing_from_students' => ['type' => 'boolean', 'default' => 'true', 'label' => 'Hide check times from students'],
        'face_confidence_threshold'         => ['type' => 'integer', 'default' => '75',    'label' => 'Face match % required'],
        'liveness_detection_enabled'        => ['type' => 'boolean', 'default' => 'true',  'label' => 'Enable liveness detection'],
        'gps_proximity_radius'              => ['type' => 'integer', 'default' => '50',    'label' => 'GPS radius (meters)'],
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $keys = array_keys($settingsConfig);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("SELECT `key`, value FROM system_settings WHERE `key` IN ($placeholders)");
        $stmt->execute($keys);
        
        $dbValues = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbValues[$row['key']] = $row['value'];
        }
        
        $settings = [];
        foreach ($settingsConfig as $key => $config) {
            $settings[$key] = [
                'value' => $dbValues[$key] ?? $config['default'],
                'label' => $config['label'],
                'type'  => $config['type']
            ];
        }
        
        Response::success($settings, 'Settings retrieved successfully');
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) { Response::error('No settings provided', 400); }
        
        $updated = 0;
        foreach ($data as $key => $value) {
            if (!isset($settingsConfig[$key])) { continue; }
            
            $type = $settingsConfig[$key]['type'];
            
            $stmt = $db->prepare("SELECT id FROM system_settings WHERE `key` = :key");
            $stmt->execute([':key' => $key]);
            
            if ($stmt->fetch()) {
                $stmt = $db->prepare("UPDATE system_settings SET value = :value, type = :type WHERE `key` = :key");
            } else {
                $stmt = $db->prepare("INSERT INTO system_settings (`key`, value, type) VALUES (:key, :value, :type)");
            }
            $stmt->execute([':key' => $key, ':value' => $value, ':type' => $type]);
            $updated++;
        }
        
        Response::success(['updated_count' => $updated], "Updated $updated settings");
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log('Attendance settings error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
