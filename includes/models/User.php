<?php
/**
 * User Model
 * Handles user-related database operations
 */

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $full_name;
    public $email;
    public $password_hash;
    public $role;
    public $phone;
    public $profile_image;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new user
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (full_name, email, password_hash, role, phone, is_active) 
                  VALUES (:full_name, :email, :password_hash, :role, :phone, :is_active)";

        $stmt = $this->conn->prepare($query);

        // Hash password
        $hashed_password = password_hash($this->password_hash, PASSWORD_BCRYPT);

        // Bind parameters
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $hashed_password);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':is_active', $this->is_active);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get all users with filters
     */
    public function getAll($filters = []) {
        $query = "SELECT u.id, u.full_name, u.email, u.role, u.phone, 
                         u.profile_image, u.is_active, u.created_at,
                         CASE 
                            WHEN u.role = 'student' THEN s.roll_number
                            WHEN u.role = 'teacher' THEN t.employee_id
                            ELSE NULL
                         END as identifier,
                         CASE 
                            WHEN u.role = 'student' THEN d.name
                            WHEN u.role = 'teacher' THEN td.name
                            ELSE NULL
                         END as department_name
                  FROM " . $this->table . " u
                  LEFT JOIN students s ON u.id = s.user_id
                  LEFT JOIN teachers t ON u.id = t.user_id
                  LEFT JOIN departments d ON s.department_id = d.id
                  LEFT JOIN departments td ON t.primary_department_id = td.id
                  WHERE 1=1";

        // Apply filters
        if (!empty($filters['role'])) {
            $query .= " AND u.role = :role";
        }
        if (!empty($filters['department_id'])) {
            $query .= " AND (s.department_id = :department_id OR t.primary_department_id = :department_id)";
        }
        if (isset($filters['is_active'])) {
            $query .= " AND u.is_active = :is_active";
        }
        if (!empty($filters['search'])) {
            $query .= " AND (u.full_name LIKE :search OR u.email LIKE :search)";
        }

        $query .= " ORDER BY u.created_at DESC";

        // Pagination
        if (isset($filters['limit'])) {
            $query .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->conn->prepare($query);

        // Bind filter parameters
        if (!empty($filters['role'])) {
            $stmt->bindParam(':role', $filters['role']);
        }
        if (!empty($filters['department_id'])) {
            $stmt->bindParam(':department_id', $filters['department_id']);
        }
        if (isset($filters['is_active'])) {
            $stmt->bindParam(':is_active', $filters['is_active'], PDO::PARAM_BOOL);
        }
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $stmt->bindParam(':search', $searchTerm);
        }
        if (isset($filters['limit'])) {
            $stmt->bindParam(':limit', $filters['limit'], PDO::PARAM_INT);
            $stmt->bindParam(':offset', $filters['offset'], PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by email
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update user
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET full_name = :full_name, 
                      email = :email, 
                      phone = :phone, 
                      is_active = :is_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Update password
     */
    public function updatePassword($newPassword) {
        $query = "UPDATE " . $this->table . " SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $hashed_password = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt->bindParam(':password_hash', $hashed_password);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Delete user
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Verify password
     */
    public function verifyPassword($email, $password) {
        $user = $this->getByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }

        return false;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId);
        }

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Get total users count
     */
    public function getTotalCount($filters = []) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE 1=1";

        if (!empty($filters['role'])) {
            $query .= " AND role = :role";
        }
        if (isset($filters['is_active'])) {
            $query .= " AND is_active = :is_active";
        }
        if (!empty($filters['search'])) {
            $query .= " AND (full_name LIKE :search OR email LIKE :search)";
        }

        $stmt = $this->conn->prepare($query);

        if (!empty($filters['role'])) {
            $stmt->bindParam(':role', $filters['role']);
        }
        if (isset($filters['is_active'])) {
            $stmt->bindParam(':is_active', $filters['is_active'], PDO::PARAM_BOOL);
        }
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $stmt->bindParam(':search', $searchTerm);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}
?>
