<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../includes/helpers.php'; // This already has e() function

// Check if user is logged in
if (!function_exists('require_login')) {
    die('Authentication system not loaded properly.');
}
require_login();

// Check permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'menu.patients')) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'You do not have permission to view patients.'
    ];
    header('Location: ?r=dashboard');
    exit;
}

// REMOVED: The duplicate e() function declaration
// The e() function is already defined in helpers.php

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get filter parameters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build base SQL query for counting total records
$count_sql = "SELECT COUNT(*) FROM patients WHERE 1=1";
$data_sql = "SELECT id, name, sex, dob, age, created_at FROM patients WHERE 1=1";
$params = [];

// Search by category
if ($q !== '') {
    switch ($category) {
        case 'name':
            $count_sql .= " AND name LIKE :q";
            $data_sql .= " AND name LIKE :q";
            $params[':q'] = "%{$q}%";
            break;
        case 'id':
            if (ctype_digit($q)) {
                $count_sql .= " AND id = :idq";
                $data_sql .= " AND id = :idq";
                $params[':idq'] = (int)$q;
            }
            break;
        case 'dob':
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $q)) {
                $count_sql .= " AND dob = :dob";
                $data_sql .= " AND dob = :dob";
                $params[':dob'] = $q;
            }
            break;
        case 'all':
        default:
            $count_sql .= " AND (name LIKE :q OR CAST(id AS CHAR) LIKE :q)";
            $data_sql .= " AND (name LIKE :q OR CAST(id AS CHAR) LIKE :q)";
            $params[':q'] = "%{$q}%";
            break;
    }
}

