<?php
/**
 * Database Setup Script - Populate Required Data
 * This script initializes departments, subjects, teachers, assignments, and schedules
 * 
 * Run: php setup-database.php (local) or access via browser on Heroku
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$dbName = getenv('JAWSDB_DB') ?: 'sams_db';
$log = [];
$errors = [];

try {
    // ==========================================
    // 1. CREATE DEPARTMENTS
    // ==========================================
    $log[] = "Creating departments...";
    
    $departments = [
        ['name' => 'Department of Computer Science', 'code' => 'CS', 'description' => 'Computer Science and Engineering'],
        ['name' => 'Department of Mechanical Engineering', 'code' => 'ME', 'description' => 'Mechanical Engineering'],
        ['name' => 'Department of Civil Engineering', 'code' => 'CE', 'description' => 'Civil Engineering'],
        ['name' => 'Department of Electrical Engineering', 'code' => 'EE', 'description' => 'Electrical Engineering'],
        ['name' => 'Department of Electronics', 'code' => 'EC', 'description' => 'Electronics and Communication'],
    ];
    
    foreach ($departments as $dept) {
        try {
            $stmt = $db->prepare("INSERT IGNORE INTO departments (name, code, description, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$dept['name'], $dept['code'], $dept['description']]);
        } catch (Exception $e) {
            $errors[] = "Department {$dept['code']}: " . $e->getMessage();
        }
    }
    
    // ==========================================
    // 2. CREATE SUBJECTS
    // ==========================================
    $log[] = "Creating subjects...";
    
    // Get department IDs
    $deptResult = $db->query("SELECT id, code FROM departments WHERE is_active = 1");
    $depts = [];
    while ($row = $deptResult->fetch()) {
        $depts[$row['code']] = $row['id'];
    }
    
    if (empty($depts)) {
        throw new Exception("No departments found. Cannot create subjects.");
    }
    
    $subjects = [
        // Computer Science
        ['name' => 'Data Structures', 'code' => 'CS101', 'dept' => 'CS', 'credits' => 4, 'semester' => 1],
        ['name' => 'Web Development', 'code' => 'CS201', 'dept' => 'CS', 'credits' => 3, 'semester' => 2],
        ['name' => 'Database Management', 'code' => 'CS202', 'dept' => 'CS', 'credits' => 3, 'semester' => 2],
        ['name' => 'Algorithm Design', 'code' => 'CS301', 'dept' => 'CS', 'credits' => 4, 'semester' => 3],
        ['name' => 'Machine Learning', 'code' => 'CS401', 'dept' => 'CS', 'credits' => 3, 'semester' => 4],
        
        // Mechanical Engineering
        ['name' => 'Engineering Mechanics', 'code' => 'ME101', 'dept' => 'ME', 'credits' => 4, 'semester' => 1],
        ['name' => 'Thermodynamics', 'code' => 'ME201', 'dept' => 'ME', 'credits' => 3, 'semester' => 2],
        ['name' => 'Fluid Mechanics', 'code' => 'ME202', 'dept' => 'ME', 'credits' => 3, 'semester' => 2],
        ['name' => 'Heat Transfer', 'code' => 'ME301', 'dept' => 'ME', 'credits' => 3, 'semester' => 3],
        
        // Civil Engineering
        ['name' => 'Structural Analysis', 'code' => 'CE101', 'dept' => 'CE', 'credits' => 4, 'semester' => 1],
        ['name' => 'Concrete Technology', 'code' => 'CE201', 'dept' => 'CE', 'credits' => 3, 'semester' => 2],
        ['name' => 'Soil Mechanics', 'code' => 'CE202', 'dept' => 'CE', 'credits' => 3, 'semester' => 2],
        ['name' => 'Foundation Design', 'code' => 'CE301', 'dept' => 'CE', 'credits' => 3, 'semester' => 3],
    ];
    
    $subjectIds = [];
    foreach ($subjects as $subj) {
        try {
            $deptId = $depts[$subj['dept']] ?? null;
            if (!$deptId) continue;
            
            $stmt = $db->prepare("INSERT IGNORE INTO subjects (name, code, department_id, credits, semester, is_active) 
                                 VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$subj['name'], $subj['code'], $deptId, $subj['credits'], $subj['semester']]);
            
            // Get inserted ID
            $subjResult = $db->query("SELECT id FROM subjects WHERE code = '{$subj['code']}'");
            if ($subjResult) {
                $row = $subjResult->fetch();
                $subjectIds[$subj['code']] = $row['id'];
            }
        } catch (Exception $e) {
            $errors[] = "Subject {$subj['code']}: " . $e->getMessage();
        }
    }
    
    // ==========================================
    // 3. CREATE TEACHERS (from existing users)
    // ==========================================
    $log[] = "Creating teacher records...";
    
    // Get teacher users
    $teacherUsers = $db->query("SELECT id, full_name, email FROM users WHERE role = 'teacher' LIMIT 5");
    $teacherIds = [];
    
    $employeeId = 1001;
    while ($teacher = $teacherUsers->fetch()) {
        try {
            $primaryDeptId = array_values($depts)[0] ?? null;
            
            $stmt = $db->prepare("INSERT IGNORE INTO teachers (user_id, employee_id, primary_department_id, designation, qualification) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $teacher['id'],
                'EMP' . ($employeeId++),
                $primaryDeptId,
                'Assistant Professor',
                'M.Tech'
            ]);
            
            // Get teacher ID
            $teacherResult = $db->query("SELECT id FROM teachers WHERE user_id = {$teacher['id']}");
            if ($teacherResult) {
                $row = $teacherResult->fetch();
                $teacherIds[$teacher['id']] = $row['id'];
            }
        } catch (Exception $e) {
            $errors[] = "Teacher {$teacher['email']}: " . $e->getMessage();
        }
    }
    
    if (empty($teacherIds)) {
        throw new Exception("No teachers created. Cannot create assignments.");
    }
    
    // ==========================================
    // 4. CREATE TEACHER ASSIGNMENTS
    // ==========================================
    $log[] = "Creating teacher assignments...";
    
    $assignments = [];
    $assignmentCounter = 0;
    
    foreach ($teacherIds as $userId => $teacherId) {
        foreach ($subjectIds as $subjCode => $subjId) {
            // Get subject's department
            $subjDeptResult = $db->query("SELECT department_id FROM subjects WHERE id = $subjId");
            $subjDept = $subjDeptResult->fetch();
            $deptId = $subjDept['department_id'];
            
            try {
                $stmt = $db->prepare("INSERT IGNORE INTO teacher_assignments 
                                     (teacher_id, subject_id, department_id, section, academic_year, semester, is_active)
                                     VALUES (?, ?, ?, ?, ?, ?, 1)");
                
                $sections = ['A', 'B'];
                $semester = rand(1, 4);
                $section = $sections[$assignmentCounter % 2];
                
                $stmt->execute([
                    $teacherId,
                    $subjId,
                    $deptId,
                    $section,
                    '2025-2026',
                    $semester
                ]);
                
                // Get assignment ID
                $assignResult = $db->query("SELECT id FROM teacher_assignments WHERE teacher_id = $teacherId AND subject_id = $subjId LIMIT 1");
                if ($assignResult) {
                    $row = $assignResult->fetch();
                    $assignments[] = $row['id'];
                }
                
                $assignmentCounter++;
            } catch (Exception $e) {
                // Silently skip duplicate assignments
            }
            
            // Limit to avoid too many assignments
            if ($assignmentCounter > 20) break;
        }
        if ($assignmentCounter > 20) break;
    }
    
    // ==========================================
    // 5. CREATE SCHEDULES
    // ==========================================
    $log[] = "Creating schedules...";
    
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $times = [
        ['start' => '09:00:00', 'end' => '10:30:00'],
        ['start' => '10:45:00', 'end' => '12:15:00'],
        ['start' => '13:00:00', 'end' => '14:30:00'],
        ['start' => '14:45:00', 'end' => '16:15:00'],
    ];
    
    $scheduleCount = 0;
    foreach ($assignments as $assignmentId) {
        foreach ($days as $dayIndex => $day) {
            try {
                $timeSlot = $times[$dayIndex % count($times)];
                
                $stmt = $db->prepare("INSERT INTO schedules 
                                     (assignment_id, day_of_week, start_time, end_time, classroom, building, is_active)
                                     VALUES (?, ?, ?, ?, ?, ?, 1)");
                
                $classroom = 'Lab-' . rand(101, 110);
                $building = 'Building-' . chr(65 + rand(0, 2)); // A, B, C
                
                $stmt->execute([
                    $assignmentId,
                    $day,
                    $timeSlot['start'],
                    $timeSlot['end'],
                    $classroom,
                    $building
                ]);
                
                $scheduleCount++;
            } catch (Exception $e) {
                $errors[] = "Schedule for assignment $assignmentId on $day: " . $e->getMessage();
            }
        }
    }
    
    // ==========================================
    // SUMMARY
    // ==========================================
    $log[] = "Created $scheduleCount schedules successfully";
    
    // Get counts
    $counts = [];
    foreach (['departments', 'subjects', 'teachers', 'teacher_assignments', 'schedules'] as $table) {
        $countResult = $db->query("SELECT COUNT(*) as cnt FROM $table");
        $row = $countResult->fetch();
        $counts[$table] = $row['cnt'];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully',
        'log' => $log,
        'counts' => $counts,
        'errors' => count($errors) > 0 ? $errors : 'None'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors
    ], JSON_PRETTY_PRINT);
}
?>
