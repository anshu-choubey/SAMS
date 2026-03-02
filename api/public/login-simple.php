<?php
/**
 * Simple Login Test Endpoint
 * Simplified login without sessions table dependency
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        Response::error('Invalid JSON input', 400);
    }

    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (!$email || !$password) {
        Response::error('Email and password are required', 400);
    }

    // Connect to database
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        Response::error('Database connection failed', 500);
    }

    // Check user exists and verify password
    $query = "SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = ?";
    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        Response::error('Database query preparation failed: ' . $db->error, 500);
    }

    $stmt->execute([$email]);
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        Response::error('Invalid email or password', 401);
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        Response::error('Invalid email or password', 401);
    }

    if (!$user['is_active']) {
        Response::error('Account is deactivated', 403);
    }

    // Return success
    Response::success([
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'is_active' => (bool)$user['is_active']
        ]
    ], 'Login successful');

} catch (Exception $e) {
    Response::error('Server error: ' . $e->getMessage(), 500);
}

?>
