<?php
/**
 * Debug Login API
 * Simple endpoint to test login step-by-step
 */

header('Content-Type: application/json');
error_log("=== Login Debug Started ===");

try {
    // Step 1: Get input
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Input: " . json_encode($input));
    
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    
    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email and password required',
            'debug' => ['email_provided' => !empty($email), 'password_provided' => !empty($password)]
        ]);
        exit;
    }
    
    // Step 2: Connect to database
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    error_log("Database connected");
    
    // Step 3: Query user
    $stmt = $db->prepare("SELECT id, email, full_name, password_hash, role, is_active FROM users WHERE email = ?");
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
        exit;
    }
    
    error_log("Query prepared");
    
    $stmt->execute([$email]);
    error_log("Query executed");
    
    $result = $stmt->get_result();
    error_log("Result: " . ($result ? "Got result object" : "No result"));
    
    $user = $result->fetch_assoc();
    error_log("User fetch: " . json_encode($user ? ['id' => $user['id'], 'email' => $user['email']] : null));
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Step 4: Verify password
    $hash_match = password_verify($password, $user['password_hash']);
    error_log("Password verify result: " . ($hash_match ? "MATCH" : "NO MATCH"));
    
    if (!$hash_match) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
        exit;
    }
    
    // Step 5: Check active status
    if (!$user['is_active']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is deactivated']);
        exit;
    }
    
    error_log("All checks passed - Login successful");
    
    // Step 6: Return success
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
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?>
