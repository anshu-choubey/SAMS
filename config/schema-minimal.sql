-- Full SAMS Database Schema (PostgreSQL)
-- Converted from MySQL dump

-- Drop tables in correct order (reverse dependencies)
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS sessions CASCADE;
DROP TABLE IF EXISTS system_settings CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS fcm_tokens CASCADE;
DROP TABLE IF EXISTS attendance CASCADE;
DROP TABLE IF EXISTS teacher_locations CASCADE;
DROP TABLE IF EXISTS schedules CASCADE;
DROP TABLE IF EXISTS teacher_assignments CASCADE;
DROP TABLE IF EXISTS teachers CASCADE;
DROP TABLE IF EXISTS students CASCADE;
DROP TABLE IF EXISTS subjects CASCADE;
DROP TABLE IF EXISTS departments CASCADE;
DROP TABLE IF EXISTS login_attempts CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'teacher', 'student')),
    phone VARCHAR(15),
    profile_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments Table
CREATE TABLE departments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    hod_id INT REFERENCES users(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subjects Table
CREATE TABLE subjects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    credits INT DEFAULT 3,
    semester INT,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teachers Table
CREATE TABLE teachers (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    employee_id VARCHAR(50) NOT NULL UNIQUE,
    primary_department_id INT REFERENCES departments(id) ON DELETE SET NULL,
    designation VARCHAR(100),
    qualification VARCHAR(255),
    joining_date DATE
);

-- Students Table
CREATE TABLE students (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    roll_number VARCHAR(50) NOT NULL UNIQUE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    semester INT NOT NULL,
    section VARCHAR(10),
    batch_year INT,
    admission_date DATE,
    face_registered BOOLEAN DEFAULT FALSE,
    face_data TEXT,
    face_registration_date TIMESTAMP
);

-- Teacher Assignments Table
CREATE TABLE teacher_assignments (
    id SERIAL PRIMARY KEY,
    teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
    subject_id INT NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    section VARCHAR(10),
    academic_year VARCHAR(20) NOT NULL,
    semester INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(teacher_id, subject_id, department_id, section, semester, academic_year)
);

-- Schedules Table
CREATE TABLE schedules (
    id SERIAL PRIMARY KEY,
    assignment_id INT NOT NULL REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    day_of_week VARCHAR(20) NOT NULL CHECK (day_of_week IN ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    classroom VARCHAR(50),
    building VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attendance Table
CREATE TABLE attendance (
    id SERIAL PRIMARY KEY,
    student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    schedule_id INT NOT NULL REFERENCES schedules(id) ON DELETE CASCADE,
    assignment_id INT NOT NULL REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    attendance_date DATE NOT NULL,
    attendance_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    student_latitude DECIMAL(10,8),
    student_longitude DECIMAL(11,8),
    teacher_latitude DECIMAL(10,8),
    teacher_longitude DECIMAL(11,8),
    distance_meters DECIMAL(8,2),
    face_confidence_score DECIMAL(5,2),
    verification_status VARCHAR(20) NOT NULL CHECK (verification_status IN ('success', 'gps_failed', 'face_failed', 'both_failed')),
    status VARCHAR(20) DEFAULT 'present' CHECK (status IN ('present', 'absent', 'late')),
    remarks TEXT,
    UNIQUE(student_id, schedule_id, attendance_date)
);

-- Teacher Locations Table
CREATE TABLE teacher_locations (
    id SERIAL PRIMARY KEY,
    teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
    schedule_id INT NOT NULL REFERENCES schedules(id) ON DELETE CASCADE,
    assignment_id INT NOT NULL REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Sessions Table
CREATE TABLE sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

-- System Settings Table
CREATE TABLE system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications Table
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL CHECK (notification_type IN ('attendance_alert', 'low_attendance', 'system', 'schedule_change', 'face_reregister')),
    target_role VARCHAR(20) CHECK (target_role IN ('admin', 'teacher', 'student', 'all')),
    target_user_id INT REFERENCES users(id) ON DELETE CASCADE,
    target_department_id INT REFERENCES departments(id) ON DELETE CASCADE,
    data JSONB,
    fcm_token TEXT,
    is_sent BOOLEAN DEFAULT FALSE,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    sent_at TIMESTAMP,
    created_by INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Login Attempts Table
CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    was_successful BOOLEAN DEFAULT FALSE
);

-- Audit Logs Table
CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSONB,
    new_values JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- FCM Tokens Table
CREATE TABLE fcm_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token TEXT NOT NULL,
    device_info JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, token)
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active ON users(is_active);

CREATE INDEX idx_departments_code ON departments(code);
CREATE INDEX idx_departments_hod ON departments(hod_id);

CREATE INDEX idx_subjects_department ON subjects(department_id);
CREATE INDEX idx_subjects_code ON subjects(code);

CREATE INDEX idx_teachers_user ON teachers(user_id);
CREATE INDEX idx_teachers_employee ON teachers(employee_id);
CREATE INDEX idx_teachers_department ON teachers(primary_department_id);

CREATE INDEX idx_students_user ON students(user_id);
CREATE INDEX idx_students_roll ON students(roll_number);
CREATE INDEX idx_students_dept_sem ON students(department_id, semester);

CREATE INDEX idx_assignments_teacher ON teacher_assignments(teacher_id);
CREATE INDEX idx_assignments_subject ON teacher_assignments(subject_id);
CREATE INDEX idx_assignments_department ON teacher_assignments(department_id);

CREATE INDEX idx_schedules_assignment ON schedules(assignment_id);
CREATE INDEX idx_schedules_day_time ON schedules(day_of_week, start_time);

CREATE INDEX idx_attendance_student ON attendance(student_id);
CREATE INDEX idx_attendance_schedule ON attendance(schedule_id);
CREATE INDEX idx_attendance_date ON attendance(attendance_date);
CREATE INDEX idx_attendance_department ON attendance(department_id);
CREATE INDEX idx_attendance_verification ON attendance(verification_status);

CREATE INDEX idx_locations_teacher ON teacher_locations(teacher_id);
CREATE INDEX idx_locations_schedule ON teacher_locations(schedule_id);
CREATE INDEX idx_locations_active ON teacher_locations(teacher_id, is_active);

CREATE INDEX idx_sessions_user ON sessions(user_id);
CREATE INDEX idx_sessions_session ON sessions(session_id);
CREATE INDEX idx_sessions_expires ON sessions(expires_at);

CREATE INDEX idx_settings_key ON system_settings(setting_key);

CREATE INDEX idx_notifications_target ON notifications(target_role, target_user_id);
CREATE INDEX idx_notifications_sent ON notifications(is_sent);
CREATE INDEX idx_notifications_created ON notifications(created_by);

CREATE INDEX idx_audit_user ON audit_logs(user_id);
CREATE INDEX idx_audit_action ON audit_logs(action);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

CREATE INDEX idx_fcm_user ON fcm_tokens(user_id);
CREATE TABLE students (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    roll_number VARCHAR(50) NOT NULL UNIQUE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    semester INT NOT NULL,
    section VARCHAR(10),
    batch_year INT,
    admission_date DATE,
    face_registered BOOLEAN DEFAULT FALSE,
    face_data TEXT,
    face_registration_date TIMESTAMP
);

-- Teacher Assignments Table
CREATE TABLE teacher_assignments (
    id SERIAL PRIMARY KEY,
    teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
    subject_id INT NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    section VARCHAR(10),
    academic_year VARCHAR(20) NOT NULL,
    semester INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(teacher_id, subject_id, department_id, section, semester, academic_year)
);

-- Schedules Table
CREATE TABLE schedules (
    id SERIAL PRIMARY KEY,
    assignment_id INT NOT NULL REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    day_of_week VARCHAR(20) NOT NULL CHECK (day_of_week IN ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    classroom VARCHAR(50),
    building VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attendance Table
CREATE TABLE attendance (
    id SERIAL PRIMARY KEY,
    student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    schedule_id INT NOT NULL REFERENCES schedules(id) ON DELETE CASCADE,
    assignment_id INT NOT NULL REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    attendance_date DATE NOT NULL,
    attendance_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    student_latitude DECIMAL(10,8),
    student_longitude DECIMAL(11,8),
    teacher_latitude DECIMAL(10,8),
    teacher_longitude DECIMAL(11,8),
    distance_meters DECIMAL(8,2),
    face_confidence_score DECIMAL(5,2),
    verification_status VARCHAR(20) NOT NULL CHECK (verification_status IN ('success', 'gps_failed', 'face_failed', 'both_failed')),
    status VARCHAR(20) DEFAULT 'present' CHECK (status IN ('present', 'absent', 'late')),
    remarks TEXT,
    UNIQUE(student_id, schedule_id, attendance_date)
);

-- Teacher Locations Table
CREATE TABLE teacher_locations (
    id SERIAL PRIMARY KEY,
    teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
    schedule_id INT NOT NULL REFERENCES schedules(id) ON DELETE CASCADE,
    assignment_id INT NOT NULL REFERENCES teacher_assignments(id) ON DELETE CASCADE,
    department_id INT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Sessions Table
CREATE TABLE sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

-- System Settings Table
CREATE TABLE system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications Table
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL CHECK (notification_type IN ('attendance_alert', 'low_attendance', 'system', 'schedule_change', 'face_reregister')),
    target_role VARCHAR(20) CHECK (target_role IN ('admin', 'teacher', 'student', 'all')),
    target_user_id INT REFERENCES users(id) ON DELETE CASCADE,
    target_department_id INT REFERENCES departments(id) ON DELETE CASCADE,
    data JSONB,
    fcm_token TEXT,
    is_sent BOOLEAN DEFAULT FALSE,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    sent_at TIMESTAMP,
    created_by INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Login Attempts Table
CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    was_successful BOOLEAN DEFAULT FALSE
);

-- Audit Logs Table
CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSONB,
    new_values JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- FCM Tokens Table (if needed)
CREATE TABLE fcm_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token TEXT NOT NULL,
    device_info JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, token)
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active ON users(is_active);

CREATE INDEX idx_departments_code ON departments(code);
CREATE INDEX idx_departments_hod ON departments(hod_id);

CREATE INDEX idx_subjects_department ON subjects(department_id);
CREATE INDEX idx_subjects_code ON subjects(code);

CREATE INDEX idx_teachers_user ON teachers(user_id);
CREATE INDEX idx_teachers_employee ON teachers(employee_id);
CREATE INDEX idx_teachers_department ON teachers(primary_department_id);

CREATE INDEX idx_students_user ON students(user_id);
CREATE INDEX idx_students_roll ON students(roll_number);
CREATE INDEX idx_students_dept_sem ON students(department_id, semester);

CREATE INDEX idx_assignments_teacher ON teacher_assignments(teacher_id);
CREATE INDEX idx_assignments_subject ON teacher_assignments(subject_id);
CREATE INDEX idx_assignments_department ON teacher_assignments(department_id);

CREATE INDEX idx_schedules_assignment ON schedules(assignment_id);
CREATE INDEX idx_schedules_day_time ON schedules(day_of_week, start_time);

CREATE INDEX idx_attendance_student ON attendance(student_id);
CREATE INDEX idx_attendance_schedule ON attendance(schedule_id);
CREATE INDEX idx_attendance_date ON attendance(attendance_date);
CREATE INDEX idx_attendance_department ON attendance(department_id);
CREATE INDEX idx_attendance_verification ON attendance(verification_status);

CREATE INDEX idx_locations_teacher ON teacher_locations(teacher_id);
CREATE INDEX idx_locations_schedule ON teacher_locations(schedule_id);
CREATE INDEX idx_locations_active ON teacher_locations(teacher_id, is_active);

CREATE INDEX idx_sessions_user ON sessions(user_id);
CREATE INDEX idx_sessions_session ON sessions(session_id);
CREATE INDEX idx_sessions_expires ON sessions(expires_at);

CREATE INDEX idx_settings_key ON system_settings(setting_key);

CREATE INDEX idx_notifications_target ON notifications(target_role, target_user_id);
CREATE INDEX idx_notifications_sent ON notifications(is_sent);
CREATE INDEX idx_notifications_created ON notifications(created_by);

CREATE INDEX idx_audit_user ON audit_logs(user_id);
CREATE INDEX idx_audit_action ON audit_logs(action);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

CREATE INDEX idx_fcm_user ON fcm_tokens(user_id);
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    department_id INT,
    credits INT DEFAULT 3,
    semester INT,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE teachers (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    department_id INT
);

CREATE TABLE teacher_assignments (
    id SERIAL PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_name VARCHAR(50),
    semester INT,
    academic_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE schedules (
    id SERIAL PRIMARY KEY,
    teacher_assignment_id INT NOT NULL,
    day_of_week INT,
    start_time TIME,
    end_time TIME,
    room VARCHAR(50),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    qr_code TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);