<?php
/**
 * Add random interval system settings
 * Run: heroku run "php scripts/add_random_interval_settings.php"
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $settings = [
        ['attendance_random_intervals_enabled', 'true'],
        ['attendance_min_interval_minutes', '10'],
        ['attendance_max_interval_minutes', '25'],
        ['attendance_hide_timing_from_students', 'true'],
        ['attendance_auto_trigger_enabled', 'true'],
        ['attendance_response_window_minutes', '3'],
    ];
    
    foreach ($settings as $s) {
        $check = $db->prepare("SELECT id FROM system_settings WHERE `key` = ?");
        $check->execute([$s[0]]);
        if (!$check->fetch()) {
            $ins = $db->prepare("INSERT INTO system_settings (`key`, value) VALUES (?, ?)");
            $ins->execute([$s[0], $s[1]]);
            echo "Added: {$s[0]}={$s[1]}\n";
        } else {
            echo "Exists: {$s[0]}\n";
        }
    }
    
    echo "\nSettings complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
