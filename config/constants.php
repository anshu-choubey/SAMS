<?php
/**
 * System Constants
 */

// Base paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('LOG_PATH', BASE_PATH . '/logs/');

// URL Configuration
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/sams-backend/');
define('API_URL', BASE_URL . 'api/');

// Security
define('SESSION_LIFETIME', 604800); // 7 days
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Attendance System
define('GPS_PROXIMITY_RADIUS', 50); // meters
define('FACE_CONFIDENCE_THRESHOLD', 75); // percentage - more realistic for face recognition
define('ATTENDANCE_WARNING_THRESHOLD', 75); // percentage

// File Upload
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Date/Time
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('TIME_FORMAT', 'H:i:s');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting
if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
