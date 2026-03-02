<?php
/**
 * API Response Helper
 * Standardized JSON responses
 */

class Response {
    /**
     * Send success response
     */
    public static function success($data = [], $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date(DATETIME_FORMAT)
        ], JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send error response
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = []) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date(DATETIME_FORMAT)
        ], JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send validation error response
     */
    public static function validationError($errors = []) {
        self::error('Validation failed', 422, $errors);
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }

    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }

    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }

    /**
     * Send server error response
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
}
?>
