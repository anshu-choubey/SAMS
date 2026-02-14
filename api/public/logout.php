<?php
/**
 * Logout API Endpoint
 * Supports both GET (web) and POST (API) requests
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

// Handle CORS
CORS::handle();

// Allow both GET and POST for logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get session_id from multiple sources
    $sessionId = null;
    
    // 1. Check POST body
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['session_id'])) {
            $sessionId = $data['session_id'];
        }
    }
    
    // 2. Check Authorization header
    if (!$sessionId) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $sessionId = str_replace('Bearer ', '', $headers['Authorization']);
        }
    }
    
    // 3. Check X-Session-ID header
    if (!$sessionId && isset($headers['X-Session-ID'])) {
        $sessionId = $headers['X-Session-ID'];
    }
    
    // 4. Check PHP session
    if (!$sessionId && isset($_SESSION['session_id'])) {
        $sessionId = $_SESSION['session_id'];
    }

    // Delete session from database
    if ($sessionId && $db) {
        $query = "DELETE FROM sessions WHERE session_token = :session_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':session_id', $sessionId);
        $stmt->execute();
    }

    // Also delete by user_id if available
    if (isset($_SESSION['user_id']) && $db) {
        $query = "DELETE FROM sessions WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
    }

    // Destroy PHP session
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }

    // For GET requests (from web links), redirect to login page
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: /public/login.php');
        exit;
    }

    // For POST requests (API), return JSON
    Response::success([], 'Logout successful');

} catch (Exception $e) {
    // For GET requests, redirect even on error
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: /public/login.php');
        exit;
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
