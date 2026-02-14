<?php
/**
 * Import MySQL dump data into Heroku PostgreSQL
 * Reads the MySQL dump file and converts it to PostgreSQL format
 */

require_once __DIR__ . '/config/database.php';

class MySQLToPostgreSQLImporter {
    private $db;
    private $dumpFile = '/Users/anshu/Desktop/backups/sams_backup.sql';

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function import() {
        if (!file_exists($this->dumpFile)) {
            throw new Exception("MySQL dump file not found: " . $this->dumpFile);
        }

        echo "Starting data import from MySQL dump to PostgreSQL...\n\n";

        // Clear existing data (except admin user)
        $this->clearExistingData();

        // Read and parse the dump file
        $content = file_get_contents($this->dumpFile);
        $this->parseAndImport($content);

        echo "\nData import completed successfully!\n";
    }

    private function clearExistingData() {
        echo "Clearing existing data...\n";

        $tables = [
            'attendance',
            'teacher_locations',
            'schedules',
            'teacher_assignments',
            'students',
            'teachers',
            'subjects',
            'users',
            'departments',
            'system_settings',
            'notifications',
            'fcm_tokens',
            'sessions',
            'audit_logs'
        ];

        foreach ($tables as $table) {
            try {
                if ($table === 'users') {
                    $this->db->exec("DELETE FROM users WHERE id > 1"); // Keep admin user
                } else {
                    $this->db->exec("DELETE FROM $table");
                }
                echo "Cleared table: $table\n";
            } catch (Exception $e) {
                echo "Warning: Could not clear table $table: " . $e->getMessage() . "\n";
            }
        }
    }

    private function parseAndImport($content) {
        // Split content into individual INSERT statements
        $inserts = [];
        $lines = explode("\n", $content);

        $currentInsert = '';
        $inInsert = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (strpos($line, 'INSERT INTO') === 0) {
                if ($inInsert && !empty($currentInsert)) {
                    $inserts[] = $currentInsert;
                }
                $currentInsert = $line;
                $inInsert = true;
            } elseif ($inInsert) {
                $currentInsert .= "\n" . $line;
                if (substr($line, -1) === ';') {
                    $inserts[] = $currentInsert;
                    $currentInsert = '';
                    $inInsert = false;
                }
            }
        }

        if (!empty($currentInsert)) {
            $inserts[] = $currentInsert;
        }

        // Process each INSERT statement
        foreach ($inserts as $insert) {
            $this->processInsertStatement($insert);
        }
    }

    private function processInsertStatement($insert) {
        // Extract table name and values
        if (!preg_match('/INSERT INTO `([^`]+)` VALUES (.*);/s', $insert, $matches)) {
            return; // Skip malformed inserts
        }

        $table = $matches[1];
        $valuesStr = $matches[2];

        echo "Processing table: $table\n";

        // Parse the VALUES part
        $values = $this->parseValues($valuesStr);

        if (empty($values)) {
            echo "No values found for table $table\n";
            return;
        }

        // Convert and insert data
        $this->insertData($table, $values);
    }

    private function parseValues($valuesStr) {
        $values = [];
        $depth = 0;
        $current = '';
        $inQuotes = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($valuesStr); $i++) {
            $char = $valuesStr[$i];

            if (!$inQuotes && ($char === '(' || $char === '[')) {
                $depth++;
                if ($depth === 1) {
                    $current = '';
                    continue;
                }
            } elseif (!$inQuotes && ($char === ')' || $char === ']')) {
                $depth--;
                if ($depth === 0) {
                    $values[] = $this->parseSingleValueSet($current);
                    $current = '';
                    continue;
                }
            } elseif ($depth > 0) {
                if (!$inQuotes && ($char === '"' || $char === "'")) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($inQuotes && $char === $quoteChar && $valuesStr[$i-1] !== '\\') {
                    $inQuotes = false;
                }
                $current .= $char;
            }
        }

        return $values;
    }

    private function parseSingleValueSet($valueStr) {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($valueStr); $i++) {
            $char = $valueStr[$i];

            if (!$inQuotes && $char === ',') {
                $values[] = trim($current);
                $current = '';
                continue;
            }

            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i === 0 || $valueStr[$i-1] !== '\\')) {
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

    private function insertData($table, $dataSets) {
        if (empty($dataSets)) return;

        // Map MySQL table names to PostgreSQL table names
        $tableMapping = [
            'attendance' => 'attendance',
            'departments' => 'departments',
            'fcm_tokens' => 'fcm_tokens',
            'notifications' => 'notifications',
            'schedules' => 'schedules',
            'sessions' => 'sessions',
            'students' => 'students',
            'subjects' => 'subjects',
            'system_settings' => 'system_settings',
            'teacher_assignments' => 'teacher_assignments',
            'teacher_locations' => 'teacher_locations',
            'teachers' => 'teachers',
            'users' => 'users'
        ];

        if (!isset($tableMapping[$table])) {
            echo "Skipping unknown table: $table\n";
            return;
        }

        $pgTable = $tableMapping[$table];

        // Get column structure for this table
        $columns = $this->getTableColumns($pgTable);
        if (empty($columns)) {
            echo "Could not get columns for table: $pgTable\n";
            return;
        }

        // Insert data in batches
        $batchSize = 100;
        $batches = array_chunk($dataSets, $batchSize);

        foreach ($batches as $batch) {
            $this->insertBatch($pgTable, $columns, $batch);
        }
    }

    private function getTableColumns($table) {
        try {
            $stmt = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$table' ORDER BY ordinal_position");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            echo "Error getting columns for $table: " . $e->getMessage() . "\n";
            return [];
        }
    }

    private function insertBatch($table, $columns, $dataSets) {
        if (empty($dataSets) || empty($columns)) return;

        // Prepare INSERT statement
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        $columnList = implode(',', $columns);
        $sql = "INSERT INTO $table ($columnList) VALUES ($placeholders)";

        try {
            $stmt = $this->db->prepare($sql);

            foreach ($dataSets as $data) {
                // Ensure data array matches column count
                $data = array_pad($data, count($columns), null);

                // Convert data types as needed
                $data = $this->convertDataTypes($table, $columns, $data);

                try {
                    $stmt->execute($data);
                } catch (Exception $e) {
                    echo "Error inserting into $table: " . $e->getMessage() . "\n";
                    echo "Data: " . json_encode($data) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "Error preparing statement for $table: " . $e->getMessage() . "\n";
        }
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
    $database = new Database();
    $db = $database->getConnection();

    $importer = new MySQLToPostgreSQLImporter($db);
    $importer->import();

} catch (Exception $e) {
    echo "Import failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>