<?php
/**
 * vaccine/index.php - List all vaccines for a patient
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_login();

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$patient = null;

if ($patient_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$patient) {
    $_SESSION['error'] = "Patient not found.";
    header('Location: ?r=patients/manage');
    exit;
}

$page_title = "Vaccine Records - " . htmlspecialchars($patient['name']);
$current_route = 'modules/vaccine/index';

include __DIR__ . '/../../templates/header.php';
?>

<style>
    .vaccine-card {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.3s;
    }
    .vaccine-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .vaccine-header {
        background: linear-gradient(135deg, #0d6efd, #0a58ca);
        color: white;
        padding: 12px 20px;
        cursor: pointer;
    }
    .vaccine-header h6 {
        margin: 0;
        font-weight: 600;
    }
    .vaccine-body {
        padding: 20px;
        background: white;
    }
    .dose-table {
        width: 100%;
        border-collapse: collapse;
    }
    .dose-table th {
        background: #f8f9fa;
        padding: 10px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }
    .dose-table td {
        padding: 10px;
        border-bottom: 1px solid #dee2e6;
    }
    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-completed { background: #d4edda; color: #155724; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-overdue { background: #f8d7da; color: #721c24; }
    .status-scheduled { background: #d1ecf1; color: #0c5460; }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="fa-solid fa-syringe text-primary me-2"></i>
                Vaccine Records
            </h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?r=dashboard/index">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?= $patient_id ?>"><?= htmlspecialchars($patient['name']) ?></a></li>
                    <li class="breadcrumb-item active">Vaccine Records</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="?r=modules/vaccine/add&patient_id=<?= $patient_id ?>" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add Vaccine Record
            </a>
            <a href="?r=patients/profile&id=<?= $patient_id ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to Profile
            </a>
        </div>
    </div>

    <!-- Patient Info Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Patient Name:</strong> <?= htmlspecialchars($patient['name']) ?>
                </div>
                <div class="col-md-2">
                    <strong>Age:</strong> <?= $patient['age'] ?? 'N/A' ?>
                </div>
                <div class="col-md-2">
                    <strong>Gender:</strong> <?= $patient['sex'] == 'M' ? 'Male' : 'Female' ?>
                </div>
                <div class="col-md-3">
                    <strong>Contact:</strong> <?= htmlspecialchars($patient['contact_number'] ?? 'N/A') ?>
                </div>
                <div class="col-md-2">
                    <strong>Patient ID:</strong> #<?= $patient['id'] ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Get vaccine records for this patient
    $stmt = $pdo->prepare("
        SELECT pv.*, 
               u.name as administered_by_name
        FROM patient_vaccines pv
        LEFT JOIN users u ON u.id = pv.administered_by
        WHERE pv.patient_id = ?
        ORDER BY FIELD(pv.vaccine_name, 'Hepatitis B vaccine', 'Pneumococcal conjugate vaccine (PCV-13)', 'Pneumococcal polysaccharide vaccine (PPSV23)', 'Influenza vaccine'), pv.id ASC
    ");
    $stmt->execute([$patient_id]);
    $vaccines = $stmt->fetchAll();
    ?>

    <?php if (empty($vaccines)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="fa-solid fa-syringe fa-3x mb-3"></i>
            <h5>No Vaccine Records Found</h5>
            <p>Click the "Add Vaccine Record" button to add vaccine records for this patient.</p>
            <a href="?r=modules/vaccine/add&patient_id=<?= $patient_id ?>" class="btn btn-primary mt-2">
                <i class="fa-solid fa-plus"></i> Add First Vaccine Record
            </a>
        </div>
    <?php else: ?>
        <!-- Vaccine Cards -->
        <?php foreach ($vaccines as $vaccine): 
            $status_class = '';
            switch($vaccine['status']) {
                case 'Completed': $status_class = 'status-completed'; break;
                case 'Pending': $status_class = 'status-pending'; break;
                case 'Overdue': $status_class = 'status-overdue'; break;
                case 'Scheduled': $status_class = 'status-scheduled'; break;
            }
        ?>
        <div class="vaccine-card">
            <div class="vaccine-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#vaccine-<?= $vaccine['id'] ?>">
                <h6>
                    <i class="fa-solid fa-syringe me-2"></i>
                    <?= htmlspecialchars($vaccine['vaccine_name']) ?>
                </h6>
                <div>
                    <span class="status-badge <?= $status_class ?> me-2"><?= $vaccine['status'] ?></span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
            <div class="collapse show" id="vaccine-<?= $vaccine['id'] ?>">
                <div class="vaccine-body">
                    <table class="dose-table">
                        <thead>
                            <tr>
                                <th>Dose Schedule</th>
                                <th>Dose Given Date</th>
                                <th>Next Dose Date</th>
                                <th>Batch No</th>
                                <th>Administered By</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= htmlspecialchars($vaccine['dose_schedule'] ?? '-') ?></td>
                                <td><?= $vaccine['dose_given_date'] ? date('d-m-Y', strtotime($vaccine['dose_given_date'])) : '-' ?></td>
                                <td>
                                    <?php if ($vaccine['next_dose_date']): ?>
                                        <?= date('d-m-Y', strtotime($vaccine['next_dose_date'])) ?>
                                        <?php if (strtotime($vaccine['next_dose_date']) < time() && $vaccine['status'] != 'Completed'): ?>
                                            <span class="badge bg-danger ms-2">Overdue</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                 </td>
                                <td><?= htmlspecialchars($vaccine['batch_no'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($vaccine['administered_by_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($vaccine['remarks'] ?? '-') ?></td>
                                <td>
                                    <a href="?r=modules/vaccine/edit&id=<?= $vaccine['id'] ?>&patient_id=<?= $patient_id ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>
                                    <a href="?r=modules/vaccine/delete&id=<?= $vaccine['id'] ?>&patient_id=<?= $patient_id ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this vaccine record?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>