<?php
/**
 * CORS Middleware
 * Handle Cross-Origin Resource Sharing
 */

class CORS {
    public static function handle() {
        // Allow from any origin
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-ID, X-Requested-With");
        header("Access-Control-Max-Age: 86400");

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit(0);
        }
    }
}
?>
