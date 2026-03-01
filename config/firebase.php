<?php
/**
 * Firebase Cloud Messaging Configuration
 */

// Define FCM Server Key Constant
function getFCMServerKey() {
    try {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/database.php';
        }
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'fcm_server_key' LIMIT 1";
            $stmt = $db->query($query);
            if ($stmt && $stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['setting_value'] ?? '';
            }
        }
    } catch (Exception $e) {
        error_log("FCM Key Error: " . $e->getMessage());
    }
    return '';
}

if (!defined('FCM_SERVER_KEY')) {
    define('FCM_SERVER_KEY', getFCMServerKey());
}

if (!defined('FCM_URL')) {
    define('FCM_URL', 'https://fcm.googleapis.com/fcm/send');
}

class FirebaseConfig {
    private $serverKey;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct() {
        // Load server key from constant or database
        $this->serverKey = FCM_SERVER_KEY ?: $this->getServerKey();
    }

    private function getServerKey() {
        return FCM_SERVER_KEY ?: '';
    }

    public function sendNotification($tokens, $title, $message, $data = []) {
        if (empty($this->serverKey)) {
            return ['success' => false, 'message' => 'FCM server key not configured'];
        }

        $notification = [
            'title' => $title,
            'body' => $message,
            'sound' => 'default',
            'badge' => '1'
        ];

        $payload = [
            'registration_ids' => is_array($tokens) ? $tokens : [$tokens],
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high'
        ];

        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode === 200,
            'response' => json_decode($result, true)
        ];
    }
}
?>
