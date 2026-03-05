-- =====================================================
-- SAMS (Student Attendance Management System) Database
-- Version: 2.0
-- Last Updated: 2026-02-11
-- =====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS sams_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sams_db;

-- Drop tables if exist (for fresh install)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS fcm_tokens;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS teacher_locations;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS teacher_assignments;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 1. Users Table
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    phone VARCHAR(15),
    profile_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Departments Table
-- =====================================================
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    hod_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hod_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. Login Attempts Table (Security)
-- =====================================================
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_email_time (email, attempt_time),
    INDEX idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. Subjects Table
-- =====================================================
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    department_id INT NOT NULL,
    credits INT DEFAULT 3,
    semester INT,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    INDEX idx_department (department_id),
    INDEX idx_code (code),
    INDEX idx_semester (semester),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. Students Table (extends users)
-- =====================================================
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    roll_number VARCHAR(50) UNIQUE NOT NULL,
    department_id INT NOT NULL,
    semester INT NOT NULL,
    section VARCHAR(10),
    batch_year INT,
    admission_date DATE,
    face_registered BOOLEAN DEFAULT FALSE,
    face_data TEXT, -- Encrypted facial embedding (AES-256)
    face_photo LONGBLOB, -- Base64 encoded face photo from registration
    face_registration_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    INDEX idx_roll_number (roll_number),
    INDEX idx_department_semester (department_id, semester),
    INDEX idx_section (section),
    INDEX idx_face_registered (face_registered)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. Teachers Table (extends users)
