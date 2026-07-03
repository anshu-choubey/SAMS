<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

$db = (new Database())->getConnection();

$db->exec("ALTER TABLE attendance_check_responses MODIFY COLUMN verification_status ENUM('success','gps_failed','face_failed','both_failed','late','pending_review') NOT NULL DEFAULT 'success'");
echo "Updated verification_status ENUM\n";

$stmt = $db->query("DESCRIBE attendance_check_responses");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " | " . $r['Type'] . "\n";
}
