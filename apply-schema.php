<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        $sql = file_get_contents(__DIR__ . '/config/schema-minimal.sql');

        // Execute the entire SQL file at once
        echo "Executing entire schema...\n";
        $db->exec($sql);
        echo "Schema applied successfully!\n";

        // Verify tables were created
        $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables created: " . implode(', ', $tables) . "\n";

    } catch (Exception $e) {
        echo "Error applying schema: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database connection failed\n";
}
?>