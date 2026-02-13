<?php
/**
 * Reports & Analytics API
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

try {
    Auth::hasRole('admin');

    $database = new Database();
    $db = $database->getConnection();

    $type = $_GET['type'] ?? 'quick_stats';
    $fromDate = $_GET['from'] ?? date('Y-m-01');
    $toDate = $_GET['to'] ?? date('Y-m-d');
    $departmentId = $_GET['department_id'] ?? null;

    switch ($type) {
        case 'quick_stats':
            $stats = getQuickStats($db, $fromDate, $toDate);
            Response::success($stats);
            break;

        case 'department':
            $report = getDepartmentReport($db, $fromDate, $toDate);
            Response::success(['report' => $report]);
            break;

        case 'student':
            $report = getStudentReport($db, $fromDate, $toDate, $departmentId);
            Response::success(['report' => $report]);
            break;

        case 'low_attendance':
            $threshold = $_GET['threshold'] ?? 75;
            $students = getLowAttendanceStudents($db, $threshold);
            Response::success(['students' => $students]);
            break;

        default:
            Response::error('Invalid report type', 400);
    }

} catch (Exception $e) {
    Response::error('Failed to generate report: ' . $e->getMessage(), 500);
}

function getQuickStats($db, $fromDate, $toDate) {
    // Overall attendance
    $query = "SELECT 
                COUNT(DISTINCT student_id) as total_students,
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as overall_attendance,
                ROUND(AVG(face_confidence_score), 2) as avg_face_confidence
              FROM attendance
              WHERE attendance_date BETWEEN :from_date AND :to_date";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);

    // Students above 75%
    $query = "SELECT COUNT(DISTINCT student_id) as count
              FROM (
                  SELECT student_id,
                         ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
                  FROM attendance
                  WHERE attendance_date BETWEEN :from_date AND :to_date
                  GROUP BY student_id
                  HAVING percentage >= 75
              ) as temp";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    $above75 = $stmt->fetch(PDO::FETCH_ASSOC);

    // Low attendance count
    $query = "SELECT COUNT(DISTINCT student_id) as count
              FROM (
                  SELECT student_id,
                         ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
                  FROM attendance
                  WHERE attendance_date BETWEEN :from_date AND :to_date
                  GROUP BY student_id
                  HAVING percentage < 75
              ) as temp";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    $lowCount = $stmt->fetch(PDO::FETCH_ASSOC);

    // Trend data (last 7 days)
    $trendQuery = "SELECT 
                      attendance_date,
                      ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
                   FROM attendance
                   WHERE attendance_date BETWEEN DATE_SUB(:to_date1, INTERVAL 6 DAY) AND :to_date2
                   GROUP BY attendance_date
                   ORDER BY attendance_date";
    
    $stmt = $db->prepare($trendQuery);
    $stmt->bindParam(':to_date1', $toDate);
    $stmt->bindParam(':to_date2', $toDate);
    $stmt->execute();
    $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Verification data
    $verifyQuery = "SELECT 
                       verification_status,
                       COUNT(*) as count
                    FROM attendance
                    WHERE attendance_date BETWEEN :from_date AND :to_date
                    GROUP BY verification_status";
    
    $stmt = $db->prepare($verifyQuery);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    $verifyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $verificationStats = [
        'success' => 0,
        'gps_failed' => 0,
        'face_failed' => 0,
        'both_failed' => 0
    ];

    foreach ($verifyData as $row) {
        $verificationStats[$row['verification_status']] = (int)$row['count'];
    }

    return [
        'overall_attendance' => (float)$overall['overall_attendance'],
        'students_above_75' => (int)$above75['count'],
        'low_attendance_count' => (int)$lowCount['count'],
        'avg_face_confidence' => (float)$overall['avg_face_confidence'],
        'trend_data' => [
            'labels' => array_column($trendData, 'attendance_date'),
            'values' => array_column($trendData, 'percentage')
        ],
        'verification_data' => $verificationStats
    ];
}

function getDepartmentReport($db, $fromDate, $toDate) {
    $query = "SELECT 
                d.name as department_name,
                COUNT(DISTINCT a.student_id) as total_students,
                COUNT(*) as total_classes,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
                ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as avg_attendance
              FROM attendance a
              JOIN departments d ON a.department_id = d.id
              WHERE a.attendance_date BETWEEN :from_date AND :to_date
              GROUP BY d.id
              ORDER BY avg_attendance DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentReport($db, $fromDate, $toDate, $departmentId = null) {
    $query = "SELECT 
                s.roll_number,
                u.full_name,
                d.name as department_name,
                COUNT(*) as total_classes,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as attended,
                ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
              FROM attendance a
              JOIN students s ON a.student_id = s.id
              JOIN users u ON s.user_id = u.id
              JOIN departments d ON s.department_id = d.id
              WHERE a.attendance_date BETWEEN :from_date AND :to_date";
    
    if ($departmentId) {
        $query .= " AND s.department_id = :department_id";
    }
    
    $query .= " GROUP BY s.id ORDER BY percentage DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    
    if ($departmentId) {
        $stmt->bindParam(':department_id', $departmentId);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLowAttendanceStudents($db, $threshold) {
    $query = "SELECT 
                s.id as student_id,
                s.roll_number,
                u.full_name,
                u.email,
                d.name as department_name,
                COUNT(*) as total_classes,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as attended,
                ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
              FROM students s
              JOIN users u ON s.user_id = u.id
              JOIN departments d ON s.department_id = d.id
              LEFT JOIN attendance a ON s.id = a.student_id
              WHERE u.is_active = TRUE
              GROUP BY s.id
              HAVING percentage < :threshold OR percentage IS NULL
              ORDER BY percentage ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':threshold', $threshold);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
