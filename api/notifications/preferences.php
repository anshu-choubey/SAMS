<?php
/**
 * Notification Preferences API
 * Allows users to manage their notification preferences
 * GET - Get current preferences
 * POST/PUT - Update preferences
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

try {
    Auth::isAuthenticated();
    $user = Auth::user();
    $userId = $user['id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Default preferences structure
    $defaultPreferences = [
        'low_attendance' => true,
        'perfect_attendance' => true,
        'absent_today' => true,
        'schedule_reminder' => true,
        'performance_praise' => true,
        'custom' => true,
        'sound_enabled' => true,
        'vibration_enabled' => true,
        'show_preview' => true
    ];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get current preferences
        $query = "SELECT preferences FROM notification_preferences WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['preferences'])) {
            $currentPrefs = json_decode($result['preferences'], true);
            $preferences = array_merge($defaultPreferences, $currentPrefs);
        } else {
            $preferences = $defaultPreferences;
        }
        
        Response::success([
            'preferences' => $preferences,
            'user_id' => $userId
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update preferences
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Handle both formats: direct map or wrapped in 'preferences' key
        $preferences = !empty($data['preferences']) ? $data['preferences'] : $data;
        
        // Validate that we only accept known preference keys
        $validKeys = array_keys($defaultPreferences);
        foreach ($preferences as $key => $value) {
            if (!in_array($key, $validKeys)) {
                Response::error("Invalid preference key: {$key}", 400);
            }
        }
        
        // Merge with defaults
        $finalPreferences = array_merge($defaultPreferences, $preferences);
        $prefsJson = json_encode($finalPreferences);
        
        // Check if preferences exist
        $checkQuery = "SELECT id FROM notification_preferences WHERE user_id = :user_id";
        $stmt = $db->prepare($checkQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Update
            $updateQuery = "UPDATE notification_preferences 
                           SET preferences = :prefs, updated_at = NOW()
                           WHERE user_id = :user_id";
            $stmt = $db->prepare($updateQuery);
            $stmt->bindParam(':prefs', $prefsJson);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
        } else {
            // Insert
            $insertQuery = "INSERT INTO notification_preferences (user_id, preferences)
                           VALUES (:user_id, :prefs)";
            $stmt = $db->prepare($insertQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':prefs', $prefsJson);
            $stmt->execute();
        }
        
        Response::success([
            'message' => 'Notification preferences updated successfully',
            'preferences' => $finalPreferences,
            'user_id' => $userId
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
