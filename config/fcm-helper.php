<?php
/**
 * FCM Configuration & Helper
 * Loads Firebase Cloud Messaging settings from database
 */

// Load settings from database
function getFCMServerKey() {
    try {
        require_once __DIR__ . '/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $valueColumn = getSystemSettingsValueColumn($db);
        if ($valueColumn) {
            $query = "SELECT {$valueColumn} AS setting_value FROM system_settings WHERE setting_key = 'fcm_server_key' LIMIT 1";
            $stmt = $db->query($query);
            
            if ($stmt && $stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['setting_value'] ?? '';
            }
        }
    } catch (Exception $e) {
        error_log("FCM Config Error: " . $e->getMessage());
    }
    
    return '';
}

function getSystemSettingsValueColumn($db) {
    static $cachedColumn = null;

    if ($cachedColumn !== null) {
        return $cachedColumn;
    }

    try {
        $query = "SELECT COLUMN_NAME
                  FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'system_settings'
                    AND COLUMN_NAME IN ('setting_value', 'value')
                  ORDER BY FIELD(COLUMN_NAME, 'setting_value', 'value')
                  LIMIT 1";
        $stmt = $db->query($query);
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $cachedColumn = $result['COLUMN_NAME'] ?? null;
        }
    } catch (Exception $e) {
        $cachedColumn = null;
    }

    return $cachedColumn;
}

// Define FCM Server Key constant if not already set
if (!defined('FCM_SERVER_KEY')) {
    define('FCM_SERVER_KEY', getFCMServerKey());
}

// FCM URL
if (!defined('FCM_URL')) {
    define('FCM_URL', 'https://fcm.googleapis.com/fcm/send');
}

/**
 * Send push notifications via FCM
 * @param array|string $tokens FCM device tokens
 * @param string $title Notification title
 * @param string $message Notification body
 * @param array $data Additional data payload
 * @return array Response with success status and details
 */
function sendFCMNotification($tokens, $title, $message, $data = []) {
    if (empty(FCM_SERVER_KEY)) {
        return [
            'success' => false,
            'sent' => 0,
            'failed' => 0,
            'message' => 'FCM Server Key not configured'
        ];
    }
    
    $tokenArray = is_array($tokens) ? $tokens : [$tokens];
    $tokenArray = array_filter($tokenArray); // Remove empty tokens
    
    if (empty($tokenArray)) {
        return [
            'success' => false,
            'sent' => 0,
            'failed' => 0,
            'message' => 'No valid tokens provided'
        ];
    }
    
    $totalSent = 0;
    $totalFailed = 0;
    
    // Send in chunks (max 500 tokens per request)
    foreach (array_chunk($tokenArray, 500) as $chunk) {
        $payload = [
            'registration_ids' => $chunk,
            'notification' => [
                'title' => $title,
                'body' => $message,
                'sound' => 'default',
                'badge' => '1'
            ],
            'data' => $data,
            'priority' => 'high'
        ];
        
        $headers = [
            'Authorization: key=' . FCM_SERVER_KEY,
            'Content-Type: application/json',
            'Connection: close'
        ];
        
        $ch = curl_init(FCM_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $totalSent += $result['success'] ?? 0;
            $totalFailed += $result['failure'] ?? 0;
        } else {
            $totalFailed += count($chunk);
        }
    }
    
    return [
        'success' => $totalSent > 0,
        'sent' => $totalSent,
        'failed' => $totalFailed,
        'total' => count($tokenArray)
    ];
}
?>
