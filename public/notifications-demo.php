<?php
/**
 * Notifications Demo Page - Testing & Access without Login
 * This is a simplified version for testing notification UI
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SAMS Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0066cc;
            --success: #28a745;
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
        
        .notification-item {
            border-left: 4px solid var(--primary);
            padding: 16px;
            margin-bottom: 12px;
            background: white;
            border-radius: 8px;
        }
        
        .badge-success {
            background: var(--success);
        }
        
        .demo-banner {
            background: #e3f2fd;
            border-left: 4px solid var(--primary);
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-lg">
            <a class="navbar-brand" href="/">
                <i class="bi bi-bell"></i> SAMS Notifications
            </a>
            <span class="badge badge-primary ms-auto">Demo Mode</span>
        </div>
    </nav>

    <div class="container-lg mt-4">
        <!-- Demo Banner -->
        <div class="demo-banner">
            <i class="bi bi-info-circle"></i>
            <strong>Demo Mode</strong> - This is a preview of the notification system. 
            <a href="/public/admin/notifications.php">Login to admin panel</a> for full functionality.
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-value">12</div>
                    <div class="stat-label">Notifications Sent</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card" style="border-left-color: var(--success);">
                    <div class="stat-value" style="color: var(--success);">8</div>
                    <div class="stat-label">Read</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card" style="border-left-color: #ff8800;">
                    <div class="stat-value" style="color: #ff8800;">4</div>
                    <div class="stat-label">Unread</div>
                </div>
            </div>
        </div>

        <!-- Sample Notifications -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-bell"></i> Sample Notifications</h5>
            </div>
            <div class="card-body">
                <!-- Low Attendance Alert -->
                <div class="notification-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">⚠️ Low Attendance Alert</h6>
                            <p class="mb-2 text-muted small">Your attendance is at 70%. Minimum required: 75%. Please attend upcoming classes.</p>
                            <small class="text-muted">Sent: Today at 2:45 PM</small>
                        </div>
                        <span class="badge badge-success">Read</span>
                    </div>
                </div>

                <!-- Perfect Attendance -->
                <div class="notification-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">🏆 Perfect Attendance</h6>
                            <p class="mb-2 text-muted small">Congratulations! You've maintained 100% attendance this semester. Keep it up!</p>
                            <small class="text-muted">Sent: Yesterday at 10:30 AM</small>
                        </div>
                        <span class="badge badge-success">Read</span>
                    </div>
                </div>

                <!-- Schedule Reminder -->
                <div class="notification-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">📚 Schedule Reminder</h6>
                            <p class="mb-2 text-muted small">You have Mathematics class scheduled tomorrow at 10:00 AM in Room 201. Be on time!</p>
                            <small class="text-muted">Sent: 2 days ago</small>
                        </div>
                        <span class="badge" style="background: #ffc107; color: #333;">Unread</span>
                    </div>
                </div>

                <!-- Performance Praise -->
                <div class="notification-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">⭐ Performance Praise</h6>
                            <p class="mb-2 text-muted small">You've been one of the most consistent attendees! Your dedication is truly appreciated.</p>
                            <small class="text-muted">Sent: 3 days ago</small>
                        </div>
                        <span class="badge badge-success">Read</span>
                    </div>
                </div>

                <!-- Absence Notification -->
                <div class="notification-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">❌ Absent Today</h6>
                            <p class="mb-2 text-muted small">You were marked absent in Computer Science today. Use the app to register for makeup session if available.</p>
                            <small class="text-muted">Sent: 5 days ago</small>
                        </div>
                        <span class="badge badge-success">Read</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Preview -->
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Notification Settings (Android App)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Notification Types</h6>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> Low Attendance Alerts</li>
                            <li><i class="bi bi-check-circle text-success"></i> Perfect Attendance</li>
                            <li><i class="bi bi-check-circle text-success"></i> Schedule Reminders</li>
                            <li><i class="bi bi-check-circle text-success"></i> Performance Praise</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Notification Settings</h6>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> Sound Enabled</li>
                            <li><i class="bi bi-check-circle text-success"></i> Vibration Enabled</li>
                            <li><i class="bi bi-check-circle text-success"></i> Show Preview</li>
                        </ul>
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <strong>ℹ️ Note:</strong> Customize these settings in the Android app's "Notification Settings" screen.
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card mt-4 mb-5">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-book"></i> How to Use Notifications</h5>
            </div>
            <div class="card-body">
                <ol>
                    <li><strong>Admin Dashboard:</strong> Go to <code>/admin/notifications.php</code> to send personalized notifications</li>
                    <li><strong>Android App:</strong> Open Settings → Notification Preferences to customize what you receive</li>
                    <li><strong>Types Available:</strong>
                        <ul>
                            <li>Low Attendance Alerts</li>
                            <li>Perfect Attendance Achievement</li>
                            <li>Absence Notifications</li>
                            <li>Schedule Reminders</li>
                            <li>Performance Praise</li>
                            <li>Custom Messages</li>
                        </ul>
                    </li>
                    <li><strong>Delivery:</strong> Notifications are sent via FCM (Firebase Cloud Messaging)</li>
                </ol>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
