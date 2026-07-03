<?php
/**
 * Teacher Start Class API
 * Starts a class session and enables attendance marking
 * Matches Android app's ClassSession response model
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

// Handle CORS
CORS::handle();

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check authentication and role
    $user = Auth::user();
    
    if (!$user) {
        Response::unauthorized('Please login to continue');
    }
    
    if ($user['role'] !== 'teacher') {
        Response::error('Access restricted to teachers only', 403);
    }

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get POST data (matching StartClassRequest)
    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('schedule_id', $data['schedule_id'] ?? '', 'Schedule ID');
    $validator->required('latitude', $data['latitude'] ?? '', 'Latitude');
    $validator->required('longitude', $data['longitude'] ?? '', 'Longitude');
    $validator->numeric('latitude', $data['latitude'] ?? '', 'Latitude');
    $validator->numeric('longitude', $data['longitude'] ?? '', 'Longitude');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }

    // Get teacher profile
    $teacherQuery = "SELECT t.* FROM teachers t WHERE t.user_id = :user_id";
    $stmt = $db->prepare($teacherQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Response::error('Teacher profile not found', 404);
    }

    // Verify schedule belongs to this teacher
    // First verify teacher is assigned to this schedule with explicit INNER JOIN
    $authQuery = "SELECT ta.id as assignment_id FROM schedules sc
                  JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                  WHERE sc.id = :schedule_id AND ta.teacher_id = :teacher_id AND sc.is_active = TRUE
                  LIMIT 1";
    $stmt = $db->prepare($authQuery);
    $stmt->bindParam(':schedule_id', $data['schedule_id']);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->execute();
    $authorization = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$authorization) {
        // Check if schedule exists at all
        $checkSchedule = "SELECT id FROM schedules WHERE id = :schedule_id LIMIT 1";
        $stmt = $db->prepare($checkSchedule);
        $stmt->bindParam(':schedule_id', $data['schedule_id']);
        $stmt->execute();
        if (!$stmt->fetch()) {
            Response::error('Schedule not found', 404);
        }
        // Schedule exists but not assigned to this teacher
        Response::error('This schedule is not assigned to you', 403);
    }

    // Now get full schedule details
    $scheduleQuery = "SELECT sc.*, ta.teacher_id, ta.subject_id, ta.department_id, ta.semester, ta.section, ta.is_active as assignment_active
                      FROM schedules sc
                      LEFT JOIN teacher_assignments ta ON sc.assignment_id = ta.id
                      WHERE sc.id = :schedule_id AND sc.is_active = TRUE";
    $stmt = $db->prepare($scheduleQuery);
    $stmt->bindParam(':schedule_id', $data['schedule_id']);
    $stmt->execute();
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        Response::error('Schedule not found or inactive', 404);
    }

    // Check if assignment is active
    if ((int)$schedule['assignment_active'] !== 1) {
        Response::error('Assignment is currently inactive', 403);
    }

    // Check if session already started
    $existingQuery = "SELECT id FROM teacher_locations 
                      WHERE schedule_id = :schedule_id AND is_active = TRUE";
    $stmt = $db->prepare($existingQuery);
    $stmt->bindParam(':schedule_id', $data['schedule_id']);
    $stmt->execute();
    if ($stmt->fetch()) {
        Response::error('Class session already started', 400);
    }

    // Get duration (default 60 minutes)
    $durationMinutes = isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : 60;
    $notes = $data['notes'] ?? null;
    
    // Multi-check configuration (default: enabled with 2-3 random checks)
    $multiCheckEnabled = isset($data['multi_check_enabled']) ? (bool)$data['multi_check_enabled'] : true;
    $totalChecksPlanned = isset($data['total_checks']) ? (int)$data['total_checks'] : rand(2, 3);
    
    // Auto-schedule configuration (intervals: 20min, 40min, 60min)
    $autoSchedule = isset($data['auto_schedule']) ? (bool)$data['auto_schedule'] : false;
    $firstCheckDelay = isset($data['first_check_delay']) ? (int)$data['first_check_delay'] : 20; // minutes

    // Start class session - insert into teacher_locations
    $insertQuery = "INSERT INTO teacher_locations 
                    (teacher_id, schedule_id, assignment_id, department_id, latitude, longitude, 
                     is_active, session_start, multi_check_enabled, total_checks_planned, 
                     auto_schedule, first_check_delay)
                    VALUES (:teacher_id, :schedule_id, :assignment_id, :department_id, :latitude, :longitude, 
                            TRUE, NOW(), :multi_check, :total_checks, :auto_schedule, :first_check_delay)";
    $stmt = $db->prepare($insertQuery);
    $stmt->bindParam(':teacher_id', $teacher['id']);
    $stmt->bindParam(':schedule_id', $data['schedule_id']);
    $stmt->bindParam(':assignment_id', $schedule['assignment_id']);
    $stmt->bindParam(':department_id', $schedule['department_id']);
    $stmt->bindParam(':latitude', $data['latitude']);
    $stmt->bindParam(':longitude', $data['longitude']);
    $stmt->bindParam(':multi_check', $multiCheckEnabled, PDO::PARAM_BOOL);
    $stmt->bindParam(':total_checks', $totalChecksPlanned);
    $stmt->bindParam(':auto_schedule', $autoSchedule, PDO::PARAM_BOOL);
    $stmt->bindParam(':first_check_delay', $firstCheckDelay);
    $stmt->execute();

    $sessionId = $db->lastInsertId();

    // Calculate expected end time
    $startedAt = date('Y-m-d H:i:s');
    $expectedEnd = date('Y-m-d H:i:s', strtotime("+{$durationMinutes} minutes"));

    // Generate QR code data (optional - can be used for quick check-in)
    $qrData = json_encode([
        'session_id' => $sessionId,
        'schedule_id' => $data['schedule_id'],
        'timestamp' => time()
    ]);
    $qrCode = base64_encode($qrData);

    // Auto-schedule checks if enabled
    if ($multiCheckEnabled && $autoSchedule && $totalChecksPlanned > 0) {
        // Calculate random intervals (spread across duration)
        $intervals = [];
        $remainingTime = $durationMinutes - $firstCheckDelay;
        $intervalGap = floor($remainingTime / max(1, $totalChecksPlanned - 1));
        
        for ($i = 0; $i < $totalChecksPlanned; $i++) {
            if ($i == 0) {
                $intervals[] = $firstCheckDelay;
            } else {
                // Add randomness: ±5 minutes
                $baseInterval = $firstCheckDelay + ($intervalGap * $i);
                $randomOffset = rand(-5, 5);
                $intervals[] = max($firstCheckDelay, min($durationMinutes - 5, $baseInterval + $randomOffset));
            }
        }
        
        // Store scheduled times
        foreach ($intervals as $index => $minutesFromStart) {
            $scheduledTime = date('Y-m-d H:i:s', strtotime("+{$minutesFromStart} minutes"));
            $windowEnd = date('Y-m-d H:i:s', strtotime("+5 minutes", strtotime($scheduledTime)));
            
            $scheduleQuery = "INSERT INTO attendance_check_points 
                              (session_id, schedule_id, check_number, check_time, window_end_time, is_active)
                              VALUES (:session_id, :schedule_id, :check_number, :check_time, :window_end, FALSE)";
            $stmt = $db->prepare($scheduleQuery);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':schedule_id', $data['schedule_id']);
            $checkNumber = $index + 1;
            $stmt->bindParam(':check_number', $checkNumber);
            $stmt->bindParam(':check_time', $scheduledTime);
            $stmt->bindParam(':window_end', $windowEnd);
            $stmt->execute();
        }
    }
    
    // Return ClassSession response
    Response::success([
        'session_id' => (int)$sessionId,
        'schedule_id' => (int)$data['schedule_id'],
        'started_at' => $startedAt,
        'expected_end' => $expectedEnd,
        'qr_code' => $qrCode,
        'attendance_window_minutes' => $durationMinutes,
        'multi_check_enabled' => $multiCheckEnabled,
        'total_checks_planned' => $totalChecksPlanned,
        'auto_schedule' => $autoSchedule,
        'scheduled_check_times' => $autoSchedule ? $intervals : []
    ], 'Class session started successfully. ' . ($multiCheckEnabled ? "Multi-check attendance enabled ({$totalChecksPlanned} " . ($autoSchedule ? "auto-scheduled" : "manual") . " checks planned)." : 'Single attendance check.'));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
