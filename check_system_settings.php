<?php
/**
 * Check system_settings table structure
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
    
    echo "🔍 SYSTEM_SETTINGS TABLE STRUCTURE:\n";
    echo str_repeat("=", 60) . "\n";
    
    $stmt = $pdo->query("DESCRIBE system_settings");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']}\n";
    }
    
    echo "\n📋 EXISTING SETTINGS:\n";
    echo str_repeat("=", 60) . "\n";
    
    $stmt = $pdo->query("SELECT * FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($settings) > 0) {
        foreach ($settings as $setting) {
            echo "  {$setting['setting_key']} = {$setting['setting_value']}\n";
        }
    } else {
        echo "  (no settings found)\n";
    }
    
    echo "\nTotal settings: " . count($settings) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
