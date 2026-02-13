<?php
require_once 'config/database.php';
require_once 'config/constants.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = "face_confidence_threshold"');
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo 'Current threshold in DB: ' . ($result ? $result['setting_value'] : 'NOT SET') . PHP_EOL;
    echo 'Constant value: ' . FACE_CONFIDENCE_THRESHOLD . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>