<?php
/**
 * SAMS Backend - FCM & API Documentation
 * Complete guide to FCM and API endpoints
 */

?>

# SAMS Backend - FCM & API Documentation

## FCM (Firebase Cloud Messaging) Status

### ✅ FCM Setup Complete
- [x] FCM database tables created (`fcm_tokens`, `notifications`)
- [x] FCM API endpoints available
- [x] FCM token registration working
- [x] Notification creation system working
- [x] Database connectivity verified

### 📊 Current System Status
```
Database Tables:
  ✓ fcm_tokens:    4 active tokens registered
  ✓ notifications: 1+ notifications created
  ✓ users:         3 users in system
  ✓ students:      1 student record
  ✓ teachers:      1 teacher record
  ✓ departments:   6 departments
```

---

## API Endpoints

### 1. FCM Token Registration
**Endpoint:** `POST /api/fcm/register.php`

Register device FCM token for push notifications.

**Request:**
```json
{
  "token": "abc123def456...",           // Required: FCM device token
  "device_type": "android",             // Optional: android|ios|web (default: android)
  "device_name": "Samsung Galaxy S21"   // Optional: Device name
}
```

**Response Success:**
```json
{
  "success": true,
  "message": "FCM token registered successfully",
  "data": null
}
```

**Response Error:**
```json
{
  "success": false,
  "message": "FCM Token is required",
  "errors": {"token": "FCM Token is required"}
}
```

### 2. Remove FCM Token
**Endpoint:** `POST /api/fcm/remove.php`

Remove or disable FCM token for a device.

**Request:**
```json
{
  "token": "abc123def456..."  // Optional: Specific token to remove
                              // If omitted, removes all user's tokens
}
```

**Response:**
```json
{
  "success": true,
  "message": "FCM token removed successfully",
  "data": null
}
```

### 3. Send Notifications
**Endpoint:** `POST /api/notifications/send.php`

Send notifications to users via FCM and store in database. **Admin only.**

**Request:**
```json
{
  "title": "Attendance Alert",
  "message": "Your attendance is below 75%",
  "type": "low_attendance",                          // Required
  "target_role": "student",                          // Optional: all|admin|teacher|student
  "target_user_id": 123,                             // Optional: Specific user ID
  "target_department_id": 1,                         // Optional: Specific department
  "data": {                                          // Optional: Custom data
    "semester": 4,
    "section": "A",
    "threshold_percentage": 75
  }
}
```

**Notification Types:**
- `attendance_alert` - Attendance-related alert
- `low_attendance` - Low attendance warning
- `system` - General system notification
- `schedule_change` - Schedule changed
- `face_reregister` - Face re-registration required

**Response:**
```json
{
  "success": true,
  "message": "Notification sent successfully",
  "data": {
    "notification_id": 1,
    "fcm_sent": 4,           // Tokens FCM accepted
    "fcm_failed": 0,         // Tokens FCM rejected
    "total_tokens": 4        // Total tokens targeted
  }
}
```

### 4. List Notifications
**Endpoint:** `GET /api/notifications/list.php`

Retrieve user's notifications.

**Query Parameters:**
```
?limit=20&offset=0&unread_only=false
```

**Response:**
```json
{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Attendance Alert",
      "message": "Your attendance is below 75%",
      "notification_type": "low_attendance",
      "is_read": false,
      "created_at": "2026-03-01 19:20:24"
    }
  ]
}
```

### 5. Mark Notification Read
**Endpoint:** `POST /api/notifications/mark-read.php`

Mark notifications as read.

**Request:**
```json
{
  "notification_id": 1  // Optional: Specific notification
                        // If omitted, marks all as read
}
```

**Response:**
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

---

## Database Schema

### fcm_tokens Table
```sql
CREATE TABLE fcm_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  token VARCHAR(500) NOT NULL UNIQUE,
  device_type ENUM('android','ios','web') DEFAULT 'android',
  device_name VARCHAR(100),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_active (is_active)
);
```

### notifications Table
```sql
CREATE TABLE notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  notification_type VARCHAR(50) NOT NULL,
  target_role VARCHAR(20),
  target_user_id INT,
  target_department_id INT,
  data JSON,
  is_read TINYINT(1) DEFAULT 0,
  is_sent TINYINT(1) DEFAULT 0,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (target_department_id) REFERENCES departments(id) ON DELETE SET NULL,
  INDEX idx_user (target_user_id),
  INDEX idx_type (notification_type),
  INDEX idx_sent (is_sent)
);
```

---

## Configuration

### FCM v1 Setup

