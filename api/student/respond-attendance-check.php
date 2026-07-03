<?php
/**
 * Student Respond to Attendance Check API
 * Students respond to random attendance checks during class
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Student.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

function getSystemSettingValue(PDO $db, string $settingKey, $defaultValue = null) {
    try {
        $query = "SELECT value FROM system_settings WHERE `key` = :setting_key LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':setting_key', $settingKey);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['value'] !== null) {
            return $result['value'];
        }
        return $defaultValue;
    } catch (Exception $e) {
        return $defaultValue;
    }
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters

    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $deltaLat = $lat2 - $lat1;
    $deltaLon = $lon2 - $lon1;

    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1) * cos($lat2) *
         sin($deltaLon / 2) * sin($deltaLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return round($earthRadius * $c, 2);
}

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
    Auth::hasRole('student');
    $user = Auth::user();

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get face confidence threshold from settings
    $faceConfidenceThreshold = (int)(getSystemSettingValue($db, 'face_confidence_threshold', 85) ?: 85);
    $gpsProximityRadius = (int)(getSystemSettingValue($db, 'gps_proximity_radius', 50) ?: 50);

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    $validator = new Validator();
    $validator->required('check_point_id', $data['check_point_id'] ?? '', 'Check Point ID');
    $validator->required('latitude', $data['latitude'] ?? '', 'Latitude');
    $validator->required('longitude', $data['longitude'] ?? '', 'Longitude');
    $validator->required('face_confidence', $data['face_confidence'] ?? '', 'Face Confidence');
    $validator->numeric('latitude', $data['latitude'] ?? '', 'Latitude');
    $validator->numeric('longitude', $data['longitude'] ?? '', 'Longitude');
    $validator->numeric('face_confidence', $data['face_confidence'] ?? '', 'Face Confidence');

    if ($validator->hasErrors()) {
        Response::validationError($validator->getErrors());
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // SERVER-SIDE FACE VERIFICATION VALIDATION (Anti-Spoofing)
    // ═══════════════════════════════════════════════════════════════════════
    
    $faceConfidence = (float)$data['face_confidence'];
    $securityIssues = [];
    
    // 1. Face confidence range validation
    //    - Must be between 0 and 100
    //    - Suspiciously perfect scores (99-100) are rare in real scenarios
    if ($faceConfidence < 0 || $faceConfidence > 100) {
        Response::error('Invalid face confidence value', 400);
    }
    
    if ($faceConfidence > 99.5) {
        // Suspiciously perfect - may be hardcoded/spoofed
        $securityIssues[] = 'perfect_score_suspicious';
        error_log("Security: Suspiciously perfect face confidence ({$faceConfidence}) from user {$user['id']}");
    }
    
    // 2. Rate limiting - prevent rapid-fire attempts
    $rateLimitQuery = "SELECT COUNT(*) as attempt_count, 
                              MAX(response_time) as last_attempt
                       FROM attendance_check_responses 
                       WHERE student_id = (SELECT id FROM students WHERE user_id = :user_id)
                       AND response_time > DATE_SUB(NOW(), INTERVAL 30 SECOND)";
    $stmt = $db->prepare($rateLimitQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $rateCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ((int)($rateCheck['attempt_count'] ?? 0) > 3) {
        Response::error('Too many attempts. Please wait a moment before trying again.', 429);
    }
    
    // 3. Check for suspicious patterns in recent attempts
    $patternQuery = "SELECT face_confidence_score, distance_meters, verification_status, response_time
                     FROM attendance_check_responses 
                     WHERE student_id = (SELECT id FROM students WHERE user_id = :user_id)
                     AND response_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                     ORDER BY response_time DESC
                     LIMIT 10";
    $stmt = $db->prepare($patternQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $recentAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($recentAttempts) >= 3) {
        // Check for identical confidence scores (spoofing indicator)
        $confidenceValues = array_column($recentAttempts, 'face_confidence_score');
        $uniqueConfidences = array_unique($confidenceValues);
        if (count($uniqueConfidences) === 1 && count($confidenceValues) >= 3) {
            $securityIssues[] = 'identical_confidence_pattern';
            error_log("Security: Identical confidence pattern detected for user {$user['id']}: " . implode(',', $confidenceValues));
        }
        
        // Check for multiple failures followed by sudden success
        $recentStatuses = array_column($recentAttempts, 'verification_status');
        $failCount = count(array_filter($recentStatuses, fn($s) => $s !== 'success'));
        if ($failCount >= 3 && $faceConfidence >= $faceConfidenceThreshold + 20) {
            $securityIssues[] = 'sudden_improvement_suspicious';
            error_log("Security: Sudden confidence improvement after failures for user {$user['id']}");
        }
    }
    
    // 4. GPS sanity check - can't be too far from last known position in short time
    if (count($recentAttempts) > 0) {
        $lastAttempt = $recentAttempts[0];
        $lastLat = isset($data['last_latitude']) ? (float)$data['last_latitude'] : null;
        $lastLon = isset($data['last_longitude']) ? (float)$data['last_longitude'] : null;
        
        // If we have previous GPS data, check for impossibly fast movement
        // (teleportation attack detection)
        // Note: This would require storing GPS in the attempts, skipping for now
    }
    
    // 5. Liveness verification data (if provided by enhanced client)
    $livenessData = $data['liveness_data'] ?? null;
    if ($livenessData !== null) {
        // Client provided liveness challenge completion data
        $challengesPassed = (int)($livenessData['challenges_passed'] ?? 0);
        $challengesRequired = (int)($livenessData['challenges_required'] ?? 2);
        $challengeHash = $livenessData['challenge_hash'] ?? null;
        
        if ($challengesPassed < $challengesRequired) {
            $securityIssues[] = 'incomplete_liveness_challenges';
        }
    }
    
    // Log all security issues for monitoring
    if (!empty($securityIssues)) {
        error_log("Face verification security issues for user {$user['id']}: " . implode(', ', $securityIssues));
    }

    // Get student profile
    $student = new Student($db);
    $studentData = $student->getByUserId($user['id']);

    if (!$studentData) {
        Response::error('Student profile not found', 404);
    }

    // Check if face registered
    $faceRegistered = isset($studentData['face_registered'])
        ? (bool)$studentData['face_registered']
        : !empty($studentData['face_data']);

    if (!$faceRegistered) {
        Response::error('Please register your face first before marking attendance', 400);
    }

    // Get check point details
    $checkQuery = "SELECT cp.*, tl.latitude as teacher_latitude, tl.longitude as teacher_longitude,
                          tl.teacher_id, tl.assignment_id, tl.department_id
                   FROM attendance_check_points cp
                   JOIN teacher_locations tl ON cp.session_id = tl.id
                   WHERE cp.id = :check_point_id AND cp.is_active = TRUE";
    $stmt = $db->prepare($checkQuery);
    $stmt->bindParam(':check_point_id', $data['check_point_id']);
    $stmt->execute();
    $checkPoint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkPoint) {
        Response::error('Invalid or inactive check point', 404);
    }

    // Check if within time window
    $now = time();
    $windowEnd = strtotime($checkPoint['window_end_time']);
    $isLate = $now > $windowEnd;

    // Check if already responded
    $existingQuery = "SELECT id FROM attendance_check_responses 
                      WHERE check_point_id = :check_point_id AND student_id = :student_id";
    $stmt = $db->prepare($existingQuery);
    $stmt->bindParam(':check_point_id', $data['check_point_id']);
    $stmt->bindParam(':student_id', $studentData['id']);
    $stmt->execute();
    if ($stmt->fetch()) {
        Response::error('You have already responded to this check', 400);
    }

    // Calculate distance
    $distance = calculateDistance(
        $checkPoint['teacher_latitude'],
        $checkPoint['teacher_longitude'],
        $data['latitude'],
        $data['longitude']
    );

    // ═══════════════════════════════════════════════════════════════════════
    // IMPROVED GPS VALIDATION (More Lenient)
    // ═══════════════════════════════════════════════════════════════════════
    
    // Get GPS accuracy from client (if provided)
    $gpsAccuracy = isset($data['gps_accuracy']) ? (float)$data['gps_accuracy'] : 0;
    
    // Add tolerance based on GPS accuracy (max 50m extra tolerance)
    $accuracyTolerance = min($gpsAccuracy, 50);
    $effectiveRadius = $gpsProximityRadius + $accuracyTolerance;
    
    // Soft boundary: within 1.5x radius is "close enough" for soft pass
    $softBoundary = $gpsProximityRadius * 1.5;
    
    // GPS validation levels:
    // 1. Within radius = valid
    // 2. Within effective radius (with accuracy) = valid  
    // 3. Within soft boundary = soft_pass (allow but flag)
    // 4. Beyond soft boundary = failed
    
    $gpsValid = $distance <= $effectiveRadius;
    $gpsSoftPass = !$gpsValid && $distance <= $softBoundary;
    
    // Log GPS details for debugging
    error_log("GPS Check: distance={$distance}m, radius={$gpsProximityRadius}m, accuracy={$gpsAccuracy}m, effective={$effectiveRadius}m");
    
    $faceValid = $data['face_confidence'] >= $faceConfidenceThreshold;
    
    // Flag suspicious attempts (from security checks above)
    $isSuspicious = !empty($securityIssues);
    $requiresReview = in_array('perfect_score_suspicious', $securityIssues) || 
                      in_array('identical_confidence_pattern', $securityIssues);

    // Determine verification status
    if ($isLate) {
        $verificationStatus = 'late';
    } elseif ($isSuspicious && $requiresReview) {
        // Don't outright reject but flag for review
        $verificationStatus = $gpsValid && $faceValid ? 'pending_review' : ($gpsValid ? 'face_failed' : 'gps_failed');
    } elseif ($gpsValid && $faceValid) {
        $verificationStatus = 'success';
    } elseif ($gpsSoftPass && $faceValid) {
        // Within soft boundary with good face match - allow with flag
        $verificationStatus = 'success';
        $securityIssues[] = 'gps_soft_pass';
        error_log("GPS soft pass: student {$user['id']} at {$distance}m (soft boundary: {$softBoundary}m)");
    } elseif (!$gpsValid && !$faceValid) {
        $verificationStatus = 'both_failed';
    } elseif (!$gpsValid) {
        $verificationStatus = 'gps_failed';
    } else {
        $verificationStatus = 'face_failed';
    }

    // Save response with security flags
    $securityFlags = !empty($securityIssues) ? json_encode($securityIssues) : null;
    
    $insertQuery = "INSERT INTO attendance_check_responses 
                    (check_point_id, student_id, schedule_id, session_id, 
                     student_latitude, student_longitude, teacher_latitude, teacher_longitude,
                     distance_meters, face_confidence_score, verification_status, device_info,
                     is_suspicious, security_flags)
                    VALUES (:check_point_id, :student_id, :schedule_id, :session_id,
                            :student_lat, :student_lon, :teacher_lat, :teacher_lon,
                            :distance, :face_confidence, :verification_status, :device_info,
                            :is_suspicious, :security_flags)";
    $stmt = $db->prepare($insertQuery);
    $stmt->bindParam(':check_point_id', $data['check_point_id']);
    $stmt->bindParam(':student_id', $studentData['id']);
    $stmt->bindParam(':schedule_id', $checkPoint['schedule_id']);
    $stmt->bindParam(':session_id', $checkPoint['session_id']);
    $stmt->bindParam(':student_lat', $data['latitude']);
    $stmt->bindParam(':student_lon', $data['longitude']);
    $stmt->bindParam(':teacher_lat', $checkPoint['teacher_latitude']);
    $stmt->bindParam(':teacher_lon', $checkPoint['teacher_longitude']);
    $stmt->bindParam(':distance', $distance);
    $stmt->bindParam(':face_confidence', $data['face_confidence']);
    $stmt->bindParam(':verification_status', $verificationStatus);
    $deviceInfo = $data['device_info'] ?? null;
    $stmt->bindParam(':device_info', $deviceInfo);
    $stmt->bindParam(':is_suspicious', $isSuspicious, PDO::PARAM_BOOL);
    $stmt->bindParam(':security_flags', $securityFlags);
    $stmt->execute();

    $responseId = $db->lastInsertId();

    // Get total successful checks and total planned for this session
    $statsQuery = "SELECT 
                    COUNT(*) as total_responses,
                    SUM(CASE WHEN verification_status = 'success' THEN 1 ELSE 0 END) as successful_checks
                   FROM attendance_check_responses
                   WHERE session_id = :session_id AND student_id = :student_id";
    $stmt = $db->prepare($statsQuery);
    $stmt->bindParam(':session_id', $checkPoint['session_id']);
    $stmt->bindParam(':student_id', $studentData['id']);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total checks planned for this session
    $planQuery = "SELECT total_checks_planned, schedule_id, assignment_id, department_id, teacher_id
                  FROM teacher_locations WHERE id = :session_id";
    $stmt = $db->prepare($planQuery);
    $stmt->bindParam(':session_id', $checkPoint['session_id']);
    $stmt->execute();
    $sessionInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalPlanned = (int)($sessionInfo['total_checks_planned'] ?? 0);
    $successfulChecks = (int)$stats['successful_checks'];
    $allChecksComplete = $totalPlanned > 0 && $successfulChecks >= $totalPlanned;
    $attendanceMarked = false;

    // Auto-mark attendance when ALL checks are completed successfully
    if ($allChecksComplete && $verificationStatus === 'success') {
        $existingAttendance = "SELECT id FROM attendance 
                               WHERE student_id = :student_id 
                               AND schedule_id = :schedule_id 
                               AND attendance_date = CURDATE()";
        $stmt = $db->prepare($existingAttendance);
        $stmt->bindParam(':student_id', $studentData['id']);
        $stmt->bindParam(':schedule_id', $sessionInfo['schedule_id']);
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            $markQuery = "INSERT INTO attendance 
                          (student_id, schedule_id, assignment_id, teacher_id, department_id,
                           attendance_date, status, face_confidence_score, 
                           student_latitude, student_longitude,
                           teacher_latitude, teacher_longitude, distance_meters,
                           verification_status, marked_at)
                          VALUES (:student_id, :schedule_id, :assignment_id, :teacher_id, :department_id,
                                  CURDATE(), 'present', :face_confidence,
                                  :student_lat, :student_lon,
                                  :teacher_lat, :teacher_lon, :distance,
                                  'success', NOW())";
            $stmt = $db->prepare($markQuery);
            $stmt->bindParam(':student_id', $studentData['id']);
            $stmt->bindParam(':schedule_id', $sessionInfo['schedule_id']);
            $stmt->bindParam(':assignment_id', $sessionInfo['assignment_id']);
            $stmt->bindParam(':teacher_id', $sessionInfo['teacher_id']);
            $stmt->bindParam(':department_id', $sessionInfo['department_id']);
            $stmt->bindParam(':face_confidence', $data['face_confidence']);
            $stmt->bindParam(':student_lat', $data['latitude']);
            $stmt->bindParam(':student_lon', $data['longitude']);
            $stmt->bindParam(':teacher_lat', $checkPoint['teacher_latitude']);
            $stmt->bindParam(':teacher_lon', $checkPoint['teacher_longitude']);
            $stmt->bindParam(':distance', $distance);
            $stmt->execute();
            $attendanceMarked = true;
        }
    }

    Response::success([
        'response_id' => (int)$responseId,
        'check_point_id' => (int)$data['check_point_id'],
        'check_number' => (int)$checkPoint['check_number'],
        'verification_status' => $verificationStatus,
        'distance_meters' => $distance,
        'face_confidence' => (float)$data['face_confidence'],
        'is_late' => $isLate,
        'total_responses' => (int)$stats['total_responses'],
        'successful_checks' => $successfulChecks,
        'total_checks_planned' => $totalPlanned,
        'all_checks_complete' => $allChecksComplete,
        'attendance_marked' => $attendanceMarked
    ], $allChecksComplete 
        ? 'All checks complete — attendance marked!' 
        : ($verificationStatus === 'success' ? 'Check-in successful!' : 'Check-in recorded with issues'));

} catch (Exception $e) {
    error_log('Respond attendance check error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
