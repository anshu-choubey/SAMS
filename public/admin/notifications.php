<?php
/**
 * Admin Notifications Management Page
 * Send and manage personalized notifications to students/teachers
 */

session_start();

// Check if admin is logged in
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get statistics - safely
$stats = [
    'total_notifications' => 0,
    'sent_count' => 0,
    'read_count' => 0,
    'admins_count' => 0
];

$recentNotifications = [];

// Try to get stats if notifications table exists
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
                $stats = $result;
            }
            
            // Get recent notifications
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
    } catch (Exception $e) {
        // Table doesn't exist or there's an error, continue with empty data
    }
}

// Get users for dropdown
$users = [];
if ($db) {
    try {
        $usersQuery = "SELECT id, full_name, role FROM users ORDER BY full_name";
        $stmt = $db->prepare($usersQuery);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Error getting users
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
    <style>
        :root {
            --primary: #0066cc;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        body {
            background: #f5f7fa;
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, #0052a3 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            border-radius: 12px;
        }
        
        .stat-card {
            text-align: center;
            padding: 24px;
            border-left: 4px solid var(--primary);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        
        .btn-primary:hover {
            background: #0052a3;
        }
        
        .badge-sent {
            background: #28a745;
        }
        
        .badge-unsent {
            background: #6c757d;
        }
        
        .badge-read {
            background: #0066cc;
        }
        
        .badge-unread {
            background: #ffc107;
        }
        
        .notification-type-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .type-low_attendance {
            background: #ffe5e5;
            color: #cc0000;
        }
        
        .type-perfect_attendance {
            background: #e5ffe5;
            color: #00cc00;
        }
        
        .type-schedule_reminder {
            background: #e5f2ff;
            color: #0066cc;
        }
        
        .type-performance_praise {
            background: #fff0e5;
            color: #ff8800;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-lg">
            <a class="navbar-brand" href="/admin/index.php">
                <i class="bi bi-graph-up"></i> SAMS Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/admin/notifications.php">
                            <i class="bi bi-bell"></i> Notifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-lg mt-4 mb-5">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h1><i class="bi bi-bell-fill"></i> Notifications Management</h1>
                <p class="text-muted">Send personalized notifications to students and teachers</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="stat-value"><?php echo $stats['total_notifications'] ?? 0; ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card" style="border-left-color: var(--success);">
                    <div class="stat-value" style="color: var(--success);"><?php echo $stats['sent_count'] ?? 0; ?></div>
                    <div class="stat-label">Sent</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card" style="border-left-color: #0066cc;">
                    <div class="stat-value" style="color: #0066cc;"><?php echo $stats['read_count'] ?? 0; ?></div>
                    <div class="stat-label">Read</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card" style="border-left-color: var(--warning);">
                    <div class="stat-value" style="color: #ff8800;"><?php echo $stats['admins_count'] ?? 0; ?></div>
                    <div class="stat-label">Admins Sent</div>
                </div>
            </div>
        </div>

        <!-- Send Notification Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Send Personalized Notification</h5>
            </div>
            <div class="card-body">
                <form id="notificationForm" onsubmit="sendNotification(event)">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Notification Type *</label>
                            <select class="form-select" id="notificationType" required onchange="updateMessagePreview()">
                                <option value="">Select notification type</option>
                                <option value="low_attendance">Low Attendance Alert</option>
                                <option value="perfect_attendance">Perfect Attendance</option>
                                <option value="absent_today">Absent Today</option>
                                <option value="schedule_reminder">Schedule Reminder</option>
                                <option value="performance_praise">Performance Praise</option>
                                <option value="custom">Custom Message</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target User *</label>
                            <select class="form-select" id="targetUser" required>
                                <option value="">Select user</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['role']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Title (Optional)</label>
                            <input type="text" class="form-control" id="customTitle" placeholder="Leave empty for auto-generated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Message (Optional)</label>
                            <input type="text" class="form-control" id="customMessage" placeholder="Leave empty for auto-generated">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Additional Data (JSON)</label>
                        <textarea class="form-control" id="customData" rows="3" placeholder='{"subject_name": "Mathematics", "class_time": "10:00 AM"}'></textarea>
                        <small class="text-muted">Optional: Add additional data for the notification</small>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> Send Notification
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Notifications -->
        <?php if (!empty($recentNotifications)): ?>
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Recent Notifications</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Target User</th>
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
                                    <td>
                                        <span class="notification-type-badge type-<?php echo htmlspecialchars($notif['notification_type']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($notif['notification_type']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($notif['target_name'] ?? 'All Users'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($notif['title'], 0, 50)); ?></td>
                                    <td><?php echo htmlspecialchars($notif['creator_name'] ?? 'System'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $notif['is_sent'] ? 'badge-sent' : 'badge-unsent'; ?>">
                                            <?php echo $notif['is_sent'] ? 'Sent' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $notif['is_read'] ? 'badge-read' : 'badge-unread'; ?>">
                                            <?php echo $notif['is_read'] ? 'Read' : 'Unread'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d, H:i', strtotime($notif['created_at'])); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-3">No notifications sent yet. Use the form above to send your first notification!</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateMessagePreview() {
            const type = document.getElementById('notificationType').value;
            const messages = {
                'low_attendance': 'Hi [Name], your attendance is at [%]. Minimum required: 75%. Please attend upcoming classes.',
                'perfect_attendance': 'Congratulations [Name]! You\'ve maintained 100% attendance. Keep it up!',
                'absent_today': 'Hi [Name], you were marked absent today. Use the app to register for makeup session if available.',
                'schedule_reminder': 'Hi [Name], you have [Subject] class scheduled tomorrow at [Time]. Be on time!',
                'performance_praise': 'Hey [Name], you\'ve been one of the most consistent attendees! Your dedication is appreciated.',
                'custom': ''
            };
            
            if (messages[type]) {
                document.getElementById('customMessage').placeholder = messages[type];
            }
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
                        target_user_id: parseInt(targetUserId),
                        title: customTitle || undefined,
                        message: customMessage || undefined,
                        data: Object.keys(customData).length > 0 ? customData : undefined
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert('✓ Notification sent successfully to ' + result.target_user);
                    document.getElementById('notificationForm').reset();
                    location.reload();
                } else {
                    alert('✗ Error: ' + (result.message || 'Failed to send notification'));
                }
            } catch (error) {
                alert('✗ Error: ' + error.message);
            }
        }
    </script>
</body>
</html>

