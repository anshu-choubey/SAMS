-- Add allow_late_attendance setting
INSERT INTO system_settings (`key`, `value`, `type`, `description`, `category`, `validation_rule`, `created_at`, `updated_at`) 
VALUES ('allow_late_attendance', '1', 'boolean', 'Allow students to mark attendance after class start time', 'Attendance', '', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
