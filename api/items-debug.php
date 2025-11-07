<?php
/**
 * Debug version of Items API Handler
 * Lost&Found Hub System
 */

require_once '../config/database.php';
require_once 'upload-handler.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/api_errors.log');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Log the request
error_log("API Request - Method: $method, Action: $action");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

if ($method === 'POST' && $action === 'update') {
    updateItemDebug();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

function updateItemDebug() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        return;
    }

    $itemId = sanitizeInput($_POST['id'] ?? '');
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $location = sanitizeInput($_POST['location'] ?? '');
    $dateOccurred = sanitizeInput($_POST['date_occurred'] ?? '');

    error_log("Update item - ID: $itemId, Title: $title");

    if (empty($itemId)) {
        echo json_encode(['success' => false, 'message' => 'Item ID required']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    try {
        // Check if user owns the item
        $stmt = $conn->prepare("SELECT user_id, image_path FROM items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item || $item['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        error_log("Item found, current image: " . ($item['image_path'] ?? 'none'));

        // Handle image upload if new image provided
        $imagePath = $item['image_path'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            error_log("New image uploaded, processing...");
            error_log("File details: " . print_r($_FILES['image'], true));
            
            $uploadResult = handleImageUpload($_FILES['image']);
            error_log("Upload result: " . print_r($uploadResult, true));
            
            if ($uploadResult['success']) {
                // Delete old image
                if ($imagePath) {
                    error_log("Deleting old image: $imagePath");
                    deleteImage($imagePath);
                }
                $imagePath = $uploadResult['path'];
                error_log("New image path: $imagePath");
            } else {
                error_log("Upload failed: " . $uploadResult['message']);
                echo json_encode(['success' => false, 'message' => 'Image upload failed: ' . $uploadResult['message']]);
                return;
            }
        } else {
            error_log("No new image or upload error: " . ($_FILES['image']['error'] ?? 'not set'));
        }

        $stmt = $conn->prepare("UPDATE items SET title = ?, description = ?, category = ?, location = ?, date_occurred = ?, image_path = ? WHERE id = ?");
        $result = $stmt->execute([$title, $description, $category, $location, $dateOccurred, $imagePath, $itemId]);

        error_log("Database update result: " . ($result ? 'success' : 'failed'));

        echo json_encode(['success' => true, 'message' => 'Item updated successfully']);

    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch(Exception $e) {
        error_log("General error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>