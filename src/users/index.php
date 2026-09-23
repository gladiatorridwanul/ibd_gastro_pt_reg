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

if (!has_permission($pdo, 'menu.users')) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'You do not have permission to access user management.'
    ];
    header('Location: ?r=dashboard');
    exit;
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND logout_time IS NULL) as active_sessions,
        (SELECT COUNT(*) FROM user_permissions WHERE user_id = u.id) as custom_permissions
        FROM users u 
        WHERE 1=1";
$params = [];

if ($role_filter) {
    $sql .= " AND u.role_type = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'active') {
    $sql .= " AND u.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $sql .= " AND u.is_active = 0";
}

if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY u.id DESC";

// Get users
$users = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

// Get all roles for filter
$roles = [];
try {
    $roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
}

// Get statistics
$total_users = 0;
$active_users = 0;
$online_users = 0;
$admin_count = 0;

try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $online_users = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE logout_time IS NULL")->fetchColumn();
    $admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role_type = 'Admin'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
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
.content-wrapper {
    color: var(--black);
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

/* Stats Cards */
.stats-row {
    margin-bottom: 24px;
}
.stat-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    padding: 20px;
    transition: all 0.2s ease;
    height: 100%;
}
.stat-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background-color: var(--ash);
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-icon i {
    color: var(--blue) !important;
    font-size: 1.8rem;
}
.stat-label {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-value {
    color: var(--black);
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
}

/* Filter Card */
.filter-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 24px;
}
.filter-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.filter-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.filter-card .card-header h6 i {
    color: var(--blue) !important;
}
.filter-card .card-body {
    padding: 20px;
}
.form-select, .form-control {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 12px;
    color: var(--black);
}
.form-select:focus, .form-control:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
}
.btn-primary {
    background-color: var(--blue);
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-primary:hover {
    background-color: #357ABD;
    color: var(--white);
}
.btn-primary i {
    color: var(--white) !important;
}
.btn-outline-primary {
    background-color: transparent;
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--blue);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-outline-primary:hover {
    background-color: var(--blue);
    color: var(--white);
}
.btn-outline-primary:hover i {
    color: var(--white) !important;
}
.btn-outline-primary i {
    color: var(--blue) !important;
}
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--black);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
    color: var(--black);
}
.btn-outline-secondary i {
    color: var(--blue) !important;
}

/* Results Card */
.results-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
}
.results-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.results-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.results-card .card-header h6 i {
    color: var(--blue) !important;
}
.record-count {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.9rem;
}
.btn-add {
    background-color: var(--blue);
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--white);
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.btn-add:hover {
    background-color: #357ABD;
    color: var(--white);
}
.btn-add i {
    color: var(--white) !important;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
}
.users-table {
    width: 100%;
    border-collapse: collapse;
}
.users-table thead th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    font-size: 0.9rem;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 2px solid var(--blue);
}
.users-table tbody td {
    color: var(--black);
    padding: 12px 16px;
    border-bottom: 1px solid var(--ash);
}
.users-table tbody tr:hover {
    background-color: var(--ash);
}
.role-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}
.role-badge.admin {
    background-color: var(--blue);
    color: var(--white);
}
.role-badge.doctor {
    background-color: var(--ash);
    color: var(--black);
}
.role-badge.medical-staff {
    background-color: var(--ash);
    color: var(--black);
}
.role-badge.receptionist {
    background-color: var(--ash);
    color: var(--black);
}
.role-badge.viewer {
    background-color: var(--ash);
    color: var(--black);
}
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}
.status-badge.active {
    background-color: var(--blue);
    color: var(--white);
}
.status-badge.inactive {
    background-color: var(--ash);
    color: var(--black);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
}
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    text-decoration: none;
    background-color: var(--ash);
}
.action-btn i {
    color: var(--blue) !important;
    font-size: 0.9rem;
    transition: color 0.2s;
}
.action-btn:hover {
    background-color: var(--blue);
}
.action-btn:hover i {
    color: var(--white) !important;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}
