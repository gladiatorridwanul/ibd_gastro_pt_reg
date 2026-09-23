<?php
/**
 * Authentication Functions for PMRMS
 * Handles all user authentication, session management, and login/logout functionality
 * 
 * @version 2.0.1
 */

// Prevent multiple inclusions - this is the key fix
if (defined('AUTH_INCLUDED')) {
    return; // Already included, exit silently
}
define('AUTH_INCLUDED', true);

// Enable error logging
error_log("auth.php loaded at " . date('Y-m-d H:i:s'));

// DO NOT START SESSION HERE - session should already be started in index.php

// Load config if not already loaded
if (!isset($config)) {
    $configPath = __DIR__ . '/../../config/config.php';
    if (!file_exists($configPath)) {
        $configPath = __DIR__ . '/../config/config.php';
    }
    if (!file_exists($configPath)) {
        $configPath = '/home/ibdgastroliverbd/public_html/config/config.php';
    }
    if (file_exists($configPath)) {
        $config = require $configPath;
        error_log("Config loaded from: " . $configPath);
    } else {
        error_log("CRITICAL: Configuration file not found in auth.php");
    }
}

// Make sure we have database connection
if (!isset($pdo) && file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
    error_log("db.php loaded from auth.php");
}

/**
 * Check if user is logged in
 * 
 * @return boolean
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id']) && !empty($_SESSION['user_id']));
    }
}

/**
 * Require login - redirect to login if not authenticated
 */
if (!function_exists('require_login')) {
    function require_login() {
        if (!isLoggedIn()) {
            error_log("require_login: User not logged in, redirecting to login");
            
            // Check if headers have already been sent
            if (!headers_sent()) {
                header('Location: ?r=login');
                exit;
            } else {
                // Headers already sent, use JavaScript redirect
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <title>Redirecting...</title>
                    <meta http-equiv="refresh" content="0;url=?r=login">
                </head>
                <body>
                    <script type="text/javascript">
                        window.location.href = "?r=login";
                    </script>
                    <p>Redirecting to login page...</p>
                </body>
                </html>';
                exit;
            }
        }
        return true;
    }
}

/**
 * Alias for require_login
 */
if (!function_exists('requireAuth')) {
    function requireAuth() {
        require_login();
    }
}

/**
 * Authenticate user by email and password
 * 
 * @param string $email User email
 * @param string $password User password
 * @return array|false User data array or false on failure
 */
