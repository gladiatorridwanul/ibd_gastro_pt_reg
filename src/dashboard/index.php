<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DO NOT include files here - they are already loaded in index.php
// The front controller already loaded all required includes

// Verify authentication functions are available
if (!function_exists('require_login')) {
    // If we're here, something went wrong with the autoloading
    error_log("CRITICAL: require_login() function not found in dashboard");
    
    // Try to load auth.php directly as fallback
    $authPath = __DIR__ . '/../includes/auth.php';
    if (file_exists($authPath)) {
        require_once $authPath;
    } else {
        die('Authentication system not loaded properly. Please contact administrator.');
    }
    
    // Check again
    if (!function_exists('require_login')) {
        die('Authentication system not loaded properly. Please contact administrator.');
    }
}

// Check if user is logged in
require_login();

// Get statistics
$stats = [];

try {
    global $pdo;
    
    if (!$pdo) {
        throw new Exception('Database connection not available');
    }
    
    // Patient statistics
    $stats['total_patients'] = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $stats['male_patients'] = $pdo->query("SELECT COUNT(*) FROM patients WHERE sex = 'M'")->fetchColumn();
    $stats['female_patients'] = $pdo->query("SELECT COUNT(*) FROM patients WHERE sex = 'F'")->fetchColumn();
    $stats['patients_today'] = $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // Follow-up statistics
    $stats['followup_today'] = $pdo->query("SELECT COUNT(*) FROM followups WHERE DATE(followup_at) = CURDATE()")->fetchColumn();
    $stats['followup_week'] = $pdo->query("SELECT COUNT(*) FROM followups WHERE WEEK(followup_at) = WEEK(CURDATE())")->fetchColumn();

    // Age distribution
    $age_distribution = [
        '0-18' => $pdo->query("SELECT COUNT(*) FROM patients WHERE age <= 18 OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) <= 18)")->fetchColumn(),
        '19-30' => $pdo->query("SELECT COUNT(*) FROM patients WHERE (age BETWEEN 19 AND 30) OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 19 AND 30)")->fetchColumn(),
        '31-45' => $pdo->query("SELECT COUNT(*) FROM patients WHERE (age BETWEEN 31 AND 45) OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 31 AND 45)")->fetchColumn(),
        '46-60' => $pdo->query("SELECT COUNT(*) FROM patients WHERE (age BETWEEN 46 AND 60) OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 46 AND 60)")->fetchColumn(),
        '60+' => $pdo->query("SELECT COUNT(*) FROM patients WHERE age > 60 OR (dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) > 60)")->fetchColumn(),
    ];

    // Recent patients
    $recent_patients = $pdo->query("SELECT id, name, sex, created_at FROM patients ORDER BY id DESC LIMIT 5")->fetchAll();

    // Recent follow-ups
    $recent_followups = $pdo->query("SELECT f.*, p.name as patient_name 
                                     FROM followups f 
                                     JOIN patients p ON p.id = f.patient_id 
                                     ORDER BY f.followup_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $stats = [];
    $age_distribution = [];
    $recent_patients = [];
    $recent_followups = [];
}

// Current user info
$user = null;
if (function_exists('user')) {
    $user = user();
}

// Include header
$headerPath = __DIR__ . '/../templates/header.php';
if (file_exists($headerPath)) {
    include $headerPath;
} else {
    // Fallback header if template not found
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard - PMRMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container-fluid">
            <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
                <div class="container-fluid">
                    <a class="navbar-brand" href="?r=dashboard">PMRMS</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="?r=profile">
                                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($user['name'] ?? 'User') ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="?r=logout">
                                    <i class="fa-solid fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
    <?php
}
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

/* Section Title */
.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--black);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--blue);
}
.section-title i {
    color: var(--blue) !important;
}

/* Chart Cards */
.chart-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    padding: 20px;
    height: 100%;
}
.chart-title {
    color: var(--black);
    font-weight: 600;
    margin-bottom: 20px;
    font-size: 1rem;
}
.chart-title i {
    color: var(--blue) !important;
    margin-right: 8px;
}

/* Progress Bars */
.progress-container {
    margin-bottom: 16px;
}
.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.progress-label {
    color: var(--black);
    font-size: 0.9rem;
}
.progress-value {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.85rem;
}
.progress {
    background-color: var(--ash);
    border-radius: 4px;
    height: 8px;
    overflow: hidden;
}
.progress-bar {
    background-color: var(--blue);
    border-radius: 4px;
    height: 100%;
}

