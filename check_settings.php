<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

$db = (new Database())->getConnection();
$stmt = $db->query("SELECT * FROM system_settings ORDER BY id");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['key'] . " = " . $r['value'] . " (type: " . ($r['type'] ?: 'null') . ")\n";
}
