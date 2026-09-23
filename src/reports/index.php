<?php
/**
 * reports/index.php - Dashboard Reports Page
 * Shows instant reports in table view with enhanced functionality
 */

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

if (!has_permission($pdo, 'menu.exports')) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'You do not have permission to access reports.'
    ];
    header('Location: ?r=dashboard');
    exit;
}

// Get date range from request
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get report type
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort column to prevent SQL injection
$allowed_sort_columns = ['id', 'name', 'created_at', 'age', 'sex', 'contact_number'];
if (!in_array($sort, $allowed_sort_columns)) {
    $sort = 'id';
}
$order = ($order === 'ASC') ? 'ASC' : 'DESC';

include __DIR__.'/../templates/header.php';
?>

<style>
/* Report Page Styles */
:root {
    --white: #FFFFFF;
    --ash: #F2F4F8;
    --blue: #4A90E2;
    --black: #000000;
}

* {
    font-family: Cambria, serif;
}

.report-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
    transition: all 0.2s;
}
.report-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}
.report-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
}
.report-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.report-card .card-header h6 i {
    color: var(--blue) !important;
}
.report-card .card-body {
    padding: 20px;
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
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}
.report-table thead th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    padding: 10px;
    border-bottom: 2px solid var(--blue);
}
.report-table tbody td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--ash);
}
.report-table tbody tr:hover {
    background-color: var(--ash);
}
.report-table .badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
}
.badge-blue {
    background-color: var(--blue);
    color: var(--white);
}
.badge-ash {
    background-color: var(--ash);
    color: var(--black);
}
.badge-success {
    background-color: #28a745;
    color: white;
}
.badge-warning {
    background-color: #ffc107;
    color: var(--black);
}
.badge-danger {
    background-color: #dc3545;
    color: white;
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
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 6px 12px;
    color: var(--black);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.85rem;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
}

.filter-form {
    background-color: var(--ash);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 10px 0;
}
.pagination {
    display: flex;
    gap: 5px;
}
.page-item {
    list-style: none;
}
.page-link {
    display: block;
    padding: 6px 12px;
    border: 1px solid var(--ash);
    border-radius: 4px;
    color: var(--black);
    text-decoration: none;
    transition: all 0.2s;
}
.page-link:hover {
    background-color: var(--ash);
}
.page-item.active .page-link {
    background-color: var(--blue);
    border-color: var(--blue);
    color: var(--white);
}
.page-item.disabled .page-link {
    opacity: 0.5;
    pointer-events: none;
}

.sort-link {
    color: var(--black);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.sort-link:hover {
    color: var(--blue);
}
.sort-link i {
    color: var(--blue) !important;
    font-size: 0.8rem;
}

.search-box {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}
.search-box input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid var(--ash);
    border-radius: 6px;
    font-family: Cambria, serif;
}
.search-box button {
    background-color: var(--blue);
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--white);
    cursor: pointer;
}

.export-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.table-responsive {
    overflow-x: auto;
}

.text-muted {
    color: var(--black);
    opacity: 0.6;
}

