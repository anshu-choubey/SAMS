<?php
/**
 * Import MySQL dump data into Heroku PostgreSQL - Table by table approach
 */

require_once __DIR__ . '/config/database.php';

class MySQLDumpImporter {
    private $db;
    private $dumpFile = '/Users/anshu/Desktop/backups/sams_backup.sql';

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function importAll() {
        echo "Starting comprehensive data import from MySQL dump...\n\n";

        // Import in order of dependencies
        $this->importTable('users');
        $this->importTable('departments'); // Already done, but let's make sure
        $this->importTable('subjects');
        $this->importTable('students');
        $this->importTable('teachers');
        $this->importTable('teacher_assignments');
        $this->importTable('schedules');
        $this->importTable('attendance');
        $this->importTable('system_settings');
        $this->importTable('sessions');
        $this->importTable('audit_logs');

        echo "\nComprehensive import completed!\n";
    }

    private function importTable($tableName) {
        echo "Importing table: $tableName\n";

        // Extract INSERT statements for this table
        $inserts = $this->extractTableInserts($tableName);
        if (empty($inserts)) {
            echo "No INSERT statements found for $tableName\n";
            return;
        }

        $imported = 0;
        foreach ($inserts as $insert) {
            try {
                $count = $this->processInsert($tableName, $insert);
                $imported += $count;
            } catch (Exception $e) {
                echo "Error importing $tableName: " . $e->getMessage() . "\n";
            }
        }

        echo "Imported $imported records for $tableName\n\n";
    }

    private function extractTableInserts($tableName) {
        $content = file_get_contents($this->dumpFile);
        $lines = explode("\n", $content);

        $inserts = [];
        $currentInsert = '';
        $inTableInsert = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (strpos($line, "INSERT INTO `$tableName`") === 0) {
                if ($inTableInsert && !empty($currentInsert)) {
                    $inserts[] = $currentInsert;
                }
                $currentInsert = $line;
                $inTableInsert = true;
            } elseif ($inTableInsert) {
                $currentInsert .= "\n" . $line;
                if (substr($line, -1) === ';') {
                    $inserts[] = $currentInsert;
                    $currentInsert = '';
                    $inTableInsert = false;
                }
            }
        }

        if (!empty($currentInsert)) {
            $inserts[] = $currentInsert;
        }

        return $inserts;
    }

    private function processInsert($tableName, $insert) {
        // Extract VALUES part
        if (!preg_match('/INSERT INTO `[^`]+` VALUES (.*);/s', $insert, $matches)) {
            return 0;
        }

        $valuesStr = $matches[1];

        // Parse the VALUES - this is the tricky part
        $rows = $this->parseValuesString($valuesStr);
        if (empty($rows)) {
            return 0;
        }

        // Insert rows
        return $this->insertRows($tableName, $rows);
    }

    private function parseValuesString($valuesStr) {
        $rows = [];
        $currentRow = '';
        $depth = 0;
        $inQuotes = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($valuesStr); $i++) {
            $char = $valuesStr[$i];

            if (!$inQuotes && $char === '(') {
                $depth++;
                if ($depth === 1) {
                    $currentRow = '';
                    continue;
                }
            } elseif (!$inQuotes && $char === ')') {
                $depth--;
                if ($depth === 0) {
                    $rows[] = $this->parseRow($currentRow);
                    $currentRow = '';
                    // Skip comma and whitespace
                    while ($i + 1 < strlen($valuesStr) && (substr($valuesStr, $i + 1, 1) === ',' || ctype_space(substr($valuesStr, $i + 1, 1)))) {
                        $i++;
                    }
                    continue;
                }
            }

            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i === 0 || $valuesStr[$i-1] !== '\\')) {
                $inQuotes = false;
            }

            if ($depth > 0) {
                $currentRow .= $char;
            }
        }

        return $rows;
    }

    private function parseRow($rowStr) {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($rowStr); $i++) {
            $char = $rowStr[$i];

            if (!$inQuotes && $char === ',') {
                $values[] = trim($current);
                $current = '';
                continue;
            }

            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i === 0 || $rowStr[$i-1] !== '\\')) {
                $inQuotes = false;
            }

            $current .= $char;
        }

        if (!empty($current)) {
            $values[] = trim($current);
        }

        return array_map([$this, 'convertValue'], $values);
    }

    private function convertValue($value) {
        $value = trim($value);

        if ($value === 'NULL' || $value === '') {
            return null;
        }

        // Remove surrounding quotes
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        // Convert boolean values
        if ($value === '1' || $value === '0') {
            return (bool)$value;
        }

        return $value;
    }

    private function insertRows($tableName, $rows) {
        if (empty($rows)) return 0;

        // Get column structure
        $columns = $this->getTableColumns($tableName);
        if (empty($columns)) {
            echo "Could not get columns for $tableName\n";
            return 0;
        }

        $inserted = 0;
        foreach ($rows as $row) {
            try {
                $this->insertRow($tableName, $columns, $row);
                $inserted++;
            } catch (Exception $e) {
                // Skip individual row errors but log them
                echo "Skipping row in $tableName: " . $e->getMessage() . "\n";
            }
        }

        return $inserted;
    }

    private function getTableColumns($table) {
        try {
            $stmt = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$table' ORDER BY ordinal_position");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    private function insertRow($table, $columns, $data) {
        // Ensure data array matches column count
        $data = array_pad($data, count($columns), null);

        // Convert data types
        $data = $this->convertDataTypes($table, $columns, $data);

        // Prepare INSERT statement
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        $columnList = implode(',', $columns);
        $sql = "INSERT INTO $table ($columnList) VALUES ($placeholders)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
    }

    private function convertDataTypes($table, $columns, $data) {
        $converted = [];

        foreach ($columns as $index => $column) {
            $value = $data[$index] ?? null;

            // Table-specific conversions
            switch ($table) {
                case 'users':
                    if ($column === 'is_active') {
                        $value = $value === null ? true : (bool)$value;
                    }
                    break;

                case 'departments':
                    if ($column === 'is_active') {
                        $value = $value === null ? true : (bool)$value;
                    }
                    break;

                case 'subjects':
                    if ($column === 'is_active') {
                        $value = $value === null ? true : (bool)$value;
                    }
                    break;

                case 'students':
                    if ($column === 'face_registered') {
                        $value = (bool)$value;
                    }
                    break;

                case 'system_settings':
                    if ($column === 'is_system') {
                        $value = $value === null ? false : (bool)$value;
                    }
                    break;
            }

            $converted[] = $value;
        }

        return $converted;
    }
}

try {
    // Set the DATABASE_URL for Heroku
    putenv('DATABASE_URL=postgres://u6pa1o10ujrn7p:p2efc1037cad4f9b81fe10b2e2ddfc9bb1fff62ec3571e12677a166f6fe9c143c@chepvbj2ergru.cluster-czrs8kj4isg7.us-east-1.rds.amazonaws.com:5432/d7mv44je5e9cj5');

    $database = new Database();
    $db = $database->getConnection();

    $importer = new MySQLDumpImporter($db);
    $importer->importAll();

} catch (Exception $e) {
    echo "Import failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>