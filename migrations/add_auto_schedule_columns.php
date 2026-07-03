<?php
/**
 * Add auto-schedule columns to teacher_locations
 */

$host = 'gx97kbnhgjzh3efb.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
$port = '3306';
$database = 'a60382na4xjudzs6';
$username = 'ql8x6of7t4e8rou4';
$password = 'j7vh4q8e55ms7q10';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ Connected\n\n";
    
    echo "Adding auto_schedule column...\n";
    try {
        $pdo->exec("ALTER TABLE teacher_locations 
                    ADD COLUMN auto_schedule BOOLEAN DEFAULT FALSE AFTER checks_completed");
        echo "✅ auto_schedule added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "⚠️  auto_schedule already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "Adding first_check_delay column...\n";
    try {
        $pdo->exec("ALTER TABLE teacher_locations 
                    ADD COLUMN first_check_delay INT DEFAULT 20 AFTER auto_schedule");
        echo "✅ first_check_delay added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "⚠️  first_check_delay already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✅ Migration complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
