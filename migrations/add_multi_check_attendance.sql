-- =====================================================
-- Multi-Check Attendance System Migration
-- Created: 2026-07-03
-- Description: Adds support for 2-3 random attendance checks per class
-- =====================================================

-- 1. Add new columns to teacher_locations table
ALTER TABLE teacher_locations 
ADD COLUMN IF NOT EXISTS multi_check_enabled BOOLEAN DEFAULT FALSE AFTER is_active,
ADD COLUMN IF NOT EXISTS total_checks_planned INT DEFAULT 1 AFTER multi_check_enabled,
ADD COLUMN IF NOT EXISTS checks_completed INT DEFAULT 0 AFTER total_checks_planned;

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

-- 4. Alter attendance table to add multi-check support
ALTER TABLE attendance
ADD COLUMN IF NOT EXISTS session_id INT AFTER department_id,
ADD COLUMN IF NOT EXISTS total_checks_required INT DEFAULT 1 AFTER attendance_time,
ADD COLUMN IF NOT EXISTS successful_checks INT DEFAULT 0 AFTER total_checks_required;

-- 5. Add foreign key for session_id if not exists
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                  WHERE TABLE_NAME = 'attendance' 
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY' 
                  AND CONSTRAINT_NAME = 'fk_attendance_session');

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE attendance ADD CONSTRAINT fk_attendance_session FOREIGN KEY (session_id) REFERENCES teacher_locations(id) ON DELETE SET NULL',
    'SELECT ''Foreign key already exists'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Add index for session_id if not exists
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE TABLE_NAME = 'attendance' 
                     AND INDEX_NAME = 'idx_session');

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE attendance ADD INDEX idx_session (session_id)',
    'SELECT ''Index already exists'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Update attendance table verification_status enum to include 'partial'
ALTER TABLE attendance 
MODIFY COLUMN verification_status ENUM('success', 'gps_failed', 'face_failed', 'both_failed', 'partial') NOT NULL;

-- 8. Update attendance table status enum to include 'partial'
ALTER TABLE attendance 
MODIFY COLUMN status ENUM('present', 'absent', 'late', 'partial') DEFAULT 'present';

-- =====================================================
-- Verification Queries
-- =====================================================

-- Check if all tables exist
SELECT 
    'Tables Created' AS status,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'attendance_check_points') AS check_points_table,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'attendance_check_responses') AS check_responses_table;

-- Check if columns were added
SELECT 
    'Columns Added' AS status,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'teacher_locations' AND COLUMN_NAME = 'multi_check_enabled') AS multi_check_enabled,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'attendance' AND COLUMN_NAME = 'session_id') AS session_id,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'attendance' AND COLUMN_NAME = 'total_checks_required') AS total_checks_required;

-- Show table structures
SHOW CREATE TABLE attendance_check_points\G
SHOW CREATE TABLE attendance_check_responses\G

-- =====================================================
-- Migration Complete
-- =====================================================
SELECT 'Multi-Check Attendance Migration Completed Successfully!' AS status;
