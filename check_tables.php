<?php
/**
 * Check Existing Tables in Heroku Database
 */

// Heroku Database Credentials
$host = 'gx97kbnhgjzh3efb.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
$port = '3306';
$database = 'a60382na4xjudzs6';
$username = 'ql8x6of7t4e8rou4';
$password = 'j7vh4q8e55ms7q10';

try {
    // Connect to database
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Connected to Heroku database successfully!\n\n";
    
    // Get all tables
    echo "📋 EXISTING TABLES:\n";
    echo str_repeat("=", 60) . "\n";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $index => $table) {
        echo ($index + 1) . ". " . $table . "\n";
    }
    
    echo "\nTotal tables: " . count($tables) . "\n\n";
    
    // Check for multi-check attendance tables
    echo "🔍 MULTI-CHECK ATTENDANCE TABLES:\n";
    echo str_repeat("=", 60) . "\n";
    
    $multiCheckTables = [
        'attendance_check_points',
        'attendance_check_responses'
    ];
    
    foreach ($multiCheckTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ $table - EXISTS\n";
            
            // Get row count
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetch()['count'];
            echo "   Rows: $count\n";
        } else {
            echo "❌ $table - MISSING (needs migration)\n";
        }
    }
    
    echo "\n";
    
    // Check teacher_locations structure
    echo "🔍 TEACHER_LOCATIONS TABLE COLUMNS:\n";
    echo str_repeat("=", 60) . "\n";
    
    if (in_array('teacher_locations', $tables)) {
        $stmt = $pdo->query("DESCRIBE teacher_locations");
        $columns = $stmt->fetchAll();
        
        $multiCheckColumns = ['multi_check_enabled', 'total_checks_planned', 'checks_completed'];
        $existingColumns = array_column($columns, 'Field');
        
        echo "Checking for multi-check columns:\n";
        foreach ($multiCheckColumns as $col) {
            if (in_array($col, $existingColumns)) {
                echo "✅ $col - EXISTS\n";
            } else {
                echo "❌ $col - MISSING (needs migration)\n";
            }
        }
        
        echo "\nAll columns:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "❌ teacher_locations table not found!\n";
    }
    
    echo "\n";
    
    // Check attendance table structure
    echo "🔍 ATTENDANCE TABLE COLUMNS:\n";
    echo str_repeat("=", 60) . "\n";
    
    if (in_array('attendance', $tables)) {
        $stmt = $pdo->query("DESCRIBE attendance");
        $columns = $stmt->fetchAll();
        
        $multiCheckColumns = ['session_id', 'total_checks_required', 'successful_checks'];
        $existingColumns = array_column($columns, 'Field');
        
        echo "Checking for multi-check columns:\n";
        foreach ($multiCheckColumns as $col) {
            if (in_array($col, $existingColumns)) {
                echo "✅ $col - EXISTS\n";
            } else {
                echo "❌ $col - MISSING (needs migration)\n";
            }
        }
        
        echo "\nAll columns:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "❌ attendance table not found!\n";
    }
    
    echo "\n";
    
    // Summary
    echo "📊 MIGRATION STATUS:\n";
    echo str_repeat("=", 60) . "\n";
    
    $needsMigration = false;
    
    if (!in_array('attendance_check_points', $tables)) {
        echo "❌ attendance_check_points table missing\n";
        $needsMigration = true;
    }
    
    if (!in_array('attendance_check_responses', $tables)) {
        echo "❌ attendance_check_responses table missing\n";
        $needsMigration = true;
    }
    
    // Check teacher_locations columns
    if (in_array('teacher_locations', $tables)) {
        $stmt = $pdo->query("DESCRIBE teacher_locations");
        $columns = array_column($stmt->fetchAll(), 'Field');
        
        if (!in_array('multi_check_enabled', $columns)) {
            echo "❌ teacher_locations missing multi-check columns\n";
            $needsMigration = true;
        }
    }
    
    // Check attendance columns
    if (in_array('attendance', $tables)) {
        $stmt = $pdo->query("DESCRIBE attendance");
        $columns = array_column($stmt->fetchAll(), 'Field');
        
        if (!in_array('session_id', $columns)) {
            echo "❌ attendance table missing multi-check columns\n";
            $needsMigration = true;
        }
    }
    
    if ($needsMigration) {
        echo "\n⚠️  MIGRATION REQUIRED\n";
        echo "Run: php run_migration.php\n";
        echo "Or: mysql ... < migrations/add_multi_check_attendance.sql\n";
    } else {
        echo "\n✅ Database is up to date!\n";
        echo "Multi-check attendance system is ready to use.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
