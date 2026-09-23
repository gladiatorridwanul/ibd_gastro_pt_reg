<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files with error checking
$requiredFiles = [
    'db.php' => __DIR__.'/../includes/db.php',
    'auth.php' => __DIR__.'/../includes/auth.php',
    'audit.php' => __DIR__.'/../includes/audit.php',
    'helpers.php' => __DIR__.'/../includes/helpers.php'
];

foreach ($requiredFiles as $name => $path) {
    if (file_exists($path)) {
        require_once $path;
        error_log("Loaded: " . $name);
    } else {
        error_log("WARNING: File not found: " . $path);
    }
}

// Get config
global $config;
if (!isset($config)) {
    // Try multiple possible config paths
    $possiblePaths = [
        __DIR__.'/../../config/config.php',
        __DIR__.'/../config/config.php',
        '/home/ibdgastroliverbd/public_html/config/config.php'
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $config = require $path;
            error_log("Config loaded from: " . $path);
            break;
        }
    }
}

// Check if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Verify user still exists (optional)
    if (function_exists('getCurrentUser') && getCurrentUser()) {
        header('Location: ?r=dashboard');
        exit;
    } else {
        // Invalid session, clear it
        unset($_SESSION['user_id']);
    }
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Check if authenticateUser function exists
        if (!function_exists('authenticateUser')) {
            error_log("CRITICAL: authenticateUser function not found!");
            $error = 'System error: Authentication function not available';
        } else {
            // Attempt authentication
            $user = authenticateUser($email, $password);
            
            if ($user) {
                // Ensure is_active is set (default to true if not in array)
                $isActive = isset($user['is_active']) ? $user['is_active'] : true;
                
                if ($isActive) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'] ?? 'User';
                    $_SESSION['user_role'] = $user['role_type'] ?? 'User';
                    $_SESSION['login_time'] = time();
                    $_SESSION['last_activity'] = time();
                    
                    // Log the login if function exists
                    if (function_exists('logActivity')) {
                        logActivity($user['id'], 'auth.login', null, null, ['email' => $email]);
                    }
                    
                    // Record session if function exists
                    if (function_exists('recordUserSession')) {
                        recordUserSession($user['id']);
                    }
                    
                    error_log("Login successful for: " . $email . ", redirecting to dashboard");
                    
                    // Redirect to dashboard
                    header('Location: ?r=dashboard');
                    exit;
                } else {
                    $error = 'Your account is inactive. Please contact administrator.';
                    error_log("Login failed - inactive account: " . $email);
                }
            } else {
                $error = 'Invalid email or password';
                error_log("Login failed for email: " . $email);
            }
        }
    }
}

