<?php
/**
 * Firebase Cloud Messaging Configuration
 */

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

function getFirebaseSetting($settingKey, $envKey = null) {
    $envValue = getenv($envKey ?: strtoupper($settingKey));
    if (!empty($envValue)) {
        return trim($envValue);
    }

    try {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/database.php';
        }

        $database = new Database();
        $db = $database->getConnection();
        if ($db) {
            $valueColumn = getSystemSettingsValueColumn($db);
            if ($valueColumn) {
                $query = "SELECT {$valueColumn} AS setting_value
                          FROM system_settings
                          WHERE setting_key = :setting_key
                          LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':setting_key', $settingKey);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['setting_value'] ?? '';
            }
        }
    } catch (Exception $e) {
        error_log('Firebase setting error: ' . $e->getMessage());
    }

    return '';
}

function getFirebaseServiceAccountConfig() {
    return [
        'service_account_json' => getFirebaseSetting('firebase_service_account_json', 'FIREBASE_SERVICE_ACCOUNT_JSON'),
        'project_id' => getFirebaseSetting('firebase_project_id', 'FIREBASE_PROJECT_ID'),
    ];
}

function firebaseBase64UrlEncode($value) {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

class FirebaseConfig {
    private $serviceAccount;
    private $projectId;
    private $accessToken;
    private $accessTokenExpiry;
    private $tokenEndpoint = 'https://oauth2.googleapis.com/token';

    public function __construct() {
        $this->serviceAccount = $this->loadServiceAccount();
        $this->projectId = $this->resolveProjectId();
    }

    public function isConfigured() {
        return !empty($this->serviceAccount) && !empty($this->projectId);
    }

    public function getProjectId() {
        return $this->projectId;
    }

    public function sendNotification($tokens, $title, $message, $data = []) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Firebase service account not configured'];
        }

        $tokenList = is_array($tokens) ? $tokens : [$tokens];
        $tokenList = array_values(array_filter(array_map('trim', $tokenList)));

        if (empty($tokenList)) {
            return ['success' => false, 'message' => 'No valid tokens provided'];
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            return ['success' => false, 'message' => 'Unable to generate Firebase access token'];
        }

        $sentCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($tokenList as $token) {
            $result = $this->sendToToken($accessToken, $token, $title, $message, $data);
            if (!empty($result['success'])) {
                $sentCount++;
            } else {
                $failedCount++;
                if (!empty($result['message'])) {
                    $errors[] = $result['message'];
                }
            }
        }

        return [
            'success' => $sentCount > 0,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => count($tokenList),
            'errors' => $errors,
            'project_id' => $this->projectId,
        ];
    }

    private function loadServiceAccount() {
        $config = getFirebaseServiceAccountConfig();
        $serviceAccountJson = $config['service_account_json'] ?? '';

        if (empty($serviceAccountJson)) {
            return null;
        }

        $serviceAccount = json_decode($serviceAccountJson, true);
        if (!is_array($serviceAccount)) {
            return null;
        }

        return $serviceAccount;
    }

    private function resolveProjectId() {
        $projectId = getFirebaseServiceAccountConfig()['project_id'] ?? '';
        if (!empty($projectId)) {
            return trim($projectId);
        }

        return $this->serviceAccount['project_id'] ?? '';
    }

    private function getAccessToken() {
        if (!empty($this->accessToken) && !empty($this->accessTokenExpiry) && time() < ($this->accessTokenExpiry - 60)) {
            return $this->accessToken;
        }

        $clientEmail = $this->serviceAccount['client_email'] ?? '';
        $privateKey = $this->serviceAccount['private_key'] ?? '';
        $tokenUri = $this->serviceAccount['token_uri'] ?? $this->tokenEndpoint;

        if (empty($clientEmail) || empty($privateKey)) {
            return '';
        }

        $issuedAt = time();
        $claims = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];

        $jwtHeader = firebaseBase64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtPayload = firebaseBase64UrlEncode(json_encode($claims));
        $unsignedJwt = $jwtHeader . '.' . $jwtPayload;

        $signature = '';
        $privateKeyResource = openssl_pkey_get_private($privateKey);
        if ($privateKeyResource === false) {
            return '';
        }

        if (!openssl_sign($unsignedJwt, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256)) {
            return '';
        }

        $jwtAssertion = $unsignedJwt . '.' . firebaseBase64UrlEncode($signature);

        $postFields = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwtAssertion,
        ]);

        $ch = curl_init($tokenUri);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => $postFields,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError) || $httpCode < 200 || $httpCode >= 300) {
            error_log('Firebase token request failed: ' . ($curlError ?: $response));
            return '';
        }

        $tokenResponse = json_decode($response, true);
        $this->accessToken = $tokenResponse['access_token'] ?? '';
        $expiresIn = (int)($tokenResponse['expires_in'] ?? 3600);
        $this->accessTokenExpiry = time() + max(60, $expiresIn);

        return $this->accessToken ?: '';
    }

    private function sendToToken($accessToken, $token, $title, $message, $data) {
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                ],
                'data' => $this->normalizeDataPayload($data),
                'android' => [
                    'priority' => 'HIGH',
                ],
            ],
        ];

        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($this->projectId) . '/messages:send';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => $curlError ?: 'Unknown cURL error'];
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => $decoded];
        }

        return [
            'success' => false,
            'message' => $decoded['error']['message'] ?? ($curlError ?: 'FCM request failed'),
            'response' => $decoded,
            'http_code' => $httpCode,
        ];
    }

    private function normalizeDataPayload($data) {
        $normalized = [];
        foreach ((array)$data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';
                continue;
            }

            if (is_scalar($value)) {
                $normalized[$key] = (string)$value;
                continue;
            }

            $normalized[$key] = json_encode($value);
        }

        return $normalized;
    }
}
?>
