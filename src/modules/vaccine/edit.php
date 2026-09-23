<?php
/**
 * vaccine/edit.php - Edit vaccine record
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_login();

$vaccine_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// Get vaccine record
$stmt = $pdo->prepare("SELECT * FROM patient_vaccines WHERE id = ?");
$stmt->execute([$vaccine_id]);
$vaccine = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vaccine) {
    $_SESSION['error'] = "Vaccine record not found.";
    header("Location: ?r=modules/vaccine/index&patient_id={$patient_id}");
    exit;
}

$patient_id = $vaccine['patient_id'];

// Get patient details
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    $_SESSION['error'] = "Patient not found.";
    header('Location: ?r=patients/manage');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vaccine_name = trim($_POST['vaccine_name'] ?? '');
    $dose_schedule = trim($_POST['dose_schedule'] ?? '');
    $dose_given_date = !empty($_POST['dose_given_date']) ? $_POST['dose_given_date'] : null;
    $next_dose_date = !empty($_POST['next_dose_date']) ? $_POST['next_dose_date'] : null;
    $batch_no = trim($_POST['batch_no'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Determine status
    $status = 'Pending';
    if ($dose_given_date) {
        $status = 'Completed';
    } elseif ($next_dose_date && strtotime($next_dose_date) < time()) {
        $status = 'Overdue';
    } elseif ($next_dose_date) {
        $status = 'Scheduled';
    }
    
    $errors = array();
    
    if (empty($vaccine_name)) {
        $errors[] = "Vaccine name is required.";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE patient_vaccines 
            SET vaccine_name = ?, dose_schedule = ?, dose_given_date = ?, next_dose_date = ?, 
                batch_no = ?, remarks = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $vaccine_name, $dose_schedule, $dose_given_date, $next_dose_date,
            $batch_no, $remarks, $status, $vaccine_id
        ]);
        
        if ($result) {
            // Update reminder
            $reminder_stmt = $pdo->prepare("DELETE FROM vaccine_reminders WHERE vaccine_id = ?");
            $reminder_stmt->execute([$vaccine_id]);
            
            if ($next_dose_date) {
                $reminder_date = date('Y-m-d', strtotime($next_dose_date . ' -7 days'));
                $reminder_stmt = $pdo->prepare("
                    INSERT INTO vaccine_reminders (patient_id, vaccine_id, reminder_date)
                    VALUES (?, ?, ?)
                ");
                $reminder_stmt->execute([$patient_id, $vaccine_id, $reminder_date]);
            }
            
            // Audit log
            if (function_exists('audit')) {
                audit($pdo, 'vaccine.update', 'patient_vaccines', $vaccine_id, array(
                    'patient_id' => $patient_id,
                    'vaccine_name' => $vaccine_name
                ));
            }
            
            $_SESSION['success'] = "Vaccine record updated successfully.";
            header("Location: ?r=modules/vaccine/index&patient_id={$patient_id}");
            exit;
        } else {
            $errors[] = "Failed to update vaccine record. Please try again.";
        }
    }
    
    $_SESSION['error'] = implode("<br>", $errors);
}

$page_title = "Edit Vaccine Record - " . htmlspecialchars($patient['name']);
$current_route = 'modules/vaccine/edit';

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="fa-solid fa-syringe text-primary me-2"></i>
                Edit Vaccine Record
            </h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?r=dashboard/index">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?= $patient_id ?>"><?= htmlspecialchars($patient['name']) ?></a></li>
                    <li class="breadcrumb-item"><a href="?r=modules/vaccine/index&patient_id=<?= $patient_id ?>">Vaccine Records</a></li>
                    <li class="breadcrumb-item active">Edit Vaccine</li>
                </ol>
            </nav>
        </div>
        <a href="?r=modules/vaccine/index&patient_id=<?= $patient_id ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Vaccine Records
        </a>
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

    <!-- Vaccine Card Form -->
    <div class="card">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="fa-solid fa-vaccine me-2 text-primary"></i> Edit Vaccine Details</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Vaccine Name <span class="text-danger">*</span></label>
                        <select name="vaccine_name" id="vaccine_name" class="form-select" required>
                            <option value="">Select Vaccine...</option>
                            <option value="Hepatitis B vaccine" <?= $vaccine['vaccine_name'] == 'Hepatitis B vaccine' ? 'selected' : '' ?>>Hepatitis B vaccine (Hepa-B 1ml IM)</option>
                            <option value="Pneumococcal conjugate vaccine (PCV-13)" <?= $vaccine['vaccine_name'] == 'Pneumococcal conjugate vaccine (PCV-13)' ? 'selected' : '' ?>>Pneumococcal conjugate vaccine (PCV-13) - Prevenor 0.5ml</option>
                            <option value="Pneumococcal polysaccharide vaccine (PPSV23)" <?= $vaccine['vaccine_name'] == 'Pneumococcal polysaccharide vaccine (PPSV23)' ? 'selected' : '' ?>>Pneumococcal polysaccharide vaccine (PPSV23) - Pneumovax 0.5</option>
                            <option value="Influenza vaccine" <?= $vaccine['vaccine_name'] == 'Influenza vaccine' ? 'selected' : '' ?>>Influenza vaccine (Influvax 0.5ml)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Dose Schedule</label>
                        <input type="text" name="dose_schedule" id="dose_schedule" class="form-control" 
                               value="<?= htmlspecialchars($vaccine['dose_schedule']) ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Dose Given Date</label>
                        <input type="date" name="dose_given_date" class="form-control" 
                               value="<?= $vaccine['dose_given_date'] ? date('Y-m-d', strtotime($vaccine['dose_given_date'])) : '' ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Next Dose Date</label>
                        <input type="date" name="next_dose_date" class="form-control" 
                               value="<?= $vaccine['next_dose_date'] ? date('Y-m-d', strtotime($vaccine['next_dose_date'])) : '' ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Batch No</label>
                        <input type="text" name="batch_no" class="form-control" value="<?= htmlspecialchars($vaccine['batch_no']) ?>">
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3"><?= htmlspecialchars($vaccine['remarks']) ?></textarea>
                    </div>
                    
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-save"></i> Update Vaccine Record
                        </button>
                        <a href="?r=modules/vaccine/index&patient_id=<?= $patient_id ?>" class="btn btn-secondary">
                            <i class="fa-solid fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Auto-populate dose schedule when vaccine is selected
    document.getElementById('vaccine_name').addEventListener('change', function() {
        const schedules = {
            'Hepatitis B vaccine': '0 month → 1 month → 6 month',
            'Pneumococcal conjugate vaccine (PCV-13)': '1 amp IM stat',
            'Pneumococcal polysaccharide vaccine (PPSV23)': '1 amp IM stat (Every 5 years) [2 months after PCV-13]',
            'Influenza vaccine': '1 amp IM stat (Every year)'
        };
        
        const selected = this.value;
        if (schedules[selected] && !document.getElementById('dose_schedule').value) {
            document.getElementById('dose_schedule').value = schedules[selected];
        }
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>