<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    Auth::hasRole('admin');

    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("DELETE FROM notifications WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