// Date range filter
if ($date_from !== '') {
    $count_sql .= " AND DATE(created_at) >= :date_from";
    $data_sql .= " AND DATE(created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $count_sql .= " AND DATE(created_at) <= :date_to";
    $data_sql .= " AND DATE(created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$data_sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';

// Get total count for pagination
try {
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    error_log("Database error in count query: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// Execute main query with pagination
$rows = [];
try {
    $stmt = $pdo->prepare($data_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in data query: " . $e->getMessage());
}

// Get statistics
$total_patients = 0;
$male_count = 0;
$female_count = 0;
$total_vaccines = 0;

try {
    $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $male_count = $pdo->query("SELECT COUNT(*) FROM patients WHERE sex = 'M'")->fetchColumn();
    $female_count = $pdo->query("SELECT COUNT(*) FROM patients WHERE sex = 'F'")->fetchColumn();
    $total_vaccines = $pdo->query("SELECT COUNT(*) FROM patient_vaccines")->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error in statistics: " . $e->getMessage());
}

// Build query string for pagination links
$query_params = $_GET;
unset($query_params['page']);
$query_string = http_build_query($query_params);
if ($query_string) $query_string .= '&';

// Include header
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
.page-header h4 {
    color: var(--black);
    font-weight: 600;
}
.page-header h4 i {
    color: var(--blue) !important;
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
.stat-trend {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.8rem;
}
.stat-trend i {
    color: var(--blue) !important;
}

/* Search Card */
.search-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 24px;
}
.search-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.search-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.search-card .card-header h6 i {
    color: var(--blue) !important;
}
.search-card .card-body {
    padding: 20px;
}
.form-select, .form-control {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 12px;
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
    padding: 10px 20px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    background-color: #357ABD;
    color: var(--white);
}
.btn-primary i {
    color: var(--white) !important;
}
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--black);
    transition: all 0.2s;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
    color: var(--black);
}
.btn-outline-secondary i {
    color: var(--blue) !important;
}
.date-filter label {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.8rem;
    margin-bottom: 4px;
}
.date-filter input {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 12px;
    color: var(--black);
}

/* Results Card */
.results-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 24px;
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
.patients-table {
    width: 100%;
    border-collapse: collapse;
}
.patients-table thead th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    font-size: 0.9rem;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 2px solid var(--blue);
}
.patients-table tbody td {
    color: var(--black);
    padding: 12px 16px;
    border-bottom: 1px solid var(--ash);
}
.patients-table tbody tr:hover {
    background-color: var(--ash);
}
.patients-table .badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}
.badge-male {
    background-color: var(--ash);
    color: var(--black);
}
.badge-female {
    background-color: var(--ash);
    color: var(--black);
}
.badge-age {
    background-color: var(--ash);
    color: var(--black);
}
.gender-icon {
    width: 24px;
    text-align: center;
}
.gender-icon i {
    color: var(--blue) !important;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    flex-wrap: wrap;
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

/* Vaccine Badge in Action Buttons - New Addition */
.vaccine-badge {
    position: relative;
}
.vaccine-count {
    position: absolute;
    top: -6px;
    right: -6px;
    background-color: #dc3545;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    font-size: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* Pagination */
.pagination-container {
    padding: 16px 20px;
    border-top: 1px solid var(--ash);
}
.pagination {
    margin: 0;
    gap: 4px;
}
.page-item .page-link {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 4px;
    padding: 8px 14px;
    color: var(--black);
    background-color: var(--white);
    text-decoration: none;
    transition: all 0.2s;
}
.page-item .page-link i {
    color: var(--blue) !important;
}
.page-item.active .page-link {
    background-color: var(--blue);
    border-color: var(--blue);
    color: var(--white);
}
.page-item.active .page-link i {
    color: var(--white) !important;
}
.page-item.disabled .page-link {
    opacity: 0.5;
    pointer-events: none;
}
.page-item .page-link:hover:not(.active) {
    background-color: var(--ash);
    border-color: var(--ash);
    color: var(--black);
}
.page-info {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.9rem;
}

/* Export Card */
.export-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
}
.export-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.export-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.export-card .card-header h6 i {
    color: var(--blue) !important;
}
.export-card .card-body {
    padding: 20px;
}
.export-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.btn-export {
    background-color: var(--blue);
    border: none;
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
.btn-export:hover {
    background-color: #357ABD;
    color: var(--white);
}
.btn-export i {
    color: var(--white) !important;
}
.btn-export-outline {
    background-color: transparent;
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--blue);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-export-outline:hover {
    background-color: var(--blue);
    color: var(--white);
}
.btn-export-outline i {
    color: var(--blue) !important;
}
.btn-export-outline:hover i {
    color: var(--white) !important;
}
.export-info {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.85rem;
    margin-top: 16px;
}
.export-info i {
    color: var(--blue) !important;
}
.export-info .text-blue {
    color: var(--blue);
    opacity: 1;
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

/* Utility Classes */
.text-blue {
    color: var(--blue) !important;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fa-solid fa-users me-2"></i>Manage Patients</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Patients</li>
                </ol>
            </nav>
        </div>
        <div>
            <span class="stat-trend">
                <i class="fa-regular fa-calendar me-1"></i><?=date('l, F j, Y')?>
            </span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row row g-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">TOTAL PATIENTS</div>
                    <div class="stat-value"><?=number_format($total_patients)?></div>
                    <div class="stat-trend">
                        <i class="fa-solid fa-arrow-up me-1"></i><?=number_format($total_records)?> filtered
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">MALE PATIENTS</div>
                    <div class="stat-value"><?=number_format($male_count)?></div>
                    <div class="stat-trend">
                        <i class="fa-solid fa-mars me-1"></i><?=number_format($total_patients > 0 ? ($male_count / $total_patients * 100) : 0, 1)?>%
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-mars"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">FEMALE PATIENTS</div>
                    <div class="stat-value"><?=number_format($female_count)?></div>
                    <div class="stat-trend">
                        <i class="fa-solid fa-venus me-1"></i><?=number_format($total_patients > 0 ? ($female_count / $total_patients * 100) : 0, 1)?>%
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-venus"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">TOTAL VACCINES</div>
                    <div class="stat-value"><?=number_format($total_vaccines)?></div>
                    <div class="stat-trend">
                        <i class="fa-solid fa-syringe me-1"></i>Immunization records
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-syringe"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Card -->
    <div class="search-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-search me-2"></i>Search Patients</h6>
        </div>
        <div class="card-body">
            <form method="get" id="searchForm">
                <input type="hidden" name="r" value="patients/manage">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <div class="d-flex gap-2">
                            <select name="category" class="form-select" style="width: 150px;">
                                <option value="all" <?= $category == 'all' ? 'selected' : '' ?>>All Fields</option>
                                <option value="name" <?= $category == 'name' ? 'selected' : '' ?>>Name</option>
                                <option value="id" <?= $category == 'id' ? 'selected' : '' ?>>ID</option>
                                <option value="dob" <?= $category == 'dob' ? 'selected' : '' ?>>DOB</option>
                            </select>
                            <input type="text" name="q" class="form-control" placeholder="Search by name, ID, or date..." value="<?= e($q) ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-search me-2"></i>Search
                            </button>
                            <a href="?r=patients/manage" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6 date-filter">
                        <label>Registration Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= e($date_from) ?>">
                    </div>
                    <div class="col-md-6 date-filter">
                        <label>Registration Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= e($date_to) ?>">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Card -->
    <div class="results-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6><i class="fa-solid fa-list me-2"></i>Patient List</h6>
            <div class="d-flex align-items-center gap-3">
                <span class="record-count">
                    Showing <?= count($rows) ?> of <?= $total_records ?> records
                </span>
                <?php if (has_permission($pdo, 'button.patient.add')): ?>
                <a href="?r=patients/add" class="btn-add">
                    <i class="fa-solid fa-plus me-2"></i>Add New Patient
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="table-container">
            <table class="patients-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Age</th>
                        <th>Registered</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $r): 
                            $gender = ($r['sex'] == 'F') ? 'Female' : 'Male';
                            $gender_icon = ($r['sex'] == 'F') ? 'venus' : 'mars';
                            
                            // Get vaccine count for this patient
                            $vaccine_count = 0;
                            try {
                                $vc_stmt = $pdo->prepare("SELECT COUNT(*) FROM patient_vaccines WHERE patient_id = ?");
                                $vc_stmt->execute([$r['id']]);
                                $vaccine_count = $vc_stmt->fetchColumn();
                            } catch (PDOException $e) {
                                // Silently fail
                            }
                        ?>
                        <tr>
                            <td class="fw-bold">#<?= $r['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="gender-icon me-2">
                                        <i class="fa-solid fa-<?= $gender_icon ?>"></i>
                                    </span>
                                    <?= e($r['name']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= ($r['sex'] == 'F') ? 'female' : 'male' ?>">
                                    <?= $gender ?>
                                </span>
                            </td>
                            <td><?= e($r['dob'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($r['age']): ?>
                                    <span class="badge badge-age"><?= e($r['age']) ?> years</span>
                                <?php else: ?>
                                    <span class="badge badge-age">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?r=patients/view&id=<?= $r['id'] ?>" class="action-btn" title="Quick View">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="?r=patients/profile&id=<?= $r['id'] ?>" class="action-btn" title="Full Profile">
                                        <i class="fa-solid fa-id-card"></i>
                                    </a>
                                    <!-- Vaccine Records Button with Count Badge -->
                                    <a href="?r=modules/vaccine/index&patient_id=<?= $r['id'] ?>" class="action-btn vaccine-badge" title="Vaccine Records (<?= $vaccine_count ?>)">
                                        <i class="fa-solid fa-syringe"></i>
                                        <?php if ($vaccine_count > 0): ?>
                                            <span class="vaccine-count"><?= $vaccine_count > 9 ? '9+' : $vaccine_count ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <?php if (has_permission($pdo, 'button.patient.edit')): ?>
                                    <a href="?r=patients/edit&id=<?= $r['id'] ?>" class="action-btn" title="Edit Patient">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (has_permission($pdo, 'button.patient.delete')): ?>
                                    <a href="?r=patients/delete&id=<?= $r['id'] ?>" class="action-btn" title="Delete Patient" 
                                       onclick="return confirm('Are you sure you want to delete this patient? This action cannot be undone.');">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="fa-solid fa-users-slash fa-3x"></i>
                                <h5>No patients found</h5>
                                <p>Try adjusting your search filters or add a new patient</p>
                                <div class="mt-3">
                                    <a href="?r=patients/manage" class="btn btn-outline-secondary me-2">
                                        <i class="fa-solid fa-times me-2"></i>Clear Filters
                                    </a>
                                    <?php if (has_permission($pdo, 'button.patient.add')): ?>
                                    <a href="?r=patients/add" class="btn btn-primary">
                                        <i class="fa-solid fa-plus me-2"></i>Add Patient
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container d-flex justify-content-between align-items-center">
            <div class="page-info">
                Page <?= $page ?> of <?= $total_pages ?>
            </div>
            <nav>
                <ul class="pagination">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $query_string ?>page=<?= $page-1 ?>" aria-label="Previous">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'page=1">1</a></li>';
                        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $query_string ?>page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'page='.$total_pages.'">'.$total_pages.'</a></li>';
                    }
                    ?>
                    
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $query_string ?>page=<?= $page+1 ?>" aria-label="Next">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <!-- Export Options -->
    <?php if (has_permission($pdo, 'export.excel') || has_permission($pdo, 'export.pdf')): ?>
    <div class="export-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-download me-2"></i>Export Options</h6>
        </div>
        <div class="card-body">
            <div class="export-buttons">
                <?php if (has_permission($pdo, 'export.excel')): ?>
                <!-- Current Results Export -->
                <a href="../export_patients.php?format=csv&q=<?= urlencode($q) ?>&category=<?= $category ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                   class="btn-export-outline">
                    <i class="fa-solid fa-file-excel me-2"></i>
                    CSV (Current)
                </a>
                <?php endif; ?>
                
                <?php if (has_permission($pdo, 'export.pdf')): ?>
                <a href="../export_patients.php?format=pdf&q=<?= urlencode($q) ?>&category=<?= $category ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                   class="btn-export-outline">
                    <i class="fa-solid fa-file-pdf me-2"></i>
                    PDF (Current)
                </a>
                <?php endif; ?>
                
                <?php if (has_permission($pdo, 'export.excel')): ?>
                <!-- All Records Export -->
                <a href="../export_patients.php?format=full_csv" class="btn-export">
                    <i class="fa-solid fa-database me-2"></i>
                    All Records CSV
                </a>
                <?php endif; ?>
                
                <?php if (has_permission($pdo, 'export.pdf')): ?>
                <a href="../export_patients.php?format=full_pdf" class="btn-export">
                    <i class="fa-solid fa-archive me-2"></i>
                    All Records PDF
                </a>
                <?php endif; ?>
            </div>
            <div class="export-info">
                <i class="fa-solid fa-info-circle me-2"></i>
                Export current search results or all patient records in CSV or PDF format.
                <?php if ($q || $date_from || $date_to): ?>
                    <span class="text-blue">Currently showing filtered results (<?= $total_records ?> records match your criteria).</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* Vaccine badge additional styles */
.vaccine-badge {
    position: relative;
}
.vaccine-count {
    position: absolute;
    top: -6px;
    right: -6px;
    background-color: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 9px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
</style>

<script>
// Auto-submit form when date filters change
document.querySelectorAll('#searchForm input[type="date"]').forEach(function(el) {
    el.addEventListener('change', function() {
        document.getElementById('searchForm').submit();
    });
});

// Add loading indicator on search
document.getElementById('searchForm')?.addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    if (btn) {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Searching...';
        btn.disabled = true;
        
        // Re-enable after 10 seconds (in case of timeout)
        setTimeout(function() {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }, 10000);
    }
});
</script>

<?php include __DIR__.'/../templates/footer.php'; ?>