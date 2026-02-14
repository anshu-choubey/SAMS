<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "Tables in database:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }

        // Check specific tables
        $required_tables = ['users', 'departments', 'subjects', 'teachers', 'teacher_assignments', 'schedules'];
        echo "\nChecking required tables:\n";
        foreach ($required_tables as $table) {
            if (in_array($table, $tables)) {
                echo "✓ $table exists\n";
            } else {
                echo "✗ $table missing\n";
            }
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database connection failed\n";
}
?>