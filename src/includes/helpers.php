<?php
/**
 * Global Helper Functions for PMRMS
 * 
 * @version 2.0.1
 */

// Prevent multiple inclusions
if (defined('HELPERS_INCLUDED')) {
    return;
}
define('HELPERS_INCLUDED', true);

// DO NOT start session here - session should already be started in index.php
// Only check if session exists, don't try to start it

/**
 * Calculate age from date of birth
 * 
 * @param string|null $dob Date of birth (Y-m-d format)
 * @return int|null Age in years or null if invalid
 */
if (!function_exists('calc_age')) {
    function calc_age($dob) {
        if (!$dob || $dob == '0000-00-00') return null;
        try {
            $d = new DateTime($dob);
            $now = new DateTime('now');
            return $d->diff($now)->y;
        } catch (Exception $e) {
            error_log("calc_age error: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Flash message handling
 * 
 * @param string $key Message key
 * @param string|null $msg Message value (null to retrieve)
 * @return string|null
 */
if (!function_exists('flash')) {
    function flash($key, $msg = null) {
        // Only access session if it's active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        
        if ($msg !== null) {
            $_SESSION['flash'][$key] = $msg;
            return;
        }
        
        if (!empty($_SESSION['flash'][$key])) {
            $m = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $m;
        }
        return null;
    }
}

/**
 * Format datetime
 * 
 * @param string|null $datetime Datetime string
 * @param string $format Format to use
 * @return string Formatted date or 'N/A'
 */
if (!function_exists('format_datetime')) {
    function format_datetime($datetime, $format = 'M d, Y h:i A') {
        if (empty($datetime) || $datetime == '0000-00-00 00:00:00') return 'N/A';
        try {
            $date = new DateTime($datetime);
            return $date->format($format);
        } catch (Exception $e) {
            error_log("format_datetime error: " . $e->getMessage());
            return 'N/A';
        }
    }
}

/**
 * Format date only
 * 
 * @param string|null $date Date string
 * @param string $format Format to use
 * @return string Formatted date or 'N/A'
 */
if (!function_exists('format_date')) {
    function format_date($date, $format = 'M d, Y') {
        if (empty($date) || $date == '0000-00-00') return 'N/A';
        try {
            $d = new DateTime($date);
            return $d->format($format);
        } catch (Exception $e) {
            error_log("format_date error: " . $e->getMessage());
            return 'N/A';
        }
    }
}

/**
 * Get all roles from database
 * 
 * @param PDO $pdo Database connection
 * @return array Array of roles
 */
if (!function_exists('get_roles')) {
    function get_roles($pdo) {
        try {
            return $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
        } catch (PDOException $e) {
            error_log("get_roles error: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get all permissions from database
 * 
 * @param PDO $pdo Database connection
 * @return array Array of permissions
 */
if (!function_exists('get_permissions')) {
    function get_permissions($pdo) {
        try {
            return $pdo->query("SELECT * FROM permissions ORDER BY category, code")->fetchAll();
        } catch (PDOException $e) {
            error_log("get_permissions error: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get base URL of the application
 * 
 * @param string $path Optional path to append
 * @return string Full URL
 */
if (!function_exists('base_url')) {
    function base_url($path = '') {
        static $base = null;
        
        if ($base === null) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $base = rtrim($protocol . $host . $script_dir, '/');
            
            // Remove /public from base if present
            if (substr($base, -7) === '/public') {
                $base = substr($base, 0, -7);
            }
        }
        
        return $base . '/' . ltrim($path, '/');
    }
}

/**
 * Safe redirect function - checks if headers are sent
 * 
 * @param string $url URL to redirect to
 * @param int $statusCode HTTP status code
 */
if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 302) {
        $fullUrl = base_url($url);
        
        // Check if headers have already been sent
        if (!headers_sent()) {
            header('Location: ' . $fullUrl, true, $statusCode);
            exit;
        } else {
            // Headers already sent, use JavaScript redirect
            echo '<script type="text/javascript">';
            echo 'window.location.href="' . $fullUrl . '";';
            echo '</script>';
            echo '<noscript>';
            echo '<meta http-equiv="refresh" content="0;url=' . $fullUrl . '">';
            echo '</noscript>';
            exit;
        }
    }
}

/**
 * Sanitize output for HTML
 * 
 * @param mixed $input Input to sanitize
 * @return string Sanitized string
 */
if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_null($input)) return '';
        return htmlspecialchars((string)$input, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Alias for sanitize - easier to type in views
 * 
 * @param mixed $input Input to sanitize
 * @return string Sanitized string
 */
if (!function_exists('e')) {
    function e($input) {
        return sanitize($input);
    }
}

/**
 * Generate random token
 * 
 * @param int $length Token length
 * @return string Random token
 */
if (!function_exists('generate_token')) {
    function generate_token($length = 32) {
        try {
            return bin2hex(random_bytes($length / 2));
        } catch (Exception $e) {
            error_log("generate_token error: " . $e->getMessage());
            // Fallback to less secure method
            return md5(uniqid(mt_rand(), true));
        }
    }
}

/**
 * Get current URL
 * 
 * @return string Current URL
 */
if (!function_exists('current_url')) {
    function current_url() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return $protocol . $host . $uri;
    }
}

/**
 * Check if current route matches
 * 
 * @param string|array $routes Route(s) to check
 * @return boolean
 */
if (!function_exists('is_active_route')) {
    function is_active_route($routes) {
        $current = $_GET['r'] ?? '';
        if (is_array($routes)) {
            foreach ($routes as $route) {
                if (strpos($current, $route) === 0) {
                    return true;
                }
            }
            return false;
        }
        return strpos($current, $routes) === 0;
    }
}

/**
 * Get page title based on route
 * 
 * @param string $route Current route
 * @return string Page title
 */
if (!function_exists('get_page_title')) {
    function get_page_title($route = null) {
        if ($route === null) {
            $route = $_GET['r'] ?? 'dashboard';
        }
        
        $titles = [
            'dashboard' => 'Dashboard',
            'patients' => 'Patients',
            'patients/manage' => 'Manage Patients',
            'patients/add' => 'Add Patient',
            'patients/edit' => 'Edit Patient',
            'patients/view' => 'Patient Details',
            'patients/profile' => 'Patient Profile',
            'patients/followup' => 'Follow-ups',
            'audit' => 'Audit Trail',
            'audit/active' => 'Active Sessions',
            'users' => 'User Management',
            'users/form' => 'User Form',
            'users/roles' => 'Role Management',
            'users/role_permissions' => 'Role Permissions',
            'users/user_permissions' => 'User Permissions',
            'exports' => 'Export Data',
            'reports' => 'Reports',
            'profile' => 'My Profile',
            'login' => 'Login'
        ];
        
        return $titles[$route] ?? 'PMRMS';
    }
}

/**
 * Get gender badge HTML
 * 
 * @param string $sex M or F
 * @return string HTML badge
 */
if (!function_exists('gender_badge')) {
    function gender_badge($sex) {
        if ($sex == 'F') {
            return '<span class="badge" style="background: #e83e8c; color: white;"><i class="fa-solid fa-venus me-1"></i>Female</span>';
        }
        return '<span class="badge" style="background: #4A90E2; color: white;"><i class="fa-solid fa-mars me-1"></i>Male</span>';
    }
}

/**
 * Get status badge HTML
 * 
 * @param int|bool $status Active status
 * @return string HTML badge
 */
if (!function_exists('status_badge')) {
    function status_badge($status) {
        if ($status) {
            return '<span class="badge bg-success">Active</span>';
        }
        return '<span class="badge bg-danger">Inactive</span>';
    }
}

/**
 * Get role badge HTML
 * 
 * @param string $role Role name
 * @return string HTML badge
 */
if (!function_exists('role_badge')) {
    function role_badge($role) {
        $colors = [
            'Admin' => 'bg-danger',
            'Doctor' => 'bg-primary',
            'Medical Staff' => 'bg-info',
            'Receptionist' => 'bg-success',
            'Viewer' => 'bg-secondary'
        ];
        
        $color = $colors[$role] ?? 'bg-secondary';
        return '<span class="badge ' . $color . '">' . htmlspecialchars($role) . '</span>';
    }
}

/**
 * Truncate text to a certain length
 * 
 * @param string $text Text to truncate
 * @param int $length Max length
 * @param string $suffix Suffix to append
 * @return string Truncated text
 */
if (!function_exists('truncate')) {
    function truncate($text, $length = 50, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . $suffix;
    }
}

/**
 * Format file size
 * 
 * @param int $bytes Size in bytes
 * @return string Formatted size
 */
if (!function_exists('format_file_size')) {
    function format_file_size($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 4) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

/**
 * Get time ago string
 * 
 * @param string $datetime Datetime string
 * @return string Time ago
 */
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        if (empty($datetime)) return 'N/A';
        
        try {
            $time = strtotime($datetime);
            $now = time();
            $diff = $now - $time;
            
            if ($diff < 60) {
                return 'just now';
            } elseif ($diff < 3600) {
                $mins = floor($diff / 60);
                return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
            } elseif ($diff < 2592000) {
                $days = floor($diff / 86400);
                return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
            } else {
                return date('M d, Y', $time);
            }
        } catch (Exception $e) {
            error_log("time_ago error: " . $e->getMessage());
            return 'N/A';
        }
    }
}

/**
 * Check if value is empty (including 0)
 * 
 * @param mixed $value Value to check
 * @return boolean
 */
if (!function_exists('is_empty')) {
    function is_empty($value) {
        return empty($value) && $value !== 0 && $value !== '0';
    }
}

/**
 * Generate pagination links
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param string $url_pattern URL pattern with :page placeholder
 * @return string HTML pagination
 */
if (!function_exists('pagination')) {
    function pagination($current_page, $total_pages, $url_pattern) {
        if ($total_pages <= 1) {
            return '';
        }
        
        $html = '<nav><ul class="pagination justify-content-center">';
        
        // Previous button
        if ($current_page > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . str_replace(':page', $current_page - 1, $url_pattern) . '">Previous</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
        }
        
        // Page numbers
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        
        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . str_replace(':page', 1, $url_pattern) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $current_page) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . str_replace(':page', $i, $url_pattern) . '">' . $i . '</a></li>';
            }
        }
        
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . str_replace(':page', $total_pages, $url_pattern) . '">' . $total_pages . '</a></li>';
        }
        
        // Next button
        if ($current_page < $total_pages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . str_replace(':page', $current_page + 1, $url_pattern) . '">Next</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
        }
        
        $html .= '</ul></nav>';
        return $html;
    }
}

/**
 * Get configuration value
 * 
 * @param string $key Dot notation key (e.g., 'app.name')
 * @param mixed $default Default value
 * @return mixed
 */
if (!function_exists('config')) {
    function config($key, $default = null) {
        global $config;
        
        $parts = explode('.', $key);
        $value = $config;
        
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return $default;
            }
            $value = $value[$part];
        }
        
        return $value;
    }
}

/**
 * Log debug message
 * 
 * @param mixed $data Data to log
 */
if (!function_exists('debug_log')) {
    function debug_log($data) {
        if (config('app.debug', false)) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $file = basename($backtrace['file']);
            $line = $backtrace['line'];
            
            error_log("[DEBUG][$file:$line] " . print_r($data, true));
        }
    }
}

// Log successful load
error_log("helpers.php loaded successfully");