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

if (!has_permission($pdo, 'button.user.permissions')) {
    $_SESSION['error'] = 'You do not have permission to manage user permissions';
    header('Location: ?r=users');
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

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: ?r=users');
    exit;
}

// Get user info
$user = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        header('Location: ?r=users');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred';
    header('Location: ?r=users');
    exit;
}

// Cannot modify Admin user permissions
if ($user['role_type'] === 'Admin') {
    $_SESSION['error'] = 'Admin user permissions cannot be modified';
    header('Location: ?r=users');
    exit;
}

// Get all permissions grouped
$grouped_permissions = [];
try {
    $permissions = $pdo->query("SELECT * FROM permissions ORDER BY category, module, code")->fetchAll();
    
    foreach ($permissions as $p) {
        $category = $p['category'];
        $module = $p['module'] ?? 'general';
        
        if (!isset($grouped_permissions[$category])) {
            $grouped_permissions[$category] = [];
        }
        if (!isset($grouped_permissions[$category][$module])) {
            $grouped_permissions[$category][$module] = [];
        }
        
        $grouped_permissions[$category][$module][] = $p;
    }
} catch (PDOException $e) {
    error_log("Error fetching permissions: " . $e->getMessage());
}

// Get current user permissions
$current_permission_ids = [];
try {
    $stmt = $pdo->prepare("SELECT permission_id FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $current_permission_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching user permissions: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: ?r=users/user_permissions&id=' . $user_id);
        exit;
    }
    
    $selected = $_POST['permissions'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Clear existing permissions
        $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$user_id]);
        
        // Insert new permissions
        if (!empty($selected)) {
            $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?, ?, 1)");
            foreach ($selected as $perm_id) {
                $stmt->execute([$user_id, $perm_id]);
            }
        }
        
        // Log the change
        if (function_exists('audit')) {
            audit($pdo, 'user.permissions.update', 'users', $user_id, [
                'user_id' => $user_id,
                'permissions' => $selected
            ]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = 'User permissions updated successfully';
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating user permissions: " . $e->getMessage());
        $_SESSION['error'] = 'Database error occurred';
    }
    
    header('Location: ?r=users/user_permissions&id=' . $user_id);
    exit;
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

/* User Info Card */
.user-info-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
    border-left: 4px solid var(--blue);
}
.user-avatar {
    width: 60px;
    height: 60px;
    background-color: var(--ash);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-avatar i {
    color: var(--blue) !important;
    font-size: 1.8rem;
}
.user-email {
    color: var(--black);
    opacity: 0.7;
    font-size: 0.9rem;
}
.role-badge {
    display: inline-block;
    padding: 4px 12px;
    background-color: var(--blue);
    color: var(--white);
    border-radius: 20px;
    font-size: 0.8rem;
}

/* Permission Cards */
.permission-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
}
.permission-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
}
.permission-card .card-header:hover {
    background-color: var(--ash);
}
.permission-card .card-body {
    padding: 20px;
}

.category-badge {
    background-color: var(--blue);
    color: var(--white);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    text-transform: uppercase;
}

.module-badge {
    background-color: var(--ash);
    color: var(--black);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    margin-left: 10px;
}

.permission-item {
    padding: 10px;
    border-radius: 6px;
    transition: all 0.2s;
    border: 1px solid transparent;
}
.permission-item:hover {
    background-color: var(--ash);
    border-color: var(--blue);
}

.form-check-input:checked {
    background-color: var(--blue);
    border-color: var(--blue);
}

/* Buttons */
.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 10px 30px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    background-color: #357ABD;
}
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 30px;
    color: var(--black);
    text-decoration: none;
    display: inline-block;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
}
.select-all-btn {
    background: none;
    border: 1px solid var(--blue);
    color: var(--blue);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}
