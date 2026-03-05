<?php
/**
 * System Settings API
 * Manages attendance, face recognition, and system settings
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/middleware/CORS.php';
require_once __DIR__ . '/../../includes/middleware/Auth.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';

CORS::handle();

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all settings or specific key
        $key = $_GET['key'] ?? null;
        
        if ($key) {
            // Get single setting
            $query = "SELECT `key`, `value`, `type`, `description` FROM system_settings WHERE `key` = :key LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$setting) {
                Response::error('Setting not found', 404);
            }
            
            // Cast value based on type
            Response::success(castSettingValue($setting));
        } else {
            // Get all settings organized by category
            $query = "SELECT `key`, `value`, `type`, `description`, `category` FROM system_settings ORDER BY `category`, `key`";
            $stmt = $db->query($query);
            $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organize by category
            $settingsByCategory = [];
            foreach ($allSettings as $setting) {
                $category = $setting['category'] ?? 'General';
                if (!isset($settingsByCategory[$category])) {
                    $settingsByCategory[$category] = [];
                }
                $settingsByCategory[$category][] = castSettingValue($setting);
            }
            
            Response::success(['settings' => $settingsByCategory]);
        }
    }

    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update setting - admin only
        Auth::hasRole('admin');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator();
        $validator->required('key', $data['key'] ?? '', 'Setting Key');
        $validator->required('value', $data['value'] ?? '', 'Setting Value');
        
        if ($validator->hasErrors()) {
            Response::validationError($validator->getErrors());
        }
        
        $key = $data['key'];
        $value = $data['value'];
        
        // Get current setting to validate type
        $checkQuery = "SELECT `type`, `validation_rule` FROM system_settings WHERE `key` = :key LIMIT 1";
        $stmt = $db->prepare($checkQuery);
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$setting) {
            Response::error("Setting '$key' does not exist", 404);
        }
        
        // Validate value based on type
        $type = $setting['type'];
        $rule = $setting['validation_rule'];
        
        if ($type === 'integer') {
            if (!is_numeric($value) || intval($value) != $value) {
                Response::error('Value must be an integer', 400);
            }
            $value = intval($value);
        } elseif ($type === 'float') {
            if (!is_numeric($value)) {
                Response::error('Value must be numeric', 400);
            }
            $value = floatval($value);
        } elseif ($type === 'boolean') {
            if (!in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no'])) {
                Response::error('Value must be true or false', 400);
            }
            $value = (strtolower($value) === 'true' || $value === 1 || $value === '1') ? '1' : '0';
        }
        
        // Apply validation rules
        if ($rule) {
            $parts = explode('|', $rule);
            foreach ($parts as $condition) {
                if (strpos($condition, 'min:') === 0) {
                    $min = intval(substr($condition, 4));
                    if ($value < $min) {
                        Response::error("Value must be at least $min", 400);
                    }
                } elseif (strpos($condition, 'max:') === 0) {
                    $max = intval(substr($condition, 4));
                    if ($value > $max) {
                        Response::error("Value must be at most $max", 400);
                    }
                }
            }
        }
        
        // Update setting
        $updateQuery = "UPDATE system_settings 
                       SET `value` = :value, `updated_at` = NOW()
                       WHERE `key` = :key";
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        
        if (!$stmt->execute()) {
            Response::error('Failed to update setting', 500);
        }
        
        Response::success([
            'key' => $key,
            'value' => $value,
            'type' => $type
        ], 'Setting updated successfully');
    }

    else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Cast setting value based on type
 */
function castSettingValue($setting) {
    $value = $setting['value'];
    $type = $setting['type'];
    
    if ($type === 'integer') {
        $value = intval($value);
    } elseif ($type === 'float') {
        $value = floatval($value);
    } elseif ($type === 'boolean') {
        $value = $value === '1' || strtolower($value) === 'true';
    }
    
    return [
        'key' => $setting['key'],
        'value' => $value,
        'type' => $type,
        'description' => $setting['description'] ?? null,
        'category' => $setting['category'] ?? 'General'
    ];
}
?>


        if (isset($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                // Use INSERT ... ON DUPLICATE KEY UPDATE for proper upsert
                $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                          VALUES (:key, :value, 'string')
                          ON DUPLICATE KEY UPDATE setting_value = :value2";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':value2', $value);
                $stmt->execute();
            }
            Response::success([], 'Settings updated successfully');
        }

        Response::error('Invalid request', 400);
    }

} catch (Exception $e) {
    Response::error('Settings operation failed: ' . $e->getMessage(), 500);
}

function getUserCount($db) {
    $query = "SELECT COUNT(*) as count FROM users WHERE is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getTeacherCount($db) {
    $query = "SELECT COUNT(*) as count FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getStudentCount($db) {
    $query = "SELECT COUNT(*) as count FROM students s JOIN users u ON s.user_id = u.id WHERE u.is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>
