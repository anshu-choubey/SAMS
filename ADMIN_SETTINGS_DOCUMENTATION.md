# Admin Attendance Settings Documentation

## Overview

The SAMS backend now includes a comprehensive admin settings system for managing multi-check attendance and continuous monitoring features. All settings are stored in the `system_settings` database table and can be managed through REST APIs.

## Deployment Status

✅ **Deployed to Heroku**: Version v123  
✅ **Database Settings**: All default settings inserted  
✅ **API Endpoints**: Active and ready to use

---

## API Endpoints

### 1. Admin Settings Management API

**Endpoint**: `/api/admin/attendance-settings.php`  
**Authentication**: Admin role required  
**Methods**: GET, POST, PUT

#### GET - Retrieve Current Settings

```bash
GET /api/admin/attendance-settings.php
Authorization: Bearer {admin_token}
```

**Response**:
```json
{
  "success": true,
  "message": "Settings retrieved successfully",
  "data": {
    "attendance_multi_check_enabled": "true",
    "attendance_default_total_checks": "3",
    "attendance_auto_schedule_enabled": "true",
    "attendance_first_check_delay": "20",
    "attendance_min_check_interval": "15",
    "attendance_max_check_interval": "30",
    "attendance_check_window_minutes": "5",
    "continuous_monitoring_enabled": "true",
    "continuous_monitoring_required": "false",
    "continuous_auto_response_enabled": "true",
    "continuous_face_detection_interval": "30",
    "liveness_detection_enabled": "true",
    "liveness_min_score": "60",
    "face_confidence_threshold": "75"
  }
}
```

#### POST/PUT - Update Settings

```bash
POST /api/admin/attendance-settings.php
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "attendance_default_total_checks": "4",
  "attendance_first_check_delay": "15",
  "liveness_min_score": "70"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Successfully updated 3 settings",
  "data": {
    "updated_count": 3,
    "settings": {
      "attendance_default_total_checks": "4",
      "attendance_first_check_delay": "15",
      "liveness_min_score": "70"
    }
  }
}
```

### 2. Student Configuration API

**Endpoint**: `/api/student/continuous-monitoring-config.php`  
**Authentication**: Student role required  
**Method**: GET

```bash
GET /api/student/continuous-monitoring-config.php?session_id=123
Authorization: Bearer {student_token}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "session": {
      "session_id": 123,
      "schedule_id": 45,
      "subject_name": "Mathematics",
      "subject_code": "MATH101",
      "teacher_name": "John Doe",
      "started_at": "2026-07-03 09:00:00",
      "expected_end": "2026-07-03 10:30:00",
      "multi_check_enabled": true,
      "total_checks_planned": 3,
      "auto_schedule": true
    },
    "scheduled_checks": [
      {
        "check_number": 1,
        "check_time": "2026-07-03 09:20:00",
        "window_end_time": "2026-07-03 09:25:00",
        "is_active": true
      }
    ],
    "settings": {
      "continuous_monitoring_enabled": true,
      "continuous_monitoring_required": false,
      "auto_response_enabled": true,
      "face_detection_interval_seconds": 30,
      "liveness_detection_enabled": true,
      "liveness_min_score": 60,
      "face_confidence_threshold": 75,
      "check_window_minutes": 5
    }
  }
}
```

---

## Settings Reference

### Multi-Check Attendance Settings

| Setting Key | Type | Default | Description |
|------------|------|---------|-------------|
| `attendance_multi_check_enabled` | boolean | `true` | Enable/disable multi-check attendance system |
| `attendance_default_total_checks` | integer | `3` | Default number of attendance checks per class |
| `attendance_auto_schedule_enabled` | boolean | `true` | Auto-schedule checks at random intervals |
| `attendance_first_check_delay` | integer | `20` | Minutes to wait before first attendance check |
| `attendance_min_check_interval` | integer | `15` | Minimum minutes between checks |
| `attendance_max_check_interval` | integer | `30` | Maximum minutes between checks |
| `attendance_check_window_minutes` | integer | `5` | Response window duration (minutes) |

### Continuous Monitoring Settings

| Setting Key | Type | Default | Description |
|------------|------|---------|-------------|
| `continuous_monitoring_enabled` | boolean | `true` | Enable continuous monitoring feature |
| `continuous_monitoring_required` | boolean | `false` | Require students to use continuous mode |
| `continuous_auto_response_enabled` | boolean | `true` | Auto-respond to checks in continuous mode |
| `continuous_face_detection_interval` | integer | `30` | Face detection interval (seconds) |

### Face Recognition & Liveness Settings

| Setting Key | Type | Default | Description |
|------------|------|---------|-------------|
| `liveness_detection_enabled` | boolean | `true` | Enable ML Kit liveness detection |
| `liveness_min_score` | integer | `60` | Minimum liveness score (0-100) |
| `face_confidence_threshold` | integer | `75` | Minimum face confidence (0-100) |

---

## How It Works

### 1. Teacher Starts Class

When a teacher starts a class with auto-scheduling enabled:

1. System reads `attendance_default_total_checks` (e.g., 3 checks)
2. First check scheduled after `attendance_first_check_delay` minutes (e.g., 20 min)
3. Remaining checks spread across class duration with random intervals:
   - Base interval = (total_time - first_delay) / (checks - 1)
   - Random offset = ±5 minutes
   - Constrained by `attendance_min_check_interval` and `attendance_max_check_interval`

**Example Schedule** (90-minute class, 3 checks):
- Check 1: 20 minutes (09:20)
- Check 2: 53 minutes (09:53) [base 55 ± random]
- Check 3: 88 minutes (10:28) [base 90 ± random]

### 2. Student Uses Continuous Monitoring

When a student joins continuous monitoring:

1. App fetches config from `/api/student/continuous-monitoring-config.php`
2. Screen loads with all backend settings
3. Face detection runs every `continuous_face_detection_interval` seconds
4. When check becomes active, auto-responds if `continuous_auto_response_enabled`
5. Validates liveness score against `liveness_min_score`
6. Validates face confidence against `face_confidence_threshold`

### 3. Cron Job Auto-Triggers Checks

A cron job (`/api/cron/trigger-scheduled-checks.php`) runs every minute:

```cron
* * * * * curl https://sams-backend-73451.herokuapp.com/api/cron/trigger-scheduled-checks.php
```

It activates checks when their `check_time` arrives and `window_end_time` hasn't passed.

---

## Configuration Examples

### Example 1: Strict Attendance (High Security)

```json
{
  "attendance_default_total_checks": "4",
  "attendance_first_check_delay": "10",
  "attendance_check_window_minutes": "3",
  "continuous_monitoring_required": "true",
  "liveness_min_score": "75",
  "face_confidence_threshold": "85"
}
```

**Use Case**: Medical schools, high-stakes exams, strict attendance policies

### Example 2: Relaxed Attendance (Student-Friendly)

```json
{
  "attendance_default_total_checks": "2",
  "attendance_first_check_delay": "30",
  "attendance_check_window_minutes": "10",
  "continuous_monitoring_required": "false",
  "liveness_min_score": "50",
  "face_confidence_threshold": "65"
}
```

**Use Case**: Large lectures, introductory courses, flexible environments

### Example 3: Balanced Approach (Recommended)

```json
{
  "attendance_default_total_checks": "3",
  "attendance_first_check_delay": "20",
  "attendance_check_window_minutes": "5",
  "continuous_monitoring_required": "false",
  "liveness_min_score": "60",
  "face_confidence_threshold": "75"
}
```

**Use Case**: Standard classroom settings (current defaults)

---

## Testing the Settings

### 1. Test Admin API

```bash
# Get current settings
curl -X GET "https://sams-backend-73451.herokuapp.com/api/admin/attendance-settings.php" \
  -H "Authorization: Bearer {admin_token}"

# Update settings
curl -X POST "https://sams-backend-73451.herokuapp.com/api/admin/attendance-settings.php" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "attendance_default_total_checks": "4",
    "liveness_min_score": "70"
  }'
```

### 2. Test Student Config API

```bash
curl -X GET "https://sams-backend-73451.herokuapp.com/api/student/continuous-monitoring-config.php?session_id=123" \
  -H "Authorization: Bearer {student_token}"
```

---

## Android Integration

The Android app's `ContinuousAttendanceScreen` now fetches all configuration from the backend:

```kotlin
// Load config when screen opens
LaunchedEffect(sessionId) {
    viewModel.loadContinuousMonitoringConfig(sessionId)
}

// Config is used for:
// - Polling interval
// - Face detection interval
// - Liveness thresholds
// - Auto-response behavior
// - Session display info
```

**Key Changes**:
- Function parameters now optional (defaults loaded from backend)
- Loading state while fetching config
- Real-time configuration without app updates
- All thresholds controlled by admin

---

## Database Structure

Settings are stored in `system_settings` table:

```sql
CREATE TABLE system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) UNIQUE,
  value TEXT,
  type VARCHAR(20),
  description TEXT,
  category VARCHAR(50),
  validation_rule VARCHAR(100),
  is_public TINYINT(1),
  updated_by INT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## Next Steps

### 1. Create Admin UI Panel

Build a web interface for admins to edit settings visually:

```html
<form id="attendanceSettings">
  <label>Total Checks per Class</label>
  <input type="number" name="attendance_default_total_checks" value="3">
  
  <label>First Check Delay (minutes)</label>
  <input type="number" name="attendance_first_check_delay" value="20">
  
  <!-- More fields... -->
  
  <button type="submit">Save Settings</button>
</form>
```

### 2. Add Setting History/Audit Log

Track who changed what and when:

```sql
CREATE TABLE settings_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100),
  old_value TEXT,
  new_value TEXT,
  changed_by INT,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 3. Add Validation Rules

Implement server-side validation:

```php
$validationRules = [
    'attendance_default_total_checks' => ['type' => 'integer', 'min' => 1, 'max' => 10],
    'liveness_min_score' => ['type' => 'integer', 'min' => 0, 'max' => 100],
    // ...
];
```

### 4. Add Role-Based Setting Visibility

Allow certain settings to be public (visible to students/teachers):

```php
// Public settings students can see
$publicSettings = [
    'continuous_monitoring_enabled',
    'liveness_detection_enabled'
];
```

---

## Troubleshooting

### Settings Not Updating

1. Check admin authentication: `Auth::hasRole('admin')`
2. Verify database connection
3. Check Heroku logs: `heroku logs --tail -a sams-backend-73451`

### Android Not Receiving Config

1. Ensure `session_id` is valid and active
2. Check student authentication token
3. Verify API endpoint URL in `ApiService.kt`
4. Check ViewModel state flow updates

### Scheduled Checks Not Triggering

1. Verify cron job is configured on Heroku
2. Check `teacher_locations.auto_schedule` is `TRUE`
3. Ensure `attendance_check_points` has scheduled entries
4. Check system time matches `check_time` values

---

## Support

For issues or questions:
- Check Heroku logs: `heroku logs --tail -a sams-backend-73451`
- Review API responses for error messages
- Validate JSON request format
- Ensure proper authentication headers

---

## Version History

- **v123** (July 3, 2026): Fixed column names, added ContinuousAttendanceScreen backend integration
- **v122** (July 3, 2026): Added admin settings APIs and continuous monitoring config
- **v121** (July 2, 2026): Multi-check attendance system deployed
