<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

$db = (new Database())->getConnection();

$integerSettings = [
    'attendance_default_total_checks',
    'attendance_first_check_delay',
    'attendance_min_check_interval',
    'attendance_max_check_interval',
    'attendance_check_window_minutes',
    'attendance_min_interval_minutes',
    'attendance_max_interval_minutes',
    'attendance_response_window_minutes',
    'liveness_min_score',
    'continuous_face_detection_interval',
];

$booleanSettings = [
    'attendance_multi_check_enabled',
    'attendance_auto_schedule_enabled',
    'attendance_random_intervals_enabled',
    'attendance_hide_timing_from_students',
    'attendance_auto_trigger_enabled',
    'continuous_monitoring_enabled',
    'continuous_monitoring_required',
    'continuous_auto_response_enabled',
    'liveness_detection_enabled',
];

foreach ($integerSettings as $key) {
    $stmt = $db->prepare("UPDATE system_settings SET type = 'integer' WHERE `key` = :k");
    $stmt->execute([':k' => $key]);
    echo "Set $key -> integer\n";
}

foreach ($booleanSettings as $key) {
    $stmt = $db->prepare("UPDATE system_settings SET type = 'boolean' WHERE `key` = :k");
    $stmt->execute([':k' => $key]);
    echo "Set $key -> boolean\n";
}

echo "\nDone! Verifying:\n";
$stmt = $db->query("SELECT `key`, value, type FROM system_settings WHERE `key` LIKE 'attendance%' OR `key` LIKE 'liveness%' OR `key` LIKE 'continuous%' ORDER BY `key`");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['key'] . " = " . $r['value'] . " (type: " . $r['type'] . ")\n";
}
