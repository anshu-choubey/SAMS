<?php
/**
 * Migration: Add semester and is_active columns to subjects table
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Adding semester and is_active columns to subjects table...\n";

    // Add semester column
    $query1 = "ALTER TABLE subjects ADD COLUMN IF NOT EXISTS semester INT";
    $stmt1 = $db->prepare($query1);
    $stmt1->execute();

    // Add is_active column
    $query2 = "ALTER TABLE subjects ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE";
    $stmt2 = $db->prepare($query2);
    $stmt2->execute();

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>