/* Gender Distribution */
.gender-container {
    display: flex;
    justify-content: space-around;
    align-items: center;
    margin-top: 10px;
}
.gender-item {
    text-align: center;
    flex: 1;
}
.gender-chart {
    position: relative;
    display: inline-block;
    margin-bottom: 12px;
}
.gender-chart canvas {
    display: block;
}
.gender-stat {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}
.gender-number {
    color: var(--black);
    font-size: 1.2rem;
    font-weight: 700;
    line-height: 1.2;
}
.gender-label {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.7rem;
    text-transform: uppercase;
}
.gender-percentage {
    margin-top: 8px;
    color: var(--black);
    opacity: 0.6;
    font-size: 0.8rem;
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
.results-card .card-body {
    padding: 20px;
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

/* Follow-up Status */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}
.status-scheduled {
    background-color: var(--ash);
    color: var(--black);
}
.status-overdue {
    background-color: var(--blue);
    color: var(--white);
}

/* Quick Action Cards */
.quick-actions-row {
    margin-top: 24px;
}
.quick-action-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    display: block;
    transition: all 0.2s;
    height: 100%;
}
.quick-action-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}
.quick-action-icon {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background-color: var(--ash);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}
.quick-action-icon i {
    color: var(--blue) !important;
    font-size: 1.8rem;
}
.quick-action-title {
    color: var(--black);
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 1rem;
}
.quick-action-desc {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.8rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
}
.empty-state i {
    color: var(--blue) !important;
    opacity: 0.3;
    font-size: 3rem;
    margin-bottom: 12px;
}
.empty-state p {
    color: var(--black);
    opacity: 0.6;
}

