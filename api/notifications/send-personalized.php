<?php
/**
 * Send Personalized Notification API
 * Sends notifications based on user behavior/attendance with personalization
 * Features:
 * - Attendance alerts (low attendance warning)
 * - Performance notifications (achievement badges)
 * - Personalized reminders
 * - Schedule change notifications specific to user
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    Auth::hasRole('admin', 'teacher');
    $user = Auth::user();
    
    $database = new Database();
    $db = $database->getConnection();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $validator->required('notification_class', $data['notification_class'] ?? '', 'Notification Class');
    $validator->required('target_user_id', $data['target_user_id'] ?? '', 'Target User');
    
    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }
    
    $notifClass = $data['notification_class'];
    $targetUserId = $data['target_user_id'];
    $customTitle = $data['title'] ?? '';
    $customMessage = $data['message'] ?? '';
    $customData = $data['data'] ?? [];
    
    // Fetch user details for personalization
    $userQuery = "SELECT u.id, u.full_name, u.role, s.roll_number FROM users u 
                  LEFT JOIN students s ON u.id = s.user_id 
                  WHERE u.id = :user_id";
    $stmt = $db->prepare($userQuery);
    $stmt->bindParam(':user_id', $targetUserId);
    $stmt->execute();
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetUser) {
        Response::error('Target user not found', 404);
    }
    
    // Prepare notification based on class
    $title = $customTitle;
    $message = $customMessage;
    $notificationType = 'system';
    $personalizationData = array_merge($customData, [
        'target_name' => $targetUser['full_name'],
        'target_roll' => $targetUser['roll_number'],
        'notification_class' => $notifClass
    ]);
    
    switch ($notifClass) {
        case 'low_attendance':
            $title = $title ?: "Attendance Alert: {$targetUser['full_name']}";
            
            // Get current attendance stats
                        $statsQuery = "SELECT 
                                                        COUNT(*) as total_sessions,
                                                        COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) as present_count
                                                     FROM attendance a
                                                     WHERE a.student_id = :student_id
                                                         AND a.attendance_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                                                         AND a.attendance_date < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)";
            $stmt = $db->prepare($statsQuery);
            $stmt->bindParam(':student_id', $targetUserId);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $attendancePercent = $stats['total_sessions'] > 0 
                ? ($stats['present_count'] / $stats['total_sessions']) * 100 
                : 0;
            
            $message = $message ?: sprintf(
                "Hi %s, your attendance is at %.1f%% this month. Minimum required: 75%%. Please attend all upcoming classes.",
                $targetUser['full_name'],
                $attendancePercent
            );
            
            $personalizationData = array_merge($personalizationData, [
                'attendance_percent' => $attendancePercent,
                'present_count' => $stats['present_count'],
                'total_sessions' => $stats['total_sessions']
            ]);
            
            $notificationType = 'low_attendance';
            break;
            
        case 'perfect_attendance':
            $title = $title ?: "🎉 Achievement Unlocked!";
            $message = $message ?: sprintf(
                "Congratulations %s! You've maintained 100%% attendance this month. Keep it up!",
                $targetUser['full_name']
            );
            $notificationType = 'attendance_alert';
            break;
            
        case 'absent_today':
            $title = $title ?: "Note: You were marked absent today";
            $message = $message ?: sprintf(
                "Hi %s, you were marked absent in today's class. Use the app to register for the makeup session if available.",
                $targetUser['full_name']
            );
            $notificationType = 'attendance_alert';
            break;
            
        case 'schedule_reminder':
            $title = $title ?: "Upcoming Class Reminder";
            
            if (isset($customData['subject_name'])) {
                $message = $message ?: sprintf(
                    "Hi %s, you have %s class scheduled tomorrow at %s. Make sure you're on time!",
                    $targetUser['full_name'],
                    $customData['subject_name'],
                    $customData['class_time'] ?? ''
                );
            }
            
            $notificationType = 'schedule_change';
            break;
            
        case 'performance_praise':
            $title = $title ?: "Great Performance!";
            $message = $message ?: sprintf(
                "Hey %s, you've been one of the most consistent attendees! Your dedication is appreciated.",
                $targetUser['full_name']
            );
            break;
            
        case 'custom':
            // Allow custom title and message, already set above
            break;
            
        default:
            Response::error('Invalid notification class', 400);
    }
    
    // Check notification preferences
    $prefQuery = "SELECT * FROM notification_preferences WHERE user_id = :user_id";
    $stmt = $db->prepare($prefQuery);
    $stmt->bindParam(':user_id', $targetUserId);
    $stmt->execute();
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If preferences exist and this type is disabled, skip sending
    if ($preferences) {
        $prefsJson = json_decode($preferences['preferences'], true);
        if (isset($prefsJson[$notifClass]) && !$prefsJson[$notifClass]) {
            Response::error('User has disabled notifications of this type', 410);
        }
    }
    
    // Store notification in database
    $insertQuery = "INSERT INTO notifications 
                    (title, message, notification_type, target_user_id, data, created_by, is_sent)
                    VALUES (:title, :message, :type, :target_user_id, :data, :created_by, 1)";
    
    $stmt = $db->prepare($insertQuery);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':type', $notificationType);
    $stmt->bindParam(':target_user_id', $targetUserId);
    $dataJson = json_encode($personalizationData);
    $stmt->bindParam(':data', $dataJson);
    $stmt->bindParam(':created_by', $user['id']);
    $stmt->execute();
    
    $notificationId = $db->lastInsertId();
    
    // Send via FCM
    $tokenQuery = "SELECT token FROM fcm_tokens WHERE user_id = :user_id AND is_active = TRUE LIMIT 1";
    $stmt = $db->prepare($tokenQuery);
    $stmt->bindParam(':user_id', $targetUserId);
    $stmt->execute();
    $tokenResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tokenResult && !empty($tokenResult['token'])) {
        $fcmMessage = [
            'token' => $tokenResult['token'],
            'notification' => [
                'title' => $title,
                'body' => $message
            ],
            'data' => [
                'notification_id' => (string)$notificationId,
                'notification_class' => $notifClass,
                'personalization_data' => json_encode($personalizationData),
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                    'color' => '#004687'
                ]
            ]
        ];
        
        try {
            $response = Firebase\Messaging::send($fcmMessage);
            
            // Update sent timestamp
            $updateQuery = "UPDATE notifications SET is_sent = TRUE, sent_at = NOW() WHERE id = :id";
            $stmt = $db->prepare($updateQuery);
            $stmt->bindParam(':id', $notificationId);
            $stmt->execute();
            
            Response::success([
                'notification_id' => $notificationId,
                'target_user' => $targetUser['full_name'],
                'title' => $title,
                'message' => $message,
                'notification_class' => $notifClass,
                'personalization_data' => $personalizationData,
                'fcm_response' => $response
            ]);
        } catch (Exception $e) {
            // Store as unsent if FCM fails
            Response::error('Failed to send FCM notification: ' . $e->getMessage(), 500);
        }
    } else {
        Response::error('No active FCM token found for user', 404);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
