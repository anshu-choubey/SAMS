# Multi-Check Attendance System Guide

## Overview

This feature implements a **multi-check attendance system** to solve geofencing issues where students on different floors or nearby areas cannot accurately mark attendance due to GPS signal variations.

## Problem Solved

- **Geofencing Issues**: Students on 1st floor when class is on 2nd floor may have GPS signal issues
- **Single Point of Failure**: One-time attendance check can be affected by temporary GPS or network issues
- **False Positives**: Proxy attendance becomes harder with multiple random checks

## How It Works

### For Teachers

1. **Start Class with Multi-Check Mode** (Default: Enabled)
   - When starting a class, the system automatically enables multi-check mode
   - 2-3 random attendance checks will be planned during the class session
   - You can manually trigger checks at random intervals

2. **Trigger Random Attendance Checks**
   - During class, trigger attendance checks 2-3 times at unpredictable intervals
   - Each check has a 5-minute response window
   - Students get notified and must respond within the window

3. **End Class & Finalize**
   - When ending class, the system automatically finalizes attendance
   - Students who passed ≥60% of checks are marked **Present**
   - Students who failed most checks are marked **Absent**
   - Partial responses are tracked for review

### For Students

1. **Receive Check Notifications**
   - When teacher triggers a check, students get a notification
   - Students have a time window (default: 5 minutes) to respond

2. **Respond to Each Check**
   - Submit GPS coordinates + face verification for each check
   - Even if one check fails (GPS issue), other checks can compensate
   - Must successfully complete at least 60% of checks to be marked present

3. **View Active Checks**
   - Students can see pending checks they need to respond to
   - See time remaining for each check

## Database Changes

### New Tables

1. **`attendance_check_points`**
   - Stores each random attendance check triggered by teacher
   - Fields: session_id, check_number, check_time, window_end_time

2. **`attendance_check_responses`**
   - Stores student responses to each check
   - Fields: check_point_id, student_id, GPS data, face_confidence, verification_status

3. **Updated `attendance` table**
   - Added: `session_id`, `total_checks_required`, `successful_checks`
   - New status: `'partial'` for students who responded but didn't meet threshold

4. **Updated `teacher_locations` table**
   - Added: `multi_check_enabled`, `total_checks_planned`, `checks_completed`

## API Endpoints

### Teacher APIs

#### 1. Start Class (Modified)
```
POST /api/teacher/start-class.php
```
**Request:**
```json
{
  "schedule_id": 123,
  "latitude": 28.6139,
  "longitude": 77.2090,
  "duration_minutes": 60,
  "multi_check_enabled": true,
  "total_checks": 3
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "session_id": 456,
    "schedule_id": 123,
    "started_at": "2026-07-03 10:00:00",
    "multi_check_enabled": true,
    "total_checks_planned": 3
  }
}
```

#### 2. Trigger Attendance Check (New)
```
POST /api/teacher/trigger-attendance-check.php
```
**Request:**
```json
{
  "session_id": 456,
  "window_minutes": 5
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "check_point_id": 789,
    "check_number": 1,
    "session_id": 456,
    "triggered_at": "2026-07-03 10:15:00",
    "window_end_time": "2026-07-03 10:20:00",
    "window_minutes": 5,
    "expected_responses": 45
  }
}
```

#### 3. Finalize Attendance (New)
```
POST /api/teacher/finalize-attendance.php
```
**Request:**
```json
{
  "session_id": 456
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "session_id": 456,
    "total_students": 45,
    "present": 40,
    "absent": 3,
    "partial": 2,
    "attendance_percentage": 88.89,
    "total_checks_conducted": 3,
    "required_successful_checks": 2
  }
}
```

#### 4. End Class (Modified)
- Now automatically finalizes attendance for multi-check sessions

### Student APIs

#### 1. Get Active Checks (New)
```
GET /api/student/active-attendance-checks.php
```
**Response:**
```json
{
  "success": true,
  "data": {
    "active_checks": [
      {
        "check_point_id": 789,
        "check_number": 1,
        "session_id": 456,
        "subject_name": "Data Structures",
        "subject_code": "CS301",
        "teacher_name": "Dr. Smith",
        "classroom": "Room 204",
        "check_time": "2026-07-03 10:15:00",
        "window_end_time": "2026-07-03 10:20:00",
        "is_expired": false,
        "seconds_remaining": 240
      }
    ],
    "total_pending": 1
  }
}
```

