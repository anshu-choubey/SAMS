<?php
/**
 * Add interval settings columns to schedules table
 * Run: heroku run "php scripts/add_schedule_interval_columns.php"
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $dbname = 'a60382na4xjudzs6'; // Heroku JawsDB name
    
    // Try to get dbname from connection
    try {
        $url = getenv('JAWSDB_URL');
        if ($url) {
            $parsed = parse_url($url);
            $dbname = ltrim($parsed['path'], '/');
        }
    } catch (Exception $e) {
        // Use default
    }
    
    function columnExists($pdo, $db, $table, $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$db, $table, $column]);
        return $stmt->fetchColumn() > 0;
    }
    
    $columns = [
        // Schedule-specific interval settings
        ['schedules', 'total_checks', 'INT DEFAULT 3'],
        ['schedules', 'min_interval_minutes', 'INT DEFAULT 10'],
        ['schedules', 'max_interval_minutes', 'INT DEFAULT 25'],
        ['schedules', 'response_window_minutes', 'INT DEFAULT 3'],
        ['schedules', 'hide_timing_from_students', 'BOOLEAN DEFAULT TRUE'],
        ['schedules', 'random_intervals_enabled', 'BOOLEAN DEFAULT TRUE'],
        ['schedules', 'auto_trigger_enabled', 'BOOLEAN DEFAULT TRUE'],
        ['schedules', 'duration_minutes', 'INT DEFAULT 60'],
    ];
    
    foreach ($columns as $col) {
        if (!columnExists($db, $dbname, $col[0], $col[1])) {
            $sql = "ALTER TABLE {$col[0]} ADD COLUMN {$col[1]} {$col[2]}";
            $db->exec($sql);
            echo "Added: {$col[0]}.{$col[1]}\n";
        } else {
            echo "Exists: {$col[0]}.{$col[1]}\n";
        }
    }
    
    echo "\nMigration complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
