<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/middleware/CORS.php';
// Handle CORS (preflight and origin handling)
CORS::handle();

error_log("Login API called with method: " . $_SERVER['REQUEST_METHOD']);
error_log("Input: " . file_get_contents('php://input'));

// Get the root directory
$baseDir = dirname(dirname(dirname(__FILE__)));
require_once $baseDir . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    error_log("Decoded data: " . json_encode($data));
    
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = true");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        error_log("User found: " . json_encode($user));
        
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            exit;
        // Test password
        if (!password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            exit;
        }
        
        // Generate session ID
        $sessionId = bin2hex(random_bytes(32));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Create session record in database
        try {
            $sessionStmt = $db->prepare("INSERT INTO sessions (user_id, session_id, ip_address, user_agent, expires_at) 
                                         VALUES (?, ?, ?, ?, ?)");
            $sessionStmt->execute([$user['id'], $sessionId, $ipAddress, $userAgent, $expiresAt]);
        } catch (Exception $e) {
            error_log("Session insert error: " . $e->getMessage());
            // Continue anyway - don't block login if session insert fails
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ],
                'session_id' => $sessionId
            ]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
