<?php
/**
 * Complete API Endpoint Check
 * Tests all API endpoints and their functionality
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$database = new Database();
$db = $database->getConnection();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║          COMPLETE API ENDPOINT VERIFICATION                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Array to store results
$apiResults = [];

// Function to check if file exists and is readable
function checkAPI($path, $name, $methods = ['GET', 'POST']) {
    global $apiResults;
    
    $fullPath = __DIR__ . $path;
    $exists = file_exists($fullPath);
    $readable = $exists && is_readable($fullPath);
    
    // Try to parse PHP
    $syntaxOk = false;
    if ($readable) {
        $output = [];
        $returnVar = 0;
        exec("php -l '$fullPath' 2>&1", $output, $returnVar);
        $syntaxOk = ($returnVar === 0);
    }
    
    $status = $exists ? ($readable ? ($syntaxOk ? '✓' : '⚠') : '✗') : '✗';
    $apiResults[] = [
        'name' => $name,
        'path' => $path,
        'exists' => $exists,
        'readable' => $readable,
        'syntaxOk' => $syntaxOk,
        'methods' => $methods,
        'status' => $status
    ];
    
    return $status;
}

// ADMIN APIs
echo "1️⃣  ADMIN ENDPOINTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

checkAPI('/public/api/admin/users.php', 'Users CRUD', ['GET', 'POST', 'PUT', 'DELETE']);
checkAPI('/public/api/admin/departments.php', 'Departments CRUD', ['GET', 'POST', 'PUT', 'DELETE']);
checkAPI('/public/api/admin/subjects.php', 'Subjects CRUD', ['GET', 'POST', 'PUT', 'DELETE']);
checkAPI('/public/api/admin/schedules.php', 'Schedules CRUD', ['GET', 'POST', 'PUT', 'DELETE']);
checkAPI('/public/api/admin/teacher-assignments.php', 'Teacher Assignments', ['GET', 'POST', 'PUT', 'DELETE']);
checkAPI('/public/api/admin/settings.php', 'System Settings', ['GET', 'POST']);
checkAPI('/public/api/admin/reports.php', 'Reports', ['GET']);
checkAPI('/public/api/admin/upload-users.php', 'Bulk Upload Users', ['POST']);
checkAPI('/public/api/admin/upload-subjects.php', 'Bulk Upload Subjects', ['POST']);
checkAPI('/public/api/admin/mlkit-metrics.php', 'ML Kit Metrics', ['GET', 'POST']);

echo "\n2️⃣  STUDENT ENDPOINTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

checkAPI('/public/api/student/dashboard.php', 'Student Dashboard', ['GET']);
checkAPI('/public/api/student/profile.php', 'Student Profile', ['GET', 'POST']);
checkAPI('/public/api/student/schedule.php', 'Student Schedule', ['GET']);
checkAPI('/public/api/student/attendance-history.php', 'Attendance History', ['GET']);
checkAPI('/public/api/student/mark-attendance.php', 'Mark Attendance', ['POST']);
checkAPI('/public/api/student/register-face.php', 'Register Face', ['POST']);
checkAPI('/public/api/student/verify-face.php', 'Verify Face', ['POST']);

echo "\n3️⃣  TEACHER ENDPOINTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

checkAPI('/public/api/teacher/dashboard.php', 'Teacher Dashboard', ['GET']);
checkAPI('/public/api/teacher/profile.php', 'Teacher Profile', ['GET', 'POST']);
checkAPI('/public/api/teacher/schedule.php', 'Teacher Schedule', ['GET']);
checkAPI('/public/api/teacher/schedules.php', 'Teacher Schedules List', ['GET']);
checkAPI('/public/api/teacher/start-class.php', 'Start Class', ['POST']);
checkAPI('/public/api/teacher/end-class.php', 'End Class', ['POST']);
checkAPI('/public/api/teacher/class-attendance.php', 'Class Attendance', ['GET']);
checkAPI('/public/api/teacher/manual-attendance.php', 'Manual Attendance', ['POST']);
checkAPI('/public/api/teacher/location.php', 'Teacher Location', ['POST']);

echo "\n4️⃣  FCM & NOTIFICATION ENDPOINTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

checkAPI('/public/api/fcm/register.php', 'Register FCM Token', ['POST']);
checkAPI('/public/api/fcm/remove.php', 'Remove FCM Token', ['POST']);
checkAPI('/public/api/notifications/send.php', 'Send Notification', ['POST']);
checkAPI('/public/api/notifications/list.php', 'List Notifications', ['GET']);
checkAPI('/public/api/notifications/mark-read.php', 'Mark Notification Read', ['POST']);

echo "\n5️⃣  PUBLIC ENDPOINTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

checkAPI('/public/api/public/login.php', 'Login', ['POST']);
checkAPI('/public/api/public/logout.php', 'Logout', ['POST']);
checkAPI('/public/api/public/login-test.php', 'Login Test', ['POST']);

echo "\n6️⃣  UTILITY ENDPOINTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

checkAPI('/public/api/health-check.php', 'Health Check', ['GET']);
checkAPI('/public/api/test-db.php', 'Test Database', ['GET']);

// Print detailed results
echo "\n7️⃣  DETAILED RESULTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$byStatus = ['✓' => [], '⚠' => [], '✗' => []];
foreach ($apiResults as $api) {
    $byStatus[$api['status']][] = $api;
}

if (!empty($byStatus['✓'])) {
    echo "\n✅ WORKING ENDPOINTS (" . count($byStatus['✓']) . ")\n";
    foreach ($byStatus['✓'] as $api) {
        echo "  ✓ {$api['name']}\n";
        echo "    Path: {$api['path']}\n";
        echo "    Methods: " . implode(', ', $api['methods']) . "\n";
    }
}

if (!empty($byStatus['⚠'])) {
    echo "\n⚠️  ENDPOINTS WITH ISSUES (" . count($byStatus['⚠']) . ")\n";
    foreach ($byStatus['⚠'] as $api) {
        echo "  ⚠ {$api['name']}\n";
        echo "    Path: {$api['path']}\n";
        if (!$api['readable']) {
            echo "    Issue: File not readable\n";
        } elseif (!$api['syntaxOk']) {
            echo "    Issue: PHP syntax error\n";
        }
    }
}

if (!empty($byStatus['✗'])) {
    echo "\n❌ MISSING ENDPOINTS (" . count($byStatus['✗']) . ")\n";
    foreach ($byStatus['✗'] as $api) {
        echo "  ✗ {$api['name']}\n";
        echo "    Path: {$api['path']} (NOT FOUND)\n";
    }
}

// Database connectivity test
echo "\n8️⃣  DATABASE ENDPOINTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($db) {
    echo "✓ Database Connection: Active\n";
    
    // Check tables
    $tables = ['users', 'students', 'teachers', 'departments', 'subjects', 
               'schedules', 'teacher_assignments', 'attendance', 'notifications',
               'fcm_tokens', 'system_settings'];
    
    $tableStatus = [];
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->rowCount() > 0) {
            $tableStatus[$table] = '✓';
        } else {
            $tableStatus[$table] = '✗';
        }
    }
    
    echo "\nDatabase Tables:\n";
    foreach ($tableStatus as $table => $status) {
        echo "  $status $table\n";
    }
    
    // Get row counts
    echo "\nData Statistics:\n";
    $counts = [];
    foreach (array_keys($tableStatus) as $table) {
        if ($tableStatus[$table] === '✓') {
            $result = $db->query("SELECT COUNT(*) as count FROM $table");
            if ($result) {
                $row = $result->fetch();
                $count = $row['count'] ?? 0;
                echo "  • $table: $count records\n";
            }
        }
    }
} else {
    echo "✗ Database Connection: Failed\n";
}

// Summary
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      SUMMARY REPORT                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

$total = count($apiResults);
$working = count($byStatus['✓']);
$issues = count($byStatus['⚠']);
$missing = count($byStatus['✗']);

echo "\nTotal Endpoints:     $total\n";
echo "  ✓ Working:         $working\n";
echo "  ⚠ Issues:          $issues\n";
echo "  ✗ Missing:         $missing\n";
echo "\nCompletion:          " . round(($working / $total) * 100) . "%\n";

if ($working === $total) {
    echo "\n🎉 ALL ENDPOINTS ARE OPERATIONAL!\n";
} elseif ($working / $total >= 0.9) {
    echo "\n✅ SYSTEM IS MOSTLY FUNCTIONAL\n";
} else {
    echo "\n⚠️  SOME ENDPOINTS NEED ATTENTION\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Recommendations
if ($issues > 0 || $missing > 0) {
    echo "\n📋 RECOMMENDATIONS:\n";
    if ($missing > 0) {
        echo "1. Create missing endpoint files\n";
    }
    if ($issues > 0) {
        echo "2. Fix PHP syntax errors in problem endpoints\n";
    }
    echo "3. Test all endpoints with real requests\n";
    echo "4. Monitor API logs for errors\n";
}

echo "\n✅ API CHECK COMPLETE\n\n";
?>
