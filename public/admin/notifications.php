<?php
/**
 * Admin Notifications Management Page
 * Send and manage personalized notifications to students/teachers
 */

session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$stats = [
    'total_notifications' => 0,
    'sent_count' => 0,
    'read_count' => 0,
    'admins_count' => 0,
];

$recentNotifications = [];
$users = [];

if ($db) {
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'notifications'");
        if ($checkTable && $checkTable->rowCount() > 0) {
            $statsQuery = "SELECT
                COUNT(*) as total_notifications,
                SUM(CASE WHEN is_sent = TRUE THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN is_read = TRUE THEN 1 ELSE 0 END) as read_count,
                COUNT(DISTINCT created_by) as admins_count
            FROM notifications";

            $stmt = $db->prepare($statsQuery);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $stats = array_merge($stats, $result);
            }

            $recentQuery = "SELECT n.*, u.full_name as creator_name, tu.full_name as target_name
                FROM notifications n
                LEFT JOIN users u ON n.created_by = u.id
                LEFT JOIN users tu ON n.target_user_id = tu.id
                ORDER BY n.created_at DESC
                LIMIT 10";

            $stmt = $db->prepare($recentQuery);
            $stmt->execute();
            $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $usersQuery = "SELECT id, full_name, role FROM users ORDER BY full_name";
        $stmt = $db->prepare($usersQuery);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Keep empty fallback data if the database is partially unavailable.
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications Management - SAMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../includes/navbar.php'; ?>

        <div class="content-wrapper">
            <h2 class="mb-4"><i class="bi bi-bell-fill"></i> Notifications Management</h2>
            <p class="text-muted mb-4">Send personalized notifications to students and teachers</p>

            <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Send Personalized Notifications</strong> - Select a user and customize the message to send targeted notifications. All notifications are logged and tracked.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="card stat-card stat-primary"><div class="card-body"><div class="stat-value"><?php echo (int) $stats['total_notifications']; ?></div><div class="stat-label">Total Notifications</div></div></div></div>
                <div class="col-md-3"><div class="card stat-card stat-success"><div class="card-body"><div class="stat-value"><?php echo (int) $stats['sent_count']; ?></div><div class="stat-label">Sent</div></div></div></div>
                <div class="col-md-3"><div class="card stat-card stat-info"><div class="card-body"><div class="stat-value"><?php echo (int) $stats['read_count']; ?></div><div class="stat-label">Read</div></div></div></div>
                <div class="col-md-3"><div class="card stat-card stat-warning"><div class="card-body"><div class="stat-value"><?php echo (int) $stats['admins_count']; ?></div><div class="stat-label">Admins Sent</div></div></div></div>
            </div>

            <div class="card mb-4 border-primary border-2">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-send-check text-primary me-2"></i>Send Personalized Notification</h5>
                    <small class="text-muted d-block mt-1">Create and send custom notifications to individual students or teachers</small>
                </div>
                <div class="card-body">
                    <form id="notificationForm" onsubmit="sendNotification(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="notificationType" class="form-label"><strong>Notification Type</strong></label>
                                <select id="notificationType" class="form-select" required onchange="updateMessagePreview()">
                                    <option value="">-- Select Type --</option>
                                    <option value="low_attendance">Low Attendance Alert</option>
                                    <option value="perfect_attendance">Perfect Attendance</option>
                                    <option value="absent_today">Absence Alert</option>
                                    <option value="schedule_reminder">Schedule Reminder</option>
                                    <option value="performance_praise">Performance Praise</option>
                                    <option value="custom">Custom Message</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="targetUser" class="form-label"><strong>Send To</strong></label>
                                <select id="targetUser" class="form-select" required>
                                    <option value="">-- Select Recipient --</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo (int) $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']) . ' (' . htmlspecialchars($user['role']) . ')'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="customTitle" class="form-label"><strong>Title</strong></label>
                                <input type="text" id="customTitle" class="form-control" placeholder="e.g., Attendance Reminder">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="customMessage" class="form-label"><strong>Message</strong></label>
                                <textarea id="customMessage" class="form-control" rows="4" placeholder="Enter your personalized message here..."></textarea>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="customData" class="form-label"><strong>Additional Data (JSON)</strong></label>
                                <textarea id="customData" class="form-control" rows="2" placeholder='{"key": "value"}'></textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send me-2"></i>Send Notification</button>
                            <button type="reset" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-2"></i>Clear Form</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($recentNotifications)): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Notifications</h6>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Target</th>
                                <th>Title</th>
                                <th>Creator</th>
                                <th>Sent</th>
                                <th>Read</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentNotifications as $notif): ?>
                            <tr>
                                <td><span class="badge bg-info" style="font-size: 11px;"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($notif['notification_type']))); ?></span></td>
                                <td><?php echo htmlspecialchars($notif['target_name'] ?? 'All Users'); ?></td>
                                <td><?php echo htmlspecialchars(substr($notif['title'], 0, 50)); ?></td>
                                <td><?php echo htmlspecialchars($notif['creator_name'] ?? 'System'); ?></td>
                                <td><span class="badge bg-<?php echo !empty($notif['is_sent']) ? 'success' : 'secondary'; ?>"><?php echo !empty($notif['is_sent']) ? '✓ Sent' : 'Pending'; ?></span></td>
                                <td><span class="badge bg-<?php echo !empty($notif['is_read']) ? 'primary' : 'warning'; ?>"><?php echo !empty($notif['is_read']) ? '✓ Read' : 'Unread'; ?></span></td>
                                <td><small class="text-muted"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bell-slash" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="text-muted mt-3"><em>No notifications sent yet</em></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function updateMessagePreview() {
            const type = document.getElementById('notificationType').value;
            const messages = {
                low_attendance: 'Hi [Name], your attendance is at [%]. Minimum required: 75%. Please attend upcoming classes.',
                perfect_attendance: 'Congratulations [Name]! You\'ve maintained 100% attendance. Keep it up!',
                absent_today: 'Hi [Name], you were marked absent today. Use the app to register for makeup session if available.',
                schedule_reminder: 'Hi [Name], you have [Subject] class scheduled tomorrow at [Time]. Be on time!',
                performance_praise: 'Hey [Name], you\'ve been one of the most consistent attendees! Your dedication is appreciated.',
                custom: ''
            };

            document.getElementById('customMessage').placeholder = messages[type] || 'Enter your personalized message here...';
        }

        async function sendNotification(event) {
            event.preventDefault();

            const notifType = document.getElementById('notificationType').value;
            const targetUserId = document.getElementById('targetUser').value;
            const customTitle = document.getElementById('customTitle').value;
            const customMessage = document.getElementById('customMessage').value;
            let customData = {};

            try {
                const dataStr = document.getElementById('customData').value;
                if (dataStr) {
                    customData = JSON.parse(dataStr);
                }
            } catch (e) {
                alert('Invalid JSON in Additional Data field');
                return;
            }

            try {
                const response = await fetch('/api/notifications/send-personalized.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        notification_class: notifType,
                        target_user_id: parseInt(targetUserId, 10),
                        title: customTitle || undefined,
                        message: customMessage || undefined,
                        data: Object.keys(customData).length > 0 ? customData : undefined
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Notification sent successfully to ' + (result.target_user || 'recipient'));
                    document.getElementById('notificationForm').reset();
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to send notification'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>