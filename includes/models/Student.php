<?php
/**
 * Student Model
 * Handles student-specific operations and face registration
 */

class Student {
    private $conn;
    private $table = 'students';

    public $id;
    public $user_id;
    public $roll_number;
    public $department_id;
    public $semester;
    public $section;
    public $batch_year;
    public $admission_date;
    public $face_registered;
    public $face_data;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create student profile
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, roll_number, department_id, semester, section, batch_year, admission_date) 
                  VALUES (:user_id, :roll_number, :department_id, :semester, :section, :batch_year, :admission_date)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':roll_number', $this->roll_number);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':semester', $this->semester);
        $stmt->bindParam(':section', $this->section);
        $stmt->bindParam(':batch_year', $this->batch_year);
        $stmt->bindParam(':admission_date', $this->admission_date);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Register face data
     */
    public function registerFace($faceEmbedding, $facePhoto = null) {
        // Encrypt face data
        $encryptedData = $this->encryptFaceData($faceEmbedding);

        $query = "UPDATE " . $this->table . " 
                  SET face_data = :face_data, 
                      face_photo = :face_photo,
                      face_registered = TRUE, 
                      face_registration_date = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':face_data', $encryptedData);
        $stmt->bindParam(':face_photo', $facePhoto);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Get face data for verification
     */
    public function getFaceData($studentId) {
        $query = "SELECT face_data, face_registered FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $studentId);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['face_registered']) {
            return $this->decryptFaceData($result['face_data']);
        }

        return null;
    }

    /**
     * Encrypt face data using AES-256
     */
    private function encryptFaceData($data) {
        $key = hash('sha256', 'SAMS_FACE_ENCRYPTION_KEY_2026', true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt face data
     */
    private function decryptFaceData($encryptedData) {
        $key = hash('sha256', 'SAMS_FACE_ENCRYPTION_KEY_2026', true);
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get student by user ID
     */
    public function getByUserId($userId) {
        $query = "SELECT s.*, d.name as department_name, d.code as department_code
                  FROM " . $this->table . " s
                  JOIN departments d ON s.department_id = d.id
                  WHERE s.user_id = :user_id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get student by ID
     */
    public function getById($id) {
        $query = "SELECT s.*, 
                         u.full_name, u.email, u.phone,
                         d.name as department_name, d.code as department_code
                  FROM " . $this->table . " s
                  JOIN users u ON s.user_id = u.id
                  JOIN departments d ON s.department_id = d.id
                  WHERE s.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if roll number exists
     */
    public function rollNumberExists($rollNumber, $excludeId = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE roll_number = :roll_number";
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':roll_number', $rollNumber);
        
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId);
        }

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Update student
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET roll_number = :roll_number, 
                      department_id = :department_id, 
                      semester = :semester, 
                      section = :section, 
                      batch_year = :batch_year
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':roll_number', $this->roll_number);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':semester', $this->semester);
        $stmt->bindParam(':section', $this->section);
        $stmt->bindParam(':batch_year', $this->batch_year);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }
}
?>