1. **Get Firebase Service Account JSON:**
  - Go to Firebase Console → Your Project → Settings → Service Accounts
  - Generate a new private key
  - Download the JSON file

2. **Store credentials securely:**
  - Add the service-account JSON to your backend secret store or environment variables
  - Do not commit the JSON file to the repository

3. **Verify Configuration:**
   - Run: `php test-fcm.php`
  - Should show that Firebase credentials are configured

### Environment Variables
```env
# .env file
FIREBASE_SERVICE_ACCOUNT_JSON={...}
FIREBASE_PROJECT_ID=careful-form-373115
APP_ENV=production
BASE_URL=http://localhost/sams-backend/
```

---

## Testing

### Test FCM Setup
```bash
cd /Users/anshu/sams-backend
php test-fcm.php
```

Output shows:
- ✓ Database Connection
- ✓ Tables exist
- ✓ FCM configuration status
- ✓ API endpoints available

### Test FCM Endpoints with Sample Data
```bash
php test-fcm-endpoints.php
```

This script:
- Registers 2 test FCM tokens (Android + iOS)
- Creates test notifications
- Verifies data in database
- Shows current system statistics

---

## Troubleshooting

### Issue: "FCM Server Key not configured"
**Solution:**
1. For FCM API v1, use a service account JSON instead of a legacy server key
2. Store the service account JSON in your backend secrets/environment variables
3. Run `php test-fcm.php` to verify

### Issue: Tokens not received by devices
**Check:**
1. Ensure device has correct FCM token
2. Verify token is registered: `SELECT * FROM fcm_tokens;`
3. Check notification was created: `SELECT * FROM notifications;`
4. Verify FCM Server Key is correct

### Issue: "Undefined constant FCM_SERVER_KEY"
**Solution:**
- Ensure the legacy helper is not being used for the v1 flow
- Check that the service-account credentials are available to the backend
- Verify the backend is loading `FIREBASE_SERVICE_ACCOUNT_JSON`

---

## Architecture

```
API Request Flow:
┌─────────────┐
│ Mobile App  │
└──────┬──────┘
       │ POST /api/fcm/register.php
       │ {token: "abc..."}
       ▼
┌──────────────────┐
│ FCM API Handler  │ ← /public/api/fcm/register.php
└──────┬───────────┘
       │ Validate token
       │ Check database
       ▼
┌──────────────────┐
│ Database         │ ← fcm_tokens table
│ (MySQL)          │
└──────────────────┘
       │
       │ Returns data when notification sent
       ▼
┌──────────────────┐
│ Firebase Cloud   │ ← FCM Server (Google)
│ Messaging        │
└──────┬───────────┘
       │ Push notification
       ▼
┌─────────────────┐
│ Mobile Device   │ ← notification received
└─────────────────┘
```

---

## File Locations

**API Endpoints:**
```
/public/api/
├── fcm/
│   ├── register.php      # Register FCM token
│   └── remove.php        # Remove FCM token
├── notifications/
│   ├── send.php          # Send notification
│   ├── list.php          # List notifications
│   └── mark-read.php     # Mark as read
├── admin/
├── student/
├── teacher/
└── public/
    ├── login.php         # User login
    ├── logout.php        # User logout
    └── login-test.php    # Test login
```

**Configuration:**
```
/config/
├── database.php          # Database connection
├── firebase.php          # FCM configuration
├── constants.php         # System constants
└── fcm-helper.php        # FCM helper functions
```

**Testing:**
```
/test-fcm.php            # Check FCM setup
/test-fcm-endpoints.php  # Test FCM functionality
```

---

## Best Practices

1. **Token Management**
   - Register token when app starts
   - Update token if it changes (Firebase may rotate tokens)
   - Remove token on logout
   - Set is_active=0 for inactive users

2. **Notification Sending**
   - Use target_role for bulk notifications
   - Use target_user_id for individual messages
   - Use target_department_id for department-specific alerts
   - Include relevant data in notification payload

3. **Error Handling**
   - Always check response.success
   - Log failed token registrations
   - Retry failed notification sends
   - Monitor FCM_failed count

4. **Security**
   - Never expose Firebase keys in client code
   - Validate user permissions before sending
   - Rate limit notification sending
   - Audit all notifications sent

---

## Support

For issues or questions:
1. Check `/NOTIFICATIONS_GUIDE.md` for detailed guide
2. Run `php test-fcm.php` to diagnose issues
3. Check logs in `/logs/` directory
4. Review database tables with `mysql -u root sams_db`

---

**Last Updated:** March 1, 2026
**Status:** ✅ Production Ready
