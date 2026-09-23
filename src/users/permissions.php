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
require_once __DIR__.'/../includes/helpers.php';

require_login();

// Check permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'menu.permissions')) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'You do not have permission to view permissions.'
    ];
    header('Location: ?r=dashboard');
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

/* Page Styles */
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

/* Stat Cards */
.stat-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    padding: 15px;
    transition: all 0.2s;
    height: 100%;
}
.stat-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}
.bg-blue {
    background-color: var(--blue) !important;
}

/* Permission Cards */
.permission-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
}
.permission-card .card-header {
    background-color: var(--ash);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
    transition: all 0.2s;
}
.permission-card .card-header:hover {
    background-color: #e5e7eb;
}
.permission-card .card-body {
    padding: 20px;
}
.permission-item {
    background-color: var(--ash);
    border-radius: 6px;
    padding: 12px;
    transition: all 0.2s;
    height: 100%;
}
.permission-item:hover {
    background-color: var(--blue);
    color: var(--white);
}
.permission-item:hover code,
.permission-item:hover small {
    color: var(--white) !important;
}
.permission-item code {
    font-size: 0.85rem;
    word-break: break-all;
}

/* Badge */
.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fa-solid fa-key"></i>Permissions Overview</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=users">User Management</a></li>
                    <li class="breadcrumb-item active">Permissions</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Role Summary Cards -->
    <div class="row g-4 mb-4">
        <?php if (!empty($roles)): ?>
            <?php foreach ($roles as $role): 
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ?");
                    $stmt->execute([$role['id']]);
                    $count = $stmt->fetchColumn();
                } catch (PDOException $e) {
                    $count = 0;
                }
            ?>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h6 class="mb-2"><?= htmlspecialchars($role['name']) ?></h6>
                    <span class="badge bg-blue text-white"><?= $count ?> permissions</span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-4">
                    <p class="text-muted">No roles found.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Permissions by Category -->
    <?php if (!empty($grouped_permissions)): ?>
        <?php foreach ($grouped_permissions as $category => $modules): ?>
        <div class="permission-card">
            <div class="card-header d-flex justify-content-between align-items-center" onclick="toggleCategory(this)">
                <div class="d-flex align-items-center gap-3">
                    <h6 class="mb-0">
                        <i class="fa-solid fa-folder me-2" style="color: var(--blue);"></i>
                        <?= ucfirst($category) ?> Permissions
                    </h6>
                    <?php 
                    $total = 0;
                    foreach ($modules as $perms) {
                        $total += count($perms);
                    }
                    ?>
                    <span class="badge bg-blue text-white"><?= $total ?> total</span>
                </div>
                <i class="fa-solid fa-chevron-down" style="color: var(--blue);"></i>
            </div>
            <div class="card-body" style="display: block;">
                <?php foreach ($modules as $module => $permissions): ?>
                <div class="mb-4">
                    <h6 class="mb-3 d-flex align-items-center" style="border-bottom: 1px solid var(--ash); padding-bottom: 8px;">
                        <i class="fa-solid fa-cube me-2" style="color: var(--blue);"></i>
                        <?= ucfirst(str_replace('_', ' ', $module)) ?>
                        <span class="badge bg-secondary ms-2"><?= count($permissions) ?></span>
                    </h6>
                    <div class="row g-3">
                        <?php foreach ($permissions as $perm): ?>
                        <div class="col-md-3">
                            <div class="permission-item">
                                <code><?= htmlspecialchars($perm['code']) ?></code>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($perm['label']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fa-solid fa-key fa-4x mb-3" style="color: var(--ash);"></i>
            <h5>No permissions found</h5>
            <p class="text-muted">The permissions table may be empty.</p>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleCategory(header) {
    const body = header.nextElementSibling;
    const icon = header.querySelector('i:last-child');
    
    if (body && body.classList.contains('card-body')) {
        if (body.style.display === 'none' || getComputedStyle(body).display === 'none') {
            body.style.display = 'block';
            if (icon) icon.className = 'fa-solid fa-chevron-down';
        } else {
            body.style.display = 'none';
            if (icon) icon.className = 'fa-solid fa-chevron-right';
        }
    }
}

// Initialize collapsed state for categories with no content
document.addEventListener('DOMContentLoaded', function() {
    // You can add any initialization here if needed
});
</script>

<?php include __DIR__.'/../templates/footer.php'; ?>