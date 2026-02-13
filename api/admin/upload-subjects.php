<?php
/**
 * CSV Upload API for Bulk Subject Import
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/Subject.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    Auth::hasRole('admin');

    if (!isset($_FILES['csv_file'])) {
        Response::error('No file uploaded', 400);
    }

    $file = $_FILES['csv_file'];
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($fileType !== 'csv') {
        Response::error('Only CSV files are allowed', 400);
    }

    $database = new Database();
    $db = $database->getConnection();
    $subjectModel = new Subject($db);

    $csvData = array_map('str_getcsv', file($file['tmp_name']));
    array_shift($csvData); // Remove header

    $inserted = 0;
    $failed = 0;
    $errors = [];

    $db->beginTransaction();

    try {
        foreach ($csvData as $index => $row) {
            $lineNumber = $index + 2;

            if (empty(array_filter($row))) continue;

            $subjectData = [
                'name' => trim($row[0] ?? ''),
                'code' => trim($row[1] ?? ''),
                'department_id' => trim($row[2] ?? ''),
                'semester' => trim($row[3] ?? ''),
                'credits' => trim($row[4] ?? 3),
                'description' => trim($row[5] ?? '')
            ];

            if (empty($subjectData['name']) || empty($subjectData['code']) || 
                empty($subjectData['department_id']) || empty($subjectData['semester'])) {
                $errors[] = "Line $lineNumber: Missing required fields";
                $failed++;
                continue;
            }

            if ($subjectModel->codeExists($subjectData['code'])) {
                $errors[] = "Line $lineNumber: Subject code already exists";
                $failed++;
                continue;
            }

            $subjectModel->name = $subjectData['name'];
            $subjectModel->code = $subjectData['code'];
            $subjectModel->department_id = $subjectData['department_id'];
            $subjectModel->semester = $subjectData['semester'];
            $subjectModel->credits = $subjectData['credits'];
            $subjectModel->description = $subjectData['description'];
            $subjectModel->is_active = true;

            if (!$subjectModel->create()) {
                $errors[] = "Line $lineNumber: Failed to create subject";
                $failed++;
                continue;
            }

            $inserted++;
        }

        $db->commit();

        Response::success([
            'inserted' => $inserted,
            'failed' => $failed,
            'errors' => $errors
        ], "$inserted subjects uploaded successfully" . ($failed > 0 ? ", $failed failed" : ''));

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    Response::error('Upload failed: ' . $e->getMessage(), 500);
}
?>
