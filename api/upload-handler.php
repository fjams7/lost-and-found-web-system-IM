<?php
/**
 * Image Upload Handler with Resizing
 * Lost&Found Hub System
 */

require_once '../config/database.php';

/**
 * Handle image upload with automatic resizing
 * @param array $file - $_FILES array element
 * @param int $maxWidth - Maximum width (default 800)
 * @param int $maxHeight - Maximum height (default 800)
 * @return array - ['success' => bool, 'path' => string, 'message' => string]
 */
function handleImageUpload($file, $maxWidth = 800, $maxHeight = 800) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed'];
    }

    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB'];
    }

    // Create upload directory if it doesn't exist
    $uploadDir = dirname(__DIR__) . '/uploads/items/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        // Determine extension from MIME type
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $extension = $mimeToExt[$fileType] ?? 'jpg';
    }
    
    $filename = uniqid('item_') . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;

    // Load and resize image
    try {
        $image = null;
        switch ($fileType) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($file['tmp_name']);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($file['tmp_name']);
                break;
        }

        if (!$image) {
            return ['success' => false, 'message' => 'Failed to process image'];
        }

        // Get original dimensions
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        
        // Only resize if image is larger than max dimensions
        if ($ratio < 1) {
            $newWidth = intval($originalWidth * $ratio);
            $newHeight = intval($originalHeight * $ratio);

            // Create new image with calculated dimensions
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG and GIF
            if ($fileType === 'image/png' || $fileType === 'image/gif') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Resize image
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            // Save resized image
            switch ($fileType) {
                case 'image/jpeg':
                case 'image/jpg':
                    imagejpeg($resizedImage, $uploadPath, 85);
                    break;
                case 'image/png':
                    imagepng($resizedImage, $uploadPath, 8);
                    break;
                case 'image/gif':
                    imagegif($resizedImage, $uploadPath);
                    break;
                case 'image/webp':
                    imagewebp($resizedImage, $uploadPath, 85);
                    break;
            }

            imagedestroy($resizedImage);
        } else {
            // Image is smaller than max dimensions, save original
            switch ($fileType) {
                case 'image/jpeg':
                case 'image/jpg':
                    imagejpeg($image, $uploadPath, 85);
                    break;
                case 'image/png':
                    imagepng($image, $uploadPath, 8);
                    break;
                case 'image/gif':
                    imagegif($image, $uploadPath);
                    break;
                case 'image/webp':
                    imagewebp($image, $uploadPath, 85);
                    break;
            }
        }

        imagedestroy($image);

        // Return relative path for database storage
        $relativePath = 'uploads/items/' . $filename;
        return ['success' => true, 'path' => $relativePath, 'message' => 'Image uploaded successfully'];

    } catch (Exception $e) {
        error_log("Image upload error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to process image: ' . $e->getMessage()];
    }
}

/**
 * Delete image file
 * @param string $imagePath - Relative path to image
 * @return bool
 */
function deleteImage($imagePath) {
    if (empty($imagePath)) {
        return true;
    }

    $fullPath = dirname(__DIR__) . '/' . $imagePath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    
    return true;
}
?>