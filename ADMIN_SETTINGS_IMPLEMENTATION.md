# SAMS Admin Settings Implementation

## Overview
The admin settings panel allows administrators to configure system-wide settings that are used by the backend API and mobile application. All settings are stored in the `system_settings` database table and can be managed through the admin panel.

## Available Settings

### 1. Attendance Settings
- **Minimum Attendance Threshold (%)**: Default 75%
  - Students below this percentage receive warnings
  - Used in reports and student dashboard

- **GPS Proximity Radius (meters)**: Default 50m
  - Maximum distance allowed for attendance marking
  - Ensures students mark attendance from within the classroom

- **Face Confidence Threshold (%)**: Default 75%
  - Minimum ML Kit face match confidence required
  - Prevents spoofing with low-quality images

- **Enable Face Verification**: Boolean flag
  - Turn on/off face-based attendance verification

- **Enable GPS Verification**: Boolean flag
  - Turn on/off GPS-based location verification

- **Allow Late Attendance**: Boolean flag
  - Whether students can mark attendance after class starts

- **Late Threshold (minutes)**: Default 15 minutes
  - Grace period after class starts to mark as late attendance

### 2. Firebase Cloud Messaging (FCM)
- **FCM Server Key**
  - Used for sending push notifications to students and teachers
  - Stored securely in the database

### 3. Email Settings (SMTP)
- **SMTP Host**: e.g., smtp.gmail.com
- **SMTP Port**: Default 587 (TLS)
- **SMTP Username**: Email address with credentials
- **SMTP Password**: Encrypted password for authentication

Used for:
- Sending reset password emails
- Attendance warnings
- System notifications

### 4. System Configuration
- **Session Timeout (seconds)**: Default 3600 (1 hour)
  - Auto-logout after this duration of inactivity
  - Applied to both web and mobile apps

- **Academic Year**: e.g., "2025-26"
  - Used in reports and student dashboard

- **Current Semester**: Integer value (1-8)
  - Used to filter and display semester-specific data

- **Max Login Attempts**: Default 5
  - Maximum failed login attempts before account lockout

- **Lockout Duration (seconds)**: Default 900 (15 minutes)
  - Duration of account lockout after max failed attempts

## Database Schema

```sql
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json'),
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
);
```

## Backend Implementation

### SettingsHelper Class
Located at: `/includes/helpers/SettingsHelper.php`

Usage:
```php
// Get a single setting with default value
$gpsRadius = SettingsHelper::get('gps_proximity_radius', 50);

// Get multiple settings
$settings = SettingsHelper::getMultiple(['gps_proximity_radius', 'face_confidence_threshold']);

// Get all public settings
$publicSettings = SettingsHelper::getAll(true);

// Set a setting
SettingsHelper::set('gps_proximity_radius', 75, 'number');

// Get grouped settings
$attendanceSettings = SettingsHelper::getAttendanceSettings();
$smtpSettings = SettingsHelper::getSmtpSettings();
$systemSettings = SettingsHelper::getSystemSettings();
```

### API Endpoints

#### Public Settings API
**Endpoint**: `/api/public/settings.php`
**Authentication**: None required (public data only)

Query Parameters:
- `type`: `all`, `attendance`, `system`, or `app` (default: `all`)

Examples:
```bash
# Get all settings
GET /api/public/settings.php

# Get only attendance settings
GET /api/public/settings.php?type=attendance

# Get system configuration
GET /api/public/settings.php?type=system

# Get app information
GET /api/public/settings.php?type=app
```

Response Example:
```json
{
  "success": true,
  "data": {
    "attendance": {
      "gps_radius": 50,
      "face_confidence_threshold": 75,
      "enable_face_verification": true,
      "enable_gps_verification": true,
      "minimum_threshold": 75,
      "allow_late_attendance": true,
      "late_threshold_minutes": 15
    },
    "system": {
      "session_timeout": 3600,
      "academic_year": "2025-26",
      "current_semester": 2,
      "maintenance_mode": false
    },
    "app_info": {
      "name": "SAMS",
      "version": "1.0.0",
      "institution": "Your Institution",
      "logo_url": "",
      "support_email": "support@sams.edu"
    }
  }
}
```

#### Admin Settings API
**Endpoint**: `/api/admin/settings.php`
**Authentication**: Admin role required

Methods:
- **GET**: Retrieve all settings
- **POST**: Update settings

