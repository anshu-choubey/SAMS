<?php
/**
 * User Controller
 * Handles user CRUD operations
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/Email.php';

class UserController {
    private $db;
    private $user;
    private $validator;

    public function __construct($db) {
        $this->db = $db;
        $this->user = new User($db);
        $this->validator = new Validator();
    }

    /**
     * Create new user
     */
    public function create($data) {
        // Validate input
        $this->validator->required('full_name', $data['full_name'] ?? '', 'Full Name');
        $this->validator->required('email', $data['email'] ?? '', 'Email');
        $this->validator->email('email', $data['email'] ?? '', 'Email');
        $this->validator->required('password', $data['password'] ?? '', 'Password');
        $this->validator->minLength('password', $data['password'] ?? '', PASSWORD_MIN_LENGTH, 'Password');
        $this->validator->required('role', $data['role'] ?? '', 'Role');
        $this->validator->enum('role', $data['role'] ?? '', ['admin', 'teacher', 'student'], 'Role');

        // Role-specific validation
        if (($data['role'] ?? '') === 'student') {
            $this->validator->required('roll_number', $data['roll_number'] ?? '', 'Roll Number');
            $this->validator->required('department_id', $data['department_id'] ?? '', 'Department');
            $this->validator->required('semester', $data['semester'] ?? '', 'Semester');
            $this->validator->numeric('semester', $data['semester'] ?? '', 'Semester');
        } elseif (($data['role'] ?? '') === 'teacher') {
            $this->validator->required('employee_id', $data['employee_id'] ?? '', 'Employee ID');
        }

        if ($this->validator->hasErrors()) {
            Response::validationError($this->validator->getErrors());
        }

        // Check if email exists
        if ($this->user->emailExists($data['email'])) {
            Response::error('Email already exists', 400);
        }

        // Create user
        $this->user->full_name = $data['full_name'];
        $this->user->email = $data['email'];
        $this->user->password_hash = $data['password'];
        $this->user->role = $data['role'];
        $this->user->phone = $data['phone'] ?? null;
        $this->user->is_active = $data['is_active'] ?? true;

        $this->db->beginTransaction();

        try {
            if (!$this->user->create()) {
                throw new Exception("Failed to create user");
            }

            $userId = $this->user->id;

            // Check unique constraints for role-specific identifiers
            if ($data['role'] === 'student') {
                $studentModel = new Student($this->db);
                if ($studentModel->rollNumberExists($data['roll_number'])) {
                    throw new Exception("Roll number already exists");
                }
                $this->createStudentProfile($userId, $data);
            } elseif ($data['role'] === 'teacher') {
                $teacherModel = new Teacher($this->db);
                if ($teacherModel->employeeIdExists($data['employee_id'])) {
                    throw new Exception("Employee ID already exists");
                }
                $this->createTeacherProfile($userId, $data);
            }

            $this->db->commit();

            // Send password email to newly created user
            $emailSent = Email::sendUserPasswordEmail(
                $data['email'],
                $data['full_name'],
                $data['password'],
                $data['role']
            );

            Response::success([
                'user_id' => $userId,
                'email' => $data['email'],
                'role' => $data['role'],
                'password_email_sent' => $emailSent
            ], 'User created successfully. Password sent to email.', 201);

        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Create student profile
     */
    private function createStudentProfile($userId, $data) {
        $student = new Student($this->db);
        
        $student->user_id = $userId;
        $student->roll_number = $data['roll_number'];
        $student->department_id = $data['department_id'];
        $student->semester = $data['semester'];
        $student->section = !empty($data['section']) ? $data['section'] : null;
        $student->batch_year = !empty($data['batch_year']) ? $data['batch_year'] : date('Y');
        $student->admission_date = !empty($data['admission_date']) ? $data['admission_date'] : date('Y-m-d');

        if (!$student->create()) {
            throw new Exception("Failed to create student profile");
        }
    }

    /**
     * Create teacher profile
     */
    private function createTeacherProfile($userId, $data) {
        $teacher = new Teacher($this->db);
        
        $teacher->user_id = $userId;
        $teacher->employee_id = $data['employee_id'];
        $teacher->primary_department_id = !empty($data['department_id']) ? $data['department_id'] : null;
        $teacher->designation = !empty($data['designation']) ? $data['designation'] : null;
        $teacher->qualification = !empty($data['qualification']) ? $data['qualification'] : null;
        $teacher->joining_date = !empty($data['joining_date']) ? $data['joining_date'] : date('Y-m-d');

        if (!$teacher->create()) {
            throw new Exception("Failed to create teacher profile");
        }
    }

    /**
     * Get all users
     */
    public function getAll($filters = []) {
        $users = $this->user->getAll($filters);
        $total = $this->user->getTotalCount($filters);

        Response::success([
            'users' => $users,
            'total' => $total,
            'page' => $filters['page'] ?? 1,
            'limit' => $filters['limit'] ?? ITEMS_PER_PAGE
        ]);
    }

    /**
     * Get user by ID
     */
    public function getById($id) {
        $user = $this->user->getById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Remove sensitive data
        unset($user['password_hash']);

        // Get role-specific data
        if ($user['role'] === 'student') {
            $student = new Student($this->db);
            $studentData = $student->getByUserId($id);
            $user['student_info'] = $studentData;
        } elseif ($user['role'] === 'teacher') {
            $teacher = new Teacher($this->db);
            $teacherData = $teacher->getByUserId($id);
            $user['teacher_info'] = $teacherData;
        }

        Response::success(['user' => $user]);
    }

    /**
     * Update user
     */
    public function update($id, $data) {
        $user = $this->user->getById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Validate input
        if (isset($data['email'])) {
            $this->validator->email('email', $data['email'], 'Email');
            if ($this->user->emailExists($data['email'], $id)) {
                Response::error('Email already exists', 400);
            }
        }

        // Update user
        $this->user->id = $id;
        $this->user->full_name = $data['full_name'] ?? $user['full_name'];
        $this->user->email = $data['email'] ?? $user['email'];
        $this->user->phone = $data['phone'] ?? $user['phone'];
        $this->user->is_active = isset($data['is_active']) ? $data['is_active'] : $user['is_active'];

        if ($this->user->update()) {
            // Update role-specific data
            if ($user['role'] === 'student' && isset($data['roll_number'])) {
                $this->updateStudentProfile($id, $data);
            } elseif ($user['role'] === 'teacher') {
                // Update teacher profile if any teacher-specific field is provided
                $this->updateTeacherProfile($id, $data);
            }
            
            Response::success([], 'User updated successfully');
        }

        Response::error('Failed to update user');
    }

    /**
     * Update student profile
     */
    private function updateStudentProfile($userId, $data) {
        $query = "UPDATE students 
                  SET roll_number = ?, department_id = ?, semester = ?, section = ?, batch_year = ? 
                  WHERE user_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $data['roll_number'],
            $data['department_id'],
            $data['semester'],
            !empty($data['section']) ? $data['section'] : null,
            !empty($data['batch_year']) ? $data['batch_year'] : date('Y'),
            $userId
        ]);
    }

    /**
     * Update teacher profile
     */
    private function updateTeacherProfile($userId, $data) {
        // Get existing teacher data
        $existingQuery = "SELECT * FROM teachers WHERE user_id = ?";
        $existingStmt = $this->db->prepare($existingQuery);
        $existingStmt->execute([$userId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) return;

        $query = "UPDATE teachers 
                  SET employee_id = ?, primary_department_id = ?, designation = ?, qualification = ?, joining_date = ? 
                  WHERE user_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            isset($data['employee_id']) ? $data['employee_id'] : $existing['employee_id'],
            isset($data['department_id']) ? ($data['department_id'] ?: null) : $existing['primary_department_id'],
            isset($data['designation']) ? ($data['designation'] ?: null) : $existing['designation'],
            isset($data['qualification']) ? ($data['qualification'] ?: null) : $existing['qualification'],
            isset($data['joining_date']) ? ($data['joining_date'] ?: null) : $existing['joining_date'],
            $userId
        ]);
    }

    /**
     * Delete user
     */
    public function delete($id) {
        $user = $this->user->getById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        $this->user->id = $id;

        if ($this->user->delete()) {
            Response::success([], 'User deleted successfully');
        }

        Response::error('Failed to delete user');
    }

    /**
     * Update user password
     */
    public function updatePassword($id, $newPassword) {
        $user = $this->user->getById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $query = "UPDATE users SET password_hash = :password WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            Response::success([], 'Password updated successfully');
        }

        Response::error('Failed to update password');
    }
}
?>
