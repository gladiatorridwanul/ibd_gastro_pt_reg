<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/audit.php';

// Log the logout if user was logged in
if (isset($_SESSION['user_id'])) {
    // Log activity
    if (function_exists('logActivity')) {
        logActivity($_SESSION['user_id'], 'auth.logout', null, null, null);
    }
    
    // Record session end time if function exists
    if (function_exists('recordUserSession')) {
        recordUserSession($_SESSION['user_id']);
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login with logout message
header('Location: ?r=login&loggedout=1');
exit;