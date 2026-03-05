<?php
/**
 * Migration: Add attendance and face recognition settings
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Update system_settings table structure if needed
    $alterQuery = "ALTER TABLE system_settings 
                   ADD COLUMN IF NOT EXISTS `category` VARCHAR(50) AFTER `description`,
                   ADD COLUMN IF NOT EXISTS `validation_rule` VARCHAR(100) AFTER `category`,
                   ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    
    try {
        $db->exec($alterQuery);
        echo "[✓] Updated system_settings table structure\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            throw $e;
        }
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
            'value' => '85',
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
            $insertQuery = "INSERT INTO system_settings (`key`, `value`, `type`, `description`, `category`, `validation_rule`, `created_at`)
                           VALUES (:key, :value, :type, :description, :category, :validation_rule, NOW())";
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
