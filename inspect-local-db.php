<?php
/**
 * Local Database Inspection Script
 * Run this on your localhost to check MySQL database structure and data
 */

// Database configuration - update these with your local MySQL credentials
$host = 'localhost';
$dbname = 'sams_db';
$username = 'root'; // Default MySQL username
$password = ''; // Default MySQL password (empty)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== LOCALHOST MYSQL DATABASE INSPECTION ===\n\n";

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "TABLE: $table\n";
        echo str_repeat("-", 50) . "\n";

        // Get table structure
        $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        echo "COLUMNS:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']} ";
            echo $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL';
            if ($column['Default'] !== null) echo " DEFAULT '{$column['Default']}'";
            if ($column['Key']) echo " [{$column['Key']}]";
            echo "\n";
        }

        // Get row count
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "ROW COUNT: $count\n";

        // Show sample data (first 5 rows)
        if ($count > 0) {
            echo "SAMPLE DATA (first 5 rows):\n";
            $stmt = $pdo->query("SELECT * FROM $table LIMIT 5");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                // Print header
                echo "  ";
                foreach (array_keys($rows[0]) as $col) {
                    echo str_pad($col, 20) . " | ";
                }
                echo "\n  " . str_repeat("-", 100) . "\n";

                // Print data
                foreach ($rows as $row) {
                    echo "  ";
                    foreach ($row as $value) {
                        $val = $value;
                        if (strlen($val) > 17) $val = substr($val, 0, 17) . "...";
                        echo str_pad($val, 20) . " | ";
                    }
                    echo "\n";
                }
            }
        }

        echo "\n" . str_repeat("=", 50) . "\n\n";
    }

    // Special focus on departments and subjects tables
    echo "=== DEPARTMENTS TABLE DETAILS ===\n";
    $depts = $pdo->query("SELECT * FROM departments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($depts as $dept) {
        echo "ID: {$dept['id']}, Name: {$dept['name']}, Code: {$dept['code']}, Active: " . ($dept['is_active'] ? 'Yes' : 'No') . "\n";
    }

    echo "\n=== SUBJECTS TABLE DETAILS ===\n";
    $subjects = $pdo->query("SELECT s.*, d.name as dept_name FROM subjects s LEFT JOIN departments d ON s.department_id = d.id ORDER BY s.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($subjects as $subj) {
        echo "ID: {$subj['id']}, Name: {$subj['name']}, Code: {$subj['code']}, Dept: {$subj['dept_name']}, Semester: {$subj['semester']}, Active: " . ($subj['is_active'] ? 'Yes' : 'No') . "\n";
    }

} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    echo "\nPlease update the database credentials at the top of this script.\n";
}
?>