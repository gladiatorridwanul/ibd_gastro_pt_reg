<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define paths
define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('INCLUDES_PATH', SRC_PATH . '/includes');

// Load configuration
if (!file_exists(CONFIG_PATH . '/config.php')) {
    die('Configuration file not found.');
}
$config = require CONFIG_PATH . '/config.php';

// Set timezone
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Dhaka');

// Error reporting based on debug mode
if ($config['app']['debug'] ?? true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');
}

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['database']['host']};dbname={$config['database']['name']};charset={$config['database']['charset']}",
        $config['database']['user'],
        $config['database']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . ($config['app']['debug'] ? $e->getMessage() : 'Please try again later.'));
}

// Start session with custom settings
if (session_status() === PHP_SESSION_NONE) {
    session_name($config['security']['session_name'] ?? 'pmrms_session');
    session_start();
}

// Load core includes in the correct order
$required_files = [
    'db.php' => INCLUDES_PATH . '/db.php',
    'auth.php' => INCLUDES_PATH . '/auth.php',
    'permissions.php' => INCLUDES_PATH . '/permissions.php',
    'helpers.php' => INCLUDES_PATH . '/helpers.php'
];

foreach ($required_files as $name => $path) {
    if (file_exists($path)) {
        require_once $path;
    } else {
        die("Required file not found: {$name}");
    }
}

// Get the requested route
$route = $_GET['r'] ?? 'dashboard';
$route = str_replace(['..', '\\'], '', $route);

// ============== FIX: Redirect old patterns to new ones ==============
$redirect_map = [
    'dashboard/index' => 'dashboard',
    'users/index' => 'users',
    'patients/index' => 'patients/manage',
    'audit/index' => 'audit',
    'reports/index' => 'reports',
    'exports/index' => 'exports',
    'profile/index' => 'profile',
    'user/index' => 'users',
    'user-management/index' => 'users',
    'patient/index' => 'patients/manage',
    'auth/profile' => 'profile',
    'auth/logout' => 'logout',
    'audit/active_users' => 'audit/active',
    'audit/active-users' => 'audit/active'
];

// Also handle any route ending with /index
if (preg_match('/^(.*)\/index$/', $route, $matches)) {
    $new_route = $matches[1];
    header('Location: ?r=' . $new_route);
    exit;
}

if (isset($redirect_map[$route])) {
    header('Location: ?r=' . $redirect_map[$route]);
    exit;
}
// ====================================================================

// Normalize route - remove any auth/ prefix
$route = str_replace(['auth/', 'auth.'], '', $route);

// If route is empty, set to dashboard
if (empty($route)) {
    $route = 'dashboard';
}

// Define protected routes (require authentication)
$protectedRoutes = [
    'dashboard', 'patients', 'patients/manage', 'patients/add', 'patients/edit',
    'patients/view', 'patients/profile', 'patients/followup', 'audit', 'audit/active',
    'users', 'users/form', 'users/roles', 'users/role_permissions',
    'users/user_permissions', 'exports', 'reports', 'profile'
];

// Check if route requires authentication
$isProtected = false;
foreach ($protectedRoutes as $protected) {
    if (strpos($route, $protected) === 0) {
        $isProtected = true;
        break;
    }
}

// If protected and not logged in, redirect to login
if ($isProtected && !isset($_SESSION['user_id'])) {
    header('Location: ?r=login');
    exit;
}

// If logged in and trying to access login, redirect to dashboard
if ($route === 'login' && isset($_SESSION['user_id'])) {
    header('Location: ?r=dashboard');
    exit;
}

// Route to appropriate file
$file = null;

