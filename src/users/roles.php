<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../includes/audit.php';
require_once __DIR__.'/../includes/helpers.php';

require_login();

// Check permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'menu.roles')) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'You do not have permission to access role management.'
    ];
    header('Location: ?r=dashboard');
    exit;
}

// Define CSRF functions if they don't exist
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

$action = $_GET['action'] ?? 'list';
$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle role deletion
if ($action === 'delete' && $role_id) {
    if (!has_permission($pdo, 'button.role.delete')) {
        $_SESSION['error'] = 'You do not have permission to delete roles.';
        header('Location: ?r=users/roles');
        exit;
    }
    
    // Check if role is in use
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_type = (SELECT name FROM roles WHERE id = ?)");
        $stmt->execute([$role_id]);
        $user_count = $stmt->fetchColumn();
        
        if ($user_count > 0) {
            $_SESSION['error'] = "Cannot delete role: {$user_count} user(s) are assigned to this role.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$role_id]);
            
            if (function_exists('audit')) {
                audit($pdo, 'role.delete', 'roles', $role_id);
            }
            
            $_SESSION['success'] = "Role deleted successfully.";
        }
    } catch (PDOException $e) {
        error_log("Error deleting role: " . $e->getMessage());
        $_SESSION['error'] = "Database error occurred.";
    }
    
    header('Location: ?r=users/roles');
    exit;
}

// Handle role creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: ?r=users/roles');
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name)) {
        $_SESSION['error'] = "Role name is required.";
    } else {
        try {
            if ($action === 'edit' && $role_id) {
                if (!has_permission($pdo, 'button.role.edit')) {
                    $_SESSION['error'] = 'You do not have permission to edit roles.';
                } else {
                    $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $role_id]);
                    
                    if (function_exists('audit')) {
                        audit($pdo, 'role.update', 'roles', $role_id, ['name' => $name]);
                    }
                    
                    $_SESSION['success'] = "Role updated successfully.";
                }
            } else {
                if (!has_permission($pdo, 'button.role.create')) {
                    $_SESSION['error'] = 'You do not have permission to create roles.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    $new_id = $pdo->lastInsertId();
                    
                    if (function_exists('audit')) {
                        audit($pdo, 'role.create', 'roles', $new_id, ['name' => $name]);
                    }
                    
                    $_SESSION['success'] = "Role created successfully.";
                }
            }
        } catch (PDOException $e) {
            error_log("Error saving role: " . $e->getMessage());
            $_SESSION['error'] = "Database error occurred.";
        }
    }
    
    header('Location: ?r=users/roles');
    exit;
}

// Get role data for editing
$role = null;
if ($action === 'edit' && $role_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $role = $stmt->fetch();
        if (!$role) {
            $_SESSION['error'] = "Role not found.";
            header('Location: ?r=users/roles');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error fetching role: " . $e->getMessage());
        $_SESSION['error'] = "Database error occurred.";
        header('Location: ?r=users/roles');
        exit;
    }
}

// Get all roles
$roles = [];
try {
    $roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
}

include __DIR__.'/../templates/header.php';
?>

<style>
/* Color Variables */
:root {
    --white: #FFFFFF;
    --ash: #F2F4F8;
    --blue: #4A90E2;
    --black: #000000;
}

* {
    font-family: Cambria, serif;
}

/* Page Header */
.page-header {
    margin-bottom: 24px;
}
.page-header h4 {
    color: var(--black);
    font-weight: 600;
}
.page-header h4 i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 8px;
    border-radius: 8px;
    margin-right: 10px;
}
.breadcrumb {
    background: transparent;
    padding: 0;
}
.breadcrumb-item.active {
    color: var(--black);
    opacity: 0.6;
}
.breadcrumb-item a {
    color: var(--blue);
    text-decoration: none;
}

/* Form Card */
.form-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 24px;
}
.form-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.form-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.form-card .card-header h6 i {
    color: var(--blue) !important;
}
.form-card .card-body {
    padding: 20px;
}
.form-label {
    color: var(--black);
    font-weight: 500;
    margin-bottom: 6px;
    font-size: 0.9rem;
}
.form-control {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 12px;
    color: var(--black);
}
.form-control:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
}