.per-page-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}
.per-page-selector select {
    padding: 4px 8px;
    border: 1px solid var(--ash);
    border-radius: 4px;
    font-family: Cambria, serif;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4><i class="fa-solid fa-chart-line me-2" style="color: var(--blue);"></i>Dashboard Reports</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="?r=reports&report_type=summary&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-outline-primary btn-sm <?= $report_type == 'summary' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-simple"></i> Summary
            </a>
            <a href="?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-outline-primary btn-sm <?= $report_type == 'patients' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i> Patients
            </a>
            <a href="?r=reports&report_type=activity&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-outline-primary btn-sm <?= $report_type == 'activity' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock-rotate-left"></i> Activity
            </a>
            <a href="?r=reports&report_type=modules&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-outline-primary btn-sm <?= $report_type == 'modules' ? 'active' : '' ?>">
                <i class="fa-solid fa-puzzle-piece"></i> Modules
            </a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="filter-form">
        <form method="get" class="row g-3">
            <input type="hidden" name="r" value="reports">
            <input type="hidden" name="report_type" value="<?= $report_type ?>">
            
            <div class="col-md-4">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn-primary w-100">
                    <i class="fa-solid fa-filter"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    <?php if ($report_type == 'summary'): ?>
        <!-- Summary Statistics -->
        <?php
        // Get summary statistics
        $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
        
        $new_patients = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE DATE(created_at) BETWEEN ? AND ?");
        $new_patients->execute([$date_from, $date_to]);
        $new_patients = $new_patients->fetchColumn();
        
        $total_followups = $pdo->prepare("SELECT COUNT(*) FROM followups WHERE DATE(followup_at) BETWEEN ? AND ?");
        $total_followups->execute([$date_from, $date_to]);
        $total_followups = $total_followups->fetchColumn();
        
        $pending_followups = $pdo->query("SELECT COUNT(*) FROM followups WHERE followup_at < NOW() AND followup_at >= CURDATE()")->fetchColumn();
        
        $male_count = $pdo->query("SELECT COUNT(*) FROM patients WHERE sex = 'M'")->fetchColumn();
        $female_count = $pdo->query("SELECT COUNT(*) FROM patients WHERE sex = 'F'")->fetchColumn();
        
        // Get age distribution
        $age_distribution = [
            '0-18' => $pdo->query("SELECT COUNT(*) FROM patients WHERE age <= 18 OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) <= 18)")->fetchColumn(),
            '19-30' => $pdo->query("SELECT COUNT(*) FROM patients WHERE age BETWEEN 19 AND 30 OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 19 AND 30)")->fetchColumn(),
            '31-45' => $pdo->query("SELECT COUNT(*) FROM patients WHERE age BETWEEN 31 AND 45 OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 31 AND 45)")->fetchColumn(),
            '46-60' => $pdo->query("SELECT COUNT(*) FROM patients WHERE age BETWEEN 46 AND 60 OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 46 AND 60)")->fetchColumn(),
            '60+' => $pdo->query("SELECT COUNT(*) FROM patients WHERE age > 60 OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) > 60)")->fetchColumn(),
        ];
        
        // Get module counts
        $module_counts = [
            'ibd_diagnoses' => $pdo->query("SELECT COUNT(*) FROM ibd_diagnoses")->fetchColumn(),
            'complaints' => $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn(),
            'treatments' => $pdo->query("SELECT COUNT(*) FROM treatments")->fetchColumn(),
            'investigations' => $pdo->query("SELECT COUNT(*) FROM investigations")->fetchColumn(),
            'followups' => $pdo->query("SELECT COUNT(*) FROM followups")->fetchColumn(),
            'socioeconomic_histories' => $pdo->query("SELECT COUNT(*) FROM socioeconomic_histories")->fetchColumn(),
            'pregnancies' => $pdo->query("SELECT COUNT(*) FROM pregnancies")->fetchColumn(),
        ];
        ?>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="report-card">
                    <div class="card-body">
                        <div class="stat-value"><?= number_format($total_patients) ?></div>
                        <div class="stat-label">Total Patients</div>
                        <div class="text-muted mt-2">
                            <i class="fa-solid fa-arrow-up me-1" style="color: var(--blue);"></i>
                            +<?= number_format($new_patients) ?> in period
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card">
                    <div class="card-body">
                        <div class="stat-value"><?= number_format($total_followups) ?></div>
                        <div class="stat-label">Follow-ups (Period)</div>
                        <div class="text-muted mt-2">
                            <i class="fa-regular fa-clock me-1" style="color: var(--blue);"></i>
                            <?= number_format($pending_followups) ?> pending
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card">
                    <div class="card-body">
                        <div class="stat-value"><?= number_format($male_count) ?></div>
                        <div class="stat-label">Male Patients</div>
                        <div class="text-muted mt-2">
                            <?= number_format($total_patients > 0 ? ($male_count / $total_patients * 100) : 0, 1) ?>% of total
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card">
                    <div class="card-body">
                        <div class="stat-value"><?= number_format($female_count) ?></div>
                        <div class="stat-label">Female Patients</div>
                        <div class="text-muted mt-2">
                            <?= number_format($total_patients > 0 ? ($female_count / $total_patients * 100) : 0, 1) ?>% of total
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Distribution Tables -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="report-card">
                    <div class="card-header">
                        <h6><i class="fa-solid fa-chart-pie me-2"></i>Age Distribution</h6>
                    </div>
                    <div class="card-body">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Age Range</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($age_distribution as $range => $count): 
                                    $percentage = $total_patients > 0 ? ($count / $total_patients * 100) : 0;
                                ?>
                                <tr>
                                    <td><?= $range ?> years</td>
                                    <td><?= number_format($count) ?></td>
                                    <td><?= number_format($percentage, 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="report-card">
                    <div class="card-header">
                        <h6><i class="fa-solid fa-cube me-2"></i>Module Usage Summary</h6>
                    </div>
                    <div class="card-body">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Total Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($module_counts as $module => $count): ?>
                                <tr>
                                    <td><?= ucwords(str_replace('_', ' ', $module)) ?></td>
                                    <td><?= number_format($count) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Daily Registrations -->
        <div class="report-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-calendar-day me-2"></i>Daily Registrations (Last 7 Days)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Registrations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $daily = $pdo->query("
                                SELECT DATE(created_at) as date, COUNT(*) as count 
                                FROM patients 
                                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                GROUP BY DATE(created_at)
                                ORDER BY date DESC
                            ")->fetchAll();
                            
                            if (!empty($daily)):
                                foreach ($daily as $d):
                            ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($d['date'])) ?></td>
                                <td><?= $d['count'] ?></td>
                            </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted">No registrations in the last 7 days</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'patients'): ?>
        <!-- Detailed Patient Report with Search, Sort, Pagination -->
        <?php
        // Build query with search - FIXED: Removed 'address' column which doesn't exist
        $count_sql = "SELECT COUNT(*) FROM patients WHERE 1=1";
        $data_sql = "SELECT id, name, age, sex, contact_number, ibd_reg_no, created_at, dob, email FROM patients WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $count_sql .= " AND (name LIKE :search OR contact_number LIKE :search OR ibd_reg_no LIKE :search OR email LIKE :search)";
            $data_sql .= " AND (name LIKE :search OR contact_number LIKE :search OR ibd_reg_no LIKE :search OR email LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        // Add date filter
        $count_sql .= " AND DATE(created_at) BETWEEN :date_from AND :date_to";
        $data_sql .= " AND DATE(created_at) BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from;
        $params[':date_to'] = $date_to;
        
        // Get total count
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Get paginated data
        $data_sql .= " ORDER BY $sort $order LIMIT :offset, :per_page";
        
        $data_stmt = $pdo->prepare($data_sql);
        foreach ($params as $key => $value) {
            $data_stmt->bindValue($key, $value);
        }
        $data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $data_stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $data_stmt->execute();
        $patients = $data_stmt->fetchAll();
        ?>
        
        <div class="report-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="fa-solid fa-users me-2"></i>Patient Details Report</h6>
                <span class="badge badge-blue">Total: <?= number_format($total_records) ?></span>
            </div>
            <div class="card-body">
                <!-- Search Box -->
                <div class="search-box">
                    <form method="get" class="d-flex gap-2 w-100">
                        <input type="hidden" name="r" value="reports">
                        <input type="hidden" name="report_type" value="patients">
                        <input type="hidden" name="date_from" value="<?= $date_from ?>">
                        <input type="hidden" name="date_to" value="<?= $date_to ?>">
                        <input type="hidden" name="page" value="1">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, contact, ID, email..." class="form-control">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-search"></i> Search
                        </button>
                        <?php if (!empty($search)): ?>
                        <a href="?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-outline-secondary">
                            <i class="fa-solid fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Export Buttons -->
                <div class="export-buttons">
                    <a href="../export_patients.php?format=csv&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>" class="btn-outline-primary btn-sm">
                        <i class="fa-solid fa-file-excel"></i> Export CSV
                    </a>
                    <a href="../export_patients.php?format=pdf&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>" class="btn-outline-primary btn-sm">
                        <i class="fa-solid fa-file-pdf"></i> Export PDF
                    </a>
                </div>
                
                <!-- Table -->
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&sort=id&order=<?= $sort == 'id' && $order == 'DESC' ? 'ASC' : 'DESC' ?>" class="sort-link">
                                        ID <i class="fa-solid fa-sort"></i>
                                    </a>
                                </th>
                                <th>
                                    <a href="?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&sort=name&order=<?= $sort == 'name' && $order == 'DESC' ? 'ASC' : 'DESC' ?>" class="sort-link">
                                        Name <i class="fa-solid fa-sort"></i>
                                    </a>
                                </th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Contact</th>
                                <th>IBD Reg No</th>
                                <th>Email</th>
                                <th>
                                    <a href="?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&sort=created_at&order=<?= $sort == 'created_at' && $order == 'DESC' ? 'ASC' : 'DESC' ?>" class="sort-link">
                                        Registered <i class="fa-solid fa-sort"></i>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($patients)): ?>
                                <?php foreach ($patients as $p): ?>
                                <tr>
                                    <td>#<?= $p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= $p['age'] ?? 'N/A' ?></td>
                                    <td>
                                        <span class="badge <?= $p['sex'] == 'M' ? 'badge-blue' : 'badge-ash' ?>">
                                            <?= $p['sex'] == 'M' ? 'Male' : 'Female' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($p['contact_number'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($p['ibd_reg_no'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($p['email'] ?? 'N/A') ?></td>
                                    <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No patients found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="per-page-selector">
                        <span>Show</span>
                        <select onchange="window.location.href='?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&per_page='+this.value">
                            <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                            <option value="250" <?= $per_page == 250 ? 'selected' : '' ?>>250</option>
                        </select>
                        <span>per page</span>
                    </div>
                    
                    <div class="text-muted">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_records) ?> of <?= $total_records ?> records
                    </div>
                    
                    <ul class="pagination">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&per_page=<?= $per_page ?>&page=<?= $page-1 ?>">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?r=reports&report_type=patients&date_from='.$date_from.'&date_to='.$date_to.'&search='.urlencode($search).'&per_page='.$per_page.'&page=1">1</a></li>';
                            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&per_page=<?= $per_page ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="?r=reports&report_type=patients&date_from='.$date_from.'&date_to='.$date_to.'&search='.urlencode($search).'&per_page='.$per_page.'&page='.$total_pages.'">'.$total_pages.'</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?r=reports&report_type=patients&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&per_page=<?= $per_page ?>&page=<?= $page+1 ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php elseif ($report_type == 'activity'): ?>
        <!-- Activity Log Report with Pagination -->
        <?php
        // Build query
        $count_sql = "SELECT COUNT(*) FROM audit_logs a WHERE DATE(a.created_at) BETWEEN :date_from AND :date_to";
        $data_sql = "SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE DATE(a.created_at) BETWEEN :date_from AND :date_to ORDER BY a.id DESC";
        
        $params = [
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];
        
        // Get total count
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Get paginated data
        $data_sql .= " LIMIT :offset, :per_page";
        $data_stmt = $pdo->prepare($data_sql);
        $data_stmt->bindValue(':date_from', $date_from);
        $data_stmt->bindValue(':date_to', $date_to);
        $data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $data_stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $data_stmt->execute();
        $logs = $data_stmt->fetchAll();
        ?>
        
        <div class="report-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="fa-solid fa-clock-rotate-left me-2"></i>Activity Log Report</h6>
                <span class="badge badge-blue">Total: <?= number_format($total_records) ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Entity</th>
                                <th>Entity ID</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                                    <td>
                                        <span class="badge <?= 
                                            strpos($log['action'], 'delete') !== false ? 'badge-danger' : 
                                            (strpos($log['action'], 'add') !== false ? 'badge-success' : 
                                            (strpos($log['action'], 'update') !== false ? 'badge-warning' : 'badge-ash')) ?>">
                                            <?= $log['action'] ?>
                                        </span>
                                    </td>
                                    <td><?= $log['entity'] ?? '-' ?></td>
                                    <td><?= $log['entity_id'] ?? '-' ?></td>
                                    <td><small><?= htmlspecialchars($log['ip'] ?? 'N/A') ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No activity logs found for this period</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="per-page-selector">
                        <span>Show</span>
                        <select onchange="window.location.href='?r=reports&report_type=activity&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&per_page='+this.value">
                            <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                            <option value="250" <?= $per_page == 250 ? 'selected' : '' ?>>250</option>
                        </select>
                    </div>
                    
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?r=reports&report_type=activity&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&per_page=<?= $per_page ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php elseif ($report_type == 'modules'): ?>
        <!-- Module Usage Report -->
        <div class="report-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-puzzle-piece me-2"></i>Module Usage Report</h6>
            </div>
            <div class="card-body">
                <?php
                $modules = [
                    'ibd_diagnoses' => 'IBD Diagnoses',
                    'complaints' => 'Complaints',
                    'treatments' => 'Treatments',
                    'investigations' => 'Investigations',
                    'followups' => 'Follow-ups',
                    'socioeconomic_histories' => 'Socioeconomic History',
                    'pregnancies' => 'Pregnancy',
                    'drug_histories' => 'Drug History',
                    'current_histories' => 'Current History'
                ];
                ?>
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Total Records</th>
                                <th>Patients with Records</th>
                                <th>Records (Period)</th>
                                <th>% of Patients</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
                            
                            foreach ($modules as $table => $label): 
                                // Check if table exists
                                $check = $pdo->query("SHOW TABLES LIKE '$table'");
                                if ($check->rowCount() == 0) continue;
                                
                                $total = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                                
                                $patients = $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM $table")->fetchColumn();
                                
                                $period = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE DATE(created_at) BETWEEN ? AND ?");
                                $period->execute([$date_from, $date_to]);
                                $period_count = $period->fetchColumn();
                                
                                $percentage = $total_patients > 0 ? round(($patients / $total_patients * 100), 1) : 0;
                            ?>
                            <tr>
                                <td><strong><?= $label ?></strong></td>
                                <td><?= number_format($total) ?></td>
                                <td><?= number_format($patients) ?></td>
                                <td><?= number_format($period_count) ?></td>
                                <td><?= $percentage ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>