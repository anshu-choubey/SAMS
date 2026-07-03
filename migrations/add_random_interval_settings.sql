-- =====================================================
-- Migration: Add Random Interval Settings for Attendance
-- Allows admin to configure random check intervals per class
-- Students will see only check count, not timing
-- =====================================================

-- Add columns to teacher_locations for random interval tracking
ALTER TABLE teacher_locations
ADD COLUMN IF NOT EXISTS random_intervals_enabled BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS min_interval_minutes INT DEFAULT 10,
ADD COLUMN IF NOT EXISTS max_interval_minutes INT DEFAULT 25,
ADD COLUMN IF NOT EXISTS hide_timing_from_students BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS next_check_time TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS auto_trigger_checks BOOLEAN DEFAULT TRUE;

-- Add columns to attendance_check_points to track scheduled vs triggered
ALTER TABLE attendance_check_points
ADD COLUMN IF NOT EXISTS is_scheduled BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS scheduled_time TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS was_auto_triggered BOOLEAN DEFAULT FALSE;

-- Add new system settings for global random interval defaults
INSERT INTO system_settings (`key`, value, type, description, category, is_public) VALUES
('attendance_random_intervals_enabled', 'true', 'boolean', 'Enable random interval attendance checks', 'Attendance', 0),
('attendance_min_interval_minutes', '10', 'integer', 'Minimum minutes between attendance checks', 'Attendance', 0),
('attendance_max_interval_minutes', '25', 'integer', 'Maximum minutes between attendance checks', 'Attendance', 0),
('attendance_hide_timing_from_students', 'true', 'boolean', 'Hide exact check timing from students (show only count)', 'Attendance', 0),
('attendance_auto_trigger_enabled', 'true', 'boolean', 'Enable auto-triggering of attendance checks', 'Attendance', 0),
('attendance_response_window_minutes', '3', 'integer', 'Minutes students have to respond to a check', 'Attendance', 0)
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Add security columns to attendance_check_responses for anti-spoofing
ALTER TABLE attendance_check_responses
ADD COLUMN IF NOT EXISTS is_suspicious BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS security_flags TEXT NULL,
ADD COLUMN IF NOT EXISTS liveness_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS challenges_completed INT DEFAULT 0;

-- =====================================================
-- Verify columns added
-- =====================================================
-- Run: DESCRIBE teacher_locations;
-- Run: DESCRIBE attendance_check_points;
-- Run: DESCRIBE attendance_check_responses;
