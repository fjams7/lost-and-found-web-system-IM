<?php
/**
 * User Registration Handler
 * Lost&Found Hub System
 */

require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and sanitize input data
$username = sanitizeInput($_POST['username'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$fullName = sanitizeInput($_POST['full_name'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');

// Validation
$errors = [];

if (empty($username)) {
    $errors[] = 'Username is required';
} elseif (strlen($username) < 3) {
    $errors[] = 'Username must be at least 3 characters long';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if (empty($password)) {
    $errors[] = 'Password is required';
} elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters long';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match';
}

if (empty($fullName)) {
    $errors[] = 'Full name is required';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Database operations
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit;
    }

    // Hash password and insert user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, phone) VALUES (?, ?, ?, ?, ?)");
    $result = $stmt->execute([$username, $email, $passwordHash, $fullName, $phone]);

    if ($result) {
        // Start session and log user in
        startSession();
        $_SESSION['user_id'] = $conn->lastInsertId();
        $_SESSION['username'] = $username;

        echo json_encode([
            'success' => true, 
            'message' => 'Registration successful! Welcome to Lost&Found Hub.',
            'redirect' => '../index.html'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }

} catch(PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    
    // Check for duplicate entry error
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
}
?>