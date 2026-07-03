# 📍 Random Interval & Timing Configuration Guide

## ✅ Backend is Updated in Heroku (v122)

Your backend is live with all the random interval features!

---

## 🎯 Where to Configure Random Intervals

### 1. **API Request Parameters** (Teacher App)

When starting a class, send these parameters:

```json
POST /api/teacher/start-class.php

{
  "schedule_id": 123,
  "latitude": 28.6139,
  "longitude": 77.2090,
  "duration_minutes": 60,              // Class duration
  "multi_check_enabled": true,          // Enable multi-check
  "total_checks": 3,                    // How many checks (1-5)
  "auto_schedule": true,                // ✅ Enable auto-scheduling
  "first_check_delay": 20               // ✅ First check after X minutes
}
```

### 2. **Random Interval Calculation** (Backend Logic)

**Location**: `/api/teacher/start-class.php` (Lines 171-185)

```php
// Calculate random intervals (spread across duration)
$intervals = [];
$remainingTime = $durationMinutes - $firstCheckDelay;
$intervalGap = floor($remainingTime / max(1, $totalChecksPlanned - 1));

for ($i = 0; $i < $totalChecksPlanned; $i++) {
    if ($i == 0) {
        // First check: exactly at firstCheckDelay
        $intervals[] = $firstCheckDelay;
    } else {
        // Subsequent checks: add ±5 minutes randomness
        $baseInterval = $firstCheckDelay + ($intervalGap * $i);
        $randomOffset = rand(-5, 5);
        $intervals[] = max($firstCheckDelay, min($durationMinutes - 5, $baseInterval + $randomOffset));
    }
}
```

**How it works**:
- **First Check**: Always at `firstCheckDelay` minutes (default: 20)
- **Remaining Checks**: Evenly spread with **±5 minutes randomness**
- **Example** (60-min class, 3 checks, first_delay=20):
  - Check 1: 20 minutes
  - Check 2: 40 ± 5 = 35-45 minutes (random)
  - Check 3: 55 ± 5 = 50-60 minutes (random)

---

## 📊 Configuration Examples

### Example 1: Short Class (1 hour, 2 checks)

```json
{
  "duration_minutes": 60,
  "total_checks": 2,
  "auto_schedule": true,
  "first_check_delay": 20
}
```

**Result**:
- Check 1: 20 min
- Check 2: 45-55 min (random)

### Example 2: Long Class (2 hours, 3 checks)

```json
{
  "duration_minutes": 120,
  "total_checks": 3,
  "auto_schedule": true,
  "first_check_delay": 25
}
```

**Result**:
- Check 1: 25 min
- Check 2: 65-75 min (random)
- Check 3: 105-115 min (random)

### Example 3: Frequent Checks (1 hour, 4 checks)

```json
{
  "duration_minutes": 60,
  "total_checks": 4,
  "auto_schedule": true,
  "first_check_delay": 15
}
```

**Result**:
- Check 1: 15 min
- Check 2: 25-35 min (random)
- Check 3: 35-45 min (random)
- Check 4: 45-55 min (random)

---

## 🔧 Where Values Are Stored

### Database Table: `teacher_locations`

```sql
CREATE TABLE teacher_locations (
    ...
    multi_check_enabled BOOLEAN DEFAULT FALSE,
    total_checks_planned INT DEFAULT 1,
    auto_schedule BOOLEAN DEFAULT FALSE,
    first_check_delay INT DEFAULT 20,
    checks_completed INT DEFAULT 0,
    ...
);
```

### Database Table: `attendance_check_points`

```sql
CREATE TABLE attendance_check_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    schedule_id INT NOT NULL,
    check_number INT NOT NULL,
    check_time TIMESTAMP NOT NULL,           -- ✅ Scheduled time (random)
    window_end_time TIMESTAMP NOT NULL,      -- ✅ Response window end
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Example Data**:
| id | session_id | check_number | check_time          | window_end_time     |
|----|------------|--------------|---------------------|---------------------|
| 1  | 456        | 1            | 2026-07-03 10:20:00 | 2026-07-03 10:25:00 |
| 2  | 456        | 2            | 2026-07-03 10:43:00 | 2026-07-03 10:48:00 |
| 3  | 456        | 3            | 2026-07-03 10:57:00 | 2026-07-03 11:02:00 |

---

## 🎮 Teacher Control Options

### Option 1: Fully Automatic (Recommended)

```json
{
  "auto_schedule": true,
  "total_checks": 3,
  "first_check_delay": 20
}
```

- Teacher doesn't need to do anything
- System triggers checks automatically
- Students get notifications

### Option 2: Manual Trigger

```json
{
  "auto_schedule": false,
  "total_checks": 3
}
```

- Teacher manually triggers each check
- Use: `POST /api/teacher/trigger-attendance-check.php`
- More control, but requires attention

### Option 3: Hybrid

```json
{
  "auto_schedule": true,
  "total_checks": 2
}
```

- 2 auto-scheduled checks
- Teacher can manually trigger additional checks if needed

---

## ⚙️ System Settings (Database)

Add these to `system_settings` table for global defaults:

```sql
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('default_multi_check_enabled', 'true', 'Enable multi-check by default'),
('default_total_checks', '3', 'Default number of checks per class'),
('default_auto_schedule', 'true', 'Enable auto-schedule by default'),
('default_first_check_delay', '20', 'Default minutes before first check'),
('min_check_interval', '15', 'Minimum minutes between checks'),
('max_check_interval', '30', 'Maximum minutes between checks'),
('default_check_window', '5', 'Default response window in minutes');
```

---

## 🤖 Cron Job (Auto-Trigger)

**File**: `/api/cron/trigger-scheduled-checks.php`

**What it does**:
- Runs every minute
- Finds checks scheduled for current time
- Activates them (sets `is_active = TRUE`)
- Sends push notifications to students

**Setup on Heroku**:
```bash
# Install Heroku Scheduler add-on
heroku addons:create scheduler:standard --app sams-backend-73451

