<?php
require_once 'config/database.php';

$db = (new Database())->getConnection();
if ($db) {
    echo "Connected to database successfully\n";

    // Run the schema
    $schema = file_get_contents('config/schema-minimal.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $db->exec($statement);
                echo "Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (Exception $e) {
                echo "Error executing: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "Schema migration completed\n";
} else {
    echo "Database connection failed\n";
}
?>