Update Example:
```bash
POST /api/admin/settings.php
Content-Type: application/json

{
  "settings": {
    "gps_proximity_radius": "75",
    "face_confidence_threshold": "85",
    "attendance_warning_threshold": "70"
  },
  "category": "attendance"
}
```

## Mobile Application Integration

### SettingsRepository
Located at: `/sams-android-app/app/src/main/java/com/sams/app/data/repository/SettingsRepository.kt`

Features:
- Fetches settings from API and caches locally
- Automatically refreshes settings if cache is older than 24 hours
- Provides Flow-based access to settings
- Uses Android Datastore for local caching

Usage in ViewModels:
```kotlin
// Inject repository
@HiltViewModel
class StudentViewModel @Inject constructor(
    private val settingsRepository: SettingsRepository
) : ViewModel() {
    // Settings are automatically loaded
    val gpsRadius: Flow<Int> = settingsRepository.getGpsRadius()
}
```

### Accessing Settings in UI
```kotlin
// In Composable functions
val viewModel: StudentViewModel = hiltViewModel()
val gpsRadius = viewModel.getGpsRadius()
val faceConfidenceThreshold = viewModel.getFaceConfidenceThreshold()
val attendanceThreshold = viewModel.getAttendanceThreshold()

// Or using StateFlow
val attendanceSettings by viewModel.attendanceSettings.collectAsState()
val gpsRadiusValue = attendanceSettings["gps_radius"] as? Int ?: 50
```

## Admin Panel

Access at: `/admin/settings.php`

The admin panel provides:
- **Attendance Settings Card**: Configure GPS radius, face confidence, and attendance thresholds
- **Firebase Settings Card**: Configure FCM server key for push notifications
- **Email Settings Card**: Configure SMTP credentials
- **System Configuration Card**: Configure session timeout and other system settings
- **Security Options**: Clear sessions, clear cache, backup database
- **Quick Stats**: Display system statistics (total users, teachers, students)

## Settings Flow Diagram

```
┌─────────────────────────┐
│   Admin Panel           │
│  (settings.php)         │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────────────┐
│  API: /api/admin/settings.php   │
│  (Requires Admin Auth)           │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│  Database: system_settings      │
│  (Cached in SettingsHelper)     │
└────────────┬────────────────────┘
             │
   ┌─────────┘
   │
   ├─► Backend PHP APIs use SettingsHelper::get()
   │
   └─► Mobile App fetches from /api/public/settings.php
       ├─► Caches in Android Datastore
       └─► Uses in Attendance Marking, Dashboard, etc.
```

## Security Considerations

1. **Sensitive Settings**:
   - `fcm_server_key`: Not exposed via public API
   - `smtp_password`: Only accessible to authorized admins
   - Private settings are marked with `is_public = FALSE`

2. **Access Control**:
   - Admin settings API requires admin role authentication
   - Public settings API exposes only safe, non-sensitive settings
   - All sensitive data handled server-side only

3. **Encryption**:
   - Passwords (SMTP) can be encrypted before storage
   - Consider using environment-based configuration for secrets

## Setting Types

- **string**: Text values (default)
- **number**: Numeric values (int/float)
- **boolean**: True/False values
- **json**: Complex JSON structures

## Default Values

If a setting is not found, these defaults are used:

| Setting | Default |
|---------|---------|
| gps_proximity_radius | 50 |
| face_confidence_threshold | 75 |
| attendance_warning_threshold | 75 |
| session_lifetime | 3600 |
| enable_face_verification | true |
| enable_gps_verification | true |
| allow_late_attendance | true |
| late_threshold_minutes | 15 |
| max_login_attempts | 5 |
| lockout_duration | 900 |

## Maintenance

### Cache Management
- SettingsHelper caches settings in memory during request lifecycle
- Mobile app caches settings in Android Datastore (24-hour TTL)
- Admin changes are immediately reflected in SettingsHelper cache

### Clearing Cache
- Admin panel provides "Clear Cache" button
- Mobile app automatically refreshes after 24 hours
- Force refresh: Delete app data and reinstall

## Future Enhancements

1. **Role-based Settings**: Different settings for different roles
2. **Time-based Settings**: Schedule setting changes for specific times
3. **A/B Testing**: Test different attendance thresholds with student groups
4. **Audit Trail**: Log all setting changes with admin who made them
5. **Settings Versioning**: Keep history of setting changes
6. **Settings Validation**: Schema validation for setting values
7. **Webhooks**: Notify external systems when settings change
