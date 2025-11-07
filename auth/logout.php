<?php
/**
 * User Logout Handler
 * Lost&Found Hub System
 */

require_once '../config/database.php';

startSession();

// Destroy all session data
session_unset();
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to home page
header('Location: ../index.html');
exit;
?>