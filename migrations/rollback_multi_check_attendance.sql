-- =====================================================
-- Multi-Check Attendance System Rollback
-- Created: 2026-07-03
-- Description: Rolls back multi-check attendance changes
-- WARNING: This will delete all check point data!
-- =====================================================

-- 1. Drop foreign key from attendance table
ALTER TABLE attendance DROP FOREIGN KEY IF EXISTS fk_attendance_session;

-- 2. Drop new columns from attendance table
ALTER TABLE attendance 
DROP COLUMN IF EXISTS session_id,
DROP COLUMN IF EXISTS total_checks_required,
DROP COLUMN IF EXISTS successful_checks;

-- 3. Revert attendance table verification_status enum
ALTER TABLE attendance 
MODIFY COLUMN verification_status ENUM('success', 'gps_failed', 'face_failed', 'both_failed') NOT NULL;

-- 4. Revert attendance table status enum
ALTER TABLE attendance 
MODIFY COLUMN status ENUM('present', 'absent', 'late') DEFAULT 'present';

-- 5. Drop attendance_check_responses table
DROP TABLE IF EXISTS attendance_check_responses;

-- 6. Drop attendance_check_points table
DROP TABLE IF EXISTS attendance_check_points;

-- 7. Drop columns from teacher_locations table
ALTER TABLE teacher_locations 
DROP COLUMN IF EXISTS multi_check_enabled,
DROP COLUMN IF EXISTS total_checks_planned,
DROP COLUMN IF EXISTS checks_completed;

-- =====================================================
-- Verification
-- =====================================================

SELECT 'Multi-Check Attendance Rollback Completed!' AS status;

-- Verify tables are dropped
SELECT 
    'Tables Dropped' AS status,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'attendance_check_points') AS check_points_exists,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'attendance_check_responses') AS check_responses_exists;
