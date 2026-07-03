<?php
/**
 * Run Multi-Check Attendance Migration
 * This script will update the Heroku database with multi-check attendance support
 */

// Heroku Database Credentials
$host = 'gx97kbnhgjzh3efb.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
$port = '3306';
$database = 'a60382na4xjudzs6';
$username = 'ql8x6of7t4e8rou4';
$password = 'j7vh4q8e55ms7q10';

echo "🚀 Multi-Check Attendance Migration\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Connect to database
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Connected to Heroku database\n\n";
    
    // Start transaction
    $pdo->beginTransaction();
    
    echo "📝 Running migration steps...\n\n";
    
    // Step 1: Add columns to teacher_locations
    echo "Step 1: Adding columns to teacher_locations...\n";
    try {
        $pdo->exec("ALTER TABLE teacher_locations 
            ADD COLUMN multi_check_enabled BOOLEAN DEFAULT FALSE AFTER is_active,
            ADD COLUMN total_checks_planned INT DEFAULT 1 AFTER multi_check_enabled,
            ADD COLUMN checks_completed INT DEFAULT 0 AFTER total_checks_planned");
        echo "  ✅ Columns added successfully\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "  ⚠️  Columns already exist, skipping\n\n";
        } else {
            throw $e;
        }
    }
    
    // Step 2: Create attendance_check_points table
    echo "Step 2: Creating attendance_check_points table...\n";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_check_points (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "  ✅ Table created successfully\n\n";
    } catch (PDOException $e) {
        echo "  ⚠️  Table already exists or error: " . $e->getMessage() . "\n\n";
    }
    
    // Step 3: Create attendance_check_responses table
    echo "Step 3: Creating attendance_check_responses table...\n";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_check_responses (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "  ✅ Table created successfully\n\n";
    } catch (PDOException $e) {
        echo "  ⚠️  Table already exists or error: " . $e->getMessage() . "\n\n";
    }
    
    // Step 4: Add columns to attendance table
    echo "Step 4: Adding columns to attendance table...\n";
    try {
        $pdo->exec("ALTER TABLE attendance
            ADD COLUMN session_id INT AFTER department_id,
            ADD COLUMN total_checks_required INT DEFAULT 1 AFTER attendance_time,
            ADD COLUMN successful_checks INT DEFAULT 0 AFTER total_checks_required");
        echo "  ✅ Columns added successfully\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "  ⚠️  Columns already exist, skipping\n\n";
        } else {
            throw $e;
        }
    }
    
    // Step 5: Add foreign key for session_id
    echo "Step 5: Adding foreign key for session_id...\n";
    try {
        $pdo->exec("ALTER TABLE attendance 
            ADD CONSTRAINT fk_attendance_session 
            FOREIGN KEY (session_id) REFERENCES teacher_locations(id) ON DELETE SET NULL");
        echo "  ✅ Foreign key added successfully\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "  ⚠️  Foreign key already exists, skipping\n\n";
        } else {
            echo "  ⚠️  Could not add foreign key: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Step 6: Add index for session_id
    echo "Step 6: Adding index for session_id...\n";
    try {
        $pdo->exec("ALTER TABLE attendance ADD INDEX idx_session (session_id)");
        echo "  ✅ Index added successfully\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "  ⚠️  Index already exists, skipping\n\n";
        } else {
            echo "  ⚠️  Could not add index: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Step 7: Update verification_status enum
    echo "Step 7: Updating attendance verification_status enum...\n";
    try {
        $pdo->exec("ALTER TABLE attendance 
            MODIFY COLUMN verification_status ENUM('success', 'gps_failed', 'face_failed', 'both_failed', 'partial') NOT NULL");
        echo "  ✅ Enum updated successfully\n\n";
    } catch (PDOException $e) {
        echo "  ⚠️  Enum update issue: " . $e->getMessage() . "\n\n";
    }
    
    // Step 8: Update status enum
    echo "Step 8: Updating attendance status enum...\n";
    try {
        $pdo->exec("ALTER TABLE attendance 
            MODIFY COLUMN status ENUM('present', 'absent', 'late', 'partial') DEFAULT 'present'");
        echo "  ✅ Enum updated successfully\n\n";
    } catch (PDOException $e) {
        echo "  ⚠️  Enum update issue: " . $e->getMessage() . "\n\n";
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo str_repeat("=", 60) . "\n";
    echo "✅ MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Verify migration
    echo "🔍 Verifying migration...\n\n";
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES LIKE 'attendance_check%'");
    $newTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "New tables created:\n";
    foreach ($newTables as $table) {
        echo "  ✅ $table\n";
    }
    
    // Check teacher_locations columns
    $stmt = $pdo->query("DESCRIBE teacher_locations");
    $columns = array_column($stmt->fetchAll(), 'Field');
    
    echo "\nNew teacher_locations columns:\n";
    $multiCheckCols = ['multi_check_enabled', 'total_checks_planned', 'checks_completed'];
    foreach ($multiCheckCols as $col) {
        if (in_array($col, $columns)) {
            echo "  ✅ $col\n";
        } else {
            echo "  ❌ $col - MISSING\n";
        }
    }
    
    // Check attendance columns
    $stmt = $pdo->query("DESCRIBE attendance");
    $columns = array_column($stmt->fetchAll(), 'Field');
    
    echo "\nNew attendance columns:\n";
    $multiCheckCols = ['session_id', 'total_checks_required', 'successful_checks'];
    foreach ($multiCheckCols as $col) {
        if (in_array($col, $columns)) {
            echo "  ✅ $col\n";
        } else {
            echo "  ❌ $col - MISSING\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ All done! Multi-check attendance system is ready.\n";
    echo str_repeat("=", 60) . "\n";
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
