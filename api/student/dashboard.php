<?php
/**
 * Student Dashboard API
 * Returns student's attendance summary, schedules, and stats
 * Matches Android app's DashboardData model
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

// Handle CORS
CORS::handle();

// Only GET method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check authentication and role
    Auth::hasRole('student');
    $user = Auth::user();

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get student profile
    $studentQuery = "SELECT s.*, d.name as department_name, d.code as department_code, u.full_name
                     FROM students s
                     JOIN departments d ON s.department_id = d.id
                     JOIN users u ON s.user_id = u.id
                     WHERE s.user_id = :user_id";
    $stmt = $db->prepare($studentQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        Response::error('Student profile not found', 404);
    }

    // Get overall attendance stats
    $attendanceQuery = "SELECT 
                            COUNT(*) as total_classes,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as attended,
                            ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as percentage
                        FROM attendance
                        WHERE student_id = :student_id";
    $stmt = $db->prepare($attendanceQuery);
    $stmt->bindParam(':student_id', $student['id']);
    $stmt->execute();
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get subject-wise attendance
    $subjectQuery = "SELECT 
                        sub.id as subject_id,
                        sub.name as subject_name,
                        sub.code as subject_code,
                        COUNT(*) as total_classes,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as attended,
                        ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as percentage
                     FROM attendance a
                     JOIN teacher_assignments ta ON a.assignment_id = ta.id
                     JOIN subjects sub ON ta.subject_id = sub.id
                     WHERE a.student_id = :student_id
                     GROUP BY sub.id
                     ORDER BY percentage ASC";
    $stmt = $db->prepare($subjectQuery);
    $stmt->bindParam(':student_id', $student['id']);
    $stmt->execute();
    $subjectWise = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format subject-wise data
    $formattedSubjectWise = array_map(function($sub) {
        return [
            'subject_id' => (int)$sub['subject_id'],
            'subject_name' => $sub['subject_name'],
            'subject_code' => $sub['subject_code'],
            'total_classes' => (int)$sub['total_classes'],
            'attended' => (int)$sub['attended'],
            'percentage' => (float)($sub['percentage'] ?? 0)
        ];
    }, $subjectWise);

    // Get recent attendance (last 10 records)
    $recentQuery = "SELECT 
                        a.id,
                        sub.name as subject_name,
                        sub.code as subject_code,
                        u.full_name as teacher_name,
                        a.attendance_date as date,
                        TIME_FORMAT(a.attendance_time, '%H:%i:%s') as time,
                        a.status,
                        a.verification_status
                    FROM attendance a
                    JOIN teacher_assignments ta ON a.assignment_id = ta.id
                    JOIN subjects sub ON ta.subject_id = sub.id
                    JOIN teachers t ON ta.teacher_id = t.id
                    JOIN users u ON t.user_id = u.id
                    WHERE a.student_id = :student_id
                    ORDER BY a.attendance_date DESC, a.attendance_time DESC
                    LIMIT 10";
    $stmt = $db->prepare($recentQuery);
    $stmt->bindParam(':student_id', $student['id']);
    $stmt->execute();
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format recent attendance
    $formattedRecent = array_map(function($record) {
        return [
            'id' => (int)$record['id'],
            'subject_name' => $record['subject_name'],
            'subject_code' => $record['subject_code'],
            'teacher_name' => $record['teacher_name'],
            'date' => $record['date'],
            'time' => $record['time'],
            'status' => $record['status'],
            'verification_status' => $record['verification_status']
        ];
    }, $recentAttendance);

    // Get low attendance subjects (below 75%)
    $lowAttendanceSubjects = [];
    foreach ($formattedSubjectWise as $sub) {
        if ($sub['percentage'] < 75 && $sub['total_classes'] > 0) {
            // Calculate classes needed to reach 75%
            $totalClasses = $sub['total_classes'];
            $attended = $sub['attended'];
            $classesNeeded = 0;
            
            // Formula: (attended + x) / (total + x) >= 0.75
            // Solving: attended + x >= 0.75 * total + 0.75 * x
            // x - 0.75x >= 0.75*total - attended
            // 0.25x >= 0.75*total - attended
            // x >= (0.75*total - attended) / 0.25
            if ($sub['percentage'] < 75) {
                $classesNeeded = (int)ceil((0.75 * $totalClasses - $attended) / 0.25);
                if ($classesNeeded < 0) $classesNeeded = 0;
            }
            
            $lowAttendanceSubjects[] = [
                'subject_name' => $sub['subject_name'],
                'percentage' => $sub['percentage'],
                'classes_needed' => $classesNeeded
            ];
        }
    }

    // Get today's schedule with session status
    $dayOfWeek = date('l'); // e.g., "Monday"
    $todayQuery = "SELECT 
                        sch.id,
                        sub.id as subject_id,
                        sub.name as subject_name,
                        sub.code as subject_code,
                        t.id as teacher_id,
                        u.full_name as teacher_name,
                        sch.day_of_week,
                        TIME_FORMAT(sch.start_time, '%H:%i:%s') as start_time,
                        TIME_FORMAT(sch.end_time, '%H:%i:%s') as end_time,
                        sch.classroom,
                        sch.building,
                        CASE 
                            WHEN tl.id IS NOT NULL AND tl.session_end IS NULL THEN 1 
                            ELSE 0 
                        END as session_active,
                        COALESCE(tl.latitude, 0) as teacher_latitude,
                        COALESCE(tl.longitude, 0) as teacher_longitude,
                        CASE 
                            WHEN att.id IS NOT NULL THEN 1 
                            ELSE 0 
                        END as attendance_marked,
                        CASE 
                            WHEN TIME(NOW()) BETWEEN sch.start_time AND sch.end_time THEN 1 
                            ELSE 0 
                        END as is_active
                    FROM schedules sch
                    JOIN teacher_assignments ta ON sch.assignment_id = ta.id
                    JOIN subjects sub ON ta.subject_id = sub.id
                    JOIN teachers t ON ta.teacher_id = t.id
                    JOIN users u ON t.user_id = u.id
                    LEFT JOIN teacher_locations tl ON sch.id = tl.schedule_id AND DATE(tl.session_start) = CURDATE() AND tl.session_end IS NULL AND tl.is_active = TRUE
                    LEFT JOIN attendance att ON sch.id = att.schedule_id AND att.student_id = :student_id AND att.attendance_date = CURDATE()
                    WHERE sch.day_of_week = :day_of_week
                    AND ta.semester = :semester
                    AND (ta.section IS NULL OR ta.section = :section)
                    AND ta.department_id = :department_id
                    AND sch.is_active = TRUE
                    ORDER BY sch.start_time ASC";
    $stmt = $db->prepare($todayQuery);
    $stmt->bindParam(':student_id', $student['id']);
    $stmt->bindParam(':day_of_week', $dayOfWeek);
    $stmt->bindParam(':semester', $student['semester']);
    $stmt->bindParam(':section', $student['section']);
    $stmt->bindParam(':department_id', $student['department_id']);
    $stmt->execute();
    $todaySchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format today's schedule
    $formattedTodaySchedule = array_map(function($sch) {
        return [
            'id' => (int)$sch['id'],
            'subject_id' => (int)$sch['subject_id'],
            'subject_name' => $sch['subject_name'],
            'subject_code' => $sch['subject_code'],
            'teacher_id' => (int)$sch['teacher_id'],
            'teacher_name' => $sch['teacher_name'],
            'day_of_week' => $sch['day_of_week'],
            'start_time' => $sch['start_time'],
            'end_time' => $sch['end_time'],
            'classroom' => $sch['classroom'],
            'building' => $sch['building'],
            'is_active' => (bool)$sch['is_active'],
            'session_active' => (bool)$sch['session_active'],
            'teacher_latitude' => (float)$sch['teacher_latitude'],
            'teacher_longitude' => (float)$sch['teacher_longitude'],
            'attendance_marked' => (bool)$sch['attendance_marked']
        ];
    }, $todaySchedule);

    // Get active session (if any class is currently active and teacher has started)
    $activeSession = null;
    foreach ($formattedTodaySchedule as $sch) {
        if ($sch['session_active'] && !$sch['attendance_marked']) {
            $activeSession = [
                'schedule_id' => $sch['id'],
                'subject_name' => $sch['subject_name'],
                'subject_code' => $sch['subject_code'],
                'teacher_id' => $sch['teacher_id'],
                'teacher_name' => $sch['teacher_name'],
                'classroom' => $sch['classroom'],
                'teacher_latitude' => $sch['teacher_latitude'],
                'teacher_longitude' => $sch['teacher_longitude']
            ];
            break;
        }
    }

    // Build response matching Android DashboardData model
    $response = [
        'profile' => [
            'id' => (int)$student['id'],
            'full_name' => $student['full_name'],
            'roll_number' => $student['roll_number'],
            'department_id' => (int)$student['department_id'],
            'department_name' => $student['department_name'],
            'semester' => (int)$student['semester'],
            'section' => $student['section'],
            'batch_year' => (int)$student['batch_year'],
            'face_registered' => (bool)$student['face_registered'],
            'face_registration_date' => $student['face_registration_date']
        ],
        'overall_attendance' => [
            'total_classes' => (int)($attendance['total_classes'] ?? 0),
            'attended' => (int)($attendance['attended'] ?? 0),
            'percentage' => (float)($attendance['percentage'] ?? 0)
        ],
        'subject_wise' => $formattedSubjectWise,
        'recent_attendance' => $formattedRecent,
        'low_attendance_subjects' => $lowAttendanceSubjects,
        'today_schedule' => $formattedTodaySchedule,
        'active_session' => $activeSession
    ];

    Response::success($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
