<?php
/**
 * Schedule Model
 * Handles class scheduling with conflict detection
 */

class Schedule {
    private $conn;
    private $table = 'schedules';

    public $id;
    public $assignment_id;
    public $day_of_week;
    public $start_time;
    public $end_time;
    public $classroom;
    public $building;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new schedule
     */
    public function create() {
        // Check for conflicts before creating
        if ($this->hasConflict()) {
            return ['success' => false, 'message' => 'Schedule conflict detected'];
        }

        try {
            $query = "INSERT INTO " . $this->table . " 
                      (assignment_id, day_of_week, start_time, end_time, classroom, building, is_active) 
                      VALUES (:assignment_id, :day_of_week, :start_time, :end_time, :classroom, :building, :is_active)";

            $stmt = $this->conn->prepare($query);

            // Use bindValue for direct value binding instead of references
            $stmt->bindValue(':assignment_id', $this->assignment_id, PDO::PARAM_INT);
            $stmt->bindValue(':day_of_week', $this->day_of_week, PDO::PARAM_STR);
            $stmt->bindValue(':start_time', $this->start_time, PDO::PARAM_STR);
            $stmt->bindValue(':end_time', $this->end_time, PDO::PARAM_STR);
            $stmt->bindValue(':classroom', $this->classroom, PDO::PARAM_STR);
            $stmt->bindValue(':building', $this->building, PDO::PARAM_STR);
            $stmt->bindValue(':is_active', $this->is_active, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return ['success' => true, 'id' => $this->id];
            }

            return ['success' => false, 'message' => 'Failed to execute insert'];
        } catch (PDOException $e) {
            error_log('Schedule create error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Check for schedule conflicts
     */
    public function hasConflict($excludeId = null) {
        try {
            // Get teacher ID from assignment
            $query = "SELECT teacher_id FROM teacher_assignments WHERE id = :assignment_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':assignment_id', $this->assignment_id, PDO::PARAM_INT);
            $stmt->execute();
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) {
                return false;
            }

            $teacherId = $assignment['teacher_id'];

            // Check for overlapping schedules for the same teacher
            $conflictQuery = "SELECT s.* FROM " . $this->table . " s
                             JOIN teacher_assignments ta ON s.assignment_id = ta.id
                             WHERE ta.teacher_id = :teacher_id
                             AND s.day_of_week = :day_of_week
                             AND s.is_active = TRUE
                             AND (
                                 (s.start_time <= :start_time AND s.end_time > :start_time)
                                 OR (s.start_time < :end_time AND s.end_time >= :end_time)
                                 OR (s.start_time >= :start_time AND s.end_time <= :end_time)
                             )";

            if ($excludeId) {
                $conflictQuery .= " AND s.id != :exclude_id";
            }

            $conflictStmt = $this->conn->prepare($conflictQuery);
            $conflictStmt->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
            $conflictStmt->bindValue(':day_of_week', $this->day_of_week, PDO::PARAM_STR);
            $conflictStmt->bindValue(':start_time', $this->start_time, PDO::PARAM_STR);
            $conflictStmt->bindValue(':end_time', $this->end_time, PDO::PARAM_STR);

            if ($excludeId) {
                $conflictStmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
            }

            $conflictStmt->execute();
            return $conflictStmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Conflict check error: ' . $e->getMessage());
            return false;
        }

    }

    /**
     * Get schedule by ID
     */
    public function getById($id) {
        $query = "SELECT s.*, 
                         ta.section, ta.academic_year, ta.semester, ta.teacher_id, ta.department_id,
                         sub.name as subject_name, sub.code as subject_code,
                         d.name as department_name, d.code as department_code,
                         u.full_name as teacher_name, t.employee_id
                  FROM " . $this->table . " s
                  JOIN teacher_assignments ta ON s.assignment_id = ta.id
                  JOIN subjects sub ON ta.subject_id = sub.id
                  JOIN departments d ON ta.department_id = d.id
                  JOIN teachers t ON ta.teacher_id = t.id
                  JOIN users u ON t.user_id = u.id
                  WHERE s.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get schedules by teacher ID
     */
    public function getByTeacherId($teacherId) {
        $filters = ['teacher_id' => $teacherId];
        return $this->getAll($filters);
    }

    /**
     * Get all schedules with filters
     */
    public function getAll($filters = []) {
        $query = "SELECT s.*, 
                         ta.teacher_id, ta.section, ta.academic_year, ta.semester,
                         sub.name as subject_name, sub.code as subject_code,
                         d.name as department_name, d.code as department_code,
                         u.full_name as teacher_name, t.employee_id
                  FROM " . $this->table . " s
                  JOIN teacher_assignments ta ON s.assignment_id = ta.id
                  JOIN subjects sub ON ta.subject_id = sub.id
                  JOIN departments d ON ta.department_id = d.id
                  JOIN teachers t ON ta.teacher_id = t.id
                  JOIN users u ON t.user_id = u.id
                  WHERE 1=1";

        if (!empty($filters['teacher_id'])) {
            $query .= " AND ta.teacher_id = :teacher_id";
        }
        if (!empty($filters['department_id'])) {
            $query .= " AND ta.department_id = :department_id";
        }
        if (!empty($filters['day_of_week'])) {
            $query .= " AND s.day_of_week = :day_of_week";
        }
        if (isset($filters['is_active'])) {
            $query .= " AND s.is_active = :is_active";
        }

        $query .= " ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time";

        $stmt = $this->conn->prepare($query);

        if (!empty($filters['teacher_id'])) {
            $stmt->bindValue(':teacher_id', $filters['teacher_id'], PDO::PARAM_INT);
        }
        if (!empty($filters['department_id'])) {
            $stmt->bindValue(':department_id', $filters['department_id'], PDO::PARAM_INT);
        }
        if (!empty($filters['day_of_week'])) {
            $stmt->bindValue(':day_of_week', $filters['day_of_week'], PDO::PARAM_STR);
        }
        if (isset($filters['is_active'])) {
            $stmt->bindValue(':is_active', $filters['is_active'], PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get current active schedule
     */
    public function getCurrentSchedule($teacherId) {
        $currentDay = date('l'); // Monday, Tuesday, etc.
        $currentTime = date('H:i:s');

        $query = "SELECT s.*, 
                         ta.teacher_id, ta.department_id, ta.section,
                         sub.name as subject_name, sub.code as subject_code,
                         d.name as department_name
                  FROM " . $this->table . " s
                  JOIN teacher_assignments ta ON s.assignment_id = ta.id
                  JOIN subjects sub ON ta.subject_id = sub.id
                  JOIN departments d ON ta.department_id = d.id
                  WHERE ta.teacher_id = :teacher_id
                  AND s.day_of_week = :day_of_week
                  AND s.start_time <= :current_time
                  AND s.end_time >= :current_time
                  AND s.is_active = TRUE
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindValue(':day_of_week', $currentDay, PDO::PARAM_STR);
        $stmt->bindValue(':current_time', $currentTime, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update schedule
     */
    public function update() {
        // Check for conflicts
        if ($this->hasConflict($this->id)) {
            return ['success' => false, 'message' => 'Schedule conflict detected'];
        }

        try {
            $query = "UPDATE " . $this->table . " 
                      SET assignment_id = :assignment_id, 
                          day_of_week = :day_of_week, 
                          start_time = :start_time, 
                          end_time = :end_time, 
                          classroom = :classroom, 
                          building = :building, 
                          is_active = :is_active
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            $stmt->bindValue(':assignment_id', $this->assignment_id, PDO::PARAM_INT);
            $stmt->bindValue(':day_of_week', $this->day_of_week, PDO::PARAM_STR);
            $stmt->bindValue(':start_time', $this->start_time, PDO::PARAM_STR);
            $stmt->bindValue(':end_time', $this->end_time, PDO::PARAM_STR);
            $stmt->bindValue(':classroom', $this->classroom, PDO::PARAM_STR);
            $stmt->bindValue(':building', $this->building, PDO::PARAM_STR);
            $stmt->bindValue(':is_active', $this->is_active, PDO::PARAM_INT);
            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'Failed to update schedule'];
        } catch (PDOException $e) {
            error_log('Schedule update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Delete schedule
     */
    public function delete() {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Schedule delete error: ' . $e->getMessage());
            return false;
        }
    }
}
?>
