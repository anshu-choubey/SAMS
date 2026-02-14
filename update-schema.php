<?php
/**
 * Update Heroku PostgreSQL schema to match MySQL dump structure
 */

require_once __DIR__ . '/config/database.php';

try {
    echo "Connecting to Heroku PostgreSQL database...\n";
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    echo "Connected successfully!\n\n";

    // Read the updated schema file
    $schemaFile = __DIR__ . '/config/schema-postgres-updated.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }

    $schemaSQL = file_get_contents($schemaFile);

    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $schemaSQL)));

    echo "Executing schema updates...\n";

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip comments and empty statements
        }

        try {
            $db->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "⚠ Warning: " . $e->getMessage() . "\n";
            echo "  Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }

    echo "\nSchema update completed!\n";

    // Verify the tables were created
    echo "\nVerifying table creation...\n";
    $tables = [
        'users', 'departments', 'subjects', 'students', 'teachers',
        'teacher_assignments', 'schedules', 'attendance', 'system_settings'
    ];

    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✓ Table '$table' exists\n";
        } catch (Exception $e) {
            echo "✗ Table '$table' error: " . $e->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>