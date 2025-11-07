<?php
/**
 * Test Upload Diagnostics
 * This script helps diagnose upload issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Upload Diagnostics</h2>";

// Check PHP configuration
echo "<h3>PHP Configuration:</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'enabled' : 'disabled') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: 'default') . "<br>";

// Check directory permissions
echo "<h3>Directory Permissions:</h3>";
$uploadDir = __DIR__ . '/uploads/items/';
echo "Upload directory: " . $uploadDir . "<br>";
echo "Directory exists: " . (file_exists($uploadDir) ? 'YES' : 'NO') . "<br>";
echo "Directory is writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "<br>";
echo "Directory permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br>";

// Check parent directory
$parentDir = __DIR__ . '/uploads/';
echo "Parent directory: " . $parentDir . "<br>";
echo "Parent is writable: " . (is_writable($parentDir) ? 'YES' : 'NO') . "<br>";
echo "Parent permissions: " . substr(sprintf('%o', fileperms($parentDir)), -4) . "<br>";

// Test file creation
echo "<h3>Write Test:</h3>";
$testFile = $uploadDir . 'test_' . time() . '.txt';
$writeResult = @file_put_contents($testFile, 'test content');
if ($writeResult !== false) {
    echo "✓ Successfully wrote test file<br>";
    echo "Test file: " . $testFile . "<br>";
    @unlink($testFile);
    echo "✓ Successfully deleted test file<br>";
} else {
    echo "✗ FAILED to write test file<br>";
    echo "Error: " . error_get_last()['message'] . "<br>";
}

// Check GD library for image processing
echo "<h3>Image Processing:</h3>";
if (extension_loaded('gd')) {
    echo "✓ GD library is loaded<br>";
    $gdInfo = gd_info();
    echo "GD Version: " . $gdInfo['GD Version'] . "<br>";
    echo "JPEG Support: " . ($gdInfo['JPEG Support'] ? 'YES' : 'NO') . "<br>";
    echo "PNG Support: " . ($gdInfo['PNG Support'] ? 'YES' : 'NO') . "<br>";
    echo "GIF Support: " . ($gdInfo['GIF Read Support'] && $gdInfo['GIF Create Support'] ? 'YES' : 'NO') . "<br>";
    echo "WebP Support: " . ($gdInfo['WebP Support'] ?? 'NO') . "<br>";
} else {
    echo "✗ GD library is NOT loaded<br>";
}

// Test upload if file is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
    echo "<h3>Upload Test Result:</h3>";
    
    $file = $_FILES['test_image'];
    echo "File name: " . $file['name'] . "<br>";
    echo "File size: " . $file['size'] . " bytes<br>";
    echo "File type: " . $file['type'] . "<br>";
    echo "Tmp name: " . $file['tmp_name'] . "<br>";
    echo "Error code: " . $file['error'] . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "✓ File uploaded successfully to temp location<br>";
        
        // Try to move file
        $destination = $uploadDir . 'test_upload_' . time() . '_' . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            echo "✓ File moved successfully to: " . $destination . "<br>";
            echo "File size on disk: " . filesize($destination) . " bytes<br>";
            
            // Try to read as image
            $imageInfo = @getimagesize($destination);
            if ($imageInfo) {
                echo "✓ Valid image file<br>";
                echo "Image dimensions: " . $imageInfo[0] . "x" . $imageInfo[1] . "<br>";
                echo "Image type: " . $imageInfo['mime'] . "<br>";
            } else {
                echo "✗ Not a valid image or cannot read<br>";
            }
            
            // Clean up
            @unlink($destination);
        } else {
            echo "✗ FAILED to move uploaded file<br>";
            echo "Destination: " . $destination . "<br>";
            echo "Last error: " . print_r(error_get_last(), true) . "<br>";
        }
    } else {
        echo "✗ Upload error: ";
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                echo "File exceeds upload_max_filesize";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                echo "File exceeds MAX_FILE_SIZE";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "File was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                echo "Missing temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                echo "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                echo "A PHP extension stopped the upload";
                break;
            default:
                echo "Unknown error";
        }
        echo "<br>";
    }
}
?>

<h3>Test Upload:</h3>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_image" accept="image/*" required>
    <button type="submit">Test Upload</button>
</form>

<p><a href="index.html">Back to Home</a></p>