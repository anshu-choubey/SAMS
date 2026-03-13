# Personalized Notifications Feature

## Overview
The personalized notifications feature allows the backend to send targeted, context-aware notifications to students and teachers based on their behavior, attendance, and performance.

## Architecture

### Backend Components

#### 1. **Notification Preferences Table**
```sql
CREATE TABLE notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

#### 2. **API Endpoints**

##### Send Personalized Notification (Admin/Teacher)
```
POST /api/notifications/send-personalized.php
```

**Request Body:**
```json
{
  "notification_class": "low_attendance",
  "target_user_id": 123,
  "title": "Optional custom title",
  "message": "Optional custom message",
  "data": {
    "subject_name": "Mathematics",
    "class_time": "10:00 AM"
  }
}
```

**Supported Notification Classes:**
- `low_attendance` - Send when attendance < 75%
- `perfect_attendance` - Celebrate 100% attendance
- `absent_today` - Notify about absence
- `schedule_reminder` - Remind about upcoming class
- `performance_praise` - Encourage consistent attendance
- `custom` - Send custom message

**Response:**
```json
{
  "success": true,
  "notification_id": 456,
  "target_user": "John Doe",
  "title": "Attendance Alert: John Doe",
  "message": "Hi John Doe, your attendance is at 70.5% this month...",
  "notification_class": "low_attendance",
  "personalization_data": {
    "attendance_percent": 70.5,
    "present_count": 12,
    "total_sessions": 17,
    "target_name": "John Doe",
    "target_roll": "2021001"
  }
}
```

##### Get Notification Preferences (User)
```
GET /api/notifications/preferences.php
```

**Response:**
```json
{
  "success": true,
  "preferences": {
    "low_attendance": true,
    "perfect_attendance": true,
    "absent_today": true,
    "schedule_reminder": true,
    "performance_praise": true,
    "custom": true,
    "sound_enabled": true,
    "vibration_enabled": true,
    "show_preview": true
  }
}
```

##### Update Notification Preferences (User)
```
POST /api/notifications/preferences.php
```

**Request Body:**
```json
{
  "preferences": {
    "low_attendance": true,
    "perfect_attendance": false,
    "sound_enabled": true,
    "vibration_enabled": false
  }
}
```

---

### Android App Components

#### 1. **NotificationPreferencesScreen.kt**
Screen for users to customize notification settings:
- Toggle individual notification types (Low Attendance, Perfect Attendance, etc.)
- Enable/disable sound, vibration, and preview
- Save preferences to backend

**Key Features:**
- Section-based organization (Notification Types, Settings)
- Real-time preference updates
- Visual feedback with icons and descriptions

#### 2. **PersonalizedNotificationsScreen.kt**
Display personalized notifications with filtering:
- List view of all notifications
- Filter by notification type
- Mark as read functionality
- Relative time display (e.g., "5m ago", "2h ago")
- Color-coded notification types

**Key Features:**
- Empty state handling
- Unread count badge
- Quick access to settings
- Notification icons by type

#### 3. **StudentViewModel Extensions**
Add to StudentViewModel:
```kotlin
fun loadPersonalizedNotifications() { ... }
fun markNotificationAsRead(notificationId: Int) { ... }
fun updateNotificationPreferences(preferences: Map<String, Any>) { ... }
```

---

## Usage Examples

### Example 1: Send Low Attendance Alert
```bash
curl -X POST http://localhost/api/notifications/send-personalized.php \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "notification_class": "low_attendance",
    "target_user_id": 123
  }'
```

### Example 2: Send Schedule Reminder
```bash
curl -X POST http://localhost/api/notifications/send-personalized.php \
  -H "Authorization: Bearer TEACHER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "notification_class": "schedule_reminder",
    "target_user_id": 456,
    "data": {
      "subject_name": "Physics",
      "class_time": "2:00 PM"
    }
  }'
```

### Example 3: Update User Preferences
```bash
curl -X POST http://localhost/api/notifications/preferences.php \
  -H "Authorization: Bearer USER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "preferences": {
      "low_attendance": true,
      "perfect_attendance": false,
      "sound_enabled": true
    }
  }'
```

---

## Features & Benefits

### For Students:
✅ **Personalized Alerts** - Get relevant notifications based on attendance
✅ **Control** - Enable/disable notification types
✅ **Encouragement** - Receive praise for good attendance
✅ **Settings** - Customize sound, vibration, and preview
✅ **History** - View all past notifications

### For Teachers/Admin:
✅ **Targeted Messaging** - Send to specific students or groups
✅ **Context-Aware** - Automatic message generation based on attendance data
✅ **Batch Send** - Send to multiple users with one endpoint
✅ **Personalization** - Automatic inclusion of user names and stats

---

## Implementation Checklist

- [x] Database migration (notification_preferences table)
- [x] Backend API endpoints (send-personalized.php, preferences.php)
- [x] Android UI screens (NotificationPreferencesScreen, PersonalizedNotificationsScreen)
- [ ] ViewModel methods integration
- [ ] FCM token verification
- [ ] API client methods in Retrofit service
- [ ] Navigation integration in main app

---

## Security Considerations

1. **Authentication**: All endpoints require valid TOKEN (user/admin)
2. **Authorization**: 
   - Only admins/teachers can send notifications
   - Users can only manage their own preferences
3. **Validation**: Notification types and data are validated server-side
4. **Encryption**: FCM tokens stored securely
5. **Rate Limiting**: Consider adding rate limits for bulk sends

---

## Future Enhancements

1. **Scheduled Notifications** - Send at specific times
2. **Batch Endpoint** - Send to multiple users in one request
3. **Analytics** - Track notification delivery and open rates
4. **Templates** - Pre-built notification templates
5. **Automation Rules** - Auto-send based on conditions
6. **A/B Testing** - Test different notification messages
7. **Multi-Language** - Support multiple languages
8. **Rich Notifications** - Images, actions, buttons

---

## Testing

### Test Cases:
1. ✅ Send notification to single user
2. ✅ Verify FCM delivery
3. ✅ Check database storage
4. ✅ Get and update preferences
5. ✅ Filter notifications by type
6. ✅ Mark notification as read
7. ✅ Verify user preferences block notifications

### Sample Test Data:
```sql
-- Insert test student
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Test Student', 'student@test.com', '$2y$12$...', 'student');

-- Send test notification
POST /api/notifications/send-personalized.php
{
  "notification_class": "low_attendance",
  "target_user_id": <student_id>
}
```

---

## Troubleshooting

### Issue: Notification not sent
- Check FCM token is active: `SELECT * FROM fcm_tokens WHERE user_id = ?`
- Verify Firebase configuration
- Check notification preferences are enabled

### Issue: User doesn't receive notification
- Verify notification_preferences allows this type
- Check FCM service is running
- Ensure user has valid FCM token
- Check notification table for is_sent flag

### Issue: Preferences not updating
- Verify POST request with correct user token
- Check JSON format of preferences
- Verify database has notification_preferences entry

---

## API Response Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad request (validation failed) |
| 404 | User or resource not found |
| 405 | Method not allowed |
| 410 | User blocked this notification type |
| 500 | Server error |

