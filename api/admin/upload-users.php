<?php
/**
 * CSV Upload API for Bulk User Import
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/models/User.php';
require_once __DIR__ . '/../../includes/models/Student.php';
require_once __DIR__ . '/../../includes/models/Teacher.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Email.php';

// Handle CORS
CORS::handle();

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Check authentication and role
    Auth::hasRole('admin');

    // Check if file uploaded
    if (!isset($_FILES['csv_file'])) {
        Response::error('No file uploaded', 400);
    }

    $file = $_FILES['csv_file'];

    // Validate file type
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileType !== 'csv') {
        Response::error('Only CSV files are allowed', 400);
    }

    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        Response::error('File size exceeds 5MB limit', 400);
    }

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Initialize models
    $userModel = new User($db);
    $studentModel = new Student($db);
    $teacherModel = new Teacher($db);

    // Parse CSV
    $csvData = array_map('str_getcsv', file($file['tmp_name']));
    $headers = array_shift($csvData); // Remove header row

    $inserted = 0;
    $failed = 0;
    $errors = [];

    $db->beginTransaction();

    try {
        foreach ($csvData as $index => $row) {
            $lineNumber = $index + 2; // +2 because of header and 0-index

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Map CSV columns
            $userData = [
                'full_name' => trim($row[0] ?? ''),
                'email' => trim($row[1] ?? ''),
                'password' => trim($row[2] ?? ''),
                'role' => trim($row[3] ?? ''),
                'identifier' => trim($row[4] ?? ''),
                'department_id' => trim($row[5] ?? ''),
                'semester' => trim($row[6] ?? ''),
                'section' => trim($row[7] ?? ''),
                'is_active' => true
            ];

            // Validate required fields
            if (empty($userData['full_name']) || empty($userData['email']) || 
                empty($userData['password']) || empty($userData['role'])) {
                $errors[] = "Line $lineNumber: Missing required fields";
                $failed++;
                continue;
            }

            // Validate email
            if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Line $lineNumber: Invalid email format";
                $failed++;
                continue;
            }

            // Check if email exists
            if ($userModel->emailExists($userData['email'])) {
                $errors[] = "Line $lineNumber: Email already exists";
                $failed++;
                continue;
            }

            // Validate role
            if (!in_array($userData['role'], ['admin', 'teacher', 'student'])) {
                $errors[] = "Line $lineNumber: Invalid role";
                $failed++;
                continue;
            }

            // Create user
            $userModel->full_name = $userData['full_name'];
            $userModel->email = $userData['email'];
            $userModel->password_hash = $userData['password'];
            $userModel->role = $userData['role'];
            $userModel->is_active = true;

            if (!$userModel->create()) {
                $errors[] = "Line $lineNumber: Failed to create user";
                $failed++;
                continue;
            }

            $userId = $userModel->id;

            // Create role-specific profile
            if ($userData['role'] === 'student') {
                if (empty($userData['identifier']) || empty($userData['department_id']) || 
                    empty($userData['semester'])) {
                    $errors[] = "Line $lineNumber: Missing student details";
                    $failed++;
                    continue;
                }

                $studentModel->user_id = $userId;
                $studentModel->roll_number = $userData['identifier'];
                $studentModel->department_id = $userData['department_id'];
                $studentModel->semester = $userData['semester'];
                $studentModel->section = $userData['section'];
                $studentModel->batch_year = date('Y');
                $studentModel->admission_date = date('Y-m-d');

                if (!$studentModel->create()) {
                    $errors[] = "Line $lineNumber: Failed to create student profile";
                    $failed++;
                    continue;
                }

            } elseif ($userData['role'] === 'teacher') {
                if (empty($userData['identifier'])) {
                    $errors[] = "Line $lineNumber: Missing employee ID";
                    $failed++;
                    continue;
                }

                $teacherModel->user_id = $userId;
                $teacherModel->employee_id = $userData['identifier'];
                $teacherModel->primary_department_id = !empty($userData['department_id']) ? 
                    $userData['department_id'] : null;
                $teacherModel->joining_date = date('Y-m-d');

                if (!$teacherModel->create()) {
                    $errors[] = "Line $lineNumber: Failed to create teacher profile";
                    $failed++;
                    continue;
                }
            }

            // Send password email to newly created user
            Email::sendUserPasswordEmail(
                $userData['email'],
                $userData['full_name'],
                $userData['password'],
                $userData['role']
            );

            $inserted++;
        }

        $db->commit();

        Response::success([
            'inserted' => $inserted,
            'failed' => $failed,
            'errors' => $errors
        ], "$inserted users uploaded successfully" . ($failed > 0 ? ", $failed failed" : ''));

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    Response::error('Upload failed: ' . $e->getMessage(), 500);
}
?>
