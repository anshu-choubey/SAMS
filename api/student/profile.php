<?php
/**
 * Student Profile API
 * Get and update student profile
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

try {
    Auth::hasRole('student');
    $user = Auth::user();

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

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $profileQuery = "SELECT s.id, s.roll_number, s.department_id, d.name as department_name,
                                    s.semester, s.section, s.batch_year, s.admission_date,
                                    s.face_registered, s.face_registration_date,
                                    u.full_name, u.email, u.phone, u.profile_image
                             FROM students s
                             LEFT JOIN departments d ON s.department_id = d.id
                             JOIN users u ON s.user_id = u.id
                             WHERE s.id = :student_id";
            $stmt = $db->prepare($profileQuery);
            $stmt->bindParam(':student_id', $student['id']);
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success([
                'profile' => [
                    'id' => (int)$profile['id'],
                    'roll_number' => $profile['roll_number'],
                    'full_name' => $profile['full_name'],
                    'email' => $profile['email'],
                    'phone' => $profile['phone'],
                    'department_id' => $profile['department_id'] ? (int)$profile['department_id'] : null,
                    'department_name' => $profile['department_name'],
                    'semester' => (int)$profile['semester'],
                    'section' => $profile['section'],
                    'batch_year' => $profile['batch_year'] ? (int)$profile['batch_year'] : null,
                    'admission_date' => $profile['admission_date'],
                    'face_registered' => (bool)$profile['face_registered'],
                    'face_registration_date' => $profile['face_registration_date'],
                    'profile_image' => $profile['profile_image']
                ]
            ]);
            break;

        case 'PUT':
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $updateFields = [];
            $params = [':student_id' => $student['id']];

            if (isset($data['semester'])) {
                $updateFields[] = "semester = :semester";
                $params[':semester'] = (int)$data['semester'];
            }

            if (isset($data['section'])) {
                $updateFields[] = "section = :section";
                $params[':section'] = $data['section'];
            }

            if (!empty($updateFields)) {
                $updateQuery = "UPDATE students SET " . implode(', ', $updateFields) . " WHERE id = :student_id";
                $stmt = $db->prepare($updateQuery);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
            }

            // Update user fields if provided
            if (isset($data['full_name']) || isset($data['phone'])) {
                $userFields = [];
                $userParams = [':user_id' => $user['id']];

                if (isset($data['full_name'])) {
                    $userFields[] = "full_name = :full_name";
                    $userParams[':full_name'] = $data['full_name'];
                }

                if (isset($data['phone'])) {
                    $userFields[] = "phone = :phone";
                    $userParams[':phone'] = $data['phone'];
                }

                if (!empty($userFields)) {
                    $userQuery = "UPDATE users SET " . implode(', ', $userFields) . " WHERE id = :user_id";
                    $stmt = $db->prepare($userQuery);
                    foreach ($userParams as $key => $value) {
                        $stmt->bindValue($key, $value);
                    }
                    $stmt->execute();
                }
            }

            // Return updated profile
            $profileQuery = "SELECT s.id, s.roll_number, s.department_id, d.name as department_name,
                                    s.semester, s.section, s.batch_year,
                                    s.face_registered, u.full_name, u.email, u.phone
                             FROM students s
                             LEFT JOIN departments d ON s.department_id = d.id
                             JOIN users u ON s.user_id = u.id
                             WHERE s.id = :student_id";
            $stmt = $db->prepare($profileQuery);
            $stmt->bindParam(':student_id', $student['id']);
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success([
                'profile' => [
                    'id' => (int)$profile['id'],
                    'roll_number' => $profile['roll_number'],
                    'full_name' => $profile['full_name'],
                    'email' => $profile['email'],
                    'phone' => $profile['phone'],
                    'department_id' => $profile['department_id'] ? (int)$profile['department_id'] : null,
                    'department_name' => $profile['department_name'],
                    'semester' => (int)$profile['semester'],
                    'section' => $profile['section'],
                    'batch_year' => $profile['batch_year'] ? (int)$profile['batch_year'] : null,
                    'face_registered' => (bool)$profile['face_registered']
                ]
            ], 'Profile updated successfully');
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
