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
$allSettings = [];
$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_setting'])) {
    $key = $_POST['setting_key'] ?? '';
    $value = $_POST['setting_value'] ?? '';
    
    if ($key && isset($_POST['setting_value'])) {
        try {
            // Get setting metadata
            $checkQuery = "SELECT `type`, `validation_rule` FROM system_settings WHERE `key` = :key LIMIT 1";
            $stmt = $db->prepare($checkQuery);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($setting) {
                $type = $setting['type'];
                $rule = $setting['validation_rule'];
                $validValue = $value;
                
                // Validate and cast value
                if ($type === 'integer') {
                    if (!is_numeric($value) || intval($value) != $value) {
                        $errorMessage = "Value must be an integer";
                    } else {
                        $validValue = intval($value);
                    }
                } elseif ($type === 'float') {
                    if (!is_numeric($value)) {
                        $errorMessage = "Value must be numeric";
                    } else {
                        $validValue = floatval($value);
                    }
                } elseif ($type === 'boolean') {
                    $validValue = in_array(strtolower($value), ['true', '1', 'yes', 'on']) ? '1' : '0';
                }
                
                // Apply validation rules
                if (!$errorMessage && $rule) {
                    $parts = explode('|', $rule);
                    foreach ($parts as $condition) {
                        if (strpos($condition, 'min:') === 0) {
                            $min = intval(substr($condition, 4));
                            if ($validValue < $min) {
                                $errorMessage = "Value must be at least $min";
                                break;
                            }
                        } elseif (strpos($condition, 'max:') === 0) {
                            $max = intval(substr($condition, 4));
                            if ($validValue > $max) {
                                $errorMessage = "Value must be at most $max";
                                break;
                            }
                        }
                    }
                }
                
                // Update if no errors
                if (!$errorMessage) {
                    $updateQuery = "UPDATE system_settings SET `value` = :value, `updated_at` = NOW() WHERE `key` = :key";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':key', $key);
                    $updateStmt->bindParam(':value', $validValue);
                    
                    if ($updateStmt->execute()) {
                        $successMessage = "✓ Setting '" . htmlspecialchars($key) . "' updated successfully!";
                    } else {
                        $errorMessage = "Failed to update setting";
                    }
                }
            }
        } catch (Exception $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }
    }
}

if ($db) {
    try {
        // Fetch all settings
        $stmt = $db->query("SELECT `key`, `value`, `type`, `description`, `category`, `validation_rule` FROM system_settings ORDER BY `category`, `key`");
        $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also keep the old array format for backward compatibility
        foreach ($allSettings as $setting) {
            $value = $setting['value'];
            if ($setting['type'] === 'integer') {
                $value = intval($value);
            } elseif ($setting['type'] === 'float') {
                $value = floatval($value);
            } elseif ($setting['type'] === 'boolean') {
                $value = ($value === '1' || strtolower($value) === 'true');
            }
            $settingsArray[$setting['key']] = $value;
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>⚙️ System Settings</h2>
                <span class="badge bg-success">Live Updates</span>
            </div>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <?php
                    // Group settings by category
                    $settingsByCategory = [];
                    foreach ($allSettings as $setting) {
                        $category = $setting['category'] ?? 'General';
                        if (!isset($settingsByCategory[$category])) {
                            $settingsByCategory[$category] = [];
                        }
                        $settingsByCategory[$category][] = $setting;
                    }
                    
                    // Display each category
                    foreach ($settingsByCategory as $category => $settings):
                        $categoryIcon = match($category) {
                            'Attendance' => 'bi-calendar-check',
                            'Face Recognition' => 'bi-face-recognition',
                            'System' => 'bi-gear',
                            'Security' => 'bi-shield-lock',
                            default => 'bi-gear'
                        };
                    ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi <?php echo $categoryIcon; ?>"></i> <?php echo htmlspecialchars($category); ?> Settings</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($settings as $setting): 
                                $value = $setting['value'];
                                // Cast for display
                                if ($setting['type'] === 'integer') {
                                    $value = intval($value);
                                } elseif ($setting['type'] === 'float') {
                                    $value = floatval($value);
                                } elseif ($setting['type'] === 'boolean') {
                                    $value = ($value === '1' || strtolower($value) === 'true');
                                }
                            ?>
                            <form method="POST" class="mb-4 pb-3 border-bottom">
                                <input type="hidden" name="update_setting" value="1">
                                <input type="hidden" name="setting_key" value="<?php echo htmlspecialchars($setting['key']); ?>">
                                
                                <div class="mb-2">
                                    <label class="form-label fw-bold">
                                        <?php echo htmlspecialchars($setting['key']); ?>
                                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($setting['type']); ?></span>
                                    </label>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted d-block">
                                        <?php echo htmlspecialchars($setting['description'] ?? 'No description'); ?>
                                    </small>
                                </div>
                                
                                <div class="input-group input-group-sm mb-3">
                                    <?php if ($setting['type'] === 'boolean'): ?>
                                        <select name="setting_value" class="form-select" required>
                                            <option value="1" <?php echo ($value === true || $value === 1) ? 'selected' : ''; ?>>Enabled</option>
                                            <option value="0" <?php echo ($value === false || $value === 0) ? 'selected' : ''; ?>>Disabled</option>
                                        </select>
                                    <?php elseif ($setting['type'] === 'integer'): ?>
                                        <input type="number" name="setting_value" class="form-control" value="<?php echo $value; ?>" step="1" required>
                                    <?php elseif ($setting['type'] === 'float'): ?>
                                        <input type="number" name="setting_value" class="form-control" value="<?php echo $value; ?>" step="0.01" required>
                                    <?php else: ?>
                                        <input type="text" name="setting_value" class="form-control" value="<?php echo htmlspecialchars($value); ?>" required>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-save"></i> Save
                                    </button>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <strong>Current:</strong> 
                                        <?php 
                                            if ($setting['type'] === 'boolean') {
                                                echo ($value === true || $value === 1) ? 'Enabled' : 'Disabled';
                                            } else {
                                                echo htmlspecialchars($value);
                                            }
                                        ?>
                                    </small>
                                    <?php if ($setting['validation_rule']): ?>
                                        <small class="text-muted">
                                            <strong>Constraints:</strong> <?php echo htmlspecialchars($setting['validation_rule']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Sidebar Info -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Settings Info</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">
                                <strong>About System Settings:</strong><br>
                                All settings are stored in the database and can be managed from this admin panel. Changes take effect immediately across the application.
                            </p>
                            
                            <h6 class="mt-4 mb-2">Key Settings:</h6>
                            <ul class="small">
                                <li><strong>Min Attendance Threshold:</strong> Warning level for students</li>
                                <li><strong>GPS Radius:</strong> Maximum distance for attendance marking</li>
                                <li><strong>Face Confidence:</strong> Minimum ML Kit confidence required</li>
                                <li><strong>Liveness Detection:</strong> Enable/disable anti-spoofing</li>
                                <li><strong>Academic Year:</strong> Current academic year</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-gear"></i> System Information</h5>
                        </div>
                        <div class="card-body small">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>PHP Version:</strong></td>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server:</strong></td>
                                    <td><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Upload Max:</strong></td>
                                    <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.remove('show');
                    alert.addEventListener('transitionend', function() {
                        alert.style.display = 'none';
                    });
                }, 5000);
            });
        });
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
