<?php
/**
 * patients/followup.php - Follow-up Patient Management
 * List and manage patient follow-ups with actionable items
 */

require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../includes/helpers.php';

require_login();
require_permission($pdo, 'menu.patients');

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query for counting
$count_sql = "SELECT COUNT(DISTINCT f.id) 
              FROM followups f
              JOIN patients p ON p.id = f.patient_id
              WHERE 1=1";
$data_sql = "SELECT f.*, p.name as patient_name, p.sex, p.contact_number,
                    TIMESTAMPDIFF(DAY, NOW(), f.followup_at) as days_difference
             FROM followups f
             JOIN patients p ON p.id = f.patient_id
             WHERE 1=1";
$params = [];
$count_params = [];

// Apply filters
if (!empty($search)) {
    $count_sql .= " AND (p.name LIKE ? OR p.id LIKE ? OR f.description LIKE ?)";
    $data_sql .= " AND (p.name LIKE ? OR p.id LIKE ? OR f.description LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
}

if ($status == 'upcoming') {
    $count_sql .= " AND f.followup_at >= NOW()";
    $data_sql .= " AND f.followup_at >= NOW()";
} elseif ($status == 'overdue') {
    $count_sql .= " AND f.followup_at < NOW()";
    $data_sql .= " AND f.followup_at < NOW()";
} elseif ($status == 'today') {
    $count_sql .= " AND DATE(f.followup_at) = CURDATE()";
    $data_sql .= " AND DATE(f.followup_at) = CURDATE()";
} elseif ($status == 'week') {
    $count_sql .= " AND WEEK(f.followup_at) = WEEK(NOW()) AND YEAR(f.followup_at) = YEAR(NOW())";
    $data_sql .= " AND WEEK(f.followup_at) = WEEK(NOW()) AND YEAR(f.followup_at) = YEAR(NOW())";
}

if (!empty($date_from)) {
    $count_sql .= " AND DATE(f.followup_at) >= ?";
    $data_sql .= " AND DATE(f.followup_at) >= ?";
    $params[] = $date_from;
    $count_params[] = $date_from;
}
if (!empty($date_to)) {
    $count_sql .= " AND DATE(f.followup_at) <= ?";
    $data_sql .= " AND DATE(f.followup_at) <= ?";
    $params[] = $date_to;
    $count_params[] = $date_to;
}

// Add ORDER BY and LIMIT to data query
$data_sql .= " ORDER BY f.followup_at ASC LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset;

// Get total count
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Get follow-up data - execute with only filter parameters (LIMIT/OFFSET are in SQL string)
$stmt = $pdo->prepare($data_sql);
$stmt->execute($params);
$followups = $stmt->fetchAll();

// Get statistics
$total_upcoming = $pdo->query("SELECT COUNT(*) FROM followups WHERE followup_at >= NOW()")->fetchColumn();
$total_overdue = $pdo->query("SELECT COUNT(*) FROM followups WHERE followup_at < NOW()")->fetchColumn();
$total_today = $pdo->query("SELECT COUNT(*) FROM followups WHERE DATE(followup_at) = CURDATE()")->fetchColumn();

include __DIR__.'/../templates/header.php';
?>

<style>
:root {
    --white: #FFFFFF;
    --ash: #F2F4F8;
    --blue: #4A90E2;
    --green: #28a745;
    --red: #dc3545;
    --black: #000000;
}

* {
    font-family: Cambria, serif;
}

.page-header {
    margin-bottom: 24px;
}

.stat-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    padding: 20px;
    transition: all 0.2s;
    height: 100%;
}
.stat-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--black);
}
.stat-label {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.85rem;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-block;
}
.status-upcoming {
    background-color: var(--blue);
    color: var(--white);
}
.status-overdue {
    background-color: var(--red);
    color: var(--white);
}
.status-today {
    background-color: var(--green);
    color: var(--white);
}

.filter-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
}
.filter-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
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

.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--white);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: Cambria, serif;
}
.btn-primary:hover {
    background-color: #357ABD;
}
.btn-outline-primary {
    background-color: transparent;
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 6px 12px;
    color: var(--blue);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.85rem;
    font-family: Cambria, serif;
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

.table {
    width: 100%;
    border-collapse: collapse;
}
.table thead th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    padding: 12px;
    border-bottom: 2px solid var(--blue);
}
.table tbody td {
    padding: 12px;
    border-bottom: 1px solid var(--ash);
    color: var(--black);
}
.table tbody tr:hover {
    background-color: var(--ash);
}

.pagination {
    display: flex;
    gap: 5px;
    list-style: none;
    padding: 0;
    margin: 0;
}
.page-item .page-link {
    border: 1px solid var(--ash);
    border-radius: 4px;
    padding: 6px 12px;
    color: var(--black);
    text-decoration: none;
    background-color: var(--white);
}
.page-item.active .page-link {
    background-color: var(--blue);
    color: var(--white);
    border-color: var(--blue);
}
.page-item.disabled .page-link {
    opacity: 0.5;
    pointer-events: none;
}

.breadcrumb {
    background: transparent;
    padding: 0;
}
.breadcrumb-item a {
    color: var(--blue);
    text-decoration: none;
}
.breadcrumb-item.active {
    color: var(--black);
    opacity: 0.6;
}

