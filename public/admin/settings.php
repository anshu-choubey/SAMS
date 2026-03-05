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
        
        // Group settings by category
        $settingsByCategory = [];
        foreach ($allSettings as $setting) {
            $category = $setting['category'] ?? 'General';
            if (!isset($settingsByCategory[$category])) {
                $settingsByCategory[$category] = [];
            }
            $settingsByCategory[$category][] = $setting;
        }
        
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

// Helper function to get setting icon
function getSettingIcon($category) {
    $icons = [
        'Attendance' => 'calendar-check',
        'Face Recognition' => 'eye',
        'System' => 'gear',
        'Security' => 'shield-lock',
        'General' => 'sliders'
    ];
    return $icons[$category] ?? 'gear';
}

// Helper function to get setting description
function getSettingDescription($key) {
    $descriptions = [
        'min_attendance_threshold' => 'Students will receive warnings if their attendance falls below this percentage',
        'gps_proximity_radius' => 'Maximum distance (in meters) a student can be from the teacher to mark attendance',
        'face_confidence_threshold' => 'Minimum confidence level (0-100%) for face recognition to accept a match',
        'enable_liveness_detection' => 'Enable anti-spoofing: prevents using photos or videos to bypass face recognition',
        'academic_year' => 'Current academic year for the institution',
        'semester_duration_weeks' => 'Duration of each semester in weeks'
    ];
    return $descriptions[$key] ?? '';
}
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
    <style>
        :root {
            --primary: #667eea;
            --success: #48bb78;
            --warning: #f6ad55;
            --danger: #f56565;
            --info: #4299e1;
        }
        
        body {
            background: #f7fafc;
        }
        
        .content-wrapper {
            padding: 30px;
        }
        
        .page-header {
            margin-bottom: 40px;
        }
        
        .page-header h2 {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: #718096;
            font-size: 15px;
        }
        
        .category-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .category-tab {
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-weight: 600;
            color: #4a5568;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .category-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .category-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }
        
        .setting-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .setting-card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .setting-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .setting-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .setting-header-text {
            flex: 1;
        }
        
        .setting-name {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }
        
        .setting-type {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 4px;
            background: #edf2f7;
            color: #4a5568;
        }
        
        .setting-description {
            font-size: 14px;
            color: #4a5568;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        
        .setting-value-group {
            flex: 1;
            margin-bottom: 16px;
        }
        
        .setting-value-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #718096;
            margin-bottom: 8px;
            display: block;
        }
        
        .setting-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            padding: 12px;
            background: #f7fafc;
            border-radius: 8px;
            word-break: break-all;
        }
        
        .setting-value.boolean-true {
            color: var(--success);
            background: #f0fff4;
        }
        
        .setting-value.boolean-false {
            color: var(--danger);
            background: #fff5f5;
        }
        
        .setting-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        input[type="number"],
        input[type="text"],
        select {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }
        
        input[type="number"]:focus,
        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-update {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .btn-update:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-update:active {
            transform: translateY(0);
        }
        
        .setting-validation {
            font-size: 12px;
            color: #718096;
            margin-top: 8px;
            display: block;
        }
        
        .range-slider {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .range-slider input[type="range"] {
            flex: 1;
            height: 6px;
            border-radius: 3px;
            background: #cbd5e0;
            outline: none;
            -webkit-appearance: none;
        }
        
        .range-slider input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
        }
        
        .range-slider input[type="range"]::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
        }
        
        .range-value {
            min-width: 45px;
            text-align: right;
            font-weight: 700;
            color: var(--primary);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 24px;
        }
        
        .alert-success {
            background: #f0fff4;
            color: #22543d;
        }
        
        .alert-danger {
            background: #fff5f5;
            color: #742a2a;
        }
        
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .setting-form {
                flex-direction: column;
            }
            
            input[type="number"],
            input[type="text"],
            select,
            .btn-update {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../includes/navbar.php'; ?>

        <!-- Page Content -->
        <div class="content-wrapper">
            <div class="page-header">
                <h2><i class="bi bi-gear"></i> System Settings</h2>
                <p>Configure application behavior and parameters</p>
            </div>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Category Tabs -->
            <div class="category-tabs">
                <div class="category-tab active" data-category="all">
                    <i class="bi bi-kanban"></i> All Settings
                </div>
                <?php 
                foreach ($settingsByCategory as $category => $settings) {
                    $icon = getSettingIcon($category);
                    echo '<div class="category-tab" data-category="' . htmlspecialchars(strtolower(str_replace(' ', '-', $category))) . '">';
                    echo '<i class="bi bi-' . $icon . '"></i> ' . htmlspecialchars($category);
                    echo '</div>';
                }
                ?>
            </div>
            
            <!-- Settings Grid -->
            <div class="settings-grid" id="settingsGrid">
                <?php
                if (empty($settingsByCategory)): ?>
                    <div style="padding: 40px; text-align: center; grid-column: 1 / -1;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #cbd5e0; margin-bottom: 20px; display: block;"></i>
                        <h3 style="color: #718096;">No Settings Found</h3>
                        <p style="color: #a0aec0;">Please check your database connection.</p>
                    </div>
                <?php else:
                foreach ($settingsByCategory as $category => $settings):
                    $categoryId = strtolower(str_replace(' ', '-', $category));
                    foreach ($settings as $setting):
                        $value = $setting['value'];
                        // Cast for display
                        if ($setting['type'] === 'integer') {
                            $value = intval($value);
                        } elseif ($setting['type'] === 'float') {
                            $value = floatval($value);
                        } elseif ($setting['type'] === 'boolean') {
                            $value = ($value === '1' || strtolower($value) === 'true');
                        }
                        
                        $icon = getSettingIcon($category);
                        $iconColor = match($setting['type']) {
                            'boolean' => 'var(--success)',
                            'integer' => 'var(--info)',
                            'float' => 'var(--warning)',
                            default => 'var(--primary)'
                        };
                        $bgColor = match($setting['type']) {
                            'boolean' => 'rgba(72, 187, 120, 0.1)',
                            'integer' => 'rgba(66, 153, 225, 0.1)',
                            'float' => 'rgba(246, 173, 85, 0.1)',
                            default => 'rgba(102, 126, 234, 0.1)'
                        };
                ?>
                <div class="setting-card" data-category="<?php echo $categoryId; ?>">
                    <div class="setting-header">
                        <div class="setting-icon" style="background: <?php echo $bgColor; ?>; color: <?php echo $iconColor; ?>;">
                            <i class="bi bi-<?php echo $icon; ?>"></i>
                        </div>
                        <div class="setting-header-text">
                            <h3 class="setting-name"><?php echo htmlspecialchars($setting['key']); ?></h3>
                            <span class="setting-type"><?php echo htmlspecialchars($setting['type']); ?></span>
                        </div>
                    </div>
                    
                    <p class="setting-description">
                        <?php echo htmlspecialchars(getSettingDescription($setting['key']) ?: ($setting['description'] ?? 'No description')); ?>
                    </p>
                    
                    <div class="setting-value-group">
                        <span class="setting-value-label">Current Value</span>
                        <div class="setting-value <?php echo ($setting['type'] === 'boolean' && ($value === true || $value === 1) ? 'boolean-true' : ($setting['type'] === 'boolean' ? 'boolean-false' : '')); ?>">
                            <?php 
                                if ($setting['type'] === 'boolean') {
                                    echo ($value === true || $value === 1) ? '<i class="bi bi-check-circle"></i> Enabled' : '<i class="bi bi-x-circle"></i> Disabled';
                                } else {
                                    echo htmlspecialchars($value);
                                }
                            ?>
                        </div>
                    </div>
                    
                    <form method="POST" class="setting-form">
                        <input type="hidden" name="update_setting" value="1">
                        <input type="hidden" name="setting_key" value="<?php echo htmlspecialchars($setting['key']); ?>">
                        
                        <?php if ($setting['type'] === 'boolean'): ?>
                            <select name="setting_value" class="form-select form-select-sm" required style="flex: 1;">
                                <option value="1" <?php echo ($value === true || $value === 1) ? 'selected' : ''; ?>>
                                    <i class="bi bi-check-circle"></i> Enabled
                                </option>
                                <option value="0" <?php echo ($value === false || $value === 0) ? 'selected' : ''; ?>>
                                    <i class="bi bi-x-circle"></i> Disabled
                                </option>
                            </select>
                        <?php elseif ($setting['type'] === 'integer'): ?>
                            <input type="number" name="setting_value" value="<?php echo $value; ?>" step="1" required>
                        <?php elseif ($setting['type'] === 'float'): ?>
                            <input type="number" name="setting_value" value="<?php echo $value; ?>" step="0.01" required>
                        <?php else: ?>
                            <input type="text" name="setting_value" value="<?php echo htmlspecialchars($value); ?>" required>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn-update">
                            <i class="bi bi-save"></i> Save
                        </button>
                    </form>
                    
                    <?php if ($setting['validation_rule']): ?>
                        <span class="setting-validation">
                            <i class="bi bi-info-circle"></i> Constraints: <?php echo htmlspecialchars($setting['validation_rule']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php
                    endforeach;
                endforeach;
                endif;
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Category tab filtering
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const selectedCategory = this.getAttribute('data-category');
                const allTabs = document.querySelectorAll('.category-tab');
                const allCards = document.querySelectorAll('.setting-card');
                
                // Update active tab
                allTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Filter cards
                allCards.forEach(card => {
                    if (selectedCategory === 'all') {
                        card.style.display = '';
                    } else {
                        const cardCategory = card.getAttribute('data-category');
                        card.style.display = cardCategory === selectedCategory ? '' : 'none';
                    }
                });
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.classList.remove('show');
            }, 5000);
        });
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
