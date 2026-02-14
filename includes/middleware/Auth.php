<?php
/**
 * Authentication Middleware
 * Supports both session-based and token-based authentication
 */

require_once __DIR__ . '/../helpers/Response.php';

class Auth {
    /**
     * Check if user is authenticated
     */
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_once __DIR__ . '/../../config/database.php';
        $database = new Database();
        $db = $database->getConnection();

        $sessionId = null;
        $userId = null;

        // 1. Check PHP session first
        if (isset($_SESSION['user_id']) && isset($_SESSION['session_id'])) {
            $sessionId = $_SESSION['session_id'];
            $userId = $_SESSION['user_id'];
        }

        // 2. Check Authorization header (Bearer token)
        if (!$sessionId) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $sessionId = str_replace('Bearer ', '', $headers['Authorization']);
            }
        }

        // 3. Check X-Session-ID header
        if (!$sessionId) {
            $headers = $headers ?? getallheaders();
            if (isset($headers['X-Session-ID'])) {
                $sessionId = $headers['X-Session-ID'];
            }
        }

        // 4. Check query parameter (for GET requests)
        if (!$sessionId && isset($_GET['session_id'])) {
            $sessionId = $_GET['session_id'];
        }

        if (!$sessionId) {
            Response::unauthorized('Please login to continue');
        }

        // Verify session in database
        $query = "SELECT s.*, u.id as user_id, u.role, u.is_active 
                  FROM sessions s 
                  JOIN users u ON s.user_id = u.id
                  WHERE s.session_token = :session_id 
                  AND s.expires_at > NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':session_id', $sessionId);
        $stmt->execute();

        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            Response::unauthorized('Session expired. Please login again');
        }

        if (!$session['is_active']) {
            Response::forbidden('Your account has been deactivated');
        }

        // Store in PHP session for consistency
        $_SESSION['user_id'] = $session['user_id'];
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['role'] = $session['role'];

        // Update last activity
        $updateQuery = "UPDATE sessions SET last_activity = NOW() WHERE session_token = :session_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':session_id', $sessionId);
        $updateStmt->execute();

        return $session['user_id'];
    }

    /**
     * Get authenticated user details
     */
    public static function user() {
        $userId = self::check();

        require_once __DIR__ . '/../../config/database.php';
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT id, full_name, email, role 
                  FROM users WHERE id = :id AND is_active = TRUE";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole($role) {
        $user = self::user();
        
        if (!$user) {
            Response::unauthorized('Please login to continue');
        }
        
        if ($user['role'] !== $role) {
            Response::forbidden("Access restricted to {$role} only");
        }

        return true;
    }

    /**
     * Logout user
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['session_id'])) {
            require_once __DIR__ . '/../../config/database.php';
            $database = new Database();
            $db = $database->getConnection();

            $query = "DELETE FROM sessions WHERE session_token = :session_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':session_id', $_SESSION['session_id']);
            $stmt->execute();
        }

        session_destroy();
    }
}
?>