switch ($route) {
    // Auth routes
    case 'login':
        $file = SRC_PATH . '/auth/login.php';
        break;
    case 'logout':
        $file = SRC_PATH . '/auth/logout.php';
        break;
    
    // Dashboard
    case 'dashboard':
        $file = SRC_PATH . '/dashboard/index.php';
        break;
    
    // Profile
    case 'profile':
        $file = SRC_PATH . '/auth/profile.php';
        break;
    
    // Patient management
    case 'patients':
    case 'patients/manage':
        $file = SRC_PATH . '/patients/manage.php';
        break;
    case 'patients/add':
        $file = SRC_PATH . '/patients/add.php';
        break;
    case 'patients/edit':
        $file = SRC_PATH . '/patients/edit.php';
        break;
    case 'patients/view':
        $file = SRC_PATH . '/patients/view.php';
        break;
    case 'patients/profile':
        $file = SRC_PATH . '/patients/profile.php';
        break;
    case 'patients/followup':
        $file = SRC_PATH . '/patients/followup.php';
        break;
    case 'patients/delete':
        $file = SRC_PATH . '/patients/delete.php';
        break;
    
    // Audit
    case 'audit':
        $file = SRC_PATH . '/audit/index.php';
        break;
    case 'audit/active':
        $file = SRC_PATH . '/audit/active_users.php';
        break;
    
    // User management
    case 'users':
        $file = SRC_PATH . '/users/index.php';
        break;
    case 'users/form':
        $file = SRC_PATH . '/users/form.php';
        break;
    case 'users/delete':
        $file = SRC_PATH . '/users/delete.php';
        break;
    case 'users/roles':
        $file = SRC_PATH . '/users/roles.php';
        break;
    case 'users/role_permissions':
        $file = SRC_PATH . '/users/role_permissions.php';
        break;
    case 'users/user_permissions':
        $file = SRC_PATH . '/users/user_permissions.php';
        break;
        
    // Documents module
    case 'modules/documents/view':
        $file = SRC_PATH . '/modules/documents/view.php';
        break;
    case 'modules/documents/add':
        $file = SRC_PATH . '/modules/documents/add.php';
        break;
    case 'modules/documents/delete':
        $file = SRC_PATH . '/modules/documents/delete.php';
        break;
    
    // Reports
    case 'reports':
        $file = SRC_PATH . '/reports/index.php';
        break;
    
    // ============== EXPORTS ROUTE - FIXED ==============
    // This routes to the export_patients.php file in the public folder
    case 'exports':
        // Check if export_patients.php exists in public folder
        $exportFile = __DIR__ . '/export_patients.php';
        if (file_exists($exportFile)) {
            require_once $exportFile;
            exit;
        } else {
            // Fallback to src/exports/index.php if exists
            $fallbackFile = SRC_PATH . '/exports/index.php';
            if (file_exists($fallbackFile)) {
                $file = $fallbackFile;
            } else {
                header("HTTP/1.0 404 Not Found");
                echo "<h1>404 - Export Page Not Found</h1>";
                echo "<p>The export page could not be located.</p>";
                exit;
            }
        }
        break;
    // ===================================================
    
    default:
        // Handle module routes (complaints, ibd, etc.)
        $parts = explode('/', $route);
        if (count($parts) >= 2) {
            // Check if it's a module route
            if ($parts[0] === 'modules' && isset($parts[1])) {
                $module = $parts[1];
                $action = $parts[2] ?? 'index';
                $moduleFile = SRC_PATH . "/modules/{$module}/{$action}.php";
                if (file_exists($moduleFile)) {
                    $file = $moduleFile;
                    break;
                }
            }
            
            // Check for direct module access
            $moduleFile = SRC_PATH . "/modules/{$parts[0]}/{$parts[1]}.php";
            if (file_exists($moduleFile)) {
                $file = $moduleFile;
                break;
            }
        }
        
        // 404
        header("HTTP/1.0 404 Not Found");
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>404 - Page Not Found</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
            <style>
                body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; }
                .error-container { text-align: center; max-width: 600px; margin: 0 auto; }
                .error-code { font-size: 80px; font-weight: 700; color: #dc3545; }
                .error-message { font-size: 24px; color: #343a40; margin: 20px 0; }
                .error-detail { color: #6c757d; margin-bottom: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='error-container'>
                    <div class='error-code'>404</div>
                    <div class='error-message'>Page Not Found</div>
                    <div class='error-detail'>The page '<strong>" . htmlspecialchars($route) . "</strong>' was not found.</div>
                    <a href='?r=dashboard' class='btn btn-primary'>Go to Dashboard</a>
                    <a href='?r=login' class='btn btn-outline-secondary ms-2'>Go to Login</a>
                </div>
            </div>
        </body>
        </html>";
        exit;
}

// Load the file if found
if ($file && file_exists($file)) {
    require_once $file;
} else if ($file && !file_exists($file)) {
    header("HTTP/1.0 404 Not Found");
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>404 - Page Not Found</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        <style>
            body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; }
            .error-container { text-align: center; max-width: 600px; margin: 0 auto; }
            .error-code { font-size: 80px; font-weight: 700; color: #dc3545; }
            .error-message { font-size: 24px; color: #343a40; margin: 20px 0; }
            .error-detail { color: #6c757d; margin-bottom: 30px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='error-container'>
                <div class='error-code'>404</div>
                <div class='error-message'>File Not Found</div>
                <div class='error-detail'>The requested file could not be located.</div>
                <a href='?r=dashboard' class='btn btn-primary'>Go to Dashboard</a>
            </div>
        </div>
    </body>
    </html>";
}
?>