if (!function_exists('authenticateUser')) {
    function authenticateUser($email, $password) {
        global $pdo;
        
        error_log("authenticateUser called for email: " . $email);
        
        if (!$pdo) {
            error_log("ERROR: Database connection not available in authenticateUser");
            return false;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                error_log("ERROR: User not found with email: " . $email);
                return false;
            }
            
            // Check if account is active
            if (isset($user['is_active']) && $user['is_active'] == 0) {
                error_log("ERROR: User account is inactive for email: " . $email);
                return false;
            }
            
            // Determine password field
            $passwordField = 'password_hash';
            if (isset($user['password'])) {
                $passwordField = 'password';
            } elseif (isset($user['pass'])) {
                $passwordField = 'pass';
            }
            
            if (password_verify($password, $user[$passwordField])) {
                error_log("SUCCESS: Password verification successful for: " . $email);
                unset($user[$passwordField]);
                return $user;
            }
            
            error_log("FAILED: Password verification failed for: " . $email);
            return false;
            
        } catch (PDOException $e) {
            error_log("EXCEPTION in authenticateUser: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Login function
 * 
 * @param string $email User email
 * @param string $password User password
 * @return boolean
 */
if (!function_exists('login')) {
    function login($email, $password) {
        $user = authenticateUser($email, $password);
        
        if ($user) {
            // Ensure session is active
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role_type'] ?? 'User';
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            error_log("Login successful for user ID: " . $user['id']);
            return true;
        }
        
        return false;
    }
}

/**
 * Get current logged in user
 * 
 * @return array|false User data or false if not logged in
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        global $pdo;
        
        if (!isLoggedIn()) {
            return false;
        }
        
        if (!$pdo) {
            error_log("Database connection not available in getCurrentUser");
            return false;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, role_type, designation, degree, phone, is_active FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                return $user;
            }
            
            // If user not found, clear session
            unset($_SESSION['user_id']);
            return false;
            
        } catch (PDOException $e) {
            error_log("Get current user error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Alias for getCurrentUser
 */
if (!function_exists('user')) {
    function user() {
        return getCurrentUser();
    }
}

/**
 * Logout user
 */
if (!function_exists('logout')) {
    function logout() {
        $user_id = $_SESSION['user_id'] ?? null;
        
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
        
        error_log("Logout completed for user ID: " . ($user_id ?? 'unknown'));
        return true;
    }
}

/**
 * Get user by ID
 * 
 * @param int $userId User ID
 * @return array|false
 */
if (!function_exists('getUserById')) {
    function getUserById($userId) {
        global $pdo;
        
        if (!$pdo) {
            return false;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, role_type, designation, degree, phone, is_active FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get all users
 * 
 * @return array
 */
if (!function_exists('getAllUsers')) {
    function getAllUsers() {
        global $pdo;
        
        if (!$pdo) {
            return [];
        }
        
        try {
            $stmt = $pdo->query("SELECT id, name, email, role_type, designation, degree, phone, is_active, created_at FROM users ORDER BY id DESC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get all active sessions
 * 
 * @param int $minutes Number of minutes to consider active (default 15)
 * @return array Array of active sessions with user details
 */
if (!function_exists('getAllActiveSessions')) {
    function getAllActiveSessions($minutes = 15) {
        global $pdo;
        
        if (!$pdo) {
            error_log("Database connection not available in getAllActiveSessions");
            return [];
        }
        
        try {
            // Check if user_sessions table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
            if ($stmt->rowCount() == 0) {
                // Create the table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `user_sessions` (
                        `id` bigint(20) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL,
                        `session_id` varchar(128) NOT NULL,
                        `login_time` datetime NOT NULL,
                        `last_activity` datetime NOT NULL,
                        `logout_time` datetime DEFAULT NULL,
                        `ip` varchar(45) DEFAULT NULL,
                        `user_agent` varchar(255) DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`),
                        KEY `session_id` (`session_id`),
                        KEY `last_activity` (`last_activity`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
            
            // Get active sessions - FIXED: Use prepare with named parameters to avoid binding issues
            $sql = "
                SELECT us.*, u.name, u.email, u.role_type 
                FROM user_sessions us 
                JOIN users u ON u.id = us.user_id 
                WHERE us.logout_time IS NULL 
                AND us.last_activity > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                ORDER BY us.last_activity DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':minutes', $minutes, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Error getting active sessions: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Record user session
 * 
 * @param int $user_id User ID
 * @return bool True on success
 */
if (!function_exists('recordUserSession')) {
    function recordUserSession($user_id) {
        global $pdo;
        
        if (!$pdo) {
            return false;
        }
        
        try {
            // Check if user_sessions table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
            if ($stmt->rowCount() == 0) {
                // Create the table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `user_sessions` (
                        `id` bigint(20) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL,
                        `session_id` varchar(128) NOT NULL,
                        `login_time` datetime NOT NULL,
                        `last_activity` datetime NOT NULL,
                        `logout_time` datetime DEFAULT NULL,
                        `ip` varchar(45) DEFAULT NULL,
                        `user_agent` varchar(255) DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`),
                        KEY `session_id` (`session_id`),
                        KEY `last_activity` (`last_activity`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
            
            $session_id = session_id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // End any existing open sessions for this user
            $endStmt = $pdo->prepare("UPDATE user_sessions SET logout_time = NOW() WHERE user_id = ? AND logout_time IS NULL");
            $endStmt->execute([$user_id]);
            
            // Insert new session
            $insertStmt = $pdo->prepare("
                INSERT INTO user_sessions (user_id, session_id, login_time, last_activity, ip, user_agent) 
                VALUES (?, ?, NOW(), NOW(), ?, ?)
            ");
            
            return $insertStmt->execute([$user_id, $session_id, $ip, $user_agent]);
            
        } catch (PDOException $e) {
            error_log("Error recording user session: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Hash a password
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

/**
 * Verify password
 * 
 * @param string $password Plain text password
 * @param string $hash Stored hash
 * @return boolean
 */
if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

/**
 * Check if user has role
 * 
 * @param string|array $roles Role or array of roles to check
 * @return boolean
 */
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (!isLoggedIn() || !isset($_SESSION['user_role'])) {
            return false;
        }
        
        if (is_array($roles)) {
            return in_array($_SESSION['user_role'], $roles);
        }
        
        return $_SESSION['user_role'] === $roles;
    }
}

/**
 * Get current user's role
 * 
 * @return string|null
 */
if (!function_exists('getCurrentUserRole')) {
    function getCurrentUserRole() {
        if (isLoggedIn()) {
            return $_SESSION['user_role'] ?? null;
        }
        return null;
    }
}

/**
 * Get current user's ID
 * 
 * @return int|null
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        if (isLoggedIn()) {
            return $_SESSION['user_id'] ?? null;
        }
        return null;
    }
}

/**
 * Get current user's name
 * 
 * @return string|null
 */
if (!function_exists('getCurrentUserName')) {
    function getCurrentUserName() {
        if (isLoggedIn()) {
            return $_SESSION['user_name'] ?? null;
        }
        return null;
    }
}

/**
 * Generate CSRF token
 * 
 * @return string
 */
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return boolean
 */
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token) {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Update user last activity
 */
if (!function_exists('updateLastActivity')) {
    function updateLastActivity() {
        if (isLoggedIn()) {
            $_SESSION['last_activity'] = time();
        }
    }
}

/**
 * Check session timeout
 * 
 * @param int $timeout Timeout in seconds (default 7200 = 2 hours)
 * @return boolean True if session is valid, false if expired
 */
if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout($timeout = 7200) {
        if (!isLoggedIn()) {
            return false;
        }
        
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            // Session expired
            logout();
            return false;
        }
        updateLastActivity();
        return true;
    }
}

// Initialize CSRF token if session is active and token doesn't exist
if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Log successful load
error_log("auth.php loaded successfully with " . (function_exists('get_defined_functions') ? count(get_defined_functions()['user']) : 'unknown') . " user functions");