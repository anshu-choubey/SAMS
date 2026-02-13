<?php
/**
 * Teacher Schedule API
 * Returns teacher's class schedule
 * Matches Android app's TeacherScheduleResponse model
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
    Auth::hasRole('teacher');
    $user = Auth::user();

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get teacher profile
    $teacherQuery = "SELECT t.* FROM teachers t WHERE t.user_id = :user_id";
    $stmt = $db->prepare($teacherQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Response::error('Teacher profile not found', 404);
    }

    // Get query parameters
    $week = isset($_GET['week']) && $_GET['week'] === 'true';
    $date = $_GET['date'] ?? null;

    // Build schedule query
    $query = "SELECT 
                sc.id,
                sub.id as subject_id,
                sub.name as subject_name,
                sub.code as subject_code,
                u.full_name as teacher_name,
                sc.day_of_week,
                TIME_FORMAT(sc.start_time, '%H:%i:%s') as start_time,
                TIME_FORMAT(sc.end_time, '%H:%i:%s') as end_time,
                sc.classroom,
                sc.building,
                sc.is_active,
                ta.semester,
                ta.section,
                CASE WHEN tl.id IS NOT NULL AND tl.is_active = TRUE THEN 1 ELSE 0 END as session_active
              FROM schedules sc
              JOIN teacher_assignments ta ON sc.assignment_id = ta.id
              JOIN subjects sub ON ta.subject_id = sub.id
              JOIN teachers t ON ta.teacher_id = t.id
              JOIN users u ON t.user_id = u.id
              LEFT JOIN teacher_locations tl ON sc.id = tl.schedule_id AND tl.is_active = TRUE AND DATE(tl.session_start) = CURDATE()
              WHERE ta.teacher_id = :teacher_id
              AND sc.is_active = TRUE
              AND ta.is_active = TRUE";

    $params = [':teacher_id' => $teacher['id']];

    if (!$week && !$date) {
        // Default to today
        $dayOfWeek = date('l');
        $query .= " AND sc.day_of_week = :day_of_week";
        $params[':day_of_week'] = $dayOfWeek;
    } elseif ($date) {
        $dayOfWeek = date('l', strtotime($date));
        $query .= " AND sc.day_of_week = :day_of_week";
        $params[':day_of_week'] = $dayOfWeek;
    }

    $query .= " ORDER BY FIELD(sc.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), sc.start_time";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format schedules to match Android TeacherScheduleItem model
    $currentTime = date('H:i:s');
    $today = date('l');
    
    $formattedSchedules = array_map(function($schedule) use ($currentTime, $today) {
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];
        $isToday = ($schedule['day_of_week'] === $today);
        $isWithinTime = $isToday && ($currentTime >= $startTime && $currentTime <= $endTime);
        $isPast = $isToday && $currentTime > $endTime;
        
        return [
            'schedule_id' => (int)$schedule['id'],
            'subject_id' => (int)$schedule['subject_id'],
            'subject_name' => $schedule['subject_name'],
            'subject_code' => $schedule['subject_code'],
            'day_of_week' => $schedule['day_of_week'],
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
            'semester' => (int)($schedule['semester'] ?? 1),
            'section' => $schedule['section'] ?? 'A',
            'classroom' => $schedule['classroom'],
            'session_active' => (bool)$schedule['session_active'],
            'is_startable' => $isWithinTime,
            'is_completed' => $isPast
        ];
    }, $schedules);
    
    // Group schedules by day of week for Android Map<String, List>
    $groupedSchedules = [];
    foreach ($formattedSchedules as $schedule) {
        $day = $schedule['day_of_week'];
        if (!isset($groupedSchedules[$day])) {
            $groupedSchedules[$day] = [];
        }
        $groupedSchedules[$day][] = $schedule;
    }

    // Get current active class if any
    $currentClass = null;
    $now = date('H:i:s');
    $today = date('l');

    $currentQuery = "SELECT 
                        sc.id,
                        sub.id as subject_id,
                        sub.name as subject_name,
                        sub.code as subject_code,
                        ta.section,
                        TIME_FORMAT(sc.start_time, '%H:%i:%s') as start_time,
                        TIME_FORMAT(sc.end_time, '%H:%i:%s') as end_time,
                        sc.classroom,
                        sc.building,
                        (SELECT COUNT(*) FROM students s WHERE s.department_id = ta.department_id AND s.semester = ta.semester 
                         AND (ta.section IS NULL OR s.section = ta.section)) as total_students,
                        sc.is_active,
                        CASE WHEN tl.id IS NOT NULL AND tl.is_active = TRUE THEN TRUE ELSE FALSE END as session_started
                     FROM schedules sc
                     JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                     JOIN subjects sub ON ta.subject_id = sub.id
                     LEFT JOIN teacher_locations tl ON sc.id = tl.schedule_id AND tl.is_active = TRUE
                     WHERE ta.teacher_id = :teacher_id
                     AND sc.day_of_week = :day_of_week
                     AND sc.start_time <= :now1
                     AND sc.end_time >= :now2
                     AND sc.is_active = TRUE
                     LIMIT 1";
    $currentStmt = $db->prepare($currentQuery);
    $currentStmt->bindParam(':teacher_id', $teacher['id']);
    $currentStmt->bindParam(':day_of_week', $today);
    $currentStmt->bindParam(':now1', $now);
    $currentStmt->bindParam(':now2', $now);
    $currentStmt->execute();
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if ($current) {
        $currentClass = [
            'id' => (int)$current['id'],
            'subject_id' => (int)$current['subject_id'],
            'subject_name' => $current['subject_name'],
            'subject_code' => $current['subject_code'],
            'section' => $current['section'],
            'start_time' => $current['start_time'],
            'end_time' => $current['end_time'],
            'classroom' => $current['classroom'],
            'building' => $current['building'],
            'total_students' => (int)$current['total_students'],
            'is_active' => (bool)$current['is_active'],
            'session_started' => (bool)$current['session_started']
        ];
    }

    // Return TeacherScheduleResponse with grouped schedules
    Response::success([
        'schedules' => $groupedSchedules
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
