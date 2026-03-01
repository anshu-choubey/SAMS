<?php
/**
 * Seed sample attendance data for testing dashboard charts
 */

require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed");
}

try {
    echo "Generating sample attendance data for dashboard charts...\n";
    
    // Get department
    $dept_stmt = $db->query("SELECT id FROM departments WHERE is_active = true LIMIT 1");
    $dept = $dept_stmt ? $dept_stmt->fetch() : null;
    
    if (!$dept) {
        echo "Warning: No active departments found. Creating sample data anyway...\n";
        // Create a sample department
        $db->exec("INSERT IGNORE INTO departments (name, code, is_active) VALUES ('Department of Computer Science', 'CS', 1)");
        $dept = $db->query("SELECT id FROM departments LIMIT 1")->fetch();
    }
    
    $dept_id = $dept['id'];
    
    // Get or create a user for testing
    $user_stmt = $db->query("SELECT id FROM users WHERE role = 'student' LIMIT 1");
    $user = $user_stmt ? $user_stmt->fetch() : null;
    
    if (!$user) {
        echo "Creating sample student user...\n";
        $db->exec("INSERT INTO users (full_name, email, password_hash, role, is_active) 
                   VALUES ('Test Student', 'teststudent@sams.edu', SHA2('password', 256), 'student', 1)");
        $user = $db->query("SELECT id FROM users WHERE email = 'teststudent@sams.edu'")->fetch();
    }
    
    $user_id = $user['id'];
    
    // Create student record if not exists
    $student_check = $db->query("SELECT id FROM students WHERE user_id = $user_id");
    if ($student_check->rowCount() == 0) {
        $db->exec("INSERT INTO students (user_id, department_id, roll_number, semester, section, batch_year) 
                   VALUES ($user_id, $dept_id, 'TEST001', 1, 'A', 2024)");
    }
    
    $student = $db->query("SELECT id FROM students WHERE user_id = $user_id LIMIT 1")->fetch();
    $student_id = $student['id'];
    
    // Get schedule and teacher
    $schedule_stmt = $db->query("SELECT id FROM schedules LIMIT 1");
    $schedule = $schedule_stmt && $schedule_stmt->rowCount() > 0 ? $schedule_stmt->fetch() : ['id' => 1];
    $schedule_id = $schedule['id'];
    
    $teacher_stmt = $db->query("SELECT u.id FROM users u WHERE u.role = 'teacher' LIMIT 1");
    $teacher = $teacher_stmt && $teacher_stmt->rowCount() > 0 ? $teacher_stmt->fetch() : ['id' => 2];
    $teacher_id = $teacher['id'];
    
    // Clear old temp attendance data
    $db->exec("DELETE FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND student_id = $student_id");
    
    echo "Adding attendance records for last 7 days...\n";
    
    // Add attendance data for last 7 days
    $insert_count = 0;
    for ($day = 6; $day >= 0; $day--) {
        $date = date('Y-m-d', strtotime("-$day days"));
        
        // Vary attendance status
        $status = ($day % 3 == 0) ? 'absent' : 'present';
        
        try {
            $db->exec("INSERT INTO attendance (student_id, schedule_id, assignment_id, teacher_id, department_id, attendance_date, status) 
                       VALUES ($student_id, $schedule_id, 1, $teacher_id, $dept_id, '$date', '$status')");
            $insert_count++;
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    
    echo "✓ Inserted $insert_count attendance records\n";
    
    // Verify weekly data
    $weekly = $db->query("SELECT 
        DATE_FORMAT(attendance_date, '%a') as day,
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent
        FROM attendance 
        WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY attendance_date
        ORDER BY attendance_date")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nWeekly Attendance Summary:\n";
    foreach ($weekly as $row) {
        $day_name = $row['day'] ?? 'Unknown';
        $present = $row['present'] ?? 0;
        $absent = $row['absent'] ?? 0;
        echo "  $day_name: Present $present, Absent $absent\n";
    }
    
    // Department stats
    $dept_stats = $db->query("SELECT d.name, d.code,
        COUNT(DISTINCT a.student_id) as students,
        SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) as present
        FROM departments d
        LEFT JOIN attendance a ON a.department_id = d.id AND a.attendance_date = CURDATE()
        WHERE d.is_active = true
        GROUP BY d.id
        ORDER BY d.name")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nDepartment-wise Attendance Today:\n";
    foreach ($dept_stats as $row) {
        echo "  {$row['code']}: {$row['present']} present out of {$row['students']} students\n";
    }
    
    echo "\n✅ Dashboard charts seeding completed!\n";
    echo "📊 Charts should now display data when you visit: http://localhost:8000/public/admin/index.php\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
