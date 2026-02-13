<?php
/**
 * Teacher Assignment Model
 * Handles multi-branch teacher assignments
 */

class TeacherAssignment {
    private $conn;
    private $table = 'teacher_assignments';

    public $id;
    public $teacher_id;
    public $subject_id;
    public $department_id;
    public $section;
    public $academic_year;
    public $semester;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new assignment
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (teacher_id, subject_id, department_id, section, academic_year, semester, is_active) 
                  VALUES (:teacher_id, :subject_id, :department_id, :section, :academic_year, :semester, :is_active)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':teacher_id', $this->teacher_id);
        $stmt->bindParam(':subject_id', $this->subject_id);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':section', $this->section);
        $stmt->bindParam(':academic_year', $this->academic_year);
        $stmt->bindParam(':semester', $this->semester);
        $stmt->bindParam(':is_active', $this->is_active);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Create multiple assignments
     */
    public function createBulk($assignments) {
        $this->conn->beginTransaction();

        try {
            foreach ($assignments as $assignment) {
                $this->teacher_id = $assignment['teacher_id'];
                $this->subject_id = $assignment['subject_id'];
                $this->department_id = $assignment['department_id'];
                $this->section = $assignment['section'] ?? null;
                $this->academic_year = $assignment['academic_year'];
                $this->semester = $assignment['semester'];
                $this->is_active = $assignment['is_active'] ?? true;

                if (!$this->create()) {
                    throw new Exception("Failed to create assignment");
                }
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * Get all assignments for a teacher
     */
    public function getByTeacherId($teacherId) {
        $query = "SELECT ta.*, 
                         s.name as subject_name, s.code as subject_code,
                         d.name as department_name, d.code as department_code,
                         u.full_name as teacher_name
                  FROM " . $this->table . " ta
                  JOIN subjects s ON ta.subject_id = s.id
                  JOIN departments d ON ta.department_id = d.id
                  JOIN teachers t ON ta.teacher_id = t.id
                  JOIN users u ON t.user_id = u.id
                  WHERE ta.teacher_id = :teacher_id AND ta.is_active = TRUE
                  ORDER BY d.name, s.name";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacherId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get assignments grouped by department
     */
    public function getByTeacherIdGrouped($teacherId) {
        $assignments = $this->getByTeacherId($teacherId);
        
        $grouped = [];
        foreach ($assignments as $assignment) {
            $deptId = $assignment['department_id'];
            if (!isset($grouped[$deptId])) {
                $grouped[$deptId] = [
                    'department_id' => $deptId,
                    'department_name' => $assignment['department_name'],
                    'department_code' => $assignment['department_code'],
                    'classes' => []
                ];
            }
            $grouped[$deptId]['classes'][] = $assignment;
        }

        return array_values($grouped);
    }

    /**
     * Get assignment by ID
     */
    public function getById($id) {
        $query = "SELECT ta.*, 
                         s.name as subject_name, s.code as subject_code,
                         d.name as department_name, d.code as department_code,
                         u.full_name as teacher_name
                  FROM " . $this->table . " ta
                  JOIN subjects s ON ta.subject_id = s.id
                  JOIN departments d ON ta.department_id = d.id
                  JOIN teachers t ON ta.teacher_id = t.id
                  JOIN users u ON t.user_id = u.id
                  WHERE ta.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update assignment
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET subject_id = :subject_id, 
                      department_id = :department_id, 
                      section = :section, 
                      academic_year = :academic_year, 
                      semester = :semester, 
                      is_active = :is_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':subject_id', $this->subject_id);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':section', $this->section);
        $stmt->bindParam(':academic_year', $this->academic_year);
        $stmt->bindParam(':semester', $this->semester);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Delete assignment
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Check for duplicate assignment
     */
    public function assignmentExists($teacherId, $subjectId, $departmentId, $section, $semester, $academicYear, $excludeId = null) {
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE teacher_id = :teacher_id 
                  AND subject_id = :subject_id 
                  AND department_id = :department_id 
                  AND section = :section 
                  AND semester = :semester 
                  AND academic_year = :academic_year";
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacherId);
        $stmt->bindParam(':subject_id', $subjectId);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->bindParam(':section', $section);
        $stmt->bindParam(':semester', $semester);
        $stmt->bindParam(':academic_year', $academicYear);
        
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId);
        }

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Get all assignments with filters
     */
    public function getAll($filters = []) {
        $query = "SELECT ta.*, 
                         s.name as subject_name, s.code as subject_code,
                         d.name as department_name, d.code as department_code,
                         u.full_name as teacher_name, t.employee_id
                  FROM " . $this->table . " ta
                  JOIN subjects s ON ta.subject_id = s.id
                  JOIN departments d ON ta.department_id = d.id
                  JOIN teachers t ON ta.teacher_id = t.id
                  JOIN users u ON t.user_id = u.id
                  WHERE 1=1";

        if (!empty($filters['department_id'])) {
            $query .= " AND ta.department_id = :department_id";
        }
        if (!empty($filters['teacher_id'])) {
            $query .= " AND ta.teacher_id = :teacher_id";
        }
        if (!empty($filters['subject_id'])) {
            $query .= " AND ta.subject_id = :subject_id";
        }
        if (isset($filters['is_active'])) {
            $query .= " AND ta.is_active = :is_active";
        }

        $query .= " ORDER BY u.full_name, d.name, s.name";

        $stmt = $this->conn->prepare($query);

        if (!empty($filters['department_id'])) {
            $stmt->bindParam(':department_id', $filters['department_id']);
        }
        if (!empty($filters['teacher_id'])) {
            $stmt->bindParam(':teacher_id', $filters['teacher_id']);
        }
        if (!empty($filters['subject_id'])) {
            $stmt->bindParam(':subject_id', $filters['subject_id']);
        }
        if (isset($filters['is_active'])) {
            $stmt->bindParam(':is_active', $filters['is_active'], PDO::PARAM_BOOL);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
