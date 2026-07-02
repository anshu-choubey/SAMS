<?php
/**
 * FCM/Notification Debugging API
 * Admin only - diagnoses FCM and notification issues
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

try {
    Auth::hasRole('admin');

    $database = new Database();
    $db = $database->getConnection();

    $diagnostics = [
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'fcm_config' => [],
        'database_status' => [],
        'token_stats' => [],
        'notification_stats' => [],
        'recent_notifications' => [],
        'recent_tokens' => [],
        'issues' => [],
        'recommendations' => []
    ];

    // 1. Check FCM Configuration
    try {
        $hasFcmKey = !empty(FCM_SERVER_KEY);
        $diagnostics['fcm_config'] = [
            'configured' => $hasFcmKey,
            'key_length' => $hasFcmKey ? strlen(FCM_SERVER_KEY) : 0,
            'key_starts_with' => $hasFcmKey ? substr(FCM_SERVER_KEY, 0, 5) : 'NOT_SET',
            'database_row_exists' => $hasFcmKey
        ];

        if (!$hasFcmKey) {
            $diagnostics['issues'][] = '⚠️ FCM Server Key is NOT SET - Notifications cannot be sent';
            $diagnostics['recommendations'][] = 'Call PUT /api/fcm/configure.php with your Firebase Server Key';
        }
    } catch (Exception $e) {
        $diagnostics['fcm_config']['error'] = $e->getMessage();
    }

    // 2. Check Database Tables Exist
    try {
        $tables = ['fcm_tokens', 'notifications', 'system_settings'];
        $diagnostics['database_status']['tables'] = [];

        foreach ($tables as $table) {
            $checkquery = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES 
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'";
            $stmt = $db->prepare($checkquery);
            $stmt->execute();
            $tableExists = (bool)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $diagnostics['database_status']['tables'][$table] = $tableExists;
        }
    } catch (Exception $e) {
        $diagnostics['database_status']['error'] = $e->getMessage();
    }

    // 3. Check Token Statistics
    try {
        $tokenQuery = "SELECT 
                        COUNT(*) as total_tokens,
                        COUNT(DISTINCT user_id) as unique_users,
                        COUNT(CASE WHEN is_active = TRUE THEN 1 END) as active_tokens,
                        COUNT(CASE WHEN device_type = 'android' THEN 1 END) as android_tokens,
                        MAX(updated_at) as latest_token_time
                       FROM fcm_tokens";
        $stmt = $db->prepare($tokenQuery);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $diagnostics['token_stats'] = [
            'total_tokens' => (int)$stats['total_tokens'],
            'unique_users' => (int)$stats['unique_users'],
            'active_tokens' => (int)$stats['active_tokens'],
            'android_tokens' => (int)$stats['android_tokens'],
            'latest_registration' => $stats['latest_token_time']
        ];

        if ((int)$stats['total_tokens'] === 0) {
            $diagnostics['issues'][] = '⚠️ No FCM tokens registered - Users haven\'t logged in or app isn\'t registering tokens';
            $diagnostics['recommendations'][] = 'Ensure users log in with the app to register their FCM tokens';
            $diagnostics['recommendations'][] = 'Check app logs for "SAMSFirebaseMessaging" tag to debug token registration';
        }
    } catch (Exception $e) {
        $diagnostics['token_stats']['error'] = $e->getMessage();
    }

    // 4. Check Notification Statistics
    try {
        $notifQuery = "SELECT 
                        COUNT(*) as total_notifications,
                        COUNT(CASE WHEN is_read = TRUE THEN 1 END) as read_notifications,
                        COUNT(CASE WHEN is_read = FALSE THEN 1 END) as unread_notifications,
                        COUNT(CASE WHEN is_sent = TRUE THEN 1 END) as sent_notifications,
                        COUNT(CASE WHEN is_sent = FALSE THEN 1 END) as unsent_notifications,
                        MAX(created_at) as latest_notification
                       FROM notifications";
        $stmt = $db->prepare($notifQuery);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $diagnostics['notification_stats'] = [
            'total_notifications' => (int)$stats['total_notifications'],
            'sent' => (int)$stats['sent_notifications'],
            'unsent' => (int)$stats['unsent_notifications'],
            'read' => (int)$stats['read_notifications'],
            'unread' => (int)$stats['unread_notifications'],
            'latest_created' => $stats['latest_notification']
        ];

        if ((int)$stats['unsent_notifications'] > 0) {
            $diagnostics['issues'][] = '⚠️ ' . $stats['unsent_notifications'] . ' notifications are marked as UNSENT - Check server logs';
        }
    } catch (Exception $e) {
        $diagnostics['notification_stats']['error'] = $e->getMessage();
    }

    // 5. Get Recent Notifications
    try {
        $recentQuery = "SELECT id, title, message, notification_type, is_sent, sent_at, created_at 
                       FROM notifications 
                       ORDER BY created_at DESC 
                       LIMIT 5";
        $stmt = $db->prepare($recentQuery);
        $stmt->execute();
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $diagnostics['recent_notifications'] = array_map(function($n) {
            return [
                'id' => (int)$n['id'],
                'title' => $n['title'],
                'type' => $n['notification_type'],
                'is_sent' => (bool)$n['is_sent'],
                'sent_at' => $n['sent_at'],
                'created_at' => $n['created_at']
            ];
        }, $recent);
    } catch (Exception $e) {
        $diagnostics['recent_notifications'] = ['error' => $e->getMessage()];
    }

    // 6. Get Recent Tokens
    try {
        $recentTokenQuery = "SELECT id, user_id, device_type, device_name, is_active, updated_at 
                            FROM fcm_tokens 
                            ORDER BY updated_at DESC 
                            LIMIT 5";
        $stmt = $db->prepare($recentTokenQuery);
        $stmt->execute();
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $diagnostics['recent_tokens'] = array_map(function($t) {
            return [
                'user_id' => (int)$t['user_id'],
                'device_type' => $t['device_type'],
                'device_name' => $t['device_name'],
                'is_active' => (bool)$t['is_active'],
                'updated_at' => $t['updated_at']
            ];
        }, $recent);
    } catch (Exception $e) {
        $diagnostics['recent_tokens'] = ['error' => $e->getMessage()];
    }

    // Final Summary
    if (empty($diagnostics['issues'])) {
        $diagnostics['status'] = 'HEALTHY';
        $diagnostics['summary'] = '✅ FCM appears to be configured correctly!';
    } else {
        $diagnostics['status'] = 'ISSUES_FOUND';
        $diagnostics['summary'] = '❌ Issues detected - see recommendations above';
    }

    Response::success($diagnostics, 'FCM Diagnostic Report');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Diagnostic error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
