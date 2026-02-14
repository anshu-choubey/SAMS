<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        echo "Database connection successful!\n";

        // Add last_activity column to sessions table
        $db->exec("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

        echo "Added last_activity column to sessions table!\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>