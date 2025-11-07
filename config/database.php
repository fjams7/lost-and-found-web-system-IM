<?php
/**
 * Database Configuration and Connection
 * Lost&Found Hub System
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lostfound_hub');
define('DB_USER', 'root');
define('DB_PASS', '');

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn;

    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            return null;
        }

        return $this->conn;
    }

    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
}

/**
 * Get a new database connection
 * @return PDO|null
 */
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Start session if not already started
 */
function startSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $conn = getDBConnection();
    if (!$conn) return null;

    try {
        $stmt = $conn->prepare("SELECT id, username, email, full_name, phone FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>