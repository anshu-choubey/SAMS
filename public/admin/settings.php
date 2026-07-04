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
    
    $editableKeys = [
        'attendance_multi_check_enabled', 'attendance_default_total_checks',
        'attendance_min_check_interval', 'attendance_max_check_interval',
        'attendance_check_window_minutes', 'attendance_hide_timing_from_students',
        'face_confidence_threshold', 'liveness_detection_enabled', 'gps_proximity_radius',
    ];
    
    if ($key && isset($_POST['setting_value']) && in_array($key, $editableKeys)) {
        try {
            // Get setting metadata — try both column name patterns
            $setting = null;
            $checkQueries = [
                "SELECT `type`, `validation_rule` FROM system_settings WHERE `key` = :key LIMIT 1",
                "SELECT setting_type AS `type`, NULL AS `validation_rule` FROM system_settings WHERE setting_key = :key LIMIT 1"
            ];
            foreach ($checkQueries as $cq) {
                try {
                    $stmt = $db->prepare($cq);
                    $stmt->bindParam(':key', $key);
                    $stmt->execute();
                    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($setting) break;
                } catch (Exception $e) { continue; }
            }
            
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
                    $updated = false;
                    $updateQueries = [
                        "UPDATE system_settings SET `value` = :value, `updated_at` = NOW() WHERE `key` = :key",
                        "UPDATE system_settings SET setting_value = :value WHERE setting_key = :key"
                    ];
                    foreach ($updateQueries as $uq) {
                        try {
                            $updateStmt = $db->prepare($uq);
                            $updateStmt->bindParam(':key', $key);
                            $updateStmt->bindParam(':value', $validValue);
                            $updateStmt->execute();
                            if ($updateStmt->rowCount() > 0) { $updated = true; break; }
                        } catch (Exception $e) { continue; }
                    }
                    
                    if ($updated) {
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

// Only show these settings in the admin panel
$allowedSettings = [
    'attendance_multi_check_enabled',
    'attendance_default_total_checks',
    'attendance_min_check_interval',
    'attendance_max_check_interval',
    'attendance_check_window_minutes',
    'attendance_hide_timing_from_students',
    'face_confidence_threshold',
    'liveness_detection_enabled',
    'gps_proximity_radius',
];

// Labels and descriptions for each setting
$settingLabels = [
    'attendance_multi_check_enabled'       => ['label' => 'Multi-Check Attendance',        'desc' => 'Require multiple check-ins during a class for attendance to count'],
    'attendance_default_total_checks'      => ['label' => 'Checks Per Class',              'desc' => 'Number of check-ins required per class session'],
    'attendance_min_check_interval'        => ['label' => 'Min Interval (minutes)',         'desc' => 'Minimum time between attendance checks'],
    'attendance_max_check_interval'        => ['label' => 'Max Interval (minutes)',         'desc' => 'Maximum time between attendance checks'],
    'attendance_check_window_minutes'      => ['label' => 'Response Window (min)',          'desc' => 'Minutes a student has to respond to each check'],
    'attendance_hide_timing_from_students' => ['label' => 'Hide Check Timing',             'desc' => 'Students won\'t see when the next check is coming'],
    'face_confidence_threshold'            => ['label' => 'Face Match Threshold (%)',       'desc' => 'Minimum face recognition confidence to accept (0-100)'],
    'liveness_detection_enabled'           => ['label' => 'Liveness Detection',            'desc' => 'Require blink/head turn to prevent photo bypass'],
    'gps_proximity_radius'                 => ['label' => 'GPS Radius (meters)',            'desc' => 'Maximum distance from teacher for valid check-in'],
];

if ($db) {
    try {
        $placeholders = implode(',', array_fill(0, count($allowedSettings), '?'));
        $readQueries = [
            "SELECT `key`, `value`, `type`, `description`, `category`, `validation_rule` FROM system_settings WHERE `key` IN ($placeholders) ORDER BY `key`",
            "SELECT setting_key AS `key`, setting_value AS `value`, setting_type AS `type`, '' AS `description`, 'Attendance' AS `category`, NULL AS `validation_rule` FROM system_settings WHERE setting_key IN ($placeholders) ORDER BY setting_key"
        ];
        $allSettings = [];
        foreach ($readQueries as $rq) {
            try {
                $stmt = $db->prepare($rq);
                $stmt->execute($allowedSettings);
                $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($allSettings)) break;
            } catch (Exception $e) { continue; }
        }
        
        // Add any missing settings with defaults
        $foundKeys = array_column($allSettings, 'key');
        $defaults = [
            'attendance_multi_check_enabled'       => ['value' => 'true',  'type' => 'boolean'],
            'attendance_default_total_checks'      => ['value' => '2',     'type' => 'integer'],
            'attendance_min_check_interval'        => ['value' => '10',    'type' => 'integer'],
            'attendance_max_check_interval'        => ['value' => '25',    'type' => 'integer'],
            'attendance_check_window_minutes'      => ['value' => '3',     'type' => 'integer'],
            'attendance_hide_timing_from_students'  => ['value' => 'true', 'type' => 'boolean'],
            'face_confidence_threshold'            => ['value' => '60',    'type' => 'integer'],
            'liveness_detection_enabled'           => ['value' => 'true',  'type' => 'boolean'],
            'gps_proximity_radius'                 => ['value' => '50',    'type' => 'integer'],
        ];
        foreach ($allowedSettings as $key) {
            if (!in_array($key, $foundKeys) && isset($defaults[$key])) {
                $allSettings[] = [
                    'key' => $key,
                    'value' => $defaults[$key]['value'],
                    'type' => $defaults[$key]['type'],
                    'description' => $settingLabels[$key]['desc'] ?? '',
                    'category' => 'Attendance',
                    'validation_rule' => null
                ];
            }
        }
        
        // Sort to match allowedSettings order
        usort($allSettings, function($a, $b) use ($allowedSettings) {
            return array_search($a['key'], $allowedSettings) - array_search($b['key'], $allowedSettings);
        });

        foreach ($allSettings as $setting) {
            $value = $setting['value'];
            if ($setting['type'] === 'integer') $value = intval($value);
            elseif ($setting['type'] === 'boolean') $value = ($value === '1' || strtolower($value) === 'true');
            $settingsArray[$setting['key']] = $value;
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$pageTitle = 'Settings';

function getSettingIcon($key) {
    $icons = [
        'attendance_multi_check_enabled'       => 'check2-all',
        'attendance_default_total_checks'      => 'hash',
        'attendance_min_check_interval'        => 'hourglass-bottom',
        'attendance_max_check_interval'        => 'hourglass-top',
        'attendance_check_window_minutes'      => 'clock',
        'attendance_hide_timing_from_students' => 'eye-slash',
        'face_confidence_threshold'            => 'percent',
        'liveness_detection_enabled'           => 'person-bounding-box',
        'gps_proximity_radius'                 => 'geo-alt',
    ];
    return $icons[$key] ?? 'gear';
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
                <h2><i class="bi bi-gear"></i> Attendance Settings</h2>
                <p>Configure attendance checks, face verification, and GPS</p>
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
            
            <!-- Settings Grid -->
            <div class="settings-grid" id="settingsGrid">
                <?php
                if (empty($allSettings)): ?>
                    <div style="padding: 40px; text-align: center; grid-column: 1 / -1;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #cbd5e0; margin-bottom: 20px; display: block;"></i>
                        <h3 style="color: #718096;">No Settings Found</h3>
                        <p style="color: #a0aec0;">Please check your database connection.</p>
                    </div>
                <?php else:
                foreach ($allSettings as $setting):
                    $value = $setting['value'];
                    if ($setting['type'] === 'integer') {
                        $value = intval($value);
                    } elseif ($setting['type'] === 'boolean') {
                        $value = ($value === '1' || strtolower($value) === 'true');
                    }
                    
                    $icon = getSettingIcon($setting['key']);
                    $label = $settingLabels[$setting['key']]['label'] ?? $setting['key'];
                    $desc  = $settingLabels[$setting['key']]['desc']  ?? ($setting['description'] ?? '');
                    $iconColor = $setting['type'] === 'boolean' ? 'var(--success)' : 'var(--info)';
                    $bgColor   = $setting['type'] === 'boolean' ? 'rgba(72, 187, 120, 0.1)' : 'rgba(66, 153, 225, 0.1)';
                ?>
                <div class="setting-card">
                    <div class="setting-header">
                        <div class="setting-icon" style="background: <?php echo $bgColor; ?>; color: <?php echo $iconColor; ?>;">
                            <i class="bi bi-<?php echo $icon; ?>"></i>
                        </div>
                        <div class="setting-header-text">
                            <h3 class="setting-name"><?php echo htmlspecialchars($label); ?></h3>
                        </div>
                    </div>
                    
                    <p class="setting-description"><?php echo htmlspecialchars($desc); ?></p>
                    
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
                                <option value="1" <?php echo ($value === true || $value === 1) ? 'selected' : ''; ?>>Enabled</option>
                                <option value="0" <?php echo ($value === false || $value === 0) ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        <?php else: ?>
                            <input type="number" name="setting_value" value="<?php echo $value; ?>" step="1" min="1" required>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn-update">
                            <i class="bi bi-save"></i> Save
                        </button>
                    </form>
                </div>
                <?php
                endforeach;
                endif;
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => { alert.classList.remove('show'); }, 5000);
        });
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
