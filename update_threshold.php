<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Update or insert the face confidence threshold
    $stmt = $conn->prepare('INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_editable) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute(['face_confidence_threshold', '75', 'number', 'Minimum face confidence score percentage', true]);

    echo 'Database updated successfully - face confidence threshold set to 75%' . PHP_EOL;

    // Verify the update
    $checkStmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = "face_confidence_threshold"');
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    echo 'Current threshold in DB: ' . ($result ? $result['setting_value'] : 'NOT FOUND') . PHP_EOL;

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>