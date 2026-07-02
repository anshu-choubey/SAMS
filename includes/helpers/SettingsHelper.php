<?php
/**
 * Settings Helper Class
 * Provides easy access to system settings
 */

class SettingsHelper {
    private static $db = null;
    private static $cache = [];

    public static function setDatabase($database) {
        self::$db = $database;
    }

    /**
     * Get a setting by key
     */
    public static function get($key, $default = null) {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        if (!self::$db) {
            return $default;
        }

        try {
            $query = "SELECT setting_value, setting_type FROM system_settings WHERE setting_key = :key";
            $stmt = self::$db->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return $default;
            }

            $value = $result['setting_value'];
            $type = $result['setting_type'];

            // Convert based on type
            $converted = self::convertValue($value, $type);
            self::$cache[$key] = $converted;

            return $converted;
        } catch (Exception $e) {
            error_log("Error getting setting: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Get multiple settings
     */
    public static function getMultiple($keys, $defaults = []) {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($key, $defaults[$key] ?? null);
        }
        return $result;
    }

    /**
     * Get all settings (optionally filtered)
     */
    public static function getAll($publicOnly = false) {
        try {
            $query = "SELECT setting_key, setting_value, setting_type FROM system_settings";
            if ($publicOnly) {
                $query .= " WHERE is_public = TRUE";
            }
            
            $stmt = self::$db->prepare($query);
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($settings as $setting) {
                $value = self::convertValue($setting['setting_value'], $setting['setting_type']);
                $result[$setting['setting_key']] = $value;
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error getting all settings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Set a setting
     */
    public static function set($key, $value, $type = 'string') {
        if (!self::$db) {
            return false;
        }

        try {
            $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                      VALUES (:key, :value, :type)
                      ON DUPLICATE KEY UPDATE setting_value = :value2, setting_type = :type2";
            
            $stmt = self::$db->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':value2', $value);
            $stmt->bindParam(':type2', $type);
            
            $result = $stmt->execute();
            
            // Clear cache
            unset(self::$cache[$key]);
            
            return $result;
        } catch (Exception $e) {
            error_log("Error setting value: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert setting value based on type
     */
    private static function convertValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : $value;
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Get Attendance Settings
     */
    public static function getAttendanceSettings() {
        return [
            'minimum_threshold' => self::get('attendance_warning_threshold', 75),
            'gps_radius' => self::get('gps_proximity_radius', 50),
            'face_confidence_threshold' => self::get('face_confidence_threshold', 75),
            'enable_face_verification' => self::get('enable_face_verification', true),
            'enable_gps_verification' => self::get('enable_gps_verification', true),
            'allow_late_attendance' => self::get('allow_late_attendance', true),
            'late_threshold_minutes' => self::get('late_threshold_minutes', 15)
        ];
    }

    /**
     * Get Firebase Settings
     */
    public static function getFirebaseSettings() {
        return [
            'firebase_service_account_json' => self::get('firebase_service_account_json', ''),
            'firebase_project_id' => self::get('firebase_project_id', ''),
            'firebase_api_key' => self::get('firebase_api_key', '')
        ];
    }

    /**
     * Get SMTP Settings
     */
    public static function getSmtpSettings() {
        return [
            'host' => self::get('smtp_host', ''),
            'port' => self::get('smtp_port', 587),
            'username' => self::get('smtp_username', ''),
            'password' => self::get('smtp_password', '')
        ];
    }

    /**
     * Get System Settings
     */
    public static function getSystemSettings() {
        return [
            'session_lifetime' => self::get('session_lifetime', 604800),
            'max_login_attempts' => self::get('max_login_attempts', 5),
            'lockout_duration' => self::get('lockout_duration', 900),
            'maintenance_mode' => self::get('maintenance_mode', false),
            'academic_year' => self::get('academic_year', '2025-26'),
            'current_semester' => self::get('current_semester', 2)
        ];
    }

    /**
     * Get App Configuration (for mobile app)
     */
    public static function getAppConfig() {
        return [
            'attendance' => self::getAttendanceSettings(),
            'system' => [
                'session_timeout' => self::get('session_lifetime', 604800),
                'academic_year' => self::get('academic_year', '2025-26'),
                'current_semester' => self::get('current_semester', 2)
            ],
            'app_info' => [
                'name' => self::get('app_name', 'SAMS'),
                'version' => self::get('app_version', '1.0.0'),
                'institution' => self::get('institution_name', 'Your Institution'),
                'logo_url' => self::get('institution_logo', '')
            ]
        ];
    }
}
?>
