<?php
/**
 * Simple script to import departments data from MySQL dump
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Importing departments data...\n";

    // Clear existing departments
    $db->exec("DELETE FROM departments");

    // Insert departments data from the dump
    $departments = [
        [1, 'eeewsd', 'FSEW', 'efeewf', null, true, '2026-02-11 06:05:06', '2026-02-11 11:26:47'],
        [2, 'grgr', 'RRGRG', 'grr', null, true, '2026-02-13 10:33:44', '2026-02-13 10:33:44'],
        [3, 'dfdgf', 'FDGGFD', 'fdggf', null, true, '2026-02-13 10:33:53', '2026-02-13 10:33:53'],
        [4, 'fvfd', 'VFDFD', 'vfdf', null, true, '2026-02-13 10:36:09', '2026-02-13 10:36:09'],
        [5, 'rhthrt', 'GFDF', '', null, true, '2026-02-13 10:40:33', '2026-02-13 10:40:33']
    ];

    $stmt = $db->prepare("INSERT INTO departments (id, name, code, description, hod_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($departments as $dept) {
        $stmt->execute($dept);
        echo "Inserted department: {$dept[1]} ({$dept[2]})\n";
    }

    echo "\nDepartments import completed!\n";

    // Verify the import
    $stmt = $db->query("SELECT COUNT(*) as count FROM departments");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total departments: " . $result['count'] . "\n";

} catch (Exception $e) {
    echo "Import failed: " . $e->getMessage() . "\n";
}
?>