/* View All Link */
.view-all-link {
    color: var(--blue);
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.view-all-link:hover {
    text-decoration: underline;
}
.view-all-link i {
    color: var(--blue) !important;
    font-size: 0.8rem;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item active">Home</li>
                </ol>
            </nav>
        </div>
        <div>
            <span class="stat-trend">
                <i class="fa-regular fa-calendar me-1"></i><?=date('l, F j, Y')?>
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-row row g-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">TOTAL PATIENTS</div>
                    <div class="stat-value"><?=number_format($stats['total_patients'] ?? 0)?></div>
                    <div class="stat-trend">
                        <i class="fa-solid fa-arrow-up me-1"></i><?=number_format($stats['patients_today'] ?? 0)?> today
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
                    <div class="stat-value"><?=number_format($stats['male_patients'] ?? 0)?></div>
                    <div class="stat-trend">
                        <i class="fa-solid fa-mars me-1"></i><?=number_format(($stats['total_patients'] ?? 0) > 0 ? (($stats['male_patients'] ?? 0) / ($stats['total_patients'] ?? 1) * 100) : 0, 1)?>% of total
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
                    <div class="stat-value"><?=number_format($stats['female_patients'] ?? 0)?></div>
                    <div class="stat-trend">
                        <i class="fa-solid fa-venus me-1"></i><?=number_format(($stats['total_patients'] ?? 0) > 0 ? (($stats['female_patients'] ?? 0) / ($stats['total_patients'] ?? 1) * 100) : 0, 1)?>% of total
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
                    <div class="stat-label">FOLLOW-UPS</div>
                    <div class="stat-value"><?=number_format($stats['followup_today'] ?? 0)?></div>
                    <div class="stat-trend">
                        <i class="fa-regular fa-calendar me-1"></i><?=number_format($stats['followup_week'] ?? 0)?> this week
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Distribution Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Gender Distribution -->
        <div class="col-md-6">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fa-solid fa-chart-pie"></i>Gender Distribution
                </div>
                <div class="gender-container">
                    <!-- Male Chart -->
                    <div class="gender-item">
                        <div class="gender-chart">
                            <canvas id="maleChart" width="120" height="120"></canvas>
                            <div class="gender-stat">
                                <div class="gender-number"><?=number_format($stats['male_patients'] ?? 0)?></div>
                                <div class="gender-label">Male</div>
                            </div>
                        </div>
                        <div class="gender-percentage">
                            <?=number_format(($stats['total_patients'] ?? 0) > 0 ? (($stats['male_patients'] ?? 0) / ($stats['total_patients'] ?? 1) * 100) : 0, 1)?>%
                        </div>
                    </div>
                    
                    <!-- Female Chart -->
                    <div class="gender-item">
                        <div class="gender-chart">
                            <canvas id="femaleChart" width="120" height="120"></canvas>
                            <div class="gender-stat">
                                <div class="gender-number"><?=number_format($stats['female_patients'] ?? 0)?></div>
                                <div class="gender-label">Female</div>
                            </div>
                        </div>
                        <div class="gender-percentage">
                            <?=number_format(($stats['total_patients'] ?? 0) > 0 ? (($stats['female_patients'] ?? 0) / ($stats['total_patients'] ?? 1) * 100) : 0, 1)?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Age Distribution -->
        <div class="col-md-6">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fa-solid fa-chart-simple"></i>Age Distribution
                </div>
                <?php foreach (($age_distribution ?? []) as $range => $count): 
                    $percentage = ($stats['total_patients'] ?? 0) > 0 ? ($count / ($stats['total_patients'] ?? 1) * 100) : 0;
                ?>
                <div class="progress-container">
                    <div class="progress-header">
                        <span class="progress-label"><?=$range?> years</span>
                        <span class="progress-value"><?=number_format($count)?> (<?=number_format($percentage, 1)?>%)</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?=$percentage?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Patients and Follow-ups Row -->
    <div class="row g-4">
        <!-- Recent Patients -->
        <div class="col-md-6">
            <div class="results-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-user-plus me-2"></i>Recently Added Patients</h6>
                    <a href="?r=patients/manage" class="view-all-link">
                        View All <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_patients)): ?>
                    <div class="table-container">
                        <table class="patients-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient Name</th>
                                    <th>Gender</th>
                                    <th>Added</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_patients as $p): 
                                    $gender_icon = ($p['sex'] == 'F') ? 'venus' : 'mars';
                                ?>
                                <tr>
                                    <td class="fw-bold">#<?= $p['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="gender-icon me-2">
                                                <i class="fa-solid fa-<?= $gender_icon ?>"></i>
                                            </span>
                                            <?= htmlspecialchars($p['name']) ?>
                                        </div>
                                    </td>
                                    <td><span class="badge"><?= $p['sex'] == 'F' ? 'Female' : 'Male' ?></span></td>
                                    <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <a href="?r=patients/view&id=<?= $p['id'] ?>" class="action-btn" title="Quick View">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-user-plus"></i>
                        <p>No recent patients</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Follow-ups -->
        <div class="col-md-6">
            <div class="results-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-calendar-check me-2"></i>Upcoming Follow-ups</h6>
                    <a href="?r=patients/followup" class="view-all-link">
                        View All <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_followups)): ?>
                    <div class="table-container">
                        <table class="patients-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Follow-up Date</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_followups as $f): 
                                    $is_overdue = strtotime($f['followup_at']) < time();
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['patient_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($f['followup_at'])) ?></td>
                                    <td>
                                        <span class="status-badge <?= $is_overdue ? 'status-overdue' : 'status-scheduled' ?>">
                                            <?= $is_overdue ? 'Overdue' : 'Scheduled' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <a href="?r=modules/followup/view&id=<?= $f['id'] ?>" class="action-btn" title="View Details">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-calendar-check"></i>
                        <p>No upcoming follow-ups</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-row">
        <h6 class="section-title"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h6>
        <div class="row g-4">
            <?php if (function_exists('has_permission') && has_permission($pdo, 'button.patient.add')): ?>
            <div class="col-md-3">
                <a href="?r=patients/add" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="quick-action-title">Add Patient</div>
                    <div class="quick-action-desc">Register new patient</div>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <a href="?r=patients/manage" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div class="quick-action-title">Search Patients</div>
                    <div class="quick-action-desc">Find patient records</div>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="?r=patients/followup" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div class="quick-action-title">Follow-ups</div>
                    <div class="quick-action-desc">Manage appointments</div>
                </a>
            </div>
            
            <?php if (function_exists('has_permission') && has_permission($pdo, 'export.excel')): ?>
            <div class="col-md-3">
                <a href="export_patients.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fa-solid fa-file-excel"></i>
                    </div>
                    <div class="quick-action-title">Export Data</div>
                    <div class="quick-action-desc">CSV & PDF formats</div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gender Distribution Charts
document.addEventListener('DOMContentLoaded', function() {
    // Male Chart
    const maleCtx = document.getElementById('maleChart')?.getContext('2d');
    if (maleCtx) {
        new Chart(maleCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?=($stats['male_patients'] ?? 0)?>, <?=($stats['total_patients'] ?? 0) - ($stats['male_patients'] ?? 0)?>],
                    backgroundColor: ['#4A90E2', '#F2F4F8'],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
    }

    // Female Chart
    const femaleCtx = document.getElementById('femaleChart')?.getContext('2d');
    if (femaleCtx) {
        new Chart(femaleCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?=($stats['female_patients'] ?? 0)?>, <?=($stats['total_patients'] ?? 0) - ($stats['female_patients'] ?? 0)?>],
                    backgroundColor: ['#4A90E2', '#F2F4F8'],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
    }
});
</script>

<?php
// Include footer
$footerPath = __DIR__ . '/../templates/footer.php';
if (file_exists($footerPath)) {
    include $footerPath;
} else {
    // Fallback footer
    echo '</div></body></html>';
}
?>