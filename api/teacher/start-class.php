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

    // Now get full schedule details including interval settings
    $scheduleQuery = "SELECT sc.*, 
                             sc.total_checks, sc.min_interval_minutes, sc.max_interval_minutes,
                             sc.response_window_minutes, sc.hide_timing_from_students,
                             sc.random_intervals_enabled, sc.auto_trigger_enabled, sc.duration_minutes,
                             ta.teacher_id, ta.subject_id, ta.department_id, ta.semester, ta.section, 
                             ta.is_active as assignment_active
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

    // ═══════════════════════════════════════════════════════════════════════
    // INTERVAL CONFIGURATION - Priority: Request > Schedule > System Settings
    // ═══════════════════════════════════════════════════════════════════════
    
    // Step 1: Start with defaults
    $durationMinutes = 60;
    $multiCheckEnabled = true;
    $totalChecksPlanned = 3;
    $autoSchedule = true;
    $firstCheckDelay = 10;
    $randomIntervalsEnabled = true;
    $minIntervalMinutes = 10;
    $maxIntervalMinutes = 25;
    $hideTimingFromStudents = true;
    $autoTriggerChecks = true;
    $responseWindowMinutes = 3;
    
    // Step 2: Apply system-wide settings
    try {
        $settingsQuery = "SELECT `key`, value FROM system_settings WHERE `key` IN (
            'attendance_random_intervals_enabled',
            'attendance_min_interval_minutes', 
            'attendance_max_interval_minutes',
            'attendance_hide_timing_from_students',
            'attendance_auto_trigger_enabled',
            'attendance_response_window_minutes'
        )";
        $stmt = $db->query($settingsQuery);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['key']) {
                case 'attendance_random_intervals_enabled':
                    $randomIntervalsEnabled = $row['value'] === 'true' || $row['value'] === '1';
                    break;
                case 'attendance_min_interval_minutes':
                    $minIntervalMinutes = (int)$row['value'];
                    break;
                case 'attendance_max_interval_minutes':
                    $maxIntervalMinutes = (int)$row['value'];
                    break;
                case 'attendance_hide_timing_from_students':
                    $hideTimingFromStudents = $row['value'] === 'true' || $row['value'] === '1';
                    break;
                case 'attendance_auto_trigger_enabled':
                    $autoTriggerChecks = $row['value'] === 'true' || $row['value'] === '1';
                    break;
                case 'attendance_response_window_minutes':
                    $responseWindowMinutes = (int)$row['value'];
                    break;
            }
        }
    } catch (Exception $e) {
        // Use defaults if settings fetch fails
    }
    
    // Step 3: Apply SCHEDULE-SPECIFIC settings (override system settings)
    // These are set by admin per-class
    if (isset($schedule['total_checks']) && $schedule['total_checks'] !== null) {
        $totalChecksPlanned = (int)$schedule['total_checks'];
    }
    if (isset($schedule['min_interval_minutes']) && $schedule['min_interval_minutes'] !== null) {
        $minIntervalMinutes = (int)$schedule['min_interval_minutes'];
    }
    if (isset($schedule['max_interval_minutes']) && $schedule['max_interval_minutes'] !== null) {
        $maxIntervalMinutes = (int)$schedule['max_interval_minutes'];
    }
    if (isset($schedule['response_window_minutes']) && $schedule['response_window_minutes'] !== null) {
        $responseWindowMinutes = (int)$schedule['response_window_minutes'];
    }
    if (isset($schedule['hide_timing_from_students']) && $schedule['hide_timing_from_students'] !== null) {
        $hideTimingFromStudents = (bool)$schedule['hide_timing_from_students'];
    }
    if (isset($schedule['random_intervals_enabled']) && $schedule['random_intervals_enabled'] !== null) {
        $randomIntervalsEnabled = (bool)$schedule['random_intervals_enabled'];
    }
    if (isset($schedule['auto_trigger_enabled']) && $schedule['auto_trigger_enabled'] !== null) {
        $autoTriggerChecks = (bool)$schedule['auto_trigger_enabled'];
    }
    if (isset($schedule['duration_minutes']) && $schedule['duration_minutes'] !== null) {
        $durationMinutes = (int)$schedule['duration_minutes'];
    }
    
    // Step 4: Apply REQUEST data (teacher can override at session start)
    if (isset($data['duration_minutes'])) {
        $durationMinutes = (int)$data['duration_minutes'];
    }
    if (isset($data['multi_check_enabled'])) {
        $multiCheckEnabled = (bool)$data['multi_check_enabled'];
    }
    if (isset($data['total_checks'])) {
        $totalChecksPlanned = (int)$data['total_checks'];
    }
    if (isset($data['auto_schedule'])) {
        $autoSchedule = (bool)$data['auto_schedule'];
    }
    if (isset($data['first_check_delay'])) {
        $firstCheckDelay = (int)$data['first_check_delay'];
    }
    if (isset($data['random_intervals_enabled'])) {
        $randomIntervalsEnabled = (bool)$data['random_intervals_enabled'];
    }
    if (isset($data['min_interval_minutes'])) {
        $minIntervalMinutes = (int)$data['min_interval_minutes'];
    }
    if (isset($data['max_interval_minutes'])) {
        $maxIntervalMinutes = (int)$data['max_interval_minutes'];
    }
    if (isset($data['hide_timing_from_students'])) {
        $hideTimingFromStudents = (bool)$data['hide_timing_from_students'];
    }
    if (isset($data['auto_trigger_checks'])) {
        $autoTriggerChecks = (bool)$data['auto_trigger_checks'];
    }
    if (isset($data['response_window_minutes'])) {
        $responseWindowMinutes = (int)$data['response_window_minutes'];
    }
    
    $notes = $data['notes'] ?? null;

    // Generate first check time (random interval from class start)
    $firstCheckMinutes = $randomIntervalsEnabled 
        ? rand($minIntervalMinutes, $maxIntervalMinutes)
        : $firstCheckDelay;
    $nextCheckTime = date('Y-m-d H:i:s', strtotime("+{$firstCheckMinutes} minutes"));
    
    // Start class session - insert into teacher_locations with random interval config
    $insertQuery = "INSERT INTO teacher_locations 
                    (teacher_id, schedule_id, assignment_id, department_id, latitude, longitude, 
                     is_active, session_start, multi_check_enabled, total_checks_planned, 
                     auto_schedule, first_check_delay, random_intervals_enabled, min_interval_minutes,
                     max_interval_minutes, hide_timing_from_students, auto_trigger_checks, next_check_time)
                    VALUES (:teacher_id, :schedule_id, :assignment_id, :department_id, :latitude, :longitude, 
                            TRUE, NOW(), :multi_check, :total_checks, :auto_schedule, :first_check_delay,
                            :random_intervals, :min_interval, :max_interval, :hide_timing, :auto_trigger, :next_check)";
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
    $stmt->bindParam(':random_intervals', $randomIntervalsEnabled, PDO::PARAM_BOOL);
    $stmt->bindParam(':min_interval', $minIntervalMinutes);
    $stmt->bindParam(':max_interval', $maxIntervalMinutes);
    $stmt->bindParam(':hide_timing', $hideTimingFromStudents, PDO::PARAM_BOOL);
    $stmt->bindParam(':auto_trigger', $autoTriggerChecks, PDO::PARAM_BOOL);
    $stmt->bindParam(':next_check', $nextCheckTime);
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

    // Generate random check intervals if enabled
    $scheduledIntervals = [];
    $checkTimesForTeacher = []; // Only teacher sees actual times
    
    if ($multiCheckEnabled && $totalChecksPlanned > 0) {
        // Calculate random intervals (spread across duration)
        $usedMinutes = [];
        
        for ($i = 0; $i < $totalChecksPlanned; $i++) {
            if ($i == 0) {
                // First check: random between min and max interval from start
                $checkMinutes = $randomIntervalsEnabled 
                    ? rand($minIntervalMinutes, $maxIntervalMinutes)
                    : $firstCheckDelay;
            } else {
                // Subsequent checks: random interval from last check
                $lastCheckMinutes = end($scheduledIntervals);
                $minNext = $lastCheckMinutes + $minIntervalMinutes;
                $maxNext = min($durationMinutes - $responseWindowMinutes, $lastCheckMinutes + $maxIntervalMinutes);
                
                if ($minNext >= $maxNext || $minNext >= $durationMinutes - $responseWindowMinutes) {
                    // Not enough time for more checks
                    break;
                }
                
                $checkMinutes = $randomIntervalsEnabled 
                    ? rand($minNext, $maxNext)
                    : $minNext;
            }
            
            // Ensure we don't exceed class duration
            if ($checkMinutes >= $durationMinutes - $responseWindowMinutes) {
                break;
            }
            
            $scheduledIntervals[] = $checkMinutes;
        }
        
        // Store scheduled check points (with is_scheduled = TRUE, is_active = FALSE initially)
        foreach ($scheduledIntervals as $index => $minutesFromStart) {
            $scheduledTime = date('Y-m-d H:i:s', strtotime("+{$minutesFromStart} minutes"));
            $windowEnd = date('Y-m-d H:i:s', strtotime("+{$responseWindowMinutes} minutes", strtotime($scheduledTime)));
            
            $scheduleQuery = "INSERT INTO attendance_check_points 
                              (session_id, schedule_id, check_number, check_time, window_end_time, 
                               is_active, is_scheduled, scheduled_time, was_auto_triggered)
                              VALUES (:session_id, :schedule_id, :check_number, :check_time, :window_end, 
                                      FALSE, TRUE, :scheduled_time, FALSE)";
            $stmt = $db->prepare($scheduleQuery);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':schedule_id', $data['schedule_id']);
            $checkNumber = $index + 1;
            $stmt->bindParam(':check_number', $checkNumber);
            $stmt->bindParam(':check_time', $scheduledTime);
            $stmt->bindParam(':window_end', $windowEnd);
            $stmt->bindParam(':scheduled_time', $scheduledTime);
            $stmt->execute();
            
            $checkTimesForTeacher[] = [
                'check_number' => $checkNumber,
                'scheduled_at' => $scheduledTime,
                'window_end' => $windowEnd,
                'minutes_from_start' => $minutesFromStart
            ];
        }
        
        // Update total_checks_planned to actual count
        $actualChecksPlanned = count($scheduledIntervals);
        $updateQuery = "UPDATE teacher_locations SET total_checks_planned = :count WHERE id = :session_id";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':count', $actualChecksPlanned);
        $stmt->bindParam(':session_id', $sessionId);
        $stmt->execute();
        $totalChecksPlanned = $actualChecksPlanned;
    }
    
    // Return ClassSession response
    // Teacher gets full timing info, students get only count (via separate API)
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
        'random_intervals_enabled' => $randomIntervalsEnabled,
        'min_interval_minutes' => $minIntervalMinutes,
        'max_interval_minutes' => $maxIntervalMinutes,
        'hide_timing_from_students' => $hideTimingFromStudents,
        'response_window_minutes' => $responseWindowMinutes,
        // Teacher sees scheduled times
        'scheduled_check_times' => $checkTimesForTeacher,
        // For backward compatibility
        'scheduled_check_intervals' => $scheduledIntervals
    ], 'Class session started successfully. ' . ($multiCheckEnabled ? "Multi-check attendance with random intervals ({$totalChecksPlanned} checks). " . ($hideTimingFromStudents ? "Timing hidden from students." : "") : 'Single attendance check.'));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
