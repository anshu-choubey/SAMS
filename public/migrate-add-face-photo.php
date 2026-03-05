<?php
/**
 * Migration: Add face_photo column to students table
 * This adds support for storing student face photos for attendance verification
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check if column already exists
    $checkQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'students' 
                   AND COLUMN_NAME = 'face_photo'";
    $stmt = $db->query($checkQuery);
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($columnExists) {
        echo json_encode([
            'success' => true,
            'message' => 'Column face_photo already exists in students table'
        ]);
        exit;
    }

    // Add the column if it doesn't exist
    $migrations = [
        // Add face_photo column to students table
        "ALTER TABLE students ADD COLUMN face_photo LONGBLOB AFTER face_registered",
        
        // Add face_registration_date column if missing
        "ALTER TABLE students ADD COLUMN face_registration_date TIMESTAMP NULL DEFAULT NULL AFTER face_registered"
    ];

    foreach ($migrations as $migration) {
        try {
            $db->exec($migration);
            echo "[✓] Executed: " . substr($migration, 0, 50) . "...\n";
        } catch (PDOException $e) {
            // Column might already exist, that's ok
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
            echo "[~] Column already exists\n";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Migration completed successfully',
        'changes' => [
            'face_photo column added to students table (LONGBLOB)',
            'face_registration_date column added to students table (TIMESTAMP)'
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed',
        'error' => $e->getMessage()
    ]);
}
?>