.form-control, .form-select {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 12px;
}
.form-control:focus, .form-select:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fa-solid fa-calendar-check me-2" style="color: var(--blue);"></i>Follow-up Patients</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard/index">Dashboard</a></li>
                    <li class="breadcrumb-item active">Follow-up Patients</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="?r=patients/manage" class="btn-outline-primary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to Patients
            </a>
            <a href="?r=modules/followup/add" class="btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> New Follow-up
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Follow-ups</div>
                        <div class="stat-value"><?= number_format($total_records) ?></div>
                        <small class="text-muted">All records</small>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar-check fa-3x" style="color: var(--blue); opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Upcoming</div>
                        <div class="stat-value" style="color: var(--blue);"><?= number_format($total_upcoming) ?></div>
                        <small class="text-muted">Future appointments</small>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-clock fa-3x" style="color: var(--blue); opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Overdue</div>
                        <div class="stat-value" style="color: var(--red);"><?= number_format($total_overdue) ?></div>
                        <small class="text-muted">Past due dates</small>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-exclamation-triangle fa-3x" style="color: var(--red); opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Today</div>
                        <div class="stat-value" style="color: var(--green);"><?= number_format($total_today) ?></div>
                        <small class="text-muted">Due today</small>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar-day fa-3x" style="color: var(--green); opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-filter me-2"></i>Filter Follow-ups</h6>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3" id="filterForm">
                <input type="hidden" name="r" value="patients/followup">
                
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Patient name, ID, description..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>All</option>
                        <option value="upcoming" <?= $status == 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="overdue" <?= $status == 'overdue' ? 'selected' : '' ?>>Overdue</option>
                        <option value="today" <?= $status == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $status == 'week' ? 'selected' : '' ?>>This Week</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn-primary w-100">
                        <i class="fa-solid fa-search"></i> Apply Filters
                    </button>
                    <a href="?r=patients/followup" class="btn-outline-primary ms-2">
                        <i class="fa-solid fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Follow-ups Table -->
    <div class="filter-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-list me-2"></i>Follow-up List</h6>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Follow-up Date</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Contact</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($followups)): ?>
                        <?php foreach ($followups as $f): 
                            $status_class = 'status-upcoming';
                            $status_text = 'Upcoming';
                            
                            if (strtotime($f['followup_at']) < time()) {
                                $status_class = 'status-overdue';
                                $status_text = 'Overdue';
                            } elseif (date('Y-m-d', strtotime($f['followup_at'])) == date('Y-m-d')) {
                                $status_class = 'status-today';
                                $status_text = 'Today';
                            }
                            
                            $days = $f['days_difference'];
                            if ($days < 0) {
                                $days_text = abs($days) . ' days ago';
                            } elseif ($days == 0) {
                                $days_text = 'Today';
                            } else {
                                $days_text = 'in ' . $days . ' days';
                            }
                        ?>
                        <tr>
                            <td><strong>#<?= $f['id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($f['patient_name']) ?></strong>
                                <br><small class="text-muted">ID: <?= $f['patient_id'] ?> | <?= $f['sex'] == 'M' ? 'Male' : 'Female' ?></small>
                            </td>
                            <td>
                                <strong><?= date('M d, Y', strtotime($f['followup_at'])) ?></strong>
                                <br><small class="text-muted"><?= date('h:i A', strtotime($f['followup_at'])) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($f['description'])): ?>
                                    <?= htmlspecialchars(substr($f['description'], 0, 50)) ?>
                                    <?= strlen($f['description']) > 50 ? '...' : '' ?>
                                <?php else: ?>
                                    <em class="text-muted">No description</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= $status_text ?>
                                </span>
                                <br><small class="text-muted"><?= $days_text ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($f['contact_number'] ?? 'N/A') ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="?r=modules/followup/view&id=<?= $f['id'] ?>" class="btn-outline-primary btn-sm" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="?r=modules/followup/add&patient_id=<?= $f['patient_id'] ?>" class="btn-outline-primary btn-sm" title="Add Another">
                                        <i class="fa-solid fa-plus"></i>
                                    </a>
                                    <a href="?r=patients/view&id=<?= $f['patient_id'] ?>" class="btn-outline-primary btn-sm" title="View Patient">
                                        <i class="fa-solid fa-user"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fa-solid fa-calendar-check fa-3x mb-3" style="color: var(--ash);"></i>
                                <h5 class="text-muted">No follow-ups found</h5>
                                <p class="small text-muted">Try adjusting your search filters or create a new follow-up</p>
                                <a href="?r=modules/followup/add" class="btn-primary btn-sm mt-2">
                                    <i class="fa-solid fa-plus"></i> New Follow-up
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="small text-muted">
            Showing page <?= $page ?> of <?= $total_pages ?> (<?= $total_records ?> total records)
        </div>
        <nav>
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?r=patients/followup&page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?r=patients/followup&page=1&search=' . urlencode($search) . '&status=' . $status . '&date_from=' . $date_from . '&date_to=' . $date_to . '">1</a></li>';
                    if ($start > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                for ($i = $start; $i <= $end; $i++): 
                ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?r=patients/followup&page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?r=patients/followup&page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $total_pages ?></a></li>
                <?php endif; ?>
                
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?r=patients/followup&page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-submit form when filters change
document.querySelectorAll('#filterForm select, #filterForm input[type="date"]').forEach(el => {
    el.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Debounce search input
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, 500);
});
</script>

<?php include __DIR__.'/../templates/footer.php'; ?>