<?php
/**
 * Heroku Database Initialization Script
 * Initializes SAMS database schema on Heroku JawsDB
 */

// Parse database URL from environment
$db_url = getenv('JAWSDB_URL') ?: getenv('DATABASE_URL');

if (!$db_url) {
    die("ERROR: Database URL not found in environment variables\n");
}

// Parse URL
$url_parts = parse_url($db_url);
$db_host = $url_parts['host'];
$db_user = $url_parts['user'];
$db_pass = $url_parts['pass'];
$db_name = ltrim($url_parts['path'], '/');
$db_port = $url_parts['port'] ?? 3306;

echo "========================================\n";
echo "SAMS Database Initialization\n";
echo "========================================\n\n";

echo "Connection Details:\n";
echo "  Host: {$db_host}\n";
echo "  User: {$db_user}\n";
echo "  Database: {$db_name}\n";
echo "  Port: {$db_port}\n\n";

// Connect to database
try {
    echo "Connecting to database...\n";
    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 30,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    echo "✓ Connected successfully\n\n";
    
    // Create database if not exists
    echo "Creating database if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}`");
    echo "✓ Database ready: {$db_name}\n\n";
    
    // Select database
    $pdo->exec("USE `{$db_name}`");
    
    // Read schema file
    $schema_file = __DIR__ . '/config/schema.sql';
    if (!file_exists($schema_file)) {
        die("ERROR: Schema file not found at {$schema_file}\n");
    }
    
    echo "Reading schema from: {$schema_file}\n";
    $schema = file_get_contents($schema_file);
    
    if (!$schema) {
        die("ERROR: Schema file is empty\n");
    }
    
    echo "Executing schema statements...\n";
    
    // Split and execute statements
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        fn($s) => !empty($s) && !preg_match('/^--/', $s)
    );
    
    $count = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        try {
            // Skip comments and empty lines
            if (empty($statement) || preg_match('/^--/', $statement)) {
                continue;
            }
            
            $pdo->exec($statement);
            $count++;
            
            // Extract table name for feedback
            if (preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $statement, $matches)) {
                echo "  ✓ Created table: {$matches[1]}\n";
            }
        } catch (Exception $e) {
            // Skip table already exists errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "  ⚠ {$e->getMessage()}\n";
                $errors++;
            }
        }
    }
    
    echo "\n========================================\n";
    echo "✓ Database Initialization Complete!\n";
    echo "========================================\n\n";
    
    // Show table list
    echo "Tables Created:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  ✓ {$table}\n";
    }
    
    echo "\nStatistics:\n";
    echo "  Statements Executed: {$count}\n";
    echo "  Errors/Warnings: {$errors}\n";
    echo "  Total Tables: " . count($tables) . "\n";
    
} catch (PDOException $e) {
    echo "ERROR: Database connection failed\n";
    echo "Details: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Initialization successful! You can now use the SAMS application.\n";

?>
