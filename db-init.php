<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        echo "Database connection successful!\n";

        // Read and execute schema.sql
        $schema = file_get_contents('../config/schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $schema)));

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $conn->exec($statement);
                echo "Executed: " . substr($statement, 0, 50) . "...\n";
            }
        }

        echo "Database schema initialized successfully!\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>