<?php
// Temporary test version without authentication
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$assignments = [];

if ($db) {
    try {
        // Get assignments
        $stmt = $db->query("SELECT ta.*, u.full_name, sub.name as subject_name
                           FROM teacher_assignments ta
                           LEFT JOIN teachers t ON ta.teacher_id = t.id
                           LEFT JOIN users u ON t.user_id = u.id
                           LEFT JOIN subjects sub ON ta.subject_id = sub.id
                           ORDER BY u.full_name");
        $assignments = $stmt->fetchAll();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

echo "<h1>Teacher Assignments Test</h1>";
echo "<table border='1'>";
echo "<tr><th>Teacher</th><th>Subject</th><th>Class</th></tr>";
foreach ($assignments as $assignment) {
    echo "<tr>";
    echo "<td>" . ($assignment['full_name'] ?? 'Unknown') . "</td>";
    echo "<td>" . $assignment['subject_name'] . "</td>";
    echo "<td>" . $assignment['class_name'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>