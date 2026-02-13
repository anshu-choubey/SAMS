<?php
/**
 * ML Kit Metrics API
 * Face verification statistics and performance metrics
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

// Handle CORS
CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    Auth::hasRole('admin');

    $database = new Database();
    $db = $database->getConnection();

    // Date range filter
    $startDate = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of month
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $departmentId = $_GET['department_id'] ?? null;

    // Overall face verification stats
    $statsQuery = "SELECT 
        COUNT(*) as total_verifications,
        SUM(CASE WHEN verification_status = 'success' THEN 1 ELSE 0 END) as successful_verifications,
        SUM(CASE WHEN verification_status = 'face_failed' THEN 1 ELSE 0 END) as face_failed,
        SUM(CASE WHEN verification_status = 'gps_failed' THEN 1 ELSE 0 END) as gps_failed,
        SUM(CASE WHEN verification_status = 'both_failed' THEN 1 ELSE 0 END) as both_failed,
        AVG(face_confidence_score) as avg_confidence_score,
        MIN(face_confidence_score) as min_confidence_score,
        MAX(face_confidence_score) as max_confidence_score,
        AVG(distance_meters) as avg_distance
        FROM attendance
        WHERE attendance_date BETWEEN :start_date AND :end_date";
    
    if ($departmentId) {
        $statsQuery .= " AND department_id = :department_id";
    }

    $stmt = $db->prepare($statsQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    if ($departmentId) {
        $stmt->bindParam(':department_id', $departmentId);
    }
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Face registration stats
    $regQuery = "SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN face_registered = TRUE THEN 1 ELSE 0 END) as face_registered,
        SUM(CASE WHEN face_registered = FALSE THEN 1 ELSE 0 END) as not_registered
        FROM students";
    
    if ($departmentId) {
        $regQuery .= " WHERE department_id = :department_id";
    }

    $stmt = $db->prepare($regQuery);
    if ($departmentId) {
        $stmt->bindParam(':department_id', $departmentId);
    }
    $stmt->execute();
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    // Students with low face match confidence (average below threshold)
    $lowConfQuery = "SELECT s.id, s.roll_number, u.full_name, d.name as department_name,
                            AVG(a.face_confidence_score) as avg_confidence,
                            COUNT(a.id) as total_attempts
                     FROM students s
                     JOIN users u ON s.user_id = u.id
                     JOIN departments d ON s.department_id = d.id
                     LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date BETWEEN :start_date AND :end_date
                     WHERE s.face_registered = TRUE";
    
    if ($departmentId) {
        $lowConfQuery .= " AND s.department_id = :department_id";
    }
    
    $lowConfQuery .= " GROUP BY s.id, s.roll_number, u.full_name, d.name
                       HAVING avg_confidence < :threshold AND avg_confidence IS NOT NULL
                       ORDER BY avg_confidence ASC
                       LIMIT 20";

    $stmt = $db->prepare($lowConfQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $threshold = FACE_CONFIDENCE_THRESHOLD;
    $stmt->bindParam(':threshold', $threshold);
    if ($departmentId) {
        $stmt->bindParam(':department_id', $departmentId);
    }
    $stmt->execute();
    $lowConfidenceStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Daily verification trend
    $trendQuery = "SELECT 
        DATE(attendance_date) as date,
        COUNT(*) as total,
        SUM(CASE WHEN verification_status = 'success' THEN 1 ELSE 0 END) as successful,
        AVG(face_confidence_score) as avg_confidence
        FROM attendance
        WHERE attendance_date BETWEEN :start_date AND :end_date";
    
    if ($departmentId) {
        $trendQuery .= " AND department_id = :department_id";
    }
    
    $trendQuery .= " GROUP BY DATE(attendance_date) ORDER BY date";

    $stmt = $db->prepare($trendQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    if ($departmentId) {
        $stmt->bindParam(':department_id', $departmentId);
    }
    $stmt->execute();
    $dailyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Confidence score distribution
    $distQuery = "SELECT 
        CASE 
            WHEN face_confidence_score >= 95 THEN '95-100'
            WHEN face_confidence_score >= 90 THEN '90-94'
            WHEN face_confidence_score >= 85 THEN '85-89'
            WHEN face_confidence_score >= 80 THEN '80-84'
            WHEN face_confidence_score >= 70 THEN '70-79'
            ELSE 'Below 70'
        END as confidence_range,
        COUNT(*) as count
        FROM attendance
        WHERE attendance_date BETWEEN :start_date AND :end_date
        AND face_confidence_score IS NOT NULL";
    
    if ($departmentId) {
        $distQuery .= " AND department_id = :department_id";
    }
    
    $distQuery .= " GROUP BY confidence_range ORDER BY MIN(face_confidence_score) DESC";

    $stmt = $db->prepare($distQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    if ($departmentId) {
        $stmt->bindParam(':department_id', $departmentId);
    }
    $stmt->execute();
    $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalVerifications = (int)$stats['total_verifications'];
    $successfulVerifications = (int)$stats['successful_verifications'];

    Response::success([
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'verification_stats' => [
            'total_verifications' => $totalVerifications,
            'successful' => $successfulVerifications,
            'face_failed' => (int)$stats['face_failed'],
            'gps_failed' => (int)$stats['gps_failed'],
            'both_failed' => (int)$stats['both_failed'],
            'success_rate' => $totalVerifications > 0 ? round(($successfulVerifications / $totalVerifications) * 100, 2) : 0
        ],
        'confidence_metrics' => [
            'average' => round((float)$stats['avg_confidence_score'], 2),
            'minimum' => round((float)$stats['min_confidence_score'], 2),
            'maximum' => round((float)$stats['max_confidence_score'], 2),
            'threshold' => FACE_CONFIDENCE_THRESHOLD
        ],
        'gps_metrics' => [
            'average_distance_meters' => round((float)$stats['avg_distance'], 2),
            'proximity_radius' => GPS_PROXIMITY_RADIUS
        ],
        'registration_stats' => [
            'total_students' => (int)$registration['total_students'],
            'face_registered' => (int)$registration['face_registered'],
            'not_registered' => (int)$registration['not_registered'],
            'registration_rate' => (int)$registration['total_students'] > 0 
                ? round(((int)$registration['face_registered'] / (int)$registration['total_students']) * 100, 2) 
                : 0
        ],
        'low_confidence_students' => array_map(function($s) {
            return [
                'student_id' => (int)$s['id'],
                'roll_number' => $s['roll_number'],
                'full_name' => $s['full_name'],
                'department' => $s['department_name'],
                'avg_confidence' => round((float)$s['avg_confidence'], 2),
                'total_attempts' => (int)$s['total_attempts']
            ];
        }, $lowConfidenceStudents),
        'daily_trend' => array_map(function($t) {
            return [
                'date' => $t['date'],
                'total' => (int)$t['total'],
                'successful' => (int)$t['successful'],
                'avg_confidence' => round((float)$t['avg_confidence'], 2)
            ];
        }, $dailyTrend),
        'confidence_distribution' => $distribution
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
