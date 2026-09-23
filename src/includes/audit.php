<?php
/**
 * Audit Logging Functions for PMRMS
 * Records all user actions for tracking and security purposes
 * 
 * @version 2.0.1
 */

// Prevent multiple inclusions
if (defined('AUDIT_INCLUDED')) {
    return;
}
define('AUDIT_INCLUDED', true);

// Enable error logging
error_log("audit.php loaded at " . date('Y-m-d H:i:s'));

// DO NOT START SESSION HERE - session should already be started in index.php

// Make sure we have database connection
if (!isset($pdo) && file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

/**
 * Main audit logging function
 * Records a single action in the audit log
 * 
 * @param PDO $pdo Database connection
 * @param string $action The action being performed (e.g., 'user.create', 'patient.edit')
 * @param string|null $entity The entity type (e.g., 'users', 'patients', 'complaints')
 * @param int|null $entity_id The ID of the entity being acted upon
 * @param mixed|null $details Additional details about the action (array or string)
 * @return bool True on success, false on failure
 */
if (!function_exists('audit')) {
    function audit($pdo, $action, $entity = null, $entity_id = null, $details = null) {
        // Get current user ID safely
        $uid = null;
        if (function_exists('user')) {
            $user = user();
            $uid = $user['id'] ?? null;
        } elseif (isset($_SESSION['user_id'])) {
            $uid = $_SESSION['user_id'];
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        try {
            // Prepare details as JSON
            $details_json = null;
            if ($details !== null) {
                $details_json = is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE);
            }
            
            $stmt = $pdo->prepare("INSERT INTO audit_logs 
                (user_id, action, entity, entity_id, details, ip, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $result = $stmt->execute([
                $uid,
                $action,
                $entity,
                $entity_id,
                $details_json,
                $ip,
                $ua
            ]);
            
            if ($result) {
                error_log("Audit log created: {$action} by user " . ($uid ?? 'guest'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Log error but don't break application
            error_log("Audit log failed: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Alias for audit() - for backward compatibility
 */
if (!function_exists('logActivity')) {
    function logActivity($user_id, $action, $entity = null, $entity_id = null, $details = null) {
        global $pdo;
        return audit($pdo, $action, $entity, $entity_id, $details);
    }
}

/**
 * Log user login events
 * 
 * @param PDO $pdo Database connection
 * @param string $email Email used for login
 * @param bool $success Whether login was successful
 * @return void
 */
if (!function_exists('audit_login')) {
    function audit_login($pdo, $email, $success = true) {
        $action = $success ? 'auth.login.success' : 'auth.login.failed';
        $details = ['email' => $email, 'time' => date('Y-m-d H:i:s')];
        
        if (!$success) {
            // Track failed login attempts
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            try {
                // Check if failed_logins table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'failed_logins'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("INSERT INTO failed_logins (email, ip, attempted_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$email, $ip]);
                }
            } catch (Exception $e) {
                error_log("Failed login tracking error: " . $e->getMessage());
            }
        }
        
        audit($pdo, $action, null, null, $details);
    }
}

/**
 * Log user logout events
 * 
 * @param PDO $pdo Database connection
 * @param int|null $user_id User ID (if available)
 * @return void
 */
if (!function_exists('audit_logout')) {
    function audit_logout($pdo, $user_id = null) {
        if ($user_id === null && function_exists('user')) {
            $user = user();
            $user_id = $user['id'] ?? null;
        }
        audit($pdo, 'auth.logout', 'users', $user_id, ['logout_time' => date('Y-m-d H:i:s')]);
    }
}

/**
 * Simplified CRUD audit logging
 * 
 * @param PDO $pdo Database connection
 * @param string $operation create|read|update|delete
 * @param string $entity Entity type
 * @param int $entity_id Entity ID
 * @param array $data Relevant data
 * @return void
 */
if (!function_exists('audit_crud')) {
    function audit_crud($pdo, $operation, $entity, $entity_id, $data = []) {
        $action = $entity . '.' . $operation;
        audit($pdo, $action, $entity, $entity_id, $data);
    }
}

/**
 * Log multiple similar actions at once
 * 
 * @param PDO $pdo Database connection
 * @param string $action Base action name
 * @param string $entity Entity type
 * @param array $entity_ids Array of entity IDs
 * @param array $common_details Common details for all entries
 * @return void
 */
if (!function_exists('audit_bulk')) {
    function audit_bulk($pdo, $action, $entity, $entity_ids, $common_details = []) {
        $details = array_merge($common_details, ['entity_ids' => $entity_ids, 'count' => count($entity_ids)]);
        audit($pdo, $action . '.bulk', $entity, null, $details);
        
        // Optionally log individually (commented out to avoid duplicate logs)
        // foreach ($entity_ids as $id) {
        //     audit($pdo, $action, $entity, $id, $common_details);
        // }
    }
}

/**
 * Log data export events
 * 
 * @param PDO $pdo Database connection
 * @param string $format csv|pdf|xlsx
 * @param string $entity Entity being exported
 * @param array $filters Filters applied to export
 * @param int $record_count Number of records exported
 * @return void
 */
if (!function_exists('audit_export')) {
    function audit_export($pdo, $format, $entity, $filters = [], $record_count = 0) {
        $details = [
            'format' => $format,
            'filters' => $filters,
            'record_count' => $record_count,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        audit($pdo, 'export.' . $format, $entity, null, $details);
    }
}

/**
 * Log application errors
 * 
 * @param PDO $pdo Database connection
 * @param string $error_type Type of error
 * @param string $message Error message
 * @param array $context Additional context
 * @return void
 */
if (!function_exists('audit_error')) {
    function audit_error($pdo, $error_type, $message, $context = []) {
        $details = [
            'error_type' => $error_type,
            'message' => $message,
            'context' => $context,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null
        ];
        audit($pdo, 'error.' . $error_type, null, null, $details);
    }
}

/**
 * Log permission changes specifically
 * 
 * @param PDO $pdo Database connection
 * @param string $target_type user|role
 * @param int $target_id User ID or Role ID
 * @param array $old_permissions Previous permissions
 * @param array $new_permissions New permissions
 * @return void
 */
if (!function_exists('audit_permission_change')) {
    function audit_permission_change($pdo, $target_type, $target_id, $old_permissions, $new_permissions) {
        $added = array_diff($new_permissions, $old_permissions);
        $removed = array_diff($old_permissions, $new_permissions);
        
        $details = [
            'target_type' => $target_type,
            'target_id' => $target_id,
            'added' => array_values($added),
            'removed' => array_values($removed),
            'added_count' => count($added),
            'removed_count' => count($removed)
        ];
        
        audit($pdo, $target_type . '.permissions.update', $target_type . 's', $target_id, $details);
    }
}

/**
 * Retrieve audit logs with filtering
 * 
 * @param PDO $pdo Database connection
 * @param array $filters Optional filters (user_id, action, date_from, date_to, entity)
 * @param int $limit Maximum number of records to return
 * @param int $offset Offset for pagination
 * @return array Audit log entries
 */
if (!function_exists('get_audit_logs')) {
    function get_audit_logs($pdo, $filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT a.*, u.name as user_name 
                FROM audit_logs a 
                LEFT JOIN users u ON u.id = a.user_id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND a.action LIKE ?";
            $params[] = '%' . $filters['action'] . '%';
        }
        
        if (!empty($filters['entity'])) {
            $sql .= " AND a.entity = ?";
            $params[] = $filters['entity'];
        }
        
        if (!empty($filters['entity_id'])) {
            $sql .= " AND a.entity_id = ?";
            $params[] = $filters['entity_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(a.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(a.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY a.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

/**
 * Get complete audit trail for a specific user
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $limit Maximum number of records
 * @return array User's audit trail
 */
if (!function_exists('get_user_audit_trail')) {
    function get_user_audit_trail($pdo, $user_id, $limit = 50) {
        return get_audit_logs($pdo, ['user_id' => $user_id], $limit);
    }
}

/**
 * Get audit trail for a specific entity
 * 
 * @param PDO $pdo Database connection
 * @param string $entity Entity type
 * @param int $entity_id Entity ID
 * @param int $limit Maximum number of records
 * @return array Entity's audit trail
 */
if (!function_exists('get_entity_audit_trail')) {
    function get_entity_audit_trail($pdo, $entity, $entity_id, $limit = 50) {
        return get_audit_logs($pdo, ['entity' => $entity, 'entity_id' => $entity_id], $limit);
    }
}

/**
 * Get summary statistics for audit logs
 * 
 * @param PDO $pdo Database connection
 * @param string $period day|week|month
 * @return array Summary statistics
 */
if (!function_exists('audit_summary')) {
    function audit_summary($pdo, $period = 'day') {
        $interval = match($period) {
            'week' => 'INTERVAL 7 DAY',
            'month' => 'INTERVAL 30 DAY',
            default => 'INTERVAL 1 DAY'
        };
        
        $sql = "SELECT 
                  DATE(created_at) as date,
                  COUNT(*) as total_actions,
                  COUNT(DISTINCT user_id) as active_users,
                  SUM(CASE WHEN action LIKE '%create%' THEN 1 ELSE 0 END) as creates,
                  SUM(CASE WHEN action LIKE '%update%' THEN 1 ELSE 0 END) as updates,
                  SUM(CASE WHEN action LIKE '%delete%' THEN 1 ELSE 0 END) as deletes,
                  SUM(CASE WHEN action LIKE '%login%' THEN 1 ELSE 0 END) as logins
                FROM audit_logs 
                WHERE created_at >= DATE_SUB(NOW(), $interval)
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        
        try {
            return $pdo->query($sql)->fetchAll();
        } catch (Exception $e) {
            error_log("Audit summary error: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Delete audit logs older than specified days
 * 
 * @param PDO $pdo Database connection
 * @param int $days Number of days to keep
 * @return int Number of deleted records
 */
if (!function_exists('clean_old_audit_logs')) {
    function clean_old_audit_logs($pdo, $days = 90) {
        try {
            $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            $count = $stmt->rowCount();
            error_log("Cleaned {$count} old audit logs");
            return $count;
        } catch (Exception $e) {
            error_log("Clean old audit logs error: " . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Format audit details for display
 * 
 * @param string $json JSON string of details
 * @return string Formatted HTML
 */
if (!function_exists('format_audit_details')) {
    function format_audit_details($json) {
        if (!$json) return '-';
        
        $details = json_decode($json, true);
        if (!$details) return htmlspecialchars($json);
        
        $html = '<ul class="list-unstyled mb-0 small">';
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</li>';
        }
        $html .= '</ul>';
        
        return $html;
    }
}

/**
 * Record user session for tracking
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
                return false;
            }
            
            $session_id = session_id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // End any existing open sessions
            $stmt = $pdo->prepare("UPDATE user_sessions SET logout_time = NOW() WHERE user_id = ? AND logout_time IS NULL");
            $stmt->execute([$user_id]);
            
            // Create new session
            $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, login_time, last_activity, ip, user_agent) VALUES (?, ?, NOW(), NOW(), ?, ?)");
            return $stmt->execute([$user_id, $session_id, $ip, $user_agent]);
            
        } catch (Exception $e) {
            error_log("Record user session error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get active user sessions
 * 
 * @param PDO $pdo Database connection
 * @param int $minutes Number of minutes to consider active
 * @return array Active sessions
 */
if (!function_exists('getActiveSessions')) {
    function getActiveSessions($pdo, $minutes = 15) {
        try {
            $stmt = $pdo->prepare("
                SELECT s.*, u.name, u.email, u.role_type 
                FROM user_sessions s
                JOIN users u ON u.id = s.user_id
                WHERE s.logout_time IS NULL 
                AND s.last_activity > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY s.last_activity DESC
            ");
            $stmt->execute([$minutes]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get active sessions error: " . $e->getMessage());
            return [];
        }
    }
}

// Log successful load
error_log("audit.php loaded successfully with " . count(get_defined_functions()['user']) . " audit functions");