<?php
require_once 'config/database.php';

$db = (new Database())->getConnection();
if ($db) {
    echo "Connected to database successfully\n";

    try {
        // Add phone and profile_image columns to users table if they don't exist
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(15)");
        echo "Added phone column to users table\n";

        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255)");
        echo "Added profile_image column to users table\n";

        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP");
        echo "Added last_login column to users table\n";

        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added updated_at column to users table\n";

        // Add missing columns to other tables if needed
        $db->exec("ALTER TABLE departments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added updated_at column to departments table\n";

        $db->exec("ALTER TABLE subjects ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added updated_at column to subjects table\n";

        $db->exec("ALTER TABLE teacher_assignments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added updated_at column to teacher_assignments table\n";

        $db->exec("ALTER TABLE schedules ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added updated_at column to schedules table\n";

        echo "Schema update completed successfully\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database connection failed\n";
}
?>