#### 2. Respond to Check (New)
```
POST /api/student/respond-attendance-check.php
```
**Request:**
```json
{
  "check_point_id": 789,
  "latitude": 28.6140,
  "longitude": 77.2091,
  "face_confidence": 92.5,
  "device_info": "Samsung Galaxy S21"
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "response_id": 1001,
    "check_point_id": 789,
    "check_number": 1,
    "verification_status": "success",
    "distance_meters": 15.3,
    "face_confidence": 92.5,
    "is_late": false,
    "total_responses": 1,
    "successful_checks": 1
  },
  "message": "Check-in successful!"
}
```

## Configuration

### System Settings

- `gps_proximity_radius`: Max distance in meters (default: 50m)
- `face_confidence_threshold`: Min face confidence % (default: 85%)
- Default check window: 5 minutes
- Default success threshold: 60% of checks

## Example Workflow

1. **10:00 AM** - Teacher starts class with 3 planned checks
2. **10:15 AM** - Teacher triggers Check #1 (5-minute window)
   - Students respond between 10:15-10:20
3. **10:35 AM** - Teacher triggers Check #2 (5-minute window)
   - Students respond between 10:35-10:40
4. **10:50 AM** - Teacher triggers Check #3 (5-minute window)
   - Students respond between 10:50-10:55
5. **11:00 AM** - Teacher ends class
   - System finalizes: Students with 2+ successful checks = Present
   - Students with <2 successful checks = Absent/Partial

## Benefits

✅ **Reduces GPS errors**: Multiple checks average out GPS fluctuations  
✅ **Fair for students**: Temporary signal loss doesn't mean absence  
✅ **Prevents proxy**: Random timing makes proxy attendance difficult  
✅ **Flexible**: Teachers control when to trigger checks  
✅ **Transparent**: Students see exactly how many checks they passed

## Migration Notes

- Existing single-check attendance still works (backward compatible)
- Old attendance records remain unchanged
- New multi-check mode is opt-in per session (default: enabled)
- Run schema updates to create new tables

## Database Migration Script

```sql
-- Run these SQL commands to update your database

-- 1. Add new columns to teacher_locations
ALTER TABLE teacher_locations 
ADD COLUMN multi_check_enabled BOOLEAN DEFAULT FALSE AFTER is_active,
ADD COLUMN total_checks_planned INT DEFAULT 1 AFTER multi_check_enabled,
ADD COLUMN checks_completed INT DEFAULT 0 AFTER total_checks_planned;

-- 2. Create attendance_check_points table
CREATE TABLE IF NOT EXISTS attendance_check_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    schedule_id INT NOT NULL,
    check_number INT NOT NULL,
    check_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    window_end_time TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES teacher_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_active (is_active),
    INDEX idx_check_time (check_time, window_end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create attendance_check_responses table
CREATE TABLE IF NOT EXISTS attendance_check_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    check_point_id INT NOT NULL,
    student_id INT NOT NULL,
    schedule_id INT NOT NULL,
    session_id INT NOT NULL,
    response_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    student_latitude DECIMAL(10, 8),
    student_longitude DECIMAL(11, 8),
    teacher_latitude DECIMAL(10, 8),
    teacher_longitude DECIMAL(11, 8),
    distance_meters DECIMAL(8, 2),
    face_confidence_score DECIMAL(5, 2),
    verification_status ENUM('success', 'gps_failed', 'face_failed', 'both_failed', 'late') NOT NULL,
    device_info VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (check_point_id) REFERENCES attendance_check_points(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES teacher_locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_response (check_point_id, student_id),
    INDEX idx_student (student_id),
    INDEX idx_check_point (check_point_id),
    INDEX idx_verification (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Update attendance table
ALTER TABLE attendance
ADD COLUMN session_id INT AFTER department_id,
ADD COLUMN total_checks_required INT DEFAULT 1 AFTER attendance_time,
ADD COLUMN successful_checks INT DEFAULT 0 AFTER total_checks_required,
ADD FOREIGN KEY (session_id) REFERENCES teacher_locations(id) ON DELETE SET NULL,
ADD INDEX idx_session (session_id),
MODIFY COLUMN verification_status ENUM('success', 'gps_failed', 'face_failed', 'both_failed', 'partial') NOT NULL,
MODIFY COLUMN status ENUM('present', 'absent', 'late', 'partial') DEFAULT 'present';
```

## Android App Changes Required

### Teacher App

1. **Add "Trigger Check" button** on active class screen
2. **Show check counter** (e.g., "2/3 checks completed")
3. **Display responses in real-time** as students check in

### Student App

1. **Add notification listener** for attendance checks
2. **Show pending checks** with countdown timer
3. **"Respond to Check" button** that captures GPS + face
4. **Show progress** (e.g., "2/3 checks completed")

## Support

For questions or issues with multi-check attendance:
- Check system logs in `/var/log/sams/attendance.log`
- Review student responses in `attendance_check_responses` table
- Contact system administrator
