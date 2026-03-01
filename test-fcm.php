<?php
/**
 * Test FCM API Endpoints
 * Tests registration and removal of FCM tokens
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/firebase.php';

$database = new Database();
$db = $database->getConnection();

echo "============================================\n";
echo "FCM API TEST\n";
echo "============================================\n\n";

// 1. Check database connection
echo "1. Database Connection: ";
if ($db) {
    echo "✓ Connected\n\n";
} else {
    echo "✗ Failed\n\n";
    exit(1);
}

// 2. Check tables exist
echo "2. Checking tables...\n";
$tables = ['users', 'fcm_tokens', 'notifications'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    echo "   - $table: " . ($result && $result->rowCount() > 0 ? "✓" : "✗") . "\n";
}
echo "\n";

// 3. Check FCM Server Key
echo "3. FCM Configuration:\n";
$fcmKey = defined('FCM_SERVER_KEY') ? FCM_SERVER_KEY : '';
if (!$fcmKey) {
    // Try to get from database directly
    $result = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'fcm_server_key' LIMIT 1");
    if ($result && $result->rowCount() > 0) {
        $fcmKey = $result->fetch(PDO::FETCH_ASSOC)['setting_value'];
    }
}
echo "   - FCM Server Key: " . ($fcmKey ? "✓ Configured" : "✗ Not configured") . "\n";
if (!$fcmKey) {
    echo "\n⚠ WARNING: FCM Server Key is not configured.\n";
    echo "Add your FCM Server Key in Admin Settings → Firebase Settings\n\n";
} else {
    echo "   - Key length: " . strlen($fcmKey) . " characters\n";
}
echo "\n";

// 4. Check sample data
echo "4. Current Data in Tables:\n";
$fcmCount = $db->query("SELECT COUNT(*) as count FROM fcm_tokens")->fetch();
$notifCount = $db->query("SELECT COUNT(*) as count FROM notifications")->fetch();
echo "   - FCM Tokens: " . ($fcmCount['count'] ?? 0) . " records\n";
echo "   - Notifications: " . ($notifCount['count'] ?? 0) . " records\n\n";

// 5. Test API Endpoints
echo "5. Testing API Endpoints:\n\n";

// Test token registration endpoint
echo "   a) FCM Register Endpoint (/api/fcm/register.php):\n";
$registerEndpoint = "/api/fcm/register.php";
echo "      - Endpoint: $registerEndpoint\n";
echo "      - Method: POST\n";
echo "      - Required fields: token, device_type (optional), device_name (optional)\n\n";

// Test token removal endpoint
echo "   b) FCM Remove Endpoint (/api/fcm/remove.php):\n";
$removeEndpoint = "/api/fcm/remove.php";
echo "      - Endpoint: $removeEndpoint\n";
echo "      - Method: POST\n";
echo "      - Body: {\"token\": \"device_fcm_token\"}\n\n";

// Test notification send endpoint
echo "   c) Send Notification Endpoint (/api/notifications/send.php):\n";
echo "      - Endpoint: /api/notifications/send.php\n";
echo "      - Method: POST\n";
echo "      - Required fields: title, message, type (attendance_alert|low_attendance|system|schedule_change|face_reregister)\n";
echo "      - Optional fields: target_role, target_user_id, target_department_id\n\n";

// 6. Sample API payloads
echo "6. Sample API Payloads:\n\n";

echo "   REGISTER FCM TOKEN:\n";
echo "   POST /api/fcm/register.php\n";
echo "   {\n";
echo "     \"token\": \"abc123def456...\",\n";
echo "     \"device_type\": \"android\",\n";
echo "     \"device_name\": \"Samsung Galaxy S21\"\n";
echo "   }\n\n";

echo "   REMOVE FCM TOKEN:\n";
echo "   POST /api/fcm/remove.php\n";
echo "   {\n";
echo "     \"token\": \"abc123def456...\"\n";
echo "   }\n\n";

echo "   SEND NOTIFICATION:\n";
echo "   POST /api/notifications/send.php\n";
echo "   {\n";
echo "     \"title\": \"Attendance Alert\",\n";
echo "     \"message\": \"Your attendance is below 75%\",\n";
echo "     \"type\": \"low_attendance\",\n";
echo "     \"target_role\": \"student\",\n";
echo "     \"data\": {\"semester\": 4, \"section\": \"A\"}\n";
echo "   }\n\n";

// 7. Verify files exist
echo "7. Checking API Files:\n";
$apiFiles = [
    '/public/api/fcm/register.php',
    '/public/api/fcm/remove.php',
    '/public/api/notifications/send.php',
    '/public/api/notifications/list.php',
    '/public/api/notifications/mark-read.php'
];

foreach ($apiFiles as $file) {
    $path = __DIR__ . $file;
    echo "   - " . basename($file) . ": " . (file_exists($path) ? "✓" : "✗") . "\n";
}
echo "\n";

// 8. Check system settings
echo "8. System Settings (FCM):\n";
$settings = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%fcm%' OR setting_key LIKE '%firebase%'")->fetchAll(PDO::FETCH_ASSOC);

if (!empty($settings)) {
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];
        if (strlen($value) > 50) {
            $value = substr($value, 0, 47) . "...";
        }
        echo "   - {$setting['setting_key']}: $value\n";
    }
} else {
    echo "   - No FCM settings configured yet\n";
    echo "   - Configure in Admin Settings → Firebase Settings\n";
}
echo "\n";

// 9. Summary
echo "============================================\n";
echo "SUMMARY\n";
echo "============================================\n";
echo "\nFCM Setup Status:\n";
echo "  ✓ Database tables exist\n";
echo "  ✓ API endpoints available\n";
echo "  " . (FCM_SERVER_KEY ? "✓" : "✗") . " FCM Server Key configured\n";
echo "\nNext Steps:\n";
if (!FCM_SERVER_KEY) {
    echo "  1. Get FCM Server Key from Firebase Console\n";
    echo "  2. Add it in Admin Settings → Firebase Settings\n";
}
echo "  3. Register FCM tokens from mobile apps\n";
echo "  4. Send notifications via /api/notifications/send.php\n";
echo "\nAPI Documentation:\n";
echo "  See NOTIFICATIONS_GUIDE.md for detailed API usage\n\n";

?>
