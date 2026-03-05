<?php
/**
 * Subject Model
 */

class Subject {
    private $conn;
    private $table = 'subjects';

    public $id;
    public $name;
    public $code;
    public $department_id;
    public $credits;
    public $semester;
    public $description;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new subject
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (name, code, department_id, credits, semester, description, is_active) 
                  VALUES (:name, :code, :department_id, :credits, :semester, :description, :is_active)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':code', $this->code);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':credits', $this->credits);
        $stmt->bindParam(':semester', $this->semester);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':is_active', $this->is_active);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get all subjects
     */
    public function getAll($filters = []) {
        $query = "SELECT s.*, d.name as department_name, d.code as department_code
                  FROM " . $this->table . " s
                  LEFT JOIN departments d ON s.department_id = d.id
                  WHERE 1=1";

        if (!empty($filters['department_id'])) {
            $query .= " AND s.department_id = :department_id";
        }
        if (!empty($filters['semester'])) {
            $query .= " AND s.semester = :semester";
        }
        if (isset($filters['is_active'])) {
            $query .= " AND s.is_active = :is_active";
        }

        $query .= " ORDER BY d.name, s.semester, s.name ASC";

        $stmt = $this->conn->prepare($query);

        if (!empty($filters['department_id'])) {
            $stmt->bindParam(':department_id', $filters['department_id']);
        }
        if (!empty($filters['semester'])) {
            $stmt->bindParam(':semester', $filters['semester']);
        }
        if (isset($filters['is_active'])) {
            $stmt->bindParam(':is_active', $filters['is_active'], PDO::PARAM_BOOL);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get subject by ID
     */
    public function getById($id) {
        $query = "SELECT s.*, d.name as department_name
                  FROM " . $this->table . " s
                  LEFT JOIN departments d ON s.department_id = d.id
                  WHERE s.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update subject
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, 
                      code = :code, 
                      department_id = :department_id, 
                      credits = :credits, 
                      semester = :semester, 
                      description = :description, 
                      is_active = :is_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':code', $this->code);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':credits', $this->credits);
        $stmt->bindParam(':semester', $this->semester);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Delete subject
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
