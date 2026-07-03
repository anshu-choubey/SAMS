<?php
/**
 * Insert Default Attendance Settings
 */

$host = 'gx97kbnhgjzh3efb.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
$port = '3306';
$database = 'a60382na4xjudzs6';
$username = 'ql8x6of7t4e8rou4';
$password = 'j7vh4q8e55ms7q10';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ Connected\n\n";
    
    $settings = [
        ['attendance_multi_check_enabled', 'true', 'Enable multi-check attendance by default'],
        ['attendance_default_total_checks', '2', 'Default number of attendance checks per class'],
        ['attendance_auto_schedule_enabled', 'true', 'Enable auto-scheduling of checks by default'],
        ['attendance_first_check_delay', '20', 'Minutes before first attendance check'],
        ['attendance_min_check_interval', '15', 'Minimum minutes between checks'],
        ['attendance_max_check_interval', '30', 'Maximum minutes between checks'],
        ['attendance_check_window_minutes', '5', 'Response window duration in minutes'],
        ['continuous_monitoring_enabled', 'true', 'Enable continuous monitoring feature'],
        ['continuous_monitoring_required', 'false', 'Require students to use continuous monitoring'],
        ['continuous_auto_response_enabled', 'true', 'Auto-respond to checks in continuous mode'],
        ['continuous_face_detection_interval', '30', 'Face detection interval in seconds'],
        ['liveness_detection_enabled', 'true', 'Enable liveness detection for face recognition'],
        ['liveness_min_score', '60', 'Minimum liveness score (0-100)'],
        ['face_confidence_threshold', '75', 'Minimum face confidence score (0-100)']
    ];
    
    $inserted = 0;
    $updated = 0;
    
    foreach ($settings as $setting) {
        list($key, $value, $description) = $setting;
        
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM system_settings WHERE `key` = ?");
        $stmt->execute([$key]);
        
        if ($stmt->fetch()) {
            // Update
            $stmt = $pdo->prepare("UPDATE system_settings SET value = ? WHERE `key` = ?");
            $stmt->execute([$value, $key]);
            echo "✅ Updated: $key = $value\n";
            $updated++;
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
            echo "✅ Inserted: $key = $value\n";
            $inserted++;
        }
    }
    
    echo "\n📊 Summary:\n";
    echo "Inserted: $inserted\n";
    echo "Updated: $updated\n";
    echo "\n✅ All settings configured!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
