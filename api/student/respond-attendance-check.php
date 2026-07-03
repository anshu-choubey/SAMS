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
        foreach (['setting_value', 'value'] as $valueColumn) {
            try {
                $query = "SELECT {$valueColumn} AS setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':setting_key', $settingKey);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && array_key_exists('setting_value', $result) && $result['setting_value'] !== null) {
                    return $result['setting_value'];
                }
            } catch (Exception $innerException) {
                continue;
            }
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

    // Verify GPS and Face
    $gpsValid = $distance <= $gpsProximityRadius;
    $faceValid = $data['face_confidence'] >= $faceConfidenceThreshold;

    // Determine verification status
    if ($isLate) {
        $verificationStatus = 'late';
    } elseif ($gpsValid && $faceValid) {
        $verificationStatus = 'success';
    } elseif (!$gpsValid && !$faceValid) {
        $verificationStatus = 'both_failed';
    } elseif (!$gpsValid) {
        $verificationStatus = 'gps_failed';
    } else {
        $verificationStatus = 'face_failed';
    }

    // Save response
    $insertQuery = "INSERT INTO attendance_check_responses 
                    (check_point_id, student_id, schedule_id, session_id, 
                     student_latitude, student_longitude, teacher_latitude, teacher_longitude,
                     distance_meters, face_confidence_score, verification_status, device_info)
                    VALUES (:check_point_id, :student_id, :schedule_id, :session_id,
                            :student_lat, :student_lon, :teacher_lat, :teacher_lon,
                            :distance, :face_confidence, :verification_status, :device_info)";
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
    $stmt->execute();

    $responseId = $db->lastInsertId();

    // Get total successful checks for this student in this session
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

    Response::success([
        'response_id' => (int)$responseId,
        'check_point_id' => (int)$data['check_point_id'],
        'check_number' => (int)$checkPoint['check_number'],
        'verification_status' => $verificationStatus,
        'distance_meters' => $distance,
        'face_confidence' => (float)$data['face_confidence'],
        'is_late' => $isLate,
        'total_responses' => (int)$stats['total_responses'],
        'successful_checks' => (int)$stats['successful_checks']
    ], $verificationStatus === 'success' ? 'Check-in successful!' : 'Check-in recorded with issues');

} catch (Exception $e) {
    error_log('Respond attendance check error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
