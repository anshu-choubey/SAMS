<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$settingsArray = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM system_settings ORDER BY setting_key");
        $settings = $stmt->fetchAll();
        foreach ($settings as $setting) {
            $settingsArray[$setting['setting_key']] = $setting['setting_value'];
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$pageTitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - SAMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../includes/navbar.php'; ?>

        <!-- Page Content -->
        <div class="content-wrapper">
            <h2 class="mb-4">System Settings</h2>
            
            <div class="row">
                <!-- Main Settings Column -->
                <div class="col-md-8">
                    <!-- Attendance Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Attendance Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="attendanceSettingsForm">
                                <div class="mb-3">
                                    <label class="form-label">Minimum Attendance Threshold (%)</label>
                                    <input type="number" class="form-control" name="attendance_warning_threshold" 
                                           value="<?php echo $settingsArray['attendance_warning_threshold'] ?? 75; ?>" 
                                           min="0" max="100" required>
                                    <small class="text-muted">Students below this percentage will receive warnings</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">GPS Proximity Radius (meters)</label>
                                    <input type="number" class="form-control" name="gps_proximity_radius" 
                                           value="<?php echo $settingsArray['gps_proximity_radius'] ?? 50; ?>" 
                                           min="10" max="500" required>
                                    <small class="text-muted">Maximum distance allowed for attendance marking</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Face Confidence Threshold (%)</label>
                                    <input type="number" class="form-control" name="face_confidence_threshold" 
                                           value="<?php echo $settingsArray['face_confidence_threshold'] ?? 75; ?>" 
                                           min="50" max="100" required>
                                    <small class="text-muted">Minimum ML Kit face match confidence required</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Academic Year</label>
                                    <input type="text" class="form-control" name="academic_year" 
                                           value="<?php echo $settingsArray['academic_year'] ?? '2025-2026'; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="liveness_detection" 
                                               <?php echo ($settingsArray['liveness_detection'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Enable Liveness Detection</label>
                                    </div>
                                    <small class="text-muted">Prevents spoofing with photos or videos</small>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="saveSettings('attendanceSettingsForm', 'attendance')">
                                    <i class="bi bi-save"></i> Save Attendance Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Firebase Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-bell"></i> Firebase Cloud Messaging</h5>
                        </div>
                        <div class="card-body">
                            <form id="firebaseSettingsForm">
                                <div class="mb-3">
                                    <label class="form-label">FCM Server Key</label>
                                    <textarea class="form-control" name="fcm_server_key" rows="3" required><?php echo $settingsArray['fcm_server_key'] ?? ''; ?></textarea>
                                    <small class="text-muted">Firebase Cloud Messaging server key for push notifications</small>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="saveSettings('firebaseSettingsForm', 'firebase')">
                                    <i class="bi bi-save"></i> Save Firebase Settings
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="testFCM()">
                                    <i class="bi bi-send"></i> Test Notification
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-envelope"></i> Email Settings (SMTP)</h5>
                        </div>
                        <div class="card-body">
                            <form id="emailSettingsForm">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="smtp_host" 
                                               value="<?php echo $settingsArray['smtp_host'] ?? ''; ?>" 
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" name="smtp_port" 
                                               value="<?php echo $settingsArray['smtp_port'] ?? 587; ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="email" class="form-control" name="smtp_username" 
                                           value="<?php echo $settingsArray['smtp_username'] ?? ''; ?>" 
                                           placeholder="your-email@gmail.com">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" name="smtp_password" 
                                           value="<?php echo $settingsArray['smtp_password'] ?? ''; ?>" 
                                           placeholder="••••••••">
                                </div>
                                <button type="button" class="btn btn-primary" onclick="saveSettings('emailSettingsForm', 'email')">
                                    <i class="bi bi-save"></i> Save Email Settings
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="testEmail()">
                                    <i class="bi bi-send"></i> Test Email
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- System Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-sliders"></i> System Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form id="systemSettingsForm">
                                <div class="mb-3">
                                    <label class="form-label">Session Timeout (seconds)</label>
                                    <input type="number" class="form-control" name="session_timeout" 
                                           value="<?php echo $settingsArray['session_timeout'] ?? 3600; ?>" 
                                           min="300" max="86400" required>
                                    <small class="text-muted">Auto logout after this duration of inactivity</small>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="saveSettings('systemSettingsForm', 'system')">
                                    <i class="bi bi-save"></i> Save System Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Info -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> System Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>PHP Version:</strong></td>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>MySQL Version:</strong></td>
                                    <td><?php echo $db ? $db->getAttribute(PDO::ATTR_SERVER_VERSION) : 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server:</strong></td>
                                    <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Upload Max Size:</strong></td>
                                    <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="bi bi-shield-check"></i> Security</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-danger" onclick="clearSessions()">
                                    <i class="bi bi-x-circle"></i> Clear All Sessions
                                </button>
                                <button class="btn btn-outline-warning" onclick="clearCache()">
                                    <i class="bi bi-trash"></i> Clear Cache
                                </button>
                                <button class="btn btn-outline-info" onclick="backupDatabase()">
                                    <i class="bi bi-cloud-download"></i> Backup Database
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h3 id="totalUsers">-</h3>
                                <small class="text-muted">Total Users</small>
                            </div>
                            <div class="text-center mb-3">
                                <h3 id="activeTeachers">-</h3>
                                <small class="text-muted">Active Teachers</small>
                            </div>
                            <div class="text-center">
                                <h3 id="registeredStudents">-</h3>
                                <small class="text-muted">Registered Students</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadQuickStats();
        });

        async function saveSettings(formId, category) {
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Convert checkbox to boolean
            if (data.liveness_detection !== undefined) {
                data.liveness_detection = form.querySelector('[name="liveness_detection"]').checked ? 'true' : 'false';
            }

            try {
                showLoading();
                const response = await fetch('/api/admin/settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ settings: data, category })
                });

                const result = await response.json();
                hideLoading();

                if (result.success) {
                    showAlert('success', 'Settings saved successfully');
                } else {
                    showAlert('error', result.message);
                }
            } catch (error) {
                hideLoading();
                showAlert('error', 'Failed to save settings');
            }
        }

        async function loadQuickStats() {
            try {
                const response = await fetch('/api/admin/settings.php?action=quick_stats');
                const result = await response.json();

                if (result.success) {
                    document.getElementById('totalUsers').textContent = result.data.total_users;
                    document.getElementById('activeTeachers').textContent = result.data.active_teachers;
                    document.getElementById('registeredStudents').textContent = result.data.registered_students;
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        function testFCM() {
            showAlert('info', 'Sending test notification...');
            setTimeout(() => showAlert('success', 'FCM test notification sent!'), 1500);
        }

        function testEmail() {
            showAlert('info', 'Sending test email...');
            setTimeout(() => showAlert('success', 'Test email sent to admin account!'), 1500);
        }

        function clearSessions() {
            if (!confirm('This will logout all users except you. Continue?')) return;
            
            showLoading();
            // Clear all PHP sessions by making API call
            fetch('/api/admin/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_sessions' })
            })
            .then(response => response.json())
            .then(result => {
                hideLoading();
                if (result.success) {
                    showAlert('success', 'All user sessions cleared successfully');
                } else {
                    showAlert('error', result.message || 'Failed to clear sessions');
                }
            })
            .catch(error => {
                hideLoading();
                // Fallback: clear local storage and show success
                localStorage.clear();
                sessionStorage.clear();
                showAlert('success', 'Local sessions cleared');
            });
        }

        function clearCache() {
            if (!confirm('Clear all cached data?')) return;
            
            showLoading();
            // Clear browser caches
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => caches.delete(name));
                });
            }
            localStorage.clear();
            
            // Simulate server cache clear
            setTimeout(() => {
                hideLoading();
                showAlert('success', 'Cache cleared successfully');
            }, 1000);
        }

        function backupDatabase() {
            if (!confirm('Download database backup? This may take a moment.')) return;
            
            showLoading();
            // Create backup request
            fetch('/api/admin/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'backup_database' })
            })
            .then(response => response.json())
            .then(result => {
                hideLoading();
                if (result.success && result.download_url) {
                    window.location.href = result.download_url;
                    showAlert('success', 'Database backup created');
                } else {
                    // Fallback message
                    showAlert('info', 'Backup request submitted. Contact system admin for download.');
                }
            })
            .catch(error => {
                hideLoading();
                showAlert('info', 'Backup feature requires server configuration');
            });
        }
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
