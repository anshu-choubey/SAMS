<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        echo "Database connection successful!\n";

        // Check if head_id column exists and rename it to hod_id
        $result = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'departments' AND column_name = 'head_id'");
        if ($result->rowCount() > 0) {
            $db->exec("ALTER TABLE departments RENAME COLUMN head_id TO hod_id");
            echo "Renamed head_id to hod_id in departments table!\n";
        } else {
            echo "head_id column not found, checking for hod_id...\n";
            $result2 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'departments' AND column_name = 'hod_id'");
            if ($result2->rowCount() > 0) {
                echo "hod_id column already exists!\n";
            } else {
                echo "Neither head_id nor hod_id found. Adding hod_id column...\n";
                $db->exec("ALTER TABLE departments ADD COLUMN hod_id INT REFERENCES users(id) ON DELETE SET NULL");
                echo "Added hod_id column to departments table!\n";
            }
        }
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>