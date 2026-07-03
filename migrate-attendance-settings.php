<?php
/**
 * Migration: Add attendance and face recognition settings
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check which columns already exist and add missing ones
    $columnsQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'system_settings' AND TABLE_SCHEMA = DATABASE()";
    $columnsStmt = $db->query($columnsQuery);
    $existingColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Rename setting_key to key if needed
    if (in_array('setting_key', $existingColumns) && !in_array('key', $existingColumns)) {
        $db->exec("ALTER TABLE system_settings CHANGE COLUMN `setting_key` `key` VARCHAR(100)");
        echo "[✓] Renamed setting_key to key\n";
    }
    
    // Rename setting_value to value if needed
    if (in_array('setting_value', $existingColumns) && !in_array('value', $existingColumns)) {
        $db->exec("ALTER TABLE system_settings CHANGE COLUMN `setting_value` `value` TEXT");
        echo "[✓] Renamed setting_value to value\n";
    }
    
    // Rename setting_type to type if needed
    if (in_array('setting_type', $existingColumns) && !in_array('type', $existingColumns)) {
        // First need to change the enum values
        $db->exec("ALTER TABLE system_settings MODIFY COLUMN `setting_type` VARCHAR(20)");
        $db->exec("ALTER TABLE system_settings CHANGE COLUMN `setting_type` `type` VARCHAR(20)");
        echo "[✓] Renamed setting_type to type\n";
    }
    
    // Add category column if missing
    if (!in_array('category', $existingColumns)) {
        $db->exec("ALTER TABLE system_settings ADD COLUMN `category` VARCHAR(50) AFTER `description`");
        echo "[✓] Added category column\n";
    }
    
    // Add validation_rule column if missing
    if (!in_array('validation_rule', $existingColumns)) {
        $db->exec("ALTER TABLE system_settings ADD COLUMN `validation_rule` VARCHAR(100) AFTER `category`");
        echo "[✓] Added validation_rule column\n";
    }
    
    // Update updated_at column if needed
    if (!in_array('updated_at', $existingColumns)) {
        $db->exec("ALTER TABLE system_settings ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "[✓] Added updated_at column\n";
    } else {
        // Ensure updated_at has the right definition
        $db->exec("ALTER TABLE system_settings MODIFY COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // Define settings to create or update
    $settings = [
        // Attendance Settings
        [
            'key' => 'min_attendance_threshold',
            'value' => '75',
            'type' => 'integer',
            'description' => 'Minimum attendance percentage for warnings (0-100)',
            'category' => 'Attendance',
            'validation_rule' => 'min:0|max:100'
        ],
        [
            'key' => 'gps_proximity_radius',
            'value' => '50',
            'type' => 'integer',
            'description' => 'Maximum distance in meters for GPS attendance verification',
            'category' => 'Attendance',
            'validation_rule' => 'min:1|max:1000'
        ],
        
        // Face Recognition Settings
        [
            'key' => 'face_confidence_threshold',
            'value' => '60',
            'type' => 'integer',
            'description' => 'Minimum ML Kit face match confidence (0-100)',
            'category' => 'Face Recognition',
            'validation_rule' => 'min:0|max:100'
        ],
        [
            'key' => 'enable_liveness_detection',
            'value' => '1',
            'type' => 'boolean',
            'description' => 'Enable liveness detection to prevent spoofing with photos/videos',
            'category' => 'Face Recognition',
            'validation_rule' => ''
        ],
        
        // Academic Settings
        [
            'key' => 'academic_year',
            'value' => '2026',
            'type' => 'integer',
            'description' => 'Current academic year',
            'category' => 'Academic',
            'validation_rule' => 'min:2000|max:2100'
        ],
        [
            'key' => 'semester_duration_weeks',
            'value' => '16',
            'type' => 'integer',
            'description' => 'Duration of each semester in weeks',
            'category' => 'Academic',
            'validation_rule' => 'min:1|max:52'
        ],
    ];

    $inserted = 0;
    $updated = 0;

    foreach ($settings as $setting) {
        $checkQuery = "SELECT id FROM system_settings WHERE `key` = :key LIMIT 1";
        $stmt = $db->prepare($checkQuery);
        $stmt->bindParam(':key', $setting['key']);
        $stmt->execute();
        $exists = $stmt->fetch();

        if ($exists) {
            // Update
            $updateQuery = "UPDATE system_settings 
                           SET `value` = :value, 
                               `type` = :type, 
                               `description` = :description,
                               `category` = :category,
                               `validation_rule` = :validation_rule,
                               `updated_at` = NOW()
                           WHERE `key` = :key";
            $stmt = $db->prepare($updateQuery);
            $stmt->bindParam(':key', $setting['key']);
            $stmt->bindParam(':value', $setting['value']);
            $stmt->bindParam(':type', $setting['type']);
            $stmt->bindParam(':description', $setting['description']);
            $stmt->bindParam(':category', $setting['category']);
            $stmt->bindParam(':validation_rule', $setting['validation_rule']);
            $stmt->execute();
            $updated++;
            echo "[✓] Updated: {$setting['key']}\n";
        } else {
            // Insert
            $insertQuery = "INSERT INTO system_settings (`key`, `value`, `type`, `description`, `category`, `validation_rule`)
                           VALUES (:key, :value, :type, :description, :category, :validation_rule)";
            $stmt = $db->prepare($insertQuery);
            $stmt->bindParam(':key', $setting['key']);
            $stmt->bindParam(':value', $setting['value']);
            $stmt->bindParam(':type', $setting['type']);
            $stmt->bindParam(':description', $setting['description']);
            $stmt->bindParam(':category', $setting['category']);
            $stmt->bindParam(':validation_rule', $setting['validation_rule']);
            $stmt->execute();
            $inserted++;
            echo "[✓] Created: {$setting['key']}\n";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Migration completed',
        'changes' => [
            'inserted' => $inserted,
            'updated' => $updated
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed',
        'error' => $e->getMessage()
    ]);
}
?>
