<?php
/**
 * File Upload Helper
 */

class FileUpload {
    /**
     * Upload image file
     */
    public static function uploadImage($file, $destination, $allowedTypes = ALLOWED_IMAGE_TYPES) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File size exceeds maximum limit'];
        }

        // Check file type
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $targetPath = $destination . $filename;

        // Create directory if not exists
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $targetPath
            ];
        }

        return ['success' => false, 'message' => 'Failed to upload file'];
    }

    /**
     * Delete file
     */
    public static function deleteFile($filePath) {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}
?>
