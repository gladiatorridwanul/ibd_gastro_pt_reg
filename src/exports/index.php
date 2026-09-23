<?php
/**
 * reports/index.php - Comprehensive Reports Dashboard
 */

require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../includes/helpers.php';

require_login();
require_permission($pdo, 'menu.exports');

// Get date range from request
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'dashboard';

include __DIR__.'/../templates/header.php';
?>

<style>
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
    padding: 6px 12px;
    color: var(--blue);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.85rem;
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

.filter-form {
    background-color: var(--ash);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4><i class="fa-solid fa-chart-line me-2" style="color: var(--blue);"></i>Reports Dashboard</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard/index">Dashboard</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </nav>
        </div>
        <!-- UPDATED: Export button now points to exports module with filter parameters -->
        <a href="?r=exports&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-primary btn-sm" target="_blank">
            <i class="fa-solid fa-file-excel"></i> Export Current Report
        </a>
    </div>

    <!-- Filter Form -->
    <div class="filter-form">
        <form method="get" class="row g-3">
            <input type="hidden" name="r" value="reports/index">
            <input type="hidden" name="report_type" value="<?= $report_type ?>">
            
            <div class="col-md-4">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Report Type</label>
                <select name="report_type" class="form-select">
                    <option value="dashboard" <?= $report_type == 'dashboard' ? 'selected' : '' ?>>Dashboard Report</option>
                    <option value="patients" <?= $report_type == 'patients' ? 'selected' : '' ?>>Patient Report</option>
                    <option value="followups" <?= $report_type == 'followups' ? 'selected' : '' ?>>Follow-up Report</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-filter"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    <?php if ($report_type == 'dashboard'): ?>
        <!-- DASHBOARD REPORT -->
        <?php
        $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
        $new_patients = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE DATE(created_at) BETWEEN ? AND ?");
        $new_patients->execute([$date_from, $date_to]);
        $new_patients = $new_patients->fetchColumn();
        
        $total_followups = $pdo->prepare("SELECT COUNT(*) FROM followups WHERE DATE(created_at) BETWEEN ? AND ?");
        $total_followups->execute([$date_from, $date_to]);
        $total_followups = $total_followups->fetchColumn();
        
        $pending_followups = $pdo->query("SELECT COUNT(*) FROM followups WHERE followup_at < NOW() AND followup_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        ?>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="report-card">
                    <div class="card-body">
                        <div class="stat-value"><?= number_format($total_patients) ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card">
                    <div class="card-body">
                        <div class="stat-value"><?= number_format($new_patients) ?></div>
                        <div class="stat-label">New Patients</div>
                        <small><?= $date_from ?> to <?= $date_to ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card">
                    <div class="card-body">
                        <div class="stat-value"><?= number_format($total_followups) ?></div>
                        <div class="stat-label">Follow-ups</div>
                        <small>In selected period</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card">
                    <div class="card-body">
                        <div class="stat-value"><?= number_format($pending_followups) ?></div>
                        <div class="stat-label">Pending Follow-ups</div>
                        <small>Last 30 days</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Patients with Actions -->
        <div class="report-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="fa-solid fa-user-plus me-2"></i>Recent Patient Registrations</h6>
                <a href="?r=patients/manage" class="btn-outline-primary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent = $pdo->query("
                            SELECT id, name, sex, contact_number, created_at 
                            FROM patients 
                            ORDER BY id DESC LIMIT 10
                        ")->fetchAll();
                        foreach ($recent as $r):
                        ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= $r['sex'] == 'M' ? 'Male' : 'Female' ?></td>
                            <td><?= htmlspecialchars($r['contact_number'] ?? 'N/A') ?></td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="?r=patients/view&id=<?= $r['id'] ?>" class="btn-outline-primary btn-sm" title="View">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="?r=patients/profile&id=<?= $r['id'] ?>" class="btn-outline-primary btn-sm" title="Profile">
                                        <i class="fa-solid fa-id-card"></i>
                                    </a>
                                    <a href="?r=modules/followup/add&patient_id=<?= $r['id'] ?>" class="btn-outline-primary btn-sm" title="Add Follow-up">
                                        <i class="fa-solid fa-calendar-plus"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upcoming Follow-ups -->
        <div class="report-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="fa-solid fa-calendar-check me-2"></i>Upcoming Follow-ups (Next 7 Days)</h6>
                <a href="?r=patients/followup" class="btn-outline-primary btn-sm">Manage Follow-ups</a>
            </div>
            <div class="card-body p-0">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Follow-up Date</th>
                            <th>Days Left</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $upcoming = $pdo->query("
                            SELECT f.*, p.name as patient_name, p.id as patient_id
                            FROM followups f
                            JOIN patients p ON p.id = f.patient_id
                            WHERE f.followup_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                            ORDER BY f.followup_at ASC
                            LIMIT 10
                        ")->fetchAll();
                        foreach ($upcoming as $f):
                            $days_left = ceil((strtotime($f['followup_at']) - time()) / 86400);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($f['patient_name']) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($f['followup_at'])) ?></td>
                            <td><?= $days_left ?> days</td>
                            <td>
                                <span class="badge" style="background-color: var(--blue); color: var(--white);">Scheduled</span>
                            </td>
                            <td>
                                <a href="?r=modules/followup/view&id=<?= $f['id'] ?>" class="btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($upcoming)): ?>
                        <tr><td colspan="5" class="text-center py-3">No upcoming follow-ups</td>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($report_type == 'patients'): ?>
        <!-- PATIENT REPORT -->
        <div class="report-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-users me-2"></i>Patient List with Actions</h6>
            </div>
            <div class="card-body p-0">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th>IBD Reg No</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $patients = $pdo->prepare("
                            SELECT id, name, age, sex, contact_number, ibd_reg_no, created_at 
                            FROM patients 
                            WHERE DATE(created_at) BETWEEN ? AND ?
                            ORDER BY id DESC 
                        ");
                        $patients->execute([$date_from, $date_to]);
                        $patients = $patients->fetchAll();
                        foreach ($patients as $p):
                        ?>
                        <tr>
                            <td>#<?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= $p['age'] ?? 'N/A' ?></td>
                            <td><?= $p['sex'] == 'M' ? 'Male' : 'Female' ?></td>
                            <td><?= htmlspecialchars($p['contact_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($p['ibd_reg_no'] ?? 'N/A') ?></td>
                            <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="?r=patients/view&id=<?= $p['id'] ?>" class="btn-outline-primary btn-sm" title="View">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="?r=patients/profile&id=<?= $p['id'] ?>" class="btn-outline-primary btn-sm" title="Profile">
                                        <i class="fa-solid fa-id-card"></i>
                                    </a>
                                    <a href="?r=modules/followup/add&patient_id=<?= $p['id'] ?>" class="btn-outline-primary btn-sm" title="Add Follow-up">
                                        <i class="fa-solid fa-calendar-plus"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($patients)): ?>
                        <tr><td colspan="8" class="text-center py-4">No patients found in selected date range</td>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($report_type == 'followups'): ?>
        <!-- FOLLOW-UP REPORT -->
        <div class="report-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-calendar-check me-2"></i>Follow-up Report</h6>
            </div>
            <div class="card-body p-0">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Follow-up Date</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $followups = $pdo->prepare("
                            SELECT f.*, p.name as patient_name, p.id as patient_id
                            FROM followups f
                            JOIN patients p ON p.id = f.patient_id
                            WHERE DATE(f.followup_at) BETWEEN ? AND ?
                            ORDER BY f.followup_at DESC
                        ");
                        $followups->execute([$date_from, $date_to]);
                        $followups = $followups->fetchAll();
                        foreach ($followups as $f):
                            $status = strtotime($f['followup_at']) < time() ? 'Overdue' : 'Scheduled';
                            $status_color = $status == 'Overdue' ? '#dc3545' : 'var(--blue)';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($f['patient_name']) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($f['followup_at'])) ?></td>
                            <td><?= htmlspecialchars(substr($f['description'] ?? '', 0, 50)) ?>...</td>
                            <td>
                                <span class="badge" style="background-color: <?= $status_color ?>; color: var(--white);">
                                    <?= $status ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($f['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="?r=modules/followup/view&id=<?= $f['id'] ?>" class="btn-outline-primary btn-sm" title="View">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="?r=modules/followup/add&patient_id=<?= $f['patient_id'] ?>" class="btn-outline-primary btn-sm" title="Add New">
                                        <i class="fa-solid fa-plus"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($followups)): ?>
                        <tr><td colspan="6" class="text-center py-4">No follow-ups found in selected date range</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>