# Add job in Heroku dashboard:
# Task: php api/cron/trigger-scheduled-checks.php
# Frequency: Every 10 minutes
```

**Manual Test**:
```bash
php api/cron/trigger-scheduled-checks.php
```

---

## 📱 Android App Integration

### Start Session with Random Intervals

```kotlin
// In TeacherViewModel
fun startSessionWithAutoSchedule(
    scheduleId: Int,
    latitude: Double,
    longitude: Double,
    totalChecks: Int = 3,
    firstCheckDelay: Int = 20,
    durationMinutes: Int = 60
) {
    viewModelScope.launch {
        val request = StartSessionRequest(
            scheduleId = scheduleId,
            latitude = latitude,
            longitude = longitude,
            multiCheckEnabled = true,
            totalChecks = totalChecks,
            autoSchedule = true,
            firstCheckDelay = firstCheckDelay,
            durationMinutes = durationMinutes
        )
        
        repository.startSession(request)
            .onSuccess { data ->
                // data.scheduledCheckTimes = [20, 43, 57]
                println("Checks scheduled at: ${data.scheduledCheckTimes}")
            }
    }
}
```

### UI Configuration (Teacher)

```kotlin
// Teacher Start Class Screen
var totalChecks by remember { mutableStateOf(3) }
var firstCheckDelay by remember { mutableStateOf(20) }
var autoSchedule by remember { mutableStateOf(true) }

Column {
    Text("Number of Checks")
    Slider(
        value = totalChecks.toFloat(),
        onValueChange = { totalChecks = it.toInt() },
        valueRange = 1f..5f,
        steps = 4
    )
    Text("$totalChecks checks")
    
    Spacer(height = 16.dp)
    
    Text("First Check After (minutes)")
    Slider(
        value = firstCheckDelay.toFloat(),
        onValueChange = { firstCheckDelay = it.toInt() },
        valueRange = 10f..30f,
        steps = 4
    )
    Text("$firstCheckDelay minutes")
    
    Spacer(height = 16.dp)
    
    Row(verticalAlignment = Alignment.CenterVertically) {
        Checkbox(
            checked = autoSchedule,
            onCheckedChange = { autoSchedule = it }
        )
        Text("Auto-schedule checks")
    }
}
```

---

## 📈 Response Timeline Visualization

```
Class Start                                           Class End
|                                                           |
├──────20min──────┤                                        |
                  └─> Check 1 (5min window)                |
                                                           |
├────────────40±5min────────────┤                         |
                                └─> Check 2 (5min window)  |
                                                           |
├──────────────────────55±5min──────────────────┤         |
                                                 └─> Check 3 (5min window)
```

---

## 🎯 Key Points

1. **Random Offset**: ±5 minutes for each check (except first)
2. **First Check**: Always at exact `firstCheckDelay` time
3. **Even Distribution**: Checks spread evenly across class duration
4. **Window Duration**: 5 minutes response window (configurable)
5. **Auto-Trigger**: Cron job activates checks at scheduled times
6. **Manual Override**: Teacher can still trigger manual checks

---

## 🔍 Verify Configuration

Check database:

```sql
-- Check session configuration
SELECT 
    id,
    schedule_id,
    multi_check_enabled,
    total_checks_planned,
    auto_schedule,
    first_check_delay,
    checks_completed
FROM teacher_locations
WHERE is_active = TRUE;

-- Check scheduled times
SELECT 
    id,
    session_id,
    check_number,
    check_time,
    window_end_time,
    is_active
FROM attendance_check_points
WHERE session_id = 456
ORDER BY check_number;
```

---

## 📊 API Response

When starting a class with auto-schedule:

```json
{
  "success": true,
  "data": {
    "session_id": 456,
    "schedule_id": 123,
    "started_at": "2026-07-03 10:00:00",
    "expected_end": "2026-07-03 11:00:00",
    "multi_check_enabled": true,
    "total_checks_planned": 3,
    "auto_schedule": true,
    "scheduled_check_times": [20, 43, 57]  // ✅ Random intervals in minutes
  },
  "message": "Class started. 3 checks auto-scheduled."
}
```

---

**Status**: ✅ Fully Implemented and Deployed
**Location**: Heroku v122 - `sams-backend-73451`
**Files Modified**: 
- `api/teacher/start-class.php` (random interval logic)
- `api/cron/trigger-scheduled-checks.php` (auto-trigger)
- Database: `teacher_locations` & `attendance_check_points` tables
