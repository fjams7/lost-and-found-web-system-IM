<?php
/**
 * Items API Handler
 * Lost&Found Hub System
 */

require_once '../config/database.php';
require_once 'upload-handler.php';
require_once 'email-sender.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getItems();
        } elseif ($action === 'user_items') {
            getUserItems();
        } elseif ($action === 'get') {
            getItem();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
    
    case 'POST':
        if ($action === 'create') {
            createItem();
        } elseif ($action === 'contact') {
            contactPoster();
        } elseif ($action === 'update') {
            updateItem();
        } elseif ($action === 'resolve') {
            resolveItem();
        } elseif ($action === 'delete') {
            deleteItem();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

/**
 * Get all items with optional filtering
 */
function getItems() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    try {
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? 'active';

        $sql = "SELECT i.*, u.username, u.full_name 
                FROM items i 
                JOIN users u ON i.user_id = u.id 
                WHERE i.status = ?";
        $params = [$status];

        if (!empty($search)) {
            $sql .= " AND (i.title LIKE ? OR i.description LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($type)) {
            $sql .= " AND i.type = ?";
            $params[] = $type;
        }

        if (!empty($category)) {
            $sql .= " AND i.category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY i.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        echo json_encode(['success' => true, 'items' => $items]);

    } catch(PDOException $e) {
        error_log("Error getting items: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to fetch items']);
    }
}

/**
 * Get single item by ID
 */
function getItem() {
    $itemId = $_GET['id'] ?? '';
    
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
        $stmt = $conn->prepare("SELECT i.*, u.username, u.full_name FROM items i JOIN users u ON i.user_id = u.id WHERE i.id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if ($item) {
            echo json_encode(['success' => true, 'item' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
        }

    } catch(PDOException $e) {
        error_log("Error getting item: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to fetch item']);
    }
}

/**
 * Get items for current user
 */
function getUserItems() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $items = $stmt->fetchAll();

        echo json_encode(['success' => true, 'items' => $items]);

    } catch(PDOException $e) {
        error_log("Error getting user items: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to fetch items']);
    }
}

/**
 * Create new item
 */
function createItem() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        return;
    }

    $type = sanitizeInput($_POST['type'] ?? '');
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $location = sanitizeInput($_POST['location'] ?? '');
    $dateOccurred = sanitizeInput($_POST['date_occurred'] ?? '');

    // Validation
    $errors = [];
    if (empty($type) || !in_array($type, ['lost', 'found'])) {
        $errors[] = 'Valid type is required';
    }
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    if (empty($category)) {
        $errors[] = 'Category is required';
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        return;
    }

    // Handle image upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = handleImageUpload($_FILES['image']);
        if ($uploadResult['success']) {
            $imagePath = $uploadResult['path'];
        } else {
            echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
            return;
        }
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO items (user_id, type, title, description, category, location, date_occurred, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $type, $title, $description, $category, $location, $dateOccurred, $imagePath]);

        echo json_encode(['success' => true, 'message' => 'Item posted successfully']);

    } catch(PDOException $e) {
        error_log("Error creating item: " . $e->getMessage());
        // Delete uploaded image if database insert fails
        if ($imagePath) {
            deleteImage($imagePath);
        }
        echo json_encode(['success' => false, 'message' => 'Failed to create item']);
    }
}

/**
 * Update item
 */
function updateItem() {
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

        // Handle image upload if new image provided
        $imagePath = $item['image_path'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleImageUpload($_FILES['image']);
            if ($uploadResult['success']) {
                // Delete old image
                if ($imagePath) {
                    deleteImage($imagePath);
                }
                $imagePath = $uploadResult['path'];
            }
        }

        $stmt = $conn->prepare("UPDATE items SET title = ?, description = ?, category = ?, location = ?, date_occurred = ?, image_path = ? WHERE id = ?");
        $stmt->execute([$title, $description, $category, $location, $dateOccurred, $imagePath, $itemId]);

        echo json_encode(['success' => true, 'message' => 'Item updated successfully']);

    } catch(PDOException $e) {
        error_log("Error updating item: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update item']);
    }
}

/**
 * Mark item as resolved
 */
function resolveItem() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        return;
    }

    $itemId = sanitizeInput($_POST['id'] ?? '');

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
        $stmt = $conn->prepare("SELECT user_id FROM items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item || $item['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        $stmt = $conn->prepare("UPDATE items SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$itemId]);

        echo json_encode(['success' => true, 'message' => 'Item marked as resolved']);

    } catch(PDOException $e) {
        error_log("Error resolving item: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to resolve item']);
    }
}

/**
 * Delete item
 */
function deleteItem() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        return;
    }

    $itemId = sanitizeInput($_POST['id'] ?? '');

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

        // Delete image file
        if ($item['image_path']) {
            deleteImage($item['image_path']);
        }

        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$itemId]);

        echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);

    } catch(PDOException $e) {
        error_log("Error deleting item: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
    }
}

/**
 * Contact poster - Now with email notification
 */
function contactPoster() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        return;
    }

    $itemId = sanitizeInput($_POST['item_id'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $contactInfo = sanitizeInput($_POST['contact_info'] ?? '');

    if (empty($itemId) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Item ID and message are required']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    try {
        // Get item details and poster information
        $stmt = $conn->prepare("
            SELECT i.*, u.email as poster_email, u.full_name as poster_name 
            FROM items i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            return;
        }

        // Get requester information
        $stmt = $conn->prepare("SELECT username, email, full_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $requester = $stmt->fetch();

        // Save contact request to database
        $stmt = $conn->prepare("INSERT INTO contacts (item_id, requester_id, message, contact_info) VALUES (?, ?, ?, ?)");
        $stmt->execute([$itemId, $_SESSION['user_id'], $message, $contactInfo]);

        // Send email notification to poster
        $emailSender = new EmailSender();
        
        $posterData = [
            'email' => $item['poster_email'],
            'name' => $item['poster_name']
        ];
        
        $requesterData = [
            'name' => $requester['full_name'],
            'email' => $requester['email']
        ];
        
        $itemData = [
            'title' => $item['title'],
            'type' => $item['type']
        ];
        
        $emailResult = $emailSender->sendContactNotification(
            $posterData,
            $requesterData,
            $itemData,
            $message,
            $contactInfo
        );

        // Log email result but don't fail the request if email fails
        if (!$emailResult['success']) {
            error_log("Email notification failed: " . $emailResult['message']);
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Contact request sent successfully. The poster will be notified via email.'
        ]);

    } catch(PDOException $e) {
        error_log("Error sending contact: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to send contact request']);
    }
}
?>