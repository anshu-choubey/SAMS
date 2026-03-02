<?php
/**
 * Teacher Profile API
 * Get and update teacher profile data
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

    // Get teacher profile
    $teacherQuery = "SELECT t.* FROM teachers t WHERE t.user_id = :user_id";
    $stmt = $db->prepare($teacherQuery);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        Response::error('Teacher profile not found', 404);
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get full profile with department info
            $profileQuery = "SELECT t.id, t.employee_id, t.primary_department_id as department_id, 
                                    d.name as department_name, t.designation, t.qualification,
                                    t.joining_date, u.full_name, u.email, u.phone, u.profile_image
                             FROM teachers t
                             LEFT JOIN departments d ON t.primary_department_id = d.id
                             JOIN users u ON t.user_id = u.id
                             WHERE t.id = :teacher_id";
            $stmt = $db->prepare($profileQuery);
            $stmt->bindParam(':teacher_id', $teacher['id']);
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success([
                'profile' => [
                    'id' => (int)$profile['id'],
                    'employee_id' => $profile['employee_id'],
                    'full_name' => $profile['full_name'],
                    'email' => $profile['email'],
                    'phone' => $profile['phone'],
                    'department_id' => $profile['department_id'] ? (int)$profile['department_id'] : null,
                    'department_name' => $profile['department_name'],
                    'designation' => $profile['designation'],
                    'qualification' => $profile['qualification'],
                    'joining_date' => $profile['joining_date'],
                    'profile_image' => $profile['profile_image']
                ]
            ]);
            break;

        case 'PUT':
        case 'POST':
            // Update profile
            $data = json_decode(file_get_contents('php://input'), true);

            $validator = new Validator();

            // Build update query dynamically based on provided fields
            $updateFields = [];
            $params = [':teacher_id' => $teacher['id']];

            if (isset($data['department_id'])) {
                // Verify department exists
                $deptCheck = $db->prepare("SELECT id FROM departments WHERE id = :dept_id");
                $deptCheck->bindParam(':dept_id', $data['department_id']);
                $deptCheck->execute();
                if (!$deptCheck->fetch() && $data['department_id'] != null) {
                    Response::error('Invalid department ID', 400);
                }
                $updateFields[] = "primary_department_id = :department_id";
                $params[':department_id'] = $data['department_id'] ?: null;
            }

            if (isset($data['employee_id'])) {
                $validator->required('employee_id', $data['employee_id'], 'Employee ID');
                if ($validator->hasErrors()) {
                    Response::validationError($validator->getErrors());
                }
                // Check if employee_id is unique (excluding current teacher)
                $empCheck = $db->prepare("SELECT id FROM teachers WHERE employee_id = :emp_id AND id != :t_id");
                $empCheck->bindParam(':emp_id', $data['employee_id']);
                $empCheck->bindParam(':t_id', $teacher['id']);
                $empCheck->execute();
                if ($empCheck->fetch()) {
                    Response::error('Employee ID already exists', 400);
                }
                $updateFields[] = "employee_id = :employee_id";
                $params[':employee_id'] = $data['employee_id'];
            }

            if (isset($data['designation'])) {
                $updateFields[] = "designation = :designation";
                $params[':designation'] = $data['designation'] ?: null;
            }

            if (isset($data['qualification'])) {
                $updateFields[] = "qualification = :qualification";
                $params[':qualification'] = $data['qualification'] ?: null;
            }

            if (isset($data['joining_date'])) {
                $updateFields[] = "joining_date = :joining_date";
                $params[':joining_date'] = $data['joining_date'] ?: null;
            }

            if (empty($updateFields)) {
                Response::error('No fields to update', 400);
            }

            $updateQuery = "UPDATE teachers SET " . implode(', ', $updateFields) . " WHERE id = :teacher_id";
            $stmt = $db->prepare($updateQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            // Also update user fields if provided
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
            $profileQuery = "SELECT t.id, t.employee_id, t.primary_department_id as department_id, 
                                    d.name as department_name, t.designation, t.qualification,
                                    t.joining_date, u.full_name, u.email, u.phone
                             FROM teachers t
                             LEFT JOIN departments d ON t.primary_department_id = d.id
                             JOIN users u ON t.user_id = u.id
                             WHERE t.id = :teacher_id";
            $stmt = $db->prepare($profileQuery);
            $stmt->bindParam(':teacher_id', $teacher['id']);
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success([
                'profile' => [
                    'id' => (int)$profile['id'],
                    'employee_id' => $profile['employee_id'],
                    'full_name' => $profile['full_name'],
                    'email' => $profile['email'],
                    'phone' => $profile['phone'],
                    'department_id' => $profile['department_id'] ? (int)$profile['department_id'] : null,
                    'department_name' => $profile['department_name'],
                    'designation' => $profile['designation'],
                    'qualification' => $profile['qualification'],
                    'joining_date' => $profile['joining_date']
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
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