/* Role Cards */
.role-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
    transition: all 0.2s;
    height: 100%;
}
.role-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}
.role-card .card-body {
    padding: 20px;
}
.role-icon {
    width: 50px;
    height: 50px;
    background-color: var(--ash);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.role-icon i {
    color: var(--blue) !important;
    font-size: 1.5rem;
}
.role-name {
    color: var(--black);
    font-weight: 600;
    margin-bottom: 4px;
}
.role-description {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.85rem;
    margin-bottom: 15px;
}

/* Buttons */
.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary:hover {
    background-color: #357ABD;
}
.btn-outline-primary {
    background-color: transparent;
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--blue);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-outline-primary:hover {
    background-color: var(--blue);
    color: var(--white);
}
.btn-outline-danger {
    background-color: transparent;
    border: 1px solid #dc3545;
    border-radius: 6px;
    padding: 8px 16px;
    color: #dc3545;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-outline-danger:hover {
    background-color: #dc3545;
    color: var(--white);
}
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--black);
    text-decoration: none;
    display: inline-block;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
}

/* Badges */
.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
}
.badge-secondary {
    background-color: var(--ash);
    color: var(--black);
}
.badge-success {
    background-color: var(--blue);
    color: var(--white);
}

/* Alert */
.alert {
    background-color: var(--ash);
    border-left: 4px solid var(--blue);
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.alert-success {
    border-left-color: #28a745;
}
.alert-danger {
    border-left-color: #dc3545;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fa-solid fa-users-gear"></i>Role Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=users">User Management</a></li>
                    <li class="breadcrumb-item active">Role Management</li>
                </ol>
            </nav>
        </div>
        <?php if (has_permission($pdo, 'button.role.create')): ?>
        <a href="?r=users/roles&action=add" class="btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> New Role
        </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-check-circle me-2" style="color: #28a745;"></i>
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-exclamation-circle me-2" style="color: #dc3545;"></i>
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'add' || ($action === 'edit' && $role)): ?>
        <!-- Role Form -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-<?= $action === 'edit' ? 'pen' : 'plus' ?> me-2"></i><?= $action === 'edit' ? 'Edit' : 'Create' ?> Role</h6>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required 
                                   value="<?= htmlspecialchars($role['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" 
                                   value="<?= htmlspecialchars($role['description'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-save"></i> Save Role
                        </button>
                        <a href="?r=users/roles" class="btn-outline-secondary">
                            <i class="fa-solid fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Roles List -->
        <div class="row g-4">
            <?php if (!empty($roles)): ?>
                <?php foreach ($roles as $r): 
                    // Get user count for this role
                    $user_count = 0;
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_type = ?");
                        $stmt->execute([$r['name']]);
                        $user_count = $stmt->fetchColumn();
                    } catch (PDOException $e) {
                        error_log("Error counting users: " . $e->getMessage());
                    }
                    
                    // Get permission count for this role
                    $perm_count = 0;
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ?");
                        $stmt->execute([$r['id']]);
                        $perm_count = $stmt->fetchColumn();
                    } catch (PDOException $e) {
                        error_log("Error counting permissions: " . $e->getMessage());
                    }
                ?>
                <div class="col-md-4">
                    <div class="role-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="role-icon">
                                    <i class="fa-solid fa-<?= $r['name'] === 'Admin' ? 'crown' : 'users-between-lines' ?>"></i>
                                </div>
                                <div>
                                    <h5 class="role-name mb-0"><?= htmlspecialchars($r['name']) ?></h5>
                                    <span class="badge badge-secondary">ID: <?= $r['id'] ?></span>
                                </div>
                            </div>
                            
                            <p class="role-description">
                                <?= htmlspecialchars($r['description'] ?: 'No description provided') ?>
                            </p>
                            
                            <div class="d-flex gap-3 mb-3">
                                <div>
                                    <span class="badge badge-success"><?= $user_count ?> users</span>
                                </div>
                                <div>
                                    <span class="badge badge-secondary"><?= $perm_count ?> permissions</span>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if (has_permission($pdo, 'button.role.permissions')): ?>
                                <a href="?r=users/role_permissions&id=<?= $r['id'] ?>" class="btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-key"></i> Permissions
                                </a>
                                <?php endif; ?>
                                
                                <?php if (has_permission($pdo, 'button.role.edit')): ?>
                                <a href="?r=users/roles&action=edit&id=<?= $r['id'] ?>" class="btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <?php endif; ?>
                                
                                <?php if (has_permission($pdo, 'button.role.delete') && $r['name'] !== 'Admin'): ?>
                                <a href="?r=users/roles&action=delete&id=<?= $r['id'] ?>" 
                                   class="btn-outline-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this role?\n\nThis action cannot be undone.')">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fa-solid fa-users-slash fa-4x mb-3" style="color: var(--ash);"></i>
                        <h5>No roles found</h5>
                        <p class="text-muted">Click "New Role" to create your first role.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>