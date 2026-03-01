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
                ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as overall_attendance,
                ROUND(AVG(CASE WHEN face_confidence_score IS NOT NULL THEN face_confidence_score END), 2) as avg_face_confidence
              FROM attendance
              WHERE attendance_date BETWEEN :from_date AND :to_date
              AND student_id IS NOT NULL";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure numeric values
    $overall['total_students'] = (int)($overall['total_students'] ?? 0);
    $overall['total_records'] = (int)($overall['total_records'] ?? 0);
    $overall['present_count'] = (int)($overall['present_count'] ?? 0);
    $overall['overall_attendance'] = (float)($overall['overall_attendance'] ?? 0);
    $overall['avg_face_confidence'] = (float)($overall['avg_face_confidence'] ?? 0);

    // Students above 75%
    $query = "SELECT COUNT(DISTINCT student_id) as count
              FROM (
                  SELECT student_id,
                         ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as percentage
                  FROM attendance
                  WHERE attendance_date BETWEEN :from_date AND :to_date
                  GROUP BY student_id
                  HAVING percentage >= 75 AND percentage IS NOT NULL
              ) as temp";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    $above75 = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['count' => 0];

    // Low attendance count
    $query = "SELECT COUNT(DISTINCT student_id) as count
              FROM (
                  SELECT student_id,
                         ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as percentage
                  FROM attendance
                  WHERE attendance_date BETWEEN :from_date AND :to_date
                  GROUP BY student_id
                  HAVING percentage < 75 OR percentage IS NULL
              ) as temp";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    $lowCount = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['count' => 0];

    // Trend data (last 7 days)
    $trendQuery = "SELECT 
                      attendance_date,
                      ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as percentage
                   FROM attendance
                   WHERE attendance_date BETWEEN DATE_SUB(:to_date1, INTERVAL 6 DAY) AND :to_date2
                   AND student_id IS NOT NULL
                   GROUP BY attendance_date
                   ORDER BY attendance_date ASC";
    
    $stmt = $db->prepare($trendQuery);
    $stmt->bindParam(':to_date1', $toDate);
    $stmt->bindParam(':to_date2', $toDate);
    $stmt->execute();
    $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Verification data
    $verifyQuery = "SELECT 
                       COALESCE(verification_status, 'no_data') as verification_status,
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
        'both_failed' => 0,
        'no_data' => 0
    ];

    foreach ($verifyData as $row) {
        if (isset($verificationStats[$row['verification_status']])) {
            $verificationStats[$row['verification_status']] = (int)$row['count'];
        }
    }

    return [
        'overall_attendance' => $overall['overall_attendance'],
        'students_above_75' => (int)($above75['count'] ?? 0),
        'low_attendance_count' => (int)($lowCount['count'] ?? 0),
        'avg_face_confidence' => $overall['avg_face_confidence'],
        'trend_data' => [
            'labels' => array_column($trendData, 'attendance_date'),
            'values' => array_map('floatval', array_column($trendData, 'percentage'))
        ],
        'verification_data' => $verificationStats
    ];
}

function getDepartmentReport($db, $fromDate, $toDate) {
    $query = "SELECT 
                d.id,
                d.code,
                d.name as department_name,
                COUNT(DISTINCT a.student_id) as total_students,
                COUNT(*) as total_classes,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
                ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as avg_attendance
              FROM departments d
              LEFT JOIN attendance a ON d.id = a.department_id 
                  AND a.attendance_date BETWEEN :from_date AND :to_date
              GROUP BY d.id, d.code, d.name
              ORDER BY avg_attendance DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure numeric values
    foreach ($result as &$row) {
        $row['total_students'] = (int)$row['total_students'];
        $row['total_classes'] = (int)$row['total_classes'];
        $row['total_present'] = (int)$row['total_present'];
        $row['avg_attendance'] = (float)($row['avg_attendance'] ?? 0);
    }
    
    return $result;
}

function getStudentReport($db, $fromDate, $toDate, $departmentId = null) {
    $query = "SELECT 
                s.id as student_id,
                s.roll_number,
                u.full_name,
                u.email,
                d.name as department_name,
                COUNT(a.id) as total_classes,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as attended,
                ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id), 0)) * 100, 2) as percentage
              FROM students s
              JOIN users u ON s.user_id = u.id
              JOIN departments d ON s.department_id = d.id
              LEFT JOIN attendance a ON s.id = a.student_id 
                  AND a.attendance_date BETWEEN :from_date AND :to_date
              WHERE u.is_active = TRUE";
    
    if ($departmentId) {
        $query .= " AND s.department_id = :department_id";
    }
    
    $query .= " GROUP BY s.id, s.roll_number, u.full_name, u.email, d.name ORDER BY percentage DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_date', $fromDate);
    $stmt->bindParam(':to_date', $toDate);
    
    if ($departmentId) {
        $stmt->bindParam(':department_id', $departmentId);
    }
    
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure numeric values
    foreach ($result as &$row) {
        $row['student_id'] = (int)$row['student_id'];
        $row['total_classes'] = (int)$row['total_classes'];
        $row['attended'] = (int)$row['attended'];
        $row['percentage'] = (float)($row['percentage'] ?? 0);
    }
    
    return $result;
}

function getLowAttendanceStudents($db, $threshold) {
    $query = "SELECT 
                s.id as student_id,
                s.roll_number,
                u.full_name,
                u.email,
                d.name as department_name,
                COUNT(a.id) as total_classes,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as attended,
                ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id), 0)) * 100, 2) as percentage
              FROM students s
              JOIN users u ON s.user_id = u.id
              JOIN departments d ON s.department_id = d.id
              LEFT JOIN attendance a ON s.id = a.student_id
              WHERE u.is_active = TRUE
              GROUP BY s.id, s.roll_number, u.full_name, u.email, d.name
              HAVING COALESCE(percentage, 0) < :threshold
              ORDER BY percentage ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure numeric values
    foreach ($result as &$row) {
        $row['student_id'] = (int)$row['student_id'];
        $row['total_classes'] = (int)$row['total_classes'];
        $row['attended'] = (int)$row['attended'];
        $row['percentage'] = (float)($row['percentage'] ?? 0);
    }
    
    return $result;
}
?>
