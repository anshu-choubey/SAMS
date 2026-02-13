<?php
/**
 * Department Model
 */

class Department {
    private $conn;
    private $table = 'departments';

    public $id;
    public $name;
    public $code;
    public $description;
    public $hod_id;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new department
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (name, code, description, hod_id, is_active) 
                  VALUES (:name, :code, :description, :hod_id, :is_active)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':code', $this->code);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':hod_id', $this->hod_id);
        $stmt->bindParam(':is_active', $this->is_active);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get all departments
     */
    public function getAll($activeOnly = false) {
        $query = "SELECT d.*, u.full_name as hod_name,
                         (SELECT COUNT(*) FROM students s WHERE s.department_id = d.id) as student_count,
                         (SELECT COUNT(*) FROM subjects sub WHERE sub.department_id = d.id) as subject_count
                  FROM " . $this->table . " d
                  LEFT JOIN users u ON d.hod_id = u.id";

        if ($activeOnly) {
            $query .= " WHERE d.is_active = TRUE";
        }

        $query .= " ORDER BY d.name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get department by ID
     */
    public function getById($id) {
        $query = "SELECT d.*, u.full_name as hod_name
                  FROM " . $this->table . " d
                  LEFT JOIN users u ON d.hod_id = u.id
                  WHERE d.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get department by code
     */
    public function getByCode($code) {
        $query = "SELECT * FROM " . $this->table . " WHERE code = :code LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':code', $code);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update department
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, 
                      code = :code, 
                      description = :description, 
                      hod_id = :hod_id, 
                      is_active = :is_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':code', $this->code);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':hod_id', $this->hod_id);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Delete department
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Check if code exists
     */
    public function codeExists($code, $excludeId = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE code = :code";
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':code', $code);
        
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId);
        }

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
?>