.select-all-btn:hover {
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
            <h4><i class="fa-solid fa-user-gear"></i>User Permissions</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=users">User Management</a></li>
                    <li class="breadcrumb-item active">User Permissions</li>
                </ol>
            </nav>
        </div>
        <a href="?r=users" class="btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Users
        </a>
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

    <!-- User Info Card -->
    <div class="user-info-card">
        <div class="d-flex align-items-center gap-4">
            <div class="user-avatar">
                <i class="fa-regular fa-user"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <h4 class="mb-0"><?= htmlspecialchars($user['name']) ?></h4>
                    <span class="role-badge"><?= htmlspecialchars($user['role_type']) ?></span>
                </div>
                <div class="user-email">
                    <i class="fa-regular fa-envelope me-2"></i><?= htmlspecialchars($user['email']) ?>
                </div>
            </div>
        </div>
    </div>

    <form method="post" id="permissionForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        
        <!-- Quick Actions -->
        <div class="mb-3 d-flex gap-2 flex-wrap">
            <button type="button" class="select-all-btn" onclick="selectAll()">
                <i class="fa-regular fa-square-check me-1"></i> Select All
            </button>
            <button type="button" class="select-all-btn" onclick="deselectAll()">
                <i class="fa-regular fa-square me-1"></i> Deselect All
            </button>
        </div>
        
        <!-- Permissions by Category -->
        <?php if (!empty($grouped_permissions)): ?>
            <?php foreach ($grouped_permissions as $category => $modules): ?>
                <?php foreach ($modules as $module => $permissions): ?>
                    <div class="permission-card">
                        <div class="card-header d-flex justify-content-between align-items-center" onclick="toggleCategory(this)">
                            <div>
                                <span class="category-badge"><?= htmlspecialchars($category) ?></span>
                                <span class="module-badge"><?= htmlspecialchars($module) ?></span>
                                <span class="badge bg-secondary ms-2"><?= count($permissions) ?> permissions</span>
                            </div>
                            <i class="fa-solid fa-chevron-down" style="color: var(--blue);"></i>
                        </div>
                        <div class="card-body" style="display: block;">
                            <div class="row g-3">
                                <?php foreach ($permissions as $p): ?>
                                    <div class="col-md-4 col-lg-3">
                                        <div class="permission-item">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="permissions[]" 
                                                       value="<?= $p['id'] ?>" 
                                                       id="perm_<?= $p['id'] ?>"
                                                       <?= in_array($p['id'], $current_permission_ids) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="perm_<?= $p['id'] ?>">
                                                    <strong><?= htmlspecialchars($p['code']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($p['label']) ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-key fa-4x mb-3" style="color: var(--ash);"></i>
                <h5>No permissions found</h5>
                <p class="text-muted">The permissions table may be empty.</p>
            </div>
        <?php endif; ?>
        
        <!-- Form Actions -->
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-save"></i> Save Permissions
            </button>
            <a href="?r=users" class="btn-outline-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
function toggleCategory(header) {
    const body = header.nextElementSibling;
    const icon = header.querySelector('i:last-child');
    
    if (body && body.classList.contains('card-body')) {
        if (body.style.display === 'none') {
            body.style.display = 'block';
            if (icon) icon.className = 'fa-solid fa-chevron-down';
        } else {
            body.style.display = 'none';
            if (icon) icon.className = 'fa-solid fa-chevron-right';
        }
    }
}

function selectAll() {
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
}

function deselectAll() {
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
}

// Auto-expand categories that have checked permissions
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.permission-card').forEach(card => {
        const checkboxes = card.querySelectorAll('input[type="checkbox"]:checked');
        if (checkboxes.length === 0) {
            const body = card.querySelector('.card-body');
            const icon = card.querySelector('.card-header i:last-child');
            if (body) {
                body.style.display = 'none';
                if (icon) icon.className = 'fa-solid fa-chevron-right';
            }
        }
    });
});
</script>

<?php include __DIR__.'/../templates/footer.php'; ?>