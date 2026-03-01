<?php
/**
 * API Health Check
 * Test all major endpoints to identify issues
 */

header('Content-Type: application/json');

$results = [];

// Test 1: Database connection
try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        $results['database'] = ['status' => 'OK', 'message' => 'Connected'];
        
        // Test query
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['users_count'] = $data['count'];
    } else {
        $results['database'] = ['status' => 'ERROR', 'message' => 'Connection failed'];
    }
} catch (Exception $e) {
    $results['database'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// Test 2: Config files
$configFiles = [
    'constants.php' => __DIR__ . '/../config/constants.php',
    'database.php' => __DIR__ . '/../config/database.php',
];

$results['config_files'] = [];
foreach ($configFiles as $name => $path) {
    $results['config_files'][$name] = file_exists($path) ? 'OK' : 'MISSING';
}

// Test 3: Required directories
$directories = [
    'logs' => __DIR__ . '/../logs/',
    'uploads' => __DIR__ . '/../uploads/',
    'api' => __DIR__ . '/',
    'includes' => __DIR__ . '/../includes/',
];

$results['directories'] = [];
foreach ($directories as $name => $path) {
    $results['directories'][$name] = is_dir($path) ? 'OK' : 'MISSING';
}

// Test 4: API files
$apiFiles = [
    'login' => __DIR__ . '/public/login.php',
    'student_dashboard' => __DIR__ . '/student/dashboard.php',
    'teacher_schedule' => __DIR__ . '/teacher/schedule.php',
];

$results['api_files'] = [];
foreach ($apiFiles as $name => $path) {
    $results['api_files'][$name] = file_exists($path) ? 'OK' : 'MISSING';
}

// Test 5: Required classes
$results['classes'] = [];
try {
    require_once __DIR__ . '/../includes/models/User.php';
    $results['classes']['User'] = 'OK';
} catch (Exception $e) {
    $results['classes']['User'] = 'ERROR: ' . $e->getMessage();
}

try {
    require_once __DIR__ . '/../includes/controllers/AuthController.php';
    $results['classes']['AuthController'] = 'OK';
} catch (Exception $e) {
    $results['classes']['AuthController'] = 'ERROR: ' . $e->getMessage();
}

// Test 6: Permissions
$results['permissions'] = [];
$results['permissions']['logs_writable'] = is_writable(__DIR__ . '/../logs') ? 'OK' : 'NOT WRITABLE';
$results['permissions']['uploads_writable'] = is_writable(__DIR__ . '/../uploads') ? 'OK' : 'NOT WRITABLE';

// Test 7: PHP Info
$results['php'] = [
    'version' => phpversion(),
    'extensions' => [
        'PDO' => extension_loaded('PDO') ? 'OK' : 'MISSING',
        'MySQLi' => extension_loaded('mysqli') ? 'OK' : 'MISSING',
        'JSON' => extension_loaded('json') ? 'OK' : 'MISSING',
    ]
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>