-- =====================================================
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    primary_department_id INT,
    designation VARCHAR(100),
    qualification VARCHAR(255),
    joining_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (primary_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_employee_id (employee_id),
    INDEX idx_department (primary_department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. Teacher Assignments Table (Multi-Branch Support)
-- =====================================================
CREATE TABLE teacher_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    department_id INT NOT NULL,
    section VARCHAR(10),
    academic_year VARCHAR(20) NOT NULL,
    semester INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (teacher_id, subject_id, department_id, section, semester, academic_year),
    INDEX idx_teacher (teacher_id),
    INDEX idx_subject (subject_id),
    INDEX idx_department (department_id),
    INDEX idx_academic_year (academic_year),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. Schedules Table
-- =====================================================
CREATE TABLE schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    classroom VARCHAR(50),
    building VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    INDEX idx_assignment (assignment_id),
    INDEX idx_day_time (day_of_week, start_time),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. Teacher Locations Table (Attendance Session Data)
-- =====================================================
CREATE TABLE teacher_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    schedule_id INT NOT NULL,
    assignment_id INT NOT NULL,
    department_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    INDEX idx_teacher_active (teacher_id, is_active),
    INDEX idx_schedule (schedule_id),
    INDEX idx_session (session_start, session_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. Attendance Table
-- =====================================================
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    schedule_id INT NOT NULL,
    assignment_id INT NOT NULL,
    teacher_id INT NOT NULL,
    department_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    student_latitude DECIMAL(10, 8),
    student_longitude DECIMAL(11, 8),
    teacher_latitude DECIMAL(10, 8),
    teacher_longitude DECIMAL(11, 8),
    distance_meters DECIMAL(8, 2),
    face_confidence_score DECIMAL(5, 2),
    verification_status ENUM('success', 'gps_failed', 'face_failed', 'both_failed') NOT NULL,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    device_info VARCHAR(255),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, schedule_id, attendance_date),
    INDEX idx_student (student_id),
    INDEX idx_date (attendance_date),
    INDEX idx_department (department_id),
    INDEX idx_verification (verification_status),
    INDEX idx_status (status),
    INDEX idx_student_date (student_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. FCM Tokens Table (Push Notifications)
-- =====================================================
CREATE TABLE fcm_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(500) NOT NULL,
    device_type ENUM('android', 'ios', 'web') DEFAULT 'android',
    device_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_token (token(255)),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. Notifications Table
-- =====================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('attendance_alert', 'low_attendance', 'system', 'schedule_change', 'face_reregister') NOT NULL,
    target_role ENUM('admin', 'teacher', 'student', 'all'),
    target_user_id INT NULL,
    target_department_id INT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    is_sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_target (target_role, target_user_id),
    INDEX idx_sent (is_sent),
    INDEX idx_read (is_read),
    INDEX idx_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. System Settings Table
-- =====================================================
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. Sessions Table (Session-based Authentication)
-- =====================================================
CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_info VARCHAR(255),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. Audit Logs Table
-- =====================================================
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_table (table_name),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VIEWS
-- =====================================================

-- View: Student Attendance Summary
CREATE OR REPLACE VIEW v_student_attendance_summary AS
SELECT 
    s.id as student_id,
    s.roll_number,
    u.full_name,
    d.name as department_name,
    s.semester,
    s.section,
    COUNT(a.id) as total_classes,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
FROM students s
JOIN users u ON s.user_id = u.id
JOIN departments d ON s.department_id = d.id
LEFT JOIN attendance a ON s.id = a.student_id
GROUP BY s.id;

-- View: Teacher Schedule Overview
CREATE OR REPLACE VIEW v_teacher_schedules AS
SELECT 
    t.id as teacher_id,
    t.employee_id,
    u.full_name as teacher_name,
    sub.name as subject_name,
    sub.code as subject_code,
    d.name as department_name,
    ta.section,
    ta.semester,
    sc.day_of_week,
    sc.start_time,
    sc.end_time,
    sc.classroom,
    sc.building,
    sc.is_active
FROM teachers t
JOIN users u ON t.user_id = u.id
JOIN teacher_assignments ta ON t.id = ta.teacher_id
JOIN subjects sub ON ta.subject_id = sub.id
JOIN departments d ON ta.department_id = d.id
JOIN schedules sc ON ta.id = sc.assignment_id
WHERE ta.is_active = TRUE;

-- View: Today's Active Classes
CREATE OR REPLACE VIEW v_todays_classes AS
SELECT 
    sc.id as schedule_id,
    ta.id as assignment_id,
    u.full_name as teacher_name,
    sub.name as subject_name,
    sub.code as subject_code,
    d.name as department_name,
    ta.section,
    sc.start_time,
    sc.end_time,
    sc.classroom,
    sc.building,
    CASE 
        WHEN tl.is_active = TRUE THEN 'active'
        ELSE 'not_started'
    END as session_status
FROM schedules sc
JOIN teacher_assignments ta ON sc.assignment_id = ta.id
JOIN teachers t ON ta.teacher_id = t.id
JOIN users u ON t.user_id = u.id
JOIN subjects sub ON ta.subject_id = sub.id
JOIN departments d ON ta.department_id = d.id
LEFT JOIN teacher_locations tl ON sc.id = tl.schedule_id AND tl.is_active = TRUE
WHERE sc.day_of_week = DAYNAME(CURDATE())
AND sc.is_active = TRUE
AND ta.is_active = TRUE;

-- View: Low Attendance Students
CREATE OR REPLACE VIEW v_low_attendance_students AS
SELECT 
    s.id as student_id,
    s.roll_number,
    u.full_name,
    u.email,
    d.name as department_name,
    s.semester,
    s.section,
    COUNT(a.id) as total_classes,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
FROM students s
JOIN users u ON s.user_id = u.id
JOIN departments d ON s.department_id = d.id
LEFT JOIN attendance a ON s.id = a.student_id
GROUP BY s.id
HAVING attendance_percentage < 75 OR attendance_percentage IS NULL;

-- =====================================================
-- DEFAULT DATA INSERTS
-- =====================================================

-- Default Admin User (Password: Admin@123)
INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES 
('System Administrator', 'admin@sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- Default System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('app_name', 'SAMS - Student Attendance Management System', 'string', 'Application name', TRUE),
('app_version', '2.0.0', 'string', 'Application version', TRUE),
('institution_name', 'Your Institution Name', 'string', 'Name of the institution', TRUE),
('institution_logo', '', 'string', 'URL to institution logo', TRUE),
('gps_proximity_radius', '50', 'number', 'GPS proximity radius in meters', FALSE),
('face_confidence_threshold', '75', 'number', 'Minimum face confidence score percentage', FALSE),
('attendance_warning_threshold', '75', 'number', 'Attendance percentage below which warning is triggered', FALSE),
('session_lifetime', '604800', 'number', 'Session lifetime in seconds', FALSE),
('max_login_attempts', '5', 'number', 'Maximum failed login attempts before lockout', FALSE),
('lockout_duration', '900', 'number', 'Account lockout duration in seconds', FALSE),
('fcm_server_key', '', 'string', 'Firebase Cloud Messaging server key', FALSE),
('allow_late_attendance', 'true', 'boolean', 'Allow marking attendance as late', FALSE),
('late_threshold_minutes', '15', 'number', 'Minutes after class starts to mark as late', FALSE),
('enable_face_verification', 'true', 'boolean', 'Enable face verification for attendance', FALSE),
('enable_gps_verification', 'true', 'boolean', 'Enable GPS verification for attendance', FALSE),
('academic_year', '2025-26', 'string', 'Current academic year', TRUE),
('current_semester', '2', 'number', 'Current semester', TRUE),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode', FALSE),
('smtp_host', '', 'string', 'SMTP server host', FALSE),
('smtp_port', '587', 'number', 'SMTP server port', FALSE),
('smtp_username', '', 'string', 'SMTP username', FALSE),
('smtp_password', '', 'string', 'SMTP password (encrypted)', FALSE),
('support_email', 'support@sams.edu', 'string', 'Support email address', TRUE);

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure: Clean expired sessions
CREATE PROCEDURE sp_clean_expired_sessions()
BEGIN
    DELETE FROM sessions WHERE expires_at < NOW();
END //

-- Procedure: Clean old login attempts (older than 24 hours)
CREATE PROCEDURE sp_clean_login_attempts()
BEGIN
    DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END //

-- Procedure: Get student attendance percentage
CREATE PROCEDURE sp_get_student_attendance(
    IN p_student_id INT,
    IN p_from_date DATE,
    IN p_to_date DATE
)
BEGIN
    SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
    FROM attendance
    WHERE student_id = p_student_id
    AND attendance_date BETWEEN p_from_date AND p_to_date;
END //

-- Procedure: Deactivate teacher attendance session
CREATE PROCEDURE sp_end_attendance_session(IN p_teacher_id INT)
BEGIN
    UPDATE teacher_locations 
    SET is_active = FALSE, session_end = NOW()
    WHERE teacher_id = p_teacher_id AND is_active = TRUE;
END //

DELIMITER ;

-- =====================================================
-- EVENTS (Scheduled Tasks)
-- =====================================================

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- Event: Clean expired sessions daily
CREATE EVENT IF NOT EXISTS evt_clean_sessions
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO CALL sp_clean_expired_sessions();

-- Event: Clean login attempts daily
CREATE EVENT IF NOT EXISTS evt_clean_login_attempts
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO CALL sp_clean_login_attempts();

-- Event: Auto-end attendance sessions after 2 hours
CREATE EVENT IF NOT EXISTS evt_auto_end_sessions
ON SCHEDULE EVERY 30 MINUTE
STARTS CURRENT_TIMESTAMP
DO
    UPDATE teacher_locations 
    SET is_active = FALSE, session_end = NOW()
    WHERE is_active = TRUE 
    AND session_start < DATE_SUB(NOW(), INTERVAL 2 HOUR);

-- =====================================================
-- TRIGGERS
-- =====================================================

DELIMITER //

-- Trigger: Update user last_login on session create
CREATE TRIGGER trg_update_last_login
AFTER INSERT ON sessions
FOR EACH ROW
BEGIN
    UPDATE users SET last_login = NOW() WHERE id = NEW.user_id;
END //

-- Trigger: Log user updates
CREATE TRIGGER trg_audit_user_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values)
    VALUES (
        NEW.id,
        'UPDATE',
        'users',
        NEW.id,
        JSON_OBJECT(
            'full_name', OLD.full_name,
            'email', OLD.email,
            'role', OLD.role,
            'is_active', OLD.is_active
        ),
        JSON_OBJECT(
            'full_name', NEW.full_name,
            'email', NEW.email,
            'role', NEW.role,
            'is_active', NEW.is_active
        )
    );
END //

DELIMITER ;

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- =====================================================

-- Insert Admin User
INSERT INTO users (full_name, email, password_hash, role, phone, is_active) VALUES
('System Administrator', 'admin@sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+91-9876543210', TRUE);

-- Insert Departments
INSERT INTO departments (name, code, description, is_active) VALUES
('Computer Science', 'CSE', 'Department of Computer Science and Engineering', TRUE),
('Information Technology', 'IT', 'Department of Information Technology', TRUE),
('Electronics', 'ECE', 'Department of Electronics and Communication', TRUE),
('Mechanical Engineering', 'ME', 'Department of Mechanical Engineering', TRUE);

-- Insert Subjects
INSERT INTO subjects (name, code, department_id, credits, semester, description, is_active) VALUES
('Data Structures', 'CSE101', 1, 4, 3, 'Introduction to Data Structures and Algorithms', TRUE),
('Database Management', 'CSE201', 1, 3, 4, 'Database Design and Management Systems', TRUE),
('Web Development', 'IT101', 2, 3, 3, 'Modern Web Development Technologies', TRUE),
('Computer Networks', 'CSE301', 1, 3, 5, 'Computer Networks and Communication', TRUE),
('Software Engineering', 'CSE401', 1, 3, 6, 'Software Development Life Cycle', TRUE),
('Digital Electronics', 'ECE101', 3, 3, 3, 'Digital Logic and Circuit Design', TRUE),
('Thermodynamics', 'ME101', 4, 3, 3, 'Engineering Thermodynamics', TRUE);

-- Insert Teacher Users
INSERT INTO users (full_name, email, password_hash, role, phone, is_active) VALUES
('Dr. Rajesh Kumar', 'rajesh.kumar@sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', '+91-9876543211', TRUE),
('Prof. Priya Sharma', 'priya.sharma@sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', '+91-9876543212', TRUE),
('Dr. Amit Singh', 'amit.singh@sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', '+91-9876543213', TRUE);

-- Insert Teachers
INSERT INTO teachers (user_id, employee_id, primary_department_id, designation, qualification, joining_date) VALUES
(2, 'T001', 1, 'Associate Professor', 'PhD Computer Science', '2020-01-15'),
(3, 'T002', 1, 'Assistant Professor', 'MTech Computer Science', '2021-07-01'),
(4, 'T003', 2, 'Professor', 'PhD Information Technology', '2019-03-20');

-- Insert Student Users
INSERT INTO users (full_name, email, password_hash, role, phone, is_active) VALUES
('Amit Kumar', 'amit.kumar@student.sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '+91-9876543221', TRUE),
('Priya Patel', 'priya.patel@student.sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '+91-9876543222', TRUE),
('Rahul Sharma', 'rahul.sharma@student.sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '+91-9876543223', TRUE),
('Sneha Gupta', 'sneha.gupta@student.sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '+91-9876543224', TRUE),
('Vikram Singh', 'vikram.singh@student.sams.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '+91-9876543225', TRUE);

-- Insert Students
INSERT INTO students (user_id, roll_number, department_id, semester, section, batch_year, admission_date, face_registered) VALUES
(5, 'CSE2024001', 1, 4, 'A', 2024, '2024-07-01', TRUE),
(6, 'CSE2024002', 1, 4, 'A', 2024, '2024-07-01', TRUE),
(7, 'IT2024001', 2, 4, 'B', 2024, '2024-07-01', FALSE),
(8, 'ECE2024001', 3, 4, 'A', 2024, '2024-07-01', TRUE),
(9, 'ME2024001', 4, 4, 'A', 2024, '2024-07-01', FALSE);

-- Insert Teacher Assignments
INSERT INTO teacher_assignments (teacher_id, subject_id, department_id, academic_year, semester, is_active) VALUES
(1, 1, 1, '2024-2025', 4, TRUE), -- Rajesh Kumar teaches Data Structures
(1, 2, 1, '2024-2025', 4, TRUE), -- Rajesh Kumar teaches Database Management
(2, 3, 2, '2024-2025', 4, TRUE), -- Priya Sharma teaches Web Development
(3, 4, 1, '2024-2025', 6, TRUE); -- Amit Singh teaches Computer Networks

-- Insert Schedules
INSERT INTO schedules (subject_id, teacher_id, day_of_week, start_time, end_time, room_number, academic_year, semester, is_active) VALUES
(1, 1, 'Monday', '09:00:00', '10:30:00', 'CS-101', '2024-2025', 4, TRUE),
(1, 1, 'Wednesday', '09:00:00', '10:30:00', 'CS-101', '2024-2025', 4, TRUE),
(2, 1, 'Tuesday', '11:00:00', '12:30:00', 'CS-102', '2024-2025', 4, TRUE),
(2, 1, 'Thursday', '11:00:00', '12:30:00', 'CS-102', '2024-2025', 4, TRUE),
(3, 2, 'Monday', '14:00:00', '15:30:00', 'IT-201', '2024-2025', 4, TRUE),
(3, 2, 'Friday', '14:00:00', '15:30:00', 'IT-201', '2024-2025', 4, TRUE);

-- Insert Sample Attendance Records
INSERT INTO attendance (student_id, schedule_id, attendance_date, status, marked_by, location_lat, location_lng, device_info, ip_address) VALUES
(1, 1, '2024-02-05', 'present', 1, 28.6139, 77.2090, 'Mobile App', '192.168.1.100'),
(1, 2, '2024-02-07', 'present', 1, 28.6139, 77.2090, 'Mobile App', '192.168.1.100'),
(2, 1, '2024-02-05', 'present', 1, 28.6139, 77.2090, 'Mobile App', '192.168.1.101'),
(2, 2, '2024-02-07', 'late', 1, 28.6139, 77.2090, 'Mobile App', '192.168.1.101'),
(4, 1, '2024-02-05', 'absent', 1, NULL, NULL, 'Web Portal', '192.168.1.102');

-- Insert System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_system) VALUES
('app_name', 'SAMS - Student Attendance Management System', 'string', 'Application name', TRUE),
('app_version', '2.0.0', 'string', 'Current application version', TRUE),
('attendance_threshold', '100', 'number', 'Attendance percentage threshold for alerts', FALSE),
('face_recognition_enabled', 'true', 'boolean', 'Enable face recognition for attendance', FALSE),
('location_tracking_enabled', 'true', 'boolean', 'Enable location tracking for attendance', FALSE),
('max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', TRUE),
('session_timeout', '3600', 'number', 'Session timeout in seconds', TRUE);

-- Insert Sample Notifications
INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES
(5, 'Welcome to SAMS', 'Welcome to the Student Attendance Management System. Please register your face for attendance.', 'info', FALSE, NOW()),
(6, 'Attendance Marked', 'Your attendance has been marked for Data Structures class on 2024-02-05.', 'success', TRUE, '2024-02-05 10:00:00'),
(7, 'Face Registration Required', 'Please register your face to mark attendance automatically.', 'warning', FALSE, NOW());

-- =====================================================
-- END OF SCHEMA
-- =====================================================
