<?php
/**
 * Attendance Model
 * Handles attendance marking with GPS and face verification
 */

class Attendance {
    private $conn;
    private $table = 'attendance';

    public $id;
    public $student_id;
    public $schedule_id;
    public $assignment_id;
    public $teacher_id;
    public $department_id;
    public $attendance_date;
    public $student_latitude;
    public $student_longitude;
    public $teacher_latitude;
    public $teacher_longitude;
    public $distance_meters;
    public $face_confidence_score;
    public $verification_status;
    public $status;
    public $remarks;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Mark attendance with dual verification
     */
    public function mark() {
        // Check if already marked today
        if ($this->isAlreadyMarked()) {
            return [
                'success' => false, 
                'message' => 'Attendance already marked for this class today'
            ];
        }

        // Verify GPS proximity
        $gpsValid = $this->verifyGPSProximity();
        
        // Get face threshold from database settings
        $thresholdQuery = "SELECT setting_value FROM system_settings WHERE setting_key = 'face_confidence_threshold'";
        $thresholdStmt = $this->conn->prepare($thresholdQuery);
        $thresholdStmt->execute();
        $thresholdResult = $thresholdStmt->fetch(PDO::FETCH_ASSOC);
        $faceThreshold = $thresholdResult ? (float)$thresholdResult['setting_value'] : (FACE_CONFIDENCE_THRESHOLD ?? 95);
        
        // Verify face confidence
        $faceValid = $this->face_confidence_score >= $faceThreshold;

        // Determine verification status
        if ($gpsValid && $faceValid) {
            $this->verification_status = 'success';
            $this->status = 'present';
        } elseif (!$gpsValid && !$faceValid) {
            $this->verification_status = 'both_failed';
            return [
                'success' => false,
                'message' => "Both GPS and face verification failed. Face: {$this->face_confidence_score}% (required: {$faceThreshold}%)"
            ];
        } elseif (!$gpsValid) {
            $this->verification_status = 'gps_failed';
            return [
                'success' => false,
                'message' => "GPS verification failed. Distance: {$this->distance_meters}m (max allowed: " . GPS_PROXIMITY_RADIUS . "m)"
            ];
        } else {
            $this->verification_status = 'face_failed';
            return [
                'success' => false,
                'message' => "Face verification failed. Confidence: {$this->face_confidence_score}% (required: {$faceThreshold}%). Please re-register your face."
            ];
        }

        $query = "INSERT INTO " . $this->table . " 
                  (student_id, schedule_id, assignment_id, teacher_id, department_id, 
                   attendance_date, student_latitude, student_longitude, 
                   teacher_latitude, teacher_longitude, distance_meters, 
                   face_confidence_score, verification_status, status, remarks) 
                  VALUES (:student_id, :schedule_id, :assignment_id, :teacher_id, :department_id,
                          :attendance_date, :student_latitude, :student_longitude,
                          :teacher_latitude, :teacher_longitude, :distance_meters,
                          :face_confidence_score, :verification_status, :status, :remarks)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':student_id', $this->student_id);
        $stmt->bindParam(':schedule_id', $this->schedule_id);
        $stmt->bindParam(':assignment_id', $this->assignment_id);
        $stmt->bindParam(':teacher_id', $this->teacher_id);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':attendance_date', $this->attendance_date);
        $stmt->bindParam(':student_latitude', $this->student_latitude);
        $stmt->bindParam(':student_longitude', $this->student_longitude);
        $stmt->bindParam(':teacher_latitude', $this->teacher_latitude);
        $stmt->bindParam(':teacher_longitude', $this->teacher_longitude);
        $stmt->bindParam(':distance_meters', $this->distance_meters);
        $stmt->bindParam(':face_confidence_score', $this->face_confidence_score);
        $stmt->bindParam(':verification_status', $this->verification_status);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':remarks', $this->remarks);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return [
                'success' => true,
                'message' => 'Attendance marked successfully',
                'attendance_id' => $this->id,
                'verification_status' => $this->verification_status,
                'distance_meters' => $this->distance_meters,
                'face_confidence' => $this->face_confidence_score
            ];
        }

        return ['success' => false, 'message' => 'Failed to mark attendance'];
    }

    /**
     * Verify GPS proximity using Haversine formula
     */
    private function verifyGPSProximity() {
        $earthRadius = 6371000; // meters

        $lat1 = deg2rad($this->teacher_latitude);
        $lon1 = deg2rad($this->teacher_longitude);
        $lat2 = deg2rad($this->student_latitude);
        $lon2 = deg2rad($this->student_longitude);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        $this->distance_meters = round($earthRadius * $c, 2);

        return $this->distance_meters <= GPS_PROXIMITY_RADIUS;
    }

    /**
     * Check if attendance already marked
     */
    private function isAlreadyMarked() {
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE student_id = :student_id 
                  AND schedule_id = :schedule_id 
                  AND attendance_date = :attendance_date";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $this->student_id);
        $stmt->bindParam(':schedule_id', $this->schedule_id);
        $stmt->bindParam(':attendance_date', $this->attendance_date);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Get attendance by student ID
     */
    public function getByStudentId($studentId, $filters = []) {
        $query = "SELECT a.*, 
                         s.day_of_week, s.start_time, s.end_time, s.classroom,
                         sub.name as subject_name, sub.code as subject_code,
                         d.name as department_name,
                         u.full_name as teacher_name
                  FROM " . $this->table . " a
                  JOIN schedules s ON a.schedule_id = s.id
                  JOIN teacher_assignments ta ON a.assignment_id = ta.id
                  JOIN subjects sub ON ta.subject_id = sub.id
                  JOIN departments d ON a.department_id = d.id
                  JOIN teachers t ON a.teacher_id = t.id
                  JOIN users u ON t.user_id = u.id
                  WHERE a.student_id = :student_id";

        if (!empty($filters['subject_id'])) {
            $query .= " AND ta.subject_id = :subject_id";
        }
        if (!empty($filters['date_from'])) {
            $query .= " AND a.attendance_date >= :date_from";
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND a.attendance_date <= :date_to";
        }

        $query .= " ORDER BY a.attendance_date DESC, a.attendance_time DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $studentId);

        if (!empty($filters['subject_id'])) {
            $stmt->bindParam(':subject_id', $filters['subject_id']);
        }
        if (!empty($filters['date_from'])) {
            $stmt->bindParam(':date_from', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $stmt->bindParam(':date_to', $filters['date_to']);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance statistics for student
     */
    public function getStudentStats($studentId, $subjectId = null) {
        $query = "SELECT 
                    COUNT(*) as total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as attended,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
                  FROM " . $this->table . " a
                  JOIN teacher_assignments ta ON a.assignment_id = ta.id
                  WHERE a.student_id = :student_id";

        if ($subjectId) {
            $query .= " AND ta.subject_id = :subject_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $studentId);

        if ($subjectId) {
            $stmt->bindParam(':subject_id', $subjectId);
        }

        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance report by department
     */
    public function getReportByDepartment($departmentId, $dateFrom, $dateTo) {
        $query = "SELECT 
                    d.name as department_name,
                    COUNT(DISTINCT a.student_id) as total_students,
                    COUNT(*) as total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as avg_attendance
                  FROM " . $this->table . " a
                  JOIN departments d ON a.department_id = d.id
                  WHERE a.department_id = :department_id
                  AND a.attendance_date BETWEEN :date_from AND :date_to
                  GROUP BY d.id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->bindParam(':date_from', $dateFrom);
        $stmt->bindParam(':date_to', $dateTo);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance report by subject
     */
    public function getReportBySubject($subjectId, $dateFrom, $dateTo) {
        $query = "SELECT 
                    sub.name as subject_name,
                    sub.code as subject_code,
                    COUNT(DISTINCT a.student_id) as total_students,
                    COUNT(*) as total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as avg_attendance
                  FROM " . $this->table . " a
                  JOIN teacher_assignments ta ON a.assignment_id = ta.id
                  JOIN subjects sub ON ta.subject_id = sub.id
                  WHERE ta.subject_id = :subject_id
                  AND a.attendance_date BETWEEN :date_from AND :date_to
                  GROUP BY sub.id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':subject_id', $subjectId);
        $stmt->bindParam(':date_from', $dateFrom);
        $stmt->bindParam(':date_to', $dateTo);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get low attendance students
     */
    public function getLowAttendanceStudents($threshold = 75) {
        $query = "SELECT 
                    s.id as student_id,
                    s.roll_number,
                    u.full_name,
                    u.email,
                    d.name as department_name,
                    COUNT(*) as total_classes,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as attended,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
                  FROM students s
                  JOIN users u ON s.user_id = u.id
                  JOIN departments d ON s.department_id = d.id
                  LEFT JOIN " . $this->table . " a ON s.id = a.student_id
                  WHERE u.is_active = TRUE
                  GROUP BY s.id
                  HAVING percentage < :threshold OR percentage IS NULL
                  ORDER BY percentage ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':threshold', $threshold);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get today's attendance for a schedule
     */
    public function getTodayAttendance($scheduleId) {
        $today = date('Y-m-d');

        $query = "SELECT a.*, 
                         s.roll_number,
                         u.full_name,
                         u.email
                  FROM " . $this->table . " a
                  JOIN students s ON a.student_id = s.id
                  JOIN users u ON s.user_id = u.id
                  WHERE a.schedule_id = :schedule_id
                  AND a.attendance_date = :today
                  ORDER BY a.attendance_time DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->bindParam(':today', $today);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance summary for dashboard
     */
    public function getDashboardStats() {
        $today = date('Y-m-d');

        $query = "SELECT 
                    COUNT(DISTINCT student_id) as students_present_today,
                    COUNT(*) as total_attendance_today,
                    ROUND(AVG(face_confidence_score), 2) as avg_face_confidence,
                    ROUND(AVG(distance_meters), 2) as avg_distance
                  FROM " . $this->table . "
                  WHERE attendance_date = :today
                  AND verification_status = 'success'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':today', $today);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mark absent students for a class when session ends
     */
    public function markAbsentForEndedClass($scheduleId, $teacherId, $departmentId, $semester, $section = null) {
        $today = date('Y-m-d');
        
        // Get all students in the class
        $studentsQuery = "SELECT s.id as student_id, s.user_id 
                         FROM students s 
                         WHERE s.department_id = :department_id 
                         AND s.semester = :semester
                         AND (s.section = :section OR :section IS NULL OR :section = '')";
        
        $stmt = $this->conn->prepare($studentsQuery);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->bindParam(':semester', $semester);
        $stmt->bindParam(':section', $section);
        $stmt->execute();
        
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get assignment details for this schedule
        $assignmentQuery = "SELECT ta.id as assignment_id, ta.subject_id, ta.teacher_id 
                           FROM schedules sc 
                           JOIN teacher_assignments ta ON sc.assignment_id = ta.id 
                           WHERE sc.id = :schedule_id";
        $stmt = $this->conn->prepare($assignmentQuery);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->execute();
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment) {
            return ['success' => false, 'message' => 'Assignment not found'];
        }
        
        $absentCount = 0;
        
        // For each student, check if they already have attendance marked
        foreach ($students as $student) {
            // Check if student already has attendance for this class today
            $checkQuery = "SELECT id FROM " . $this->table . " 
                          WHERE student_id = :student_id 
                          AND schedule_id = :schedule_id 
                          AND attendance_date = :attendance_date";
            
            $stmt = $this->conn->prepare($checkQuery);
            $stmt->bindParam(':student_id', $student['student_id']);
            $stmt->bindParam(':schedule_id', $scheduleId);
            $stmt->bindParam(':attendance_date', $today);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Student hasn't marked attendance, mark as absent
                $insertQuery = "INSERT INTO " . $this->table . " 
                               (student_id, schedule_id, assignment_id, teacher_id, department_id, 
                                attendance_date, verification_status, status, remarks) 
                               VALUES (:student_id, :schedule_id, :assignment_id, :teacher_id, :department_id,
                                       :attendance_date, 'success', 'absent', 'Auto-marked absent when class ended')";
                
                $stmt = $this->conn->prepare($insertQuery);
                $stmt->bindParam(':student_id', $student['student_id']);
                $stmt->bindParam(':schedule_id', $scheduleId);
                $stmt->bindParam(':assignment_id', $assignment['assignment_id']);
                $stmt->bindParam(':teacher_id', $teacherId);
                $stmt->bindParam(':department_id', $departmentId);
                $stmt->bindParam(':attendance_date', $today);
                $stmt->execute();
                
                $absentCount++;
            }
        }
        
        return [
            'success' => true, 
            'absent_marked' => $absentCount,
            'message' => "Marked $absentCount students as absent"
        ];
    }
}
?>
