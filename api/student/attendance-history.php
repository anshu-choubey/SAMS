<?php
/**
 * Student Attendance History API
 * Returns paginated attendance records for the student
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
    $studentQuery = "SELECT s.* FROM students s WHERE s.user_id = :user_id";
    $stmt = $db->prepare($studentQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        Response::error('Student profile not found', 404);
    }

    // Get query parameters
    $subjectId = $_GET['subject_id'] ?? null;
    $fromDate = $_GET['from_date'] ?? null;
    $toDate = $_GET['to_date'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? ITEMS_PER_PAGE);
    $offset = ($page - 1) * $perPage;

    // Build query
    $whereConditions = ["a.student_id = :student_id"];
    $params = [':student_id' => $student['id']];

    if ($subjectId) {
        $whereConditions[] = "ta.subject_id = :subject_id";
        $params[':subject_id'] = $subjectId;
    }

    if ($fromDate) {
        $whereConditions[] = "a.attendance_date >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $whereConditions[] = "a.attendance_date <= :to_date";
        $params[':to_date'] = $toDate;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM attendance a
                   JOIN teacher_assignments ta ON a.assignment_id = ta.id
                   WHERE $whereClause";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $perPage);

    // Get attendance records
    $query = "SELECT 
                a.id,
                a.schedule_id,
                a.attendance_date,
                a.attendance_time,
                a.status,
                a.verification_status,
                a.face_confidence_score,
                a.distance_meters,
                a.remarks,
                sub.id as subject_id,
                sub.name as subject_name,
                sub.code as subject_code,
                u.full_name as teacher_name,
                sc.start_time,
                sc.end_time,
                sc.classroom,
                d.name as department_name
              FROM attendance a
              JOIN teacher_assignments ta ON a.assignment_id = ta.id
              JOIN subjects sub ON ta.subject_id = sub.id
              JOIN teachers t ON ta.teacher_id = t.id
              JOIN users u ON t.user_id = u.id
              JOIN schedules sc ON a.schedule_id = sc.id
              JOIN departments d ON ta.department_id = d.id
              WHERE $whereClause
              ORDER BY a.attendance_date DESC, a.attendance_time DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format records to match Android AttendanceRecord model
    $formattedRecords = array_map(function($record) {
        return [
            'attendance_id' => (int)$record['id'],
            'schedule_id' => (int)$record['schedule_id'],
            'subject_name' => $record['subject_name'],
            'subject_code' => $record['subject_code'],
            'teacher_name' => $record['teacher_name'],
            'date' => $record['attendance_date'],
            'time' => $record['attendance_time'],
            'status' => $record['status'],
            'verification_status' => $record['verification_status'],
            'face_confidence' => $record['face_confidence_score'] !== null ? (float)$record['face_confidence_score'] : null,
            'distance_meters' => $record['distance_meters'] !== null ? (float)$record['distance_meters'] : null
        ];
    }, $records);

    Response::success([
        'records' => $formattedRecords,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => (int)$totalPages,
            'total_records' => $totalRecords,
            'per_page' => $perPage
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
