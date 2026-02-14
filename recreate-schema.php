<?php
/**
 * Recreate Heroku PostgreSQL schema to match MySQL dump structure
 */

require_once __DIR__ . '/config/database.php';

try {
    echo "Connecting to Heroku PostgreSQL database...\n";
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    echo "Connected successfully!\n\n";

    // Drop all tables in correct order (reverse dependency order)
    echo "Dropping existing tables...\n";
    $dropTables = [
        'audit_logs',
        'sessions',
        'login_attempts',
        'notifications',
        'fcm_tokens',
        'attendance',
        'teacher_locations',
        'schedules',
        'teacher_assignments',
        'teachers',
        'students',
        'subjects',
        'departments',
        'system_settings',
        'users'
    ];

    foreach ($dropTables as $table) {
        try {
            $db->exec("DROP TABLE IF EXISTS $table CASCADE");
            echo "✓ Dropped table: $table\n";
        } catch (Exception $e) {
            echo "⚠ Could not drop $table: " . $e->getMessage() . "\n";
        }
    }

    echo "\nCreating new tables...\n";

    // Create tables in correct order
    $createStatements = [
        // Users table
        "CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) CHECK (role IN ('admin', 'teacher', 'student')) NOT NULL,
            phone VARCHAR(15),
            profile_image VARCHAR(255),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // Departments table
        "CREATE TABLE departments (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(20) NOT NULL UNIQUE,
            description TEXT,
            hod_id INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (hod_id) REFERENCES users(id) ON DELETE SET NULL
        )",

        // Subjects table
        "CREATE TABLE subjects (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(20) NOT NULL UNIQUE,
            description TEXT,
            department_id INT,
            credits INT DEFAULT 3,
            semester INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
        )",

        // Students table
        "CREATE TABLE students (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            roll_number VARCHAR(50) NOT NULL UNIQUE,
            department_id INT NOT NULL,
            semester INT NOT NULL,
            section VARCHAR(10),
            batch_year INT,
            admission_date DATE,
            face_registered BOOLEAN DEFAULT FALSE,
            face_data TEXT,
            face_registration_date TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
        )",

        // Teachers table
        "CREATE TABLE teachers (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            employee_id VARCHAR(20) NOT NULL UNIQUE,
            department_id INT,
            specialization VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        )",

        // Teacher assignments table
        "CREATE TABLE teacher_assignments (
            id SERIAL PRIMARY KEY,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            class_name VARCHAR(50),
            semester INT,
            academic_year VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            UNIQUE(teacher_id, subject_id, academic_year)
        )",

        // Schedules table
        "CREATE TABLE schedules (
            id SERIAL PRIMARY KEY,
            teacher_assignment_id INT NOT NULL,
            day_of_week INT CHECK (day_of_week BETWEEN 1 AND 7),
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            room VARCHAR(50),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            qr_code TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_assignment_id) REFERENCES teacher_assignments(id) ON DELETE CASCADE
        )",

        // Teacher locations table
        "CREATE TABLE teacher_locations (
            id SERIAL PRIMARY KEY,
            teacher_id INT NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            accuracy DECIMAL(5,2),
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
        )",

        // Attendance table
        "CREATE TABLE attendance (
            id SERIAL PRIMARY KEY,
            student_id INT NOT NULL,
            schedule_id INT NOT NULL,
            assignment_id INT NOT NULL,
            teacher_id INT NOT NULL,
            department_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            attendance_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            student_latitude DECIMAL(10,8),
            student_longitude DECIMAL(11,8),
            teacher_latitude DECIMAL(10,8),
            teacher_longitude DECIMAL(11,8),
            distance_meters DECIMAL(8,2),
            face_confidence_score DECIMAL(5,2),
            verification_status VARCHAR(20) CHECK (verification_status IN ('success', 'gps_failed', 'face_failed', 'both_failed')) NOT NULL,
            status VARCHAR(20) CHECK (status IN ('present', 'absent', 'late')) DEFAULT 'present',
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
            FOREIGN KEY (assignment_id) REFERENCES teacher_assignments(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
            UNIQUE(student_id, schedule_id, attendance_date)
        )",

        // FCM tokens table
        "CREATE TABLE fcm_tokens (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            token TEXT NOT NULL,
            device_type VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id, token)
        )",

        // Notifications table
        "CREATE TABLE notifications (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50),
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",

        // System settings table
        "CREATE TABLE system_settings (
            id SERIAL PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            description VARCHAR(255),
            is_system BOOLEAN DEFAULT FALSE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // Sessions table
        "CREATE TABLE sessions (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) NOT NULL UNIQUE,
            ip_address VARCHAR(45),
            user_agent TEXT,
            expires_at TIMESTAMP NOT NULL,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",

        // Login attempts table
        "CREATE TABLE login_attempts (
            id SERIAL PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT FALSE
        )",

        // Audit logs table
        "CREATE TABLE audit_logs (
            id SERIAL PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(100),
            record_id INT,
            old_values TEXT,
            new_values TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    ];

    foreach ($createStatements as $sql) {
        try {
            $db->exec($sql);
            // Extract table name from SQL for display
            if (preg_match('/CREATE TABLE (\w+)/', $sql, $matches)) {
                echo "✓ Created table: {$matches[1]}\n";
            }
        } catch (Exception $e) {
            echo "✗ Error creating table: " . $e->getMessage() . "\n";
            echo "  SQL: " . substr($sql, 0, 100) . "...\n";
        }
    }

    echo "\nCreating indexes...\n";

    // Create indexes
    $indexStatements = [
        "CREATE INDEX idx_users_email ON users(email)",
        "CREATE INDEX idx_users_role ON users(role)",
        "CREATE INDEX idx_students_roll_number ON students(roll_number)",
        "CREATE INDEX idx_students_department_semester ON students(department_id, semester)",
        "CREATE INDEX idx_attendance_schedule ON attendance(schedule_id)",
        "CREATE INDEX idx_attendance_assignment ON attendance(assignment_id)",
        "CREATE INDEX idx_attendance_teacher ON attendance(teacher_id)",
        "CREATE INDEX idx_attendance_student ON attendance(student_id)",
        "CREATE INDEX idx_attendance_date ON attendance(attendance_date)",
        "CREATE INDEX idx_attendance_department ON attendance(department_id)",
        "CREATE INDEX idx_attendance_verification ON attendance(verification_status)",
        "CREATE INDEX idx_audit_logs_user ON audit_logs(user_id)",
        "CREATE INDEX idx_audit_logs_action ON audit_logs(action)",
        "CREATE INDEX idx_audit_logs_created ON audit_logs(created_at)"
    ];

    foreach ($indexStatements as $sql) {
        try {
            $db->exec($sql);
            echo "✓ Created index: " . substr($sql, 16) . "\n";
        } catch (Exception $e) {
            echo "⚠ Error creating index: " . $e->getMessage() . "\n";
        }
    }

    echo "\nInserting default data...\n";

    // Insert default admin user
    $db->exec("INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES
        ('System Administrator', 'admin@sams.com', '\$2y\$12\$EfE9yo8DnNgdDAIohaOmUu.kGE8ghPFtfsyjpYmwJBjfF9bLUSb96', 'admin', true)");
    echo "✓ Inserted admin user\n";

    // Insert default system settings
    $db->exec("INSERT INTO system_settings (setting_key, setting_value, description, is_system) VALUES
        ('app_name', 'SAMS - Student Attendance Management System', 'Application name', true),
        ('app_version', '2.0', 'Application version', true),
        ('attendance_threshold', '75', 'Minimum attendance percentage', false),
        ('gps_proximity_radius', '1000', 'Location accuracy in meters', false),
        ('face_confidence_threshold', '50', 'Face recognition confidence threshold', false),
        ('liveness_detection', 'true', 'Enable liveness detection', false),
        ('academic_year', '2025-2026', 'Current academic year', false),
        ('session_timeout', '3600', 'Session timeout in seconds', true)");
    echo "✓ Inserted system settings\n";

    echo "\nSchema recreation completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>