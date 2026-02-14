<?php
/**
 * Authentication Controller
 * Handles login, logout, and session management
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';

class AuthController {
    private $db;
    private $user;
    private $validator;

    public function __construct($db) {
        $this->db = $db;
        $this->user = new User($db);
        $this->validator = new Validator();
    }

    /**
     * Login user
     */
    public function login($data) {
        // Validate input
        $this->validator->required('email', $data['email'] ?? '', 'Email');
        $this->validator->email('email', $data['email'] ?? '', 'Email');
        $this->validator->required('password', $data['password'] ?? '', 'Password');

        if ($this->validator->hasErrors()) {
            Response::validationError($this->validator->getErrors());
        }

        // Verify credentials
        $user = $this->user->verifyPassword($data['email'], $data['password']);

        if (!$user) {
            Response::error('Invalid email or password', 401);
        }

        if (!$user['is_active']) {
            Response::error('Your account has been deactivated', 403);
        }

        // Create session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionId = bin2hex(random_bytes(32));
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['role'] = $user['role'];

        // Store session in database
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $query = "INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                  VALUES (:user_id, :session_id, :ip_address, :user_agent, :expires_at)";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':session_id', $sessionId);
        $stmt->bindParam(':ip_address', $ipAddress);
        $stmt->bindParam(':user_agent', $userAgent);
        $stmt->bindParam(':expires_at', $expiresAt);
        $stmt->execute();

        // Build response matching Android LoginResponse model
        $responseData = [
            'user' => [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_active' => (int)$user['is_active']
            ],
            'session_id' => $sessionId
        ];

        // Include student profile if user is a student
        if ($user['role'] === 'student') {
            $profileQuery = "SELECT s.id, s.roll_number, s.department_id, d.name as department_name,
                                    s.semester, s.section, s.batch_year
                             FROM students s
                             JOIN departments d ON s.department_id = d.id
                             WHERE s.user_id = :user_id";
            $stmt = $this->db->prepare($profileQuery);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($profile) {
                $responseData['profile'] = [
                    'id' => (int)$profile['id'],
                    'roll_number' => $profile['roll_number'],
                    'department_id' => (int)$profile['department_id'],
                    'department_name' => $profile['department_name'],
                    'semester' => (int)$profile['semester'],
                    'section' => $profile['section'],
                    'batch_year' => (int)$profile['batch_year']
                ];
            }
        }

        // Include teacher profile if user is a teacher
        if ($user['role'] === 'teacher') {
            $teacherQuery = "SELECT t.id, t.employee_id, t.primary_department_id as department_id, d.name as department_name,
                                    t.designation, t.qualification
                             FROM teachers t
                             LEFT JOIN departments d ON t.primary_department_id = d.id
                             WHERE t.user_id = :user_id";
            $stmt = $this->db->prepare($teacherQuery);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->execute();
            $teacherProfile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($teacherProfile) {
                $responseData['teacher_profile'] = [
                    'id' => (int)$teacherProfile['id'],
                    'employee_id' => $teacherProfile['employee_id'],
                    'department_id' => (int)$teacherProfile['department_id'],
                    'department_name' => $teacherProfile['department_name'],
                    'designation' => $teacherProfile['designation'],
                    'qualification' => $teacherProfile['qualification']
                ];
            }
        }

        Response::success($responseData, 'Login successful');
    }

    /**
     * Logout user
     */
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['session_id'])) {
            // Delete session from database
            $query = "DELETE FROM sessions WHERE session_token = :session_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $_SESSION['session_id']);
            $stmt->execute();
        }

        // Destroy session
        session_destroy();

        Response::success([], 'Logout successful');
    }

    /**
     * Get current user
     */
    public function getCurrentUser() {
        require_once __DIR__ . '/../middleware/Auth.php';
        $user = Auth::user();

        Response::success(['user' => $user]);
    }

    /**
     * Change password
     */
    public function changePassword($data) {
        require_once __DIR__ . '/../middleware/Auth.php';
        $userId = Auth::check();

        // Validate input
        $this->validator->required('current_password', $data['current_password'] ?? '', 'Current Password');
        $this->validator->required('new_password', $data['new_password'] ?? '', 'New Password');
        $this->validator->minLength('new_password', $data['new_password'] ?? '', PASSWORD_MIN_LENGTH, 'New Password');

        if ($this->validator->hasErrors()) {
            Response::validationError($this->validator->getErrors());
        }

        // Get user
        $user = $this->user->getById($userId);

        // Verify current password
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            Response::error('Current password is incorrect', 400);
        }

        // Update password
        $this->user->id = $userId;
        if ($this->user->updatePassword($data['new_password'])) {
            Response::success([], 'Password changed successfully');
        }

        Response::error('Failed to change password');
    }
}
?>
