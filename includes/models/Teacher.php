<?php
/**
 * Teacher Model
 */

class Teacher {
    private $conn;
    private $table = 'teachers';

    public $id;
    public $user_id;
    public $employee_id;
    public $primary_department_id;
    public $designation;
    public $qualification;
    public $joining_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create teacher profile
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, employee_id, primary_department_id, designation, qualification, joining_date) 
                  VALUES (:user_id, :employee_id, :primary_department_id, :designation, :qualification, :joining_date)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':employee_id', $this->employee_id);
        $stmt->bindParam(':primary_department_id', $this->primary_department_id);
        $stmt->bindParam(':designation', $this->designation);
        $stmt->bindParam(':qualification', $this->qualification);
        $stmt->bindParam(':joining_date', $this->joining_date);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get teacher by user ID
     */
    public function getByUserId($userId) {
        $query = "SELECT t.*, d.name as department_name, d.code as department_code
                  FROM " . $this->table . " t
                  LEFT JOIN departments d ON t.primary_department_id = d.id
                  WHERE t.user_id = :user_id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get teacher by ID
     */
    public function getById($id) {
        $query = "SELECT t.*, 
                         u.full_name, u.email, u.phone,
                         d.name as department_name, d.code as department_code
                  FROM " . $this->table . " t
                  JOIN users u ON t.user_id = u.id
                  LEFT JOIN departments d ON t.primary_department_id = d.id
                  WHERE t.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if employee ID exists
     */
    public function employeeIdExists($employeeId, $excludeId = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE employee_id = :employee_id";
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':employee_id', $employeeId);
        
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId);
        }

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Update teacher
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET employee_id = :employee_id, 
                      primary_department_id = :primary_department_id, 
                      designation = :designation, 
                      qualification = :qualification
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':employee_id', $this->employee_id);
        $stmt->bindParam(':primary_department_id', $this->primary_department_id);
        $stmt->bindParam(':designation', $this->designation);
        $stmt->bindParam(':qualification', $this->qualification);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Update teacher location
     */
    public function updateLocation($scheduleId, $assignmentId, $departmentId, $latitude, $longitude) {
        // Deactivate previous location
        $deactivateQuery = "UPDATE teacher_locations SET is_active = FALSE WHERE teacher_id = :teacher_id AND is_active = TRUE";
        $deactivateStmt = $this->conn->prepare($deactivateQuery);
        $deactivateStmt->bindParam(':teacher_id', $this->id);
        $deactivateStmt->execute();

        // Insert new location
        $query = "INSERT INTO teacher_locations 
                  (teacher_id, schedule_id, assignment_id, department_id, latitude, longitude, is_active) 
                  VALUES (:teacher_id, :schedule_id, :assignment_id, :department_id, :latitude, :longitude, TRUE)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $this->id);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->bindParam(':assignment_id', $assignmentId);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);

        return $stmt->execute();
    }

    /**
     * Get current active location
     */
    public function getCurrentLocation() {
        $query = "SELECT * FROM teacher_locations 
                  WHERE teacher_id = :teacher_id AND is_active = TRUE 
                  ORDER BY session_start DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $this->id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