// Don't include the regular header - start fresh
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMRMS - Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Color Variables - White Background, Black Text, Blue Accents */
        :root {
            --white: #FFFFFF;
            --light-gray: #f8f9fa;
            --border-gray: #e9ecef;
            --text-black: #212529;
            --text-muted: #6c757d;
            --blue: #4A90E2;
            --blue-hover: #357ABD;
            --red: #dc3545;
            --green: #28a745;
            --yellow: #ffc107;
        }

        body {
            background-color: var(--white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        
        .login-container {
            max-width: 450px;
            margin: 0 auto;
            width: 100%;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 15px;
            background: var(--white);
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--border-gray);
        }
        
        .institute-name {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--text-black);
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        
        .app-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--blue);
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        
        .app-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-gray);
        }
        
        .login-card {
            border: 1px solid var(--border-gray);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            background-color: var(--white);
            overflow: hidden;
        }
        
        .login-card .card-body {
            padding: 40px 30px;
        }
        
        .form-label {
            color: var(--text-muted);
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1.5px solid var(--border-gray);
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s;
            color: var(--text-black);
        }
        
        .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 0.2rem rgba(74,144,226,0.1);
        }
        
        .input-group-text {
            background: transparent;
            border: 1.5px solid var(--border-gray);
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: var(--blue);
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .btn-login {
            background-color: var(--blue);
            border: 1px solid var(--blue);
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            color: var(--white);
        }
        
        .btn-login:hover {
            background-color: var(--blue-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(74,144,226,0.2);
        }
        
        .btn-login i {
            color: var(--white) !important;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--red);
            color: var(--red);
        }
        
        .alert-danger i {
            color: var(--red) !important;
        }
        
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid var(--yellow);
            color: #856404;
        }
        
        .alert-warning i {
            color: var(--yellow) !important;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-left: 4px solid var(--green);
            color: #155724;
        }
        
        .alert-success i {
            color: var(--green) !important;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .footer-text a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer-text a:hover {
            text-decoration: underline;
        }
        
        .text-muted {
            color: var(--text-muted) !important;
        }
        
        .demo-credentials {
            background-color: var(--light-gray);
            border: 1px solid var(--border-gray);
            border-radius: 8px;
            padding: 12px;
            margin-top: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .demo-credentials:hover {
            border-color: var(--blue);
            box-shadow: 0 2px 8px rgba(74,144,226,0.1);
        }
        
        .demo-credentials small {
            color: var(--text-muted);
        }
        
        .demo-credentials .text-primary {
            color: var(--blue) !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <!-- Logo and Institute Info -->
            <div class="logo-section">
                <!-- Logo Image with fallback -->
                <img src="assets/images/bmu-logo.png" 
                     alt="Institute Logo" 
                     class="logo-img"
                     onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'120\' height=\'120\' viewBox=\'0 0 24 24\' fill=\'%234A90E2\'%3E%3Cpath d=\'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z\'/%3E%3C/svg%3E';">
                
                <!-- Application Subtitle -->
                <div class="app-subtitle">
                    Patient Medical Record Management System
                </div>
                
                <!-- Institute Name -->
                <div class="institute-name">
                    Dhaka Medical College & Hospital
                </div>
            </div>
            
            <!-- Login Card -->
            <div class="card login-card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>
                            <?=htmlspecialchars($error)?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['timeout'])): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fa-solid fa-clock me-2"></i>
                            Your session has expired. Please login again.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['loggedout'])): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fa-solid fa-check-circle me-2"></i>
                            You have been successfully logged out.
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" autocomplete="off">
                        <!-- Email Field -->
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-semibold mb-2">
                                <i class="fa-regular fa-envelope me-1" style="color: var(--blue);"></i>EMAIL ADDRESS
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-regular fa-envelope" style="color: var(--blue);"></i>
                                </span>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?=htmlspecialchars($email)?>" 
                                       placeholder="Enter your email" 
                                       required 
                                       autofocus
                                       autocomplete="username">
                            </div>
                        </div>
                        
                        <!-- Password Field -->
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-semibold mb-2">
                                <i class="fa-solid fa-lock me-1" style="color: var(--blue);"></i>PASSWORD
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-lock" style="color: var(--blue);"></i>
                                </span>
                                <input type="password" 
                                       name="password" 
                                       class="form-control" 
                                       placeholder="Enter your password" 
                                       required
                                       autocomplete="current-password">
                            </div>
                        </div>
                        
                        <!-- Login Button -->
                        <button type="submit" class="btn btn-login w-100">
                            <i class="fa-solid fa-sign-in-alt me-2"></i>LOGIN TO DASHBOARD
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer-text">
                <p class="mb-1">
                    <i class="fa-regular fa-copyright me-1" style="color: var(--blue);"></i><?=date('Y')?> <strong style="color: var(--blue);">DarkBangla v2.0.1</strong>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto-fill demo credentials -->
    <script>
        function fillDemoCredentials() {
            document.querySelector('input[name="email"]').value = 'admin@example.com';
            document.querySelector('input[name="password"]').value = 'admin123';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const demoBox = document.querySelector('.demo-credentials');
            if (demoBox) {
                demoBox.style.cursor = 'pointer';
                demoBox.title = 'Click to auto-fill demo credentials';
            }
        });
    </script>
</body>
</html>