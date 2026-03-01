<?php
/**
 * Verify Database Setup - Show counts of all tables
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    $result = [
        'database' => getenv('JAWSDB_DB') ?: 'sams_db',
        'environment' => getenv('JAWSDB_URL') ? 'Heroku/Production' : 'Local Development',
        'tables' => []
    ];
    
    $tables = ['users', 'departments', 'subjects', 'teachers', 'teacher_assignments', 'schedules', 'students', 'sessions'];
    
    foreach ($tables as $table) {
        try {
            $countQuery = $db->query("SELECT COUNT(*) as cnt FROM $table");
            if ($countQuery) {
                $row = $countQuery->fetch();
                $result['tables'][$table] = intval($row['cnt']);
            }
        } catch (Exception $e) {
            $result['tables'][$table] = 'Error: ' . $e->getMessage();
        }
    }
    
    // Get sample schedules
    $schedResult = $db->query("SELECT s.id, s.day_of_week, s.start_time, s.end_time, s.classroom, 
                                     ta.id as assignment_id, subj.name as subject, t.id as teacher_id
                              FROM schedules s
                              JOIN teacher_assignments ta ON s.assignment_id = ta.id
                              JOIN subjects subj ON ta.subject_id = subj.id
                              JOIN teachers t ON ta.teacher_id = t.id
                              LIMIT 5");
    
    $result['sample_schedules'] = [];
    if ($schedResult) {
        while ($sched = $schedResult->fetch()) {
            $result['sample_schedules'][] = $sched;
        }
    }
    
    http_response_code(200);
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
