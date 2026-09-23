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
require_permission($pdo, 'menu.audit');

// Get filter parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$sql = "SELECT a.*, u.name as user_name 
        FROM audit_logs a 
        LEFT JOIN users u ON u.id = a.user_id 
        WHERE 1=1";
$params = [];

if ($user_id) {
  $sql .= " AND a.user_id = ?";
  $params[] = $user_id;
}

if ($action) {
  $sql .= " AND a.action LIKE ?";
  $params[] = "%$action%";
}

if ($date_from) {
  $sql .= " AND DATE(a.created_at) >= ?";
  $params[] = $date_from;
}

if ($date_to) {
  $sql .= " AND DATE(a.created_at) <= ?";
  $params[] = $date_to;
}

$sql .= " ORDER BY a.id DESC LIMIT 500";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching audit logs: " . $e->getMessage());
    $logs = [];
}

// Get users for filter dropdown
$users = [];
try {
    $users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

// Get distinct actions for filter
$actions = [];
try {
    $actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching actions: " . $e->getMessage());
}

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
.form-label {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.8rem;
    margin-bottom: 4px;
}
.form-control, .form-select {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 12px;
    color: var(--black);
}
.form-control:focus, .form-select:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
}
.btn-filter {
    background-color: var(--blue);
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    width: 100%;
}
.btn-filter:hover {
    background-color: #357ABD;
}
.btn-filter i {
    color: var(--white) !important;
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
.btn-active {
    background-color: transparent;
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 6px 12px;
    color: var(--blue);
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-active:hover {
    background-color: var(--blue);
    color: var(--white);
}
.btn-active:hover i {
    color: var(--white) !important;
}
.btn-active i {
    color: var(--blue) !important;
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
    font-size: 0.85rem;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 2px solid var(--blue);
}
.audit-table tbody td {
    color: var(--black);
    padding: 12px 16px;
    border-bottom: 1px solid var(--ash);
    font-size: 0.9rem;
}
.audit-table tbody tr:hover {
    background-color: var(--ash);
}
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}
.badge-success {
    background-color: #28a745;
    color: white;
}
.badge-danger {
    background-color: #dc3545;
    color: white;
}
.badge-info {
    background-color: var(--blue);
    color: white;
}
.badge-warning {
    background-color: #ffc107;
    color: var(--black);
}
.details-preview {
    max-width: 250px;
    overflow-x: auto;
    white-space: pre-wrap;
    font-size: 0.8rem;
    background-color: var(--ash);
    padding: 6px 8px;
    border-radius: 4px;
    margin: 0;
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
            <h5><i class="fa-solid fa-clipboard-list"></i>Audit Trail</h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Audit Trail</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="?r=audit/active" class="btn-active">
                <i class="fa-solid fa-user-clock"></i> View Active Users
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-filter me-2"></i>Filter Logs</h6>
        </div>
        <div class="card-body">
            <form method="get">
                <input type="hidden" name="r" value="audit">
                
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?=$u['id']?>" <?=$user_id == $u['id'] ? 'selected' : ''?>>
                                    <?=htmlspecialchars($u['name'])?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Action</label>
                        <select name="action" class="form-select">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $a): ?>
                                <option value="<?=$a['action']?>" <?=$action == $a['action'] ? 'selected' : ''?>>
                                    <?=htmlspecialchars($a['action'])?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($date_from)?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($date_to)?>">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn-filter">
                            <i class="fa-solid fa-search me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="results-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6><i class="fa-solid fa-list me-2"></i>Audit Logs</h6>
            <span class="text-muted small">
                Showing <?=count($logs)?> of 500 most recent records
            </span>
        </div>
        <div class="table-container">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Entity ID</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $l): 
                            $badge_class = 'badge-info';
                            if (strpos($l['action'], 'delete') !== false) {
                                $badge_class = 'badge-danger';
                            } elseif (strpos($l['action'], 'add') !== false || strpos($l['action'], 'create') !== false) {
                                $badge_class = 'badge-success';
                            } elseif (strpos($l['action'], 'update') !== false || strpos($l['action'], 'edit') !== false) {
                                $badge_class = 'badge-warning';
                            }
                        ?>
                        <tr>
                            <td class="fw-bold">#<?=$l['id']?></td>
                            <td><?=function_exists('format_datetime') ? format_datetime($l['created_at']) : date('Y-m-d H:i:s', strtotime($l['created_at']))?></td>
                            <td><?=htmlspecialchars($l['user_name'] ?? 'System')?></td>
                            <td>
                                <span class="badge <?=$badge_class?>">
                                    <?=htmlspecialchars($l['action'])?>
                                </span>
                            </td>
                            <td><?=htmlspecialchars($l['entity'] ?? '-')?></td>
                            <td><?=$l['entity_id'] ?? '-'?></td>
                            <td>
                                <?php if (!empty($l['details'])): ?>
                                    <?php 
                                    $details = json_decode($l['details'], true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($details)) {
                                        $preview = json_encode($details);
                                    } else {
                                        $preview = $l['details'];
                                    }
                                    ?>
                                    <pre class="details-preview"><?=htmlspecialchars(substr($preview, 0, 100))?><?=strlen($preview) > 100 ? '...' : ''?></pre>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><small><?=htmlspecialchars($l['ip'] ?? '-')?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fa-solid fa-clipboard-list fa-3x"></i>
                                <h6>No Audit Logs Found</h6>
                                <p>Try adjusting your filter criteria</p>
                                <a href="?r=audit" class="btn-active">
                                    <i class="fa-solid fa-times me-2"></i>Clear Filters
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>