.empty-state i {
    color: var(--blue) !important;
    opacity: 0.3;
    font-size: 4rem;
    margin-bottom: 16px;
}
.empty-state h5 {
    color: var(--black);
    margin-bottom: 8px;
}
.empty-state p {
    color: var(--black);
    opacity: 0.6;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fa-solid fa-users-gear"></i>User Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">User Management</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <?php if (has_permission($pdo, 'button.role.create')): ?>
            <a href="?r=users/roles" class="btn-outline-primary btn-sm">
                <i class="fa-solid fa-users-between-lines"></i> Manage Roles
            </a>
            <?php endif; ?>
            <?php if (has_permission($pdo, 'button.user.create')): ?>
            <a href="?r=users/form" class="btn-primary btn-sm">
                <i class="fa-solid fa-user-plus"></i> Add User
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-row row g-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">TOTAL USERS</div>
                    <div class="stat-value"><?= number_format($total_users) ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">ACTIVE USERS</div>
                    <div class="stat-value"><?= number_format($active_users) ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">ONLINE NOW</div>
                    <div class="stat-value"><?= number_format($online_users) ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">ADMIN USERS</div>
                    <div class="stat-value"><?= number_format($admin_count) ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-filter me-2"></i>Filter Users</h6>
        </div>
        <div class="card-body">
            <form method="get" id="filterForm" class="row g-3">
                <input type="hidden" name="r" value="users">
                
                <div class="col-md-4">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['name']) ?>" <?= (isset($_GET['role']) && $_GET['role'] == $role['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?= (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-search"></i>
                        </button>
                        <a href="?r=users" class="btn-outline-secondary">
                            <i class="fa-solid fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Card -->
    <div class="results-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6><i class="fa-solid fa-list me-2"></i>User List</h6>
            <span class="record-count">
                Showing <?= count($users) ?> users
            </span>
        </div>
        
        <div class="table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Sessions</th>
                        <th>Custom Permissions</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): 
                            $role_class = strtolower(str_replace(' ', '-', $u['role_type']));
                        ?>
                        <tr>
                            <td class="fw-bold">#<?= $u['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fa-regular fa-circle-user me-2" style="color: var(--blue);"></i>
                                    <?= htmlspecialchars($u['name']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="role-badge <?= $role_class ?>">
                                    <?= htmlspecialchars($u['role_type']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= $u['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['active_sessions'] > 0): ?>
                                    <span class="badge" style="background-color: var(--blue); color: var(--white);">
                                        <i class="fa-solid fa-circle me-1" style="font-size: 0.5rem;"></i>
                                        <?= $u['active_sessions'] ?> online
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background-color: var(--ash); color: var(--black);">
                                        Offline
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['custom_permissions'] > 0): ?>
                                    <span class="badge" style="background-color: var(--blue); color: var(--white);">
                                        <?= $u['custom_permissions'] ?> overrides
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background-color: var(--ash); color: var(--black);">
                                        Role based
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (has_permission($pdo, 'button.user.edit')): ?>
                                    <a href="?r=users/form&id=<?= $u['id'] ?>" class="action-btn" title="Edit User">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (has_permission($pdo, 'button.user.permissions') && $u['role_type'] !== 'Admin'): ?>
                                    <a href="?r=users/user_permissions&id=<?= $u['id'] ?>" class="action-btn" title="Manage User Permissions">
                                        <i class="fa-solid fa-user-gear"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fa-solid fa-users-slash fa-3x"></i>
                                <h5>No users found</h5>
                                <p>Try adjusting your search filters</p>
                                <a href="?r=users" class="btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-times me-2"></i>Clear All Filters
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Auto-submit form when filters change
document.querySelectorAll('#filterForm select').forEach(function(el) {
    el.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Debounce search input
let searchTimeout;
const searchInput = document.querySelector('input[name="search"]');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500);
    });
}
</script>

<?php include __DIR__.'/../templates/footer.php'; ?>