<?php
/**
 * Permissions Management System
 * Handles all permission checks and management functions
 */

if (!defined('PERMISSIONS_INCLUDED')) {
    define('PERMISSIONS_INCLUDED', true);

    /**
     * Check if current user has a specific permission
     * 
     * @param PDO $pdo Database connection
     * @param string $code Permission code to check
     * @return bool True if user has permission
     */
    function has_permission($pdo, $code) {
        $u = user();
        if (!$u) return false;
        
        // Admin has all permissions
        if ($u['role_type'] === 'Admin') return true;
        
        static $cache = [];
        $cache_key = $u['id'] . '_' . $code;
        
        if (!isset($cache[$cache_key])) {
            try {
                // Check user-specific permissions first (overrides role)
                $stmt = $pdo->prepare("SELECT granted FROM user_permissions up
                                       JOIN permissions p ON p.id = up.permission_id
                                       WHERE up.user_id = ? AND p.code = ?");
                $stmt->execute([$u['id'], $code]);
                $user_perm = $stmt->fetch();
                
                if ($user_perm) {
                    // User-specific permission exists, use it
                    $cache[$cache_key] = (bool)$user_perm['granted'];
                } else {
                    // Check role-based permissions
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp
                                           JOIN permissions p ON p.id = rp.permission_id
                                           JOIN roles r ON r.id = rp.role_id
                                           WHERE r.name = ? AND p.code = ?");
                    $stmt->execute([$u['role_type'], $code]);
                    $cache[$cache_key] = $stmt->fetchColumn() > 0;
                }
            } catch (PDOException $e) {
                error_log("Permission check error: " . $e->getMessage());
                $cache[$cache_key] = false;
            }
        }
        
        return $cache[$cache_key];
    }

    /**
     * Require a specific permission or show 403 error
     * 
     * @param PDO $pdo Database connection
     * @param string $code Permission code to require
     */
    function require_permission($pdo, $code) {
        if (!has_permission($pdo, $code)) {
            http_response_code(403);
            
            // Try to include custom 403 page
            $paths = [
                __DIR__.'/../templates/403.php',
                __DIR__.'/../../templates/403.php'
            ];
            
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    include $path;
                    exit;
                }
            }
            
            // Fallback 403 message
            die('<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>');
        }
    }

    /**
     * Get all permissions for a specific user
     * 
     * @param PDO $pdo Database connection
     * @param int $user_id User ID
     * @return array Array of permissions
     */
    function get_user_permissions($pdo, $user_id) {
        try {
            // Get user's role
            $stmt = $pdo->prepare("SELECT role_type FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) return [];
            
            // Get role-based permissions
            $stmt = $pdo->prepare("SELECT p.* FROM permissions p
                                  JOIN role_permissions rp ON rp.permission_id = p.id
                                  JOIN roles r ON r.id = rp.role_id
                                  WHERE r.name = ?");
            $stmt->execute([$user['role_type']]);
            $role_perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get user-specific overrides
            $stmt = $pdo->prepare("SELECT p.*, up.granted FROM permissions p
                                  JOIN user_permissions up ON up.permission_id = p.id
                                  WHERE up.user_id = ?");
            $stmt->execute([$user_id]);
            $user_perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Merge, with user permissions overriding role permissions
            $permissions = [];
            foreach ($role_perms as $p) {
                $permissions[$p['code']] = $p;
            }
            foreach ($user_perms as $p) {
                if ($p['granted']) {
                    $permissions[$p['code']] = $p;
                } else {
                    unset($permissions[$p['code']]);
                }
            }
            
            return array_values($permissions);
        } catch (PDOException $e) {
            error_log("Error getting user permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all permissions for a specific role
     * 
     * @param PDO $pdo Database connection
     * @param string $role_name Role name
     * @return array Array of permissions
     */
    function get_role_permissions($pdo, $role_name) {
        try {
            $stmt = $pdo->prepare("SELECT p.* FROM permissions p
                                  JOIN role_permissions rp ON rp.permission_id = p.id
                                  JOIN roles r ON r.id = rp.role_id
                                  WHERE r.name = ?
                                  ORDER BY p.category, p.module, p.code");
            $stmt->execute([$role_name]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting role permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all permissions grouped by category and module
     * 
     * @param PDO $pdo Database connection
     * @return array Grouped permissions
     */
    function get_all_permissions_grouped($pdo) {
        try {
            $permissions = $pdo->query("SELECT * FROM permissions ORDER BY category, module, code")->fetchAll();
            
            $grouped = [];
            foreach ($permissions as $p) {
                $category = $p['category'];
                $module = $p['module'] ?? 'general';
                
                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }
                if (!isset($grouped[$category][$module])) {
                    $grouped[$category][$module] = [];
                }
                
                $grouped[$category][$module][] = $p;
            }
            
            return $grouped;
        } catch (PDOException $e) {
            error_log("Error grouping permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all roles
     * 
     * @param PDO $pdo Database connection
     * @return array Array of roles
     */
    function get_all_roles($pdo) {
        try {
            return $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific role by ID
     * 
     * @param PDO $pdo Database connection
     * @param int $role_id Role ID
     * @return array|false Role data or false if not found
     */
    function get_role($pdo, $role_id) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
            $stmt->execute([$role_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new role
     * 
     * @param PDO $pdo Database connection
     * @param string $name Role name
     * @param string $description Role description
     * @return int|false New role ID or false on error
     */
    function create_role($pdo, $name, $description = '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing role
     * 
     * @param PDO $pdo Database connection
     * @param int $role_id Role ID
     * @param string $name Role name
     * @param string $description Role description
     * @return bool True on success
     */
    function update_role($pdo, $role_id, $name, $description = '') {
        try {
            $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
            return $stmt->execute([$name, $description, $role_id]);
        } catch (PDOException $e) {
            error_log("Error updating role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a role
     * 
     * @param PDO $pdo Database connection
     * @param int $role_id Role ID
     * @return bool True on success
     */
    function delete_role($pdo, $role_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            return $stmt->execute([$role_id]);
        } catch (PDOException $e) {
            error_log("Error deleting role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign permissions to a role
     * 
     * @param PDO $pdo Database connection
     * @param int $role_id Role ID
     * @param array $permission_ids Array of permission IDs
     * @return bool True on success
     */
    function assign_role_permissions($pdo, $role_id, $permission_ids) {
        try {
            $pdo->beginTransaction();
            
            // Clear existing permissions
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
            
            // Insert new permissions
            if (!empty($permission_ids)) {
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($permission_ids as $pid) {
                    $stmt->execute([$role_id, $pid]);
                }
            }
            
            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error assigning role permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign permissions to a user
     * 
     * @param PDO $pdo Database connection
     * @param int $user_id User ID
     * @param array $permission_ids Array of permission IDs
     * @return bool True on success
     */
    function assign_user_permissions($pdo, $user_id, $permission_ids) {
        try {
            $pdo->beginTransaction();
            
            // Clear existing permissions
            $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$user_id]);
            
            // Insert new permissions
            if (!empty($permission_ids)) {
                $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?, ?, 1)");
                foreach ($permission_ids as $pid) {
                    $stmt->execute([$user_id, $pid]);
                }
            }
            
            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error assigning user permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke specific permissions from a user
     * 
     * @param PDO $pdo Database connection
     * @param int $user_id User ID
     * @param array $permission_ids Array of permission IDs to revoke
     * @return bool True on success
     */
    function revoke_user_permissions($pdo, $user_id, $permission_ids) {
        try {
            if (empty($permission_ids)) return true;
            
            $placeholders = implode(',', array_fill(0, count($permission_ids), '?'));
            $params = array_merge([$user_id], $permission_ids);
            
            $sql = "DELETE FROM user_permissions WHERE user_id = ? AND permission_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error revoking user permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user permission overrides
     * 
     * @param PDO $pdo Database connection
     * @param int $user_id User ID
     * @return array User permission overrides
     */
    function get_user_permission_overrides($pdo, $user_id) {
        try {
            $stmt = $pdo->prepare("SELECT p.*, up.granted FROM permissions p
                                  JOIN user_permissions up ON up.permission_id = p.id
                                  WHERE up.user_id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting user permission overrides: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user has any of the given permissions
     * 
     * @param PDO $pdo Database connection
     * @param array $permission_codes Array of permission codes
     * @return bool True if user has any of the permissions
     */
    function has_any_permission($pdo, $permission_codes) {
        foreach ($permission_codes as $code) {
            if (has_permission($pdo, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     * 
     * @param PDO $pdo Database connection
     * @param array $permission_codes Array of permission codes
     * @return bool True if user has all permissions
     */
    function has_all_permissions($pdo, $permission_codes) {
        foreach ($permission_codes as $code) {
            if (!has_permission($pdo, $code)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get permission by code
     * 
     * @param PDO $pdo Database connection
     * @param string $code Permission code
     * @return array|false Permission data or false if not found
     */
    function get_permission_by_code($pdo, $code) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM permissions WHERE code = ?");
            $stmt->execute([$code]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting permission by code: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get permissions by category
     * 
     * @param PDO $pdo Database connection
     * @param string $category Permission category
     * @return array Array of permissions
     */
    function get_permissions_by_category($pdo, $category) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM permissions WHERE category = ? ORDER BY module, code");
            $stmt->execute([$category]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting permissions by category: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get permissions by module
     * 
     * @param PDO $pdo Database connection
     * @param string $module Module name
     * @return array Array of permissions
     */
    function get_permissions_by_module($pdo, $module) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM permissions WHERE module = ? ORDER BY code");
            $stmt->execute([$module]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting permissions by module: " . $e->getMessage());
            return [];
        }
    }
}