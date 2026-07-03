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

    $scheduleQuery = "SELECT sc.*, sc.duration_minutes,
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
    // INTERVAL CONFIGURATION - from Admin Panel settings only
    // ═══════════════════════════════════════════════════════════════════════
    
    $durationMinutes = 60;
    $multiCheckEnabled = true;
    $totalChecksPlanned = 2;
    $autoSchedule = true;
    $firstCheckDelay = 1;
    $randomIntervalsEnabled = true;
    $minIntervalMinutes = 10;
    $maxIntervalMinutes = 25;
    $hideTimingFromStudents = true;
    $autoTriggerChecks = true;
    $responseWindowMinutes = 3;
    
    // Load settings from admin panel (system_settings table)
    // Try both column name patterns (key/value vs setting_key/setting_value)
    $settingsKeys = [
        'attendance_multi_check_enabled', 'attendance_default_total_checks',
        'attendance_random_intervals_enabled', 'attendance_min_check_interval',
        'attendance_max_check_interval', 'attendance_check_window_minutes',
        'attendance_hide_timing_from_students'
    ];
    $placeholders = implode(',', array_fill(0, count($settingsKeys), '?'));
    
    $settingsQueries = [
        "SELECT `key` AS k, `value` AS v FROM system_settings WHERE `key` IN ($placeholders)",
        "SELECT setting_key AS k, setting_value AS v FROM system_settings WHERE setting_key IN ($placeholders)"
    ];
    
    $settingsLoaded = false;
    foreach ($settingsQueries as $settingsQuery) {
        try {
            $stmt = $db->prepare($settingsQuery);
            $stmt->execute($settingsKeys);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    switch ($row['k']) {
                        case 'attendance_multi_check_enabled':
                            $multiCheckEnabled = $row['v'] === 'true' || $row['v'] === '1';
                            break;
                        case 'attendance_default_total_checks':
                            $totalChecksPlanned = (int)$row['v'];
                            break;
                        case 'attendance_random_intervals_enabled':
                            $randomIntervalsEnabled = $row['v'] === 'true' || $row['v'] === '1';
                            break;
                        case 'attendance_min_check_interval':
                            $minIntervalMinutes = (int)$row['v'];
                            break;
                        case 'attendance_max_check_interval':
                            $maxIntervalMinutes = (int)$row['v'];
                            break;
                        case 'attendance_check_window_minutes':
                            $responseWindowMinutes = (int)$row['v'];
                            break;
                        case 'attendance_hide_timing_from_students':
                            $hideTimingFromStudents = $row['v'] === 'true' || $row['v'] === '1';
                            break;
                    }
                }
                $settingsLoaded = true;
                break;
            }
        } catch (Exception $e) { continue; }
    }
    if (!$settingsLoaded) {
        error_log("Warning: Could not load attendance settings from system_settings table, using defaults");
    }
    
    // Schedule duration override (if set per-class)
    if (isset($schedule['duration_minutes']) && $schedule['duration_minutes'] !== null) {
        $durationMinutes = (int)$schedule['duration_minutes'];
    }
    
    $notes = $data['notes'] ?? null;

    // Generate first check time
    // First check should appear within 1-3 minutes so students don't wait too long
    $firstCheckMinutes = $randomIntervalsEnabled 
        ? rand(1, min(3, $minIntervalMinutes))
        : max(1, $firstCheckDelay);
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
                // First check: appear quickly (1-3 min) so student doesn't wait too long
                $checkMinutes = $randomIntervalsEnabled 
                    ? rand(1, min(3, $minIntervalMinutes))
                    : max(1, $firstCheckDelay);
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
        // ✅ FIX: Android expects List<Int> for scheduled_check_times (minutes from start)
        'scheduled_check_times' => $scheduledIntervals,
        // Detailed check info (for admin/web use)
        'scheduled_check_details' => $checkTimesForTeacher
    ], 'Class session started successfully. ' . ($multiCheckEnabled ? "Multi-check attendance with random intervals ({$totalChecksPlanned} checks). " . ($hideTimingFromStudents ? "Timing hidden from students." : "") : 'Single attendance check.'));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
