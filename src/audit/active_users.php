<?php
// Enable error reporting for debugging (remove in production)
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

// Verify that the function exists before using it
if (!function_exists('getAllActiveSessions')) {
    die('Critical error: getAllActiveSessions() function not found. Please check auth.php include path.');
}

require_login();
require_permission($pdo, 'menu.audit');

// Get active sessions - FIXED: Don't pass $pdo, just call the function with minutes parameter
$active = getAllActiveSessions(15); // 15 minutes idle threshold

// Calculate idle minutes for each session
foreach ($active as &$session) {
    if (isset($session['last_activity'])) {
        $last_activity = strtotime($session['last_activity']);
        $now = time();
        $idle_seconds = $now - $last_activity;
        $session['idle_minutes'] = round($idle_seconds / 60);
    } else {
        $session['idle_minutes'] = 0;
    }
}

// Get total users count
try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $total_users = 0;
    error_log("Error counting users: " . $e->getMessage());
}

$active_count = count($active);

include __DIR__.'/../templates/header.php';
?>

<style>
/* Color Variables - Solid Colors Only */
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
.page-header h5 {
    color: var(--black);
    font-weight: 600;
}
.page-header h5 i {
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

/* Table Styles */
.table-container {
    overflow-x: auto;
}
.audit-table {
    width: 100%;
    border-collapse: collapse;
}
.audit-table thead th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    font-size: 0.9rem;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 2px solid var(--blue);
}
.audit-table tbody td {
    color: var(--black);
    padding: 12px 16px;
    border-bottom: 1px solid var(--ash);
}
.audit-table tbody tr:hover {
    background-color: var(--ash);
}
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}
.bg-success {
    background-color: #28a745;
    color: white;
}
.bg-warning {
    background-color: #ffc107;
    color: var(--black);
}
.bg-danger {
    background-color: #dc3545;
    color: white;
}
.bg-info {
    background-color: var(--blue);
    color: white;
}

/* Button Styles */
.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
}
.btn-primary:hover {
    background-color: #357ABD;
}
.btn-primary i {
    color: var(--white) !important;
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
    font-size: 0.9rem;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
}
.btn-outline-secondary i {
    color: var(--blue) !important;
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
.empty-state h6 {
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
            <h5><i class="fa-solid fa-user-clock"></i>Active Users</h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=audit">Audit Trail</a></li>
                    <li class="breadcrumb-item active">Active Users</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="?r=audit" class="btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to Audit
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row row g-4">
        <div class="col-md-6">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">TOTAL USERS</div>
                    <div class="stat-value"><?=number_format($total_users)?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">ACTIVE NOW</div>
                    <div class="stat-value"><?=number_format($active_count)?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Sessions Table -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0">
                <i class="fa-solid fa-list text-primary me-2"></i>
                Currently Active Sessions (Last 15 Minutes)
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-container">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Login Time</th>
                            <th>Last Activity</th>
                            <th>Idle (min)</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($active)): ?>
                            <?php foreach ($active as $a): 
                                $idle = $a['idle_minutes'] ?? 0;
                                $badge_class = $idle < 5 ? 'bg-success' : ($idle < 15 ? 'bg-warning' : 'bg-danger');
                            ?>
                            <tr>
                                <td><strong><?=htmlspecialchars($a['name'] ?? $a['user_name'] ?? 'Unknown')?></strong></td>
                                <td><?=isset($a['login_time']) ? (function_exists('format_datetime') ? format_datetime($a['login_time']) : date('Y-m-d H:i:s', strtotime($a['login_time']))) : 'N/A'?></td>
                                <td><?=isset($a['last_activity']) ? (function_exists('format_datetime') ? format_datetime($a['last_activity']) : date('Y-m-d H:i:s', strtotime($a['last_activity']))) : 'N/A'?></td>
                                <td>
                                    <span class="badge <?=$badge_class?>"><?=$idle?> min</span>
                                </td>
                                <td><small><?=htmlspecialchars($a['ip'] ?? 'N/A')?></small></td>
                                <td class="small text-muted" style="max-width: 300px; overflow-x: auto; white-space: nowrap;">
                                    <?=htmlspecialchars($a['user_agent'] ?? 'N/A')?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-user-clock fa-3x"></i>
                                    <h6>No Active Users</h6>
                                    <p>There are no active user sessions at the moment.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>