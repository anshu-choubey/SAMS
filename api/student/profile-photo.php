<?php
// API endpoint for student profile photo upload and download
// POST /api/student/profile-photo - Upload new profile photo
// GET /api/student/profile-photo/:userId - Download profile photo

require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

header('Content-Type: application/json');

// Authenticate student
$studentId = authenticateStudent();
if (!$studentId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // ✅ Upload profile photo (after face registration)
    handlePhotoUpload($studentId);
} elseif ($method === 'GET') {
    // ✅ Download profile photo
    handlePhotoDownload($studentId);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handlePhotoUpload($studentId) {
    global $conn;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['photoBase64'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Photo data missing']);
            return;
        }
        
        $photoBase64 = $input['photoBase64'];
        
        // Decode Base64 to binary
        $photoBinary = base64_decode($photoBase64, true);
        if ($photoBinary === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Base64 format']);
            return;
        }
        
        // Generate unique filename
        $photoDir = __DIR__ . '/../../uploads/student-photos/';
        if (!is_dir($photoDir)) {
            mkdir($photoDir, 0755, true);
        }
        
        $filename = 'student_' . $studentId . '_profile_' . time() . '.jpg';
        $filepath = $photoDir . $filename;
        
        // Save file
        if (file_put_contents($filepath, $photoBinary) === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save photo']);
            return;
        }
        
        // Update database with photo path
        $stmt = $conn->prepare("
            UPDATE students 
            SET profile_photo = ?, updated_at = NOW() 
            WHERE student_id = ?
        ");
        
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            return;
        }
        
        $photoPath = '/uploads/student-photos/' . $filename;
        $stmt->bind_param('si', $photoPath, $studentId);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Profile photo uploaded successfully',
                'photoUrl' => $photoPath
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePhotoDownload($studentId) {
    global $conn;
    
    try {
        // Get photo path from database
        $stmt = $conn->prepare("
            SELECT profile_photo 
            FROM students 
            WHERE student_id = ?
        ");
        
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            return;
        }
        
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
        
        if (!$student || !$student['profile_photo']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No profile photo found']);
            return;
        }
        
        $photoPath = __DIR__ . '/../../' . ltrim($student['profile_photo'], '/');
        
        if (!file_exists($photoPath)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Photo file not found']);
            return;
        }
        
        // Return as Base64 encoded JSON
        $photoBase64 = base64_encode(file_get_contents($photoPath));
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'photoBase64' => $photoBase64,
            'photoUrl' => $student['profile_photo']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
