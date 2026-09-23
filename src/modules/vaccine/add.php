<?php
/**
 * vaccine/add.php - Add multiple vaccine records for a patient in single entry
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vaccine_names = $_POST['vaccine_name'] ?? array();
    $dose_schedules = $_POST['dose_schedule'] ?? array();
    $dose_given_dates = $_POST['dose_given_date'] ?? array();
    $next_dose_dates = $_POST['next_dose_date'] ?? array();
    $batch_nos = $_POST['batch_no'] ?? array();
    $remarks_list = $_POST['remarks'] ?? array();
    
    $administered_by = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
    
    // Generate unique batch ID for this submission
    $batch_id = 'BATCH_' . date('YmdHis') . '_' . uniqid();
    
    $success_count = 0;
    $errors = array();
    
    foreach ($vaccine_names as $index => $vaccine_name) {
        $vaccine_name = trim($vaccine_name);
        
        // Skip empty vaccine names
        if (empty($vaccine_name)) {
            continue;
        }
        
        $dose_schedule = trim($dose_schedules[$index] ?? '');
        $dose_given_date = !empty($dose_given_dates[$index]) ? $dose_given_dates[$index] : null;
        $next_dose_date = !empty($next_dose_dates[$index]) ? $next_dose_dates[$index] : null;
        $batch_no = trim($batch_nos[$index] ?? '');
        $remarks = trim($remarks_list[$index] ?? '');
        
        // Determine status
        $status = 'Pending';
        if ($dose_given_date) {
            $status = 'Completed';
        } elseif ($next_dose_date && strtotime($next_dose_date) < time()) {
            $status = 'Overdue';
        } elseif ($next_dose_date) {
            $status = 'Scheduled';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO patient_vaccines (patient_id, batch_id, vaccine_name, dose_schedule, dose_given_date, next_dose_date, batch_no, administered_by, remarks, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $patient_id, $batch_id, $vaccine_name, $dose_schedule, $dose_given_date,
            $next_dose_date, $batch_no, $administered_by, $remarks, $status, $administered_by
        ]);
        
        if ($result) {
            $vaccine_id = $pdo->lastInsertId();
            
            // Create reminder if next dose date is set
            if ($next_dose_date) {
                $reminder_date = date('Y-m-d', strtotime($next_dose_date . ' -7 days'));
                $reminder_stmt = $pdo->prepare("
                    INSERT INTO vaccine_reminders (patient_id, vaccine_id, reminder_date)
                    VALUES (?, ?, ?)
                ");
                $reminder_stmt->execute([$patient_id, $vaccine_id, $reminder_date]);
            }
            
            $success_count++;
        } else {
            $errors[] = "Failed to add: " . $vaccine_name;
        }
    }
    
    // Audit log
    if (function_exists('audit') && $success_count > 0) {
        audit($pdo, 'vaccine.add.multiple', 'patient_vaccines', null, array(
            'patient_id' => $patient_id,
            'batch_id' => $batch_id,
            'vaccines_added' => $success_count,
            'vaccine_names' => array_filter($vaccine_names)
        ));
    }
    
    if ($success_count > 0) {
        $_SESSION['success'] = "$success_count vaccine record(s) added successfully.";
    }
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    header("Location: ?r=modules/vaccine/index&patient_id={$patient_id}");
    exit;
}

// Get default dose schedule options
$vaccine_options = array(
    'Hepatitis B vaccine' => 'Hepatitis B vaccine (Hepa-B 1ml IM)',
    'Pneumococcal conjugate vaccine (PCV-13)' => 'Pneumococcal conjugate vaccine (PCV-13) - Prevenor 0.5ml',
    'Pneumococcal polysaccharide vaccine (PPSV23)' => 'Pneumococcal polysaccharide vaccine (PPSV23) - Pneumovax 0.5',
    'Influenza vaccine' => 'Influenza vaccine (Influvax 0.5ml)'
);

$page_title = "Add Multiple Vaccines - " . htmlspecialchars($patient['name']);
$current_route = 'modules/vaccine/add';

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="fa-solid fa-syringe text-primary me-2"></i>
                Add Multiple Vaccine Records
            </h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?r=dashboard/index">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?= $patient_id ?>"><?= htmlspecialchars($patient['name']) ?></a></li>
                    <li class="breadcrumb-item"><a href="?r=modules/vaccine/index&patient_id=<?= $patient_id ?>">Vaccine Records</a></li>
                    <li class="breadcrumb-item active">Add Multiple Vaccines</li>
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

    <!-- Multiple Vaccine Card Form -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fa-solid fa-vaccine me-2 text-primary"></i> Vaccine Details</h6>
            <button type="button" class="btn btn-sm btn-primary" id="addMoreVaccine">
                <i class="fa-solid fa-plus"></i> Add Another Vaccine
            </button>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="vaccineForm">
                <div id="vaccineRows">
                    <!-- Row 1 - Default row -->
                    <div class="vaccine-row card mb-3 border">
                        <div class="card-header bg-light py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong><i class="fa-solid fa-vial me-1"></i> Vaccine #1</strong>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-row" style="display: none;">
                                    <i class="fa-solid fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Vaccine Name <span class="text-danger">*</span></label>
                                    <select name="vaccine_name[]" class="form-select vaccine-select" required>
                                        <option value="">Select Vaccine...</option>
                                        <?php foreach ($vaccine_options as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Dose Schedule</label>
                                    <input type="text" name="dose_schedule[]" class="form-control dose-schedule" 
                                           placeholder="e.g., 0 month, 1 month, 6 month">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Dose Given Date</label>
                                    <input type="date" name="dose_given_date[]" class="form-control">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Next Dose Date</label>
                                    <input type="date" name="next_dose_date[]" class="form-control">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Batch No</label>
                                    <input type="text" name="batch_no[]" class="form-control" placeholder="Enter batch number">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks[]" class="form-control" rows="2" placeholder="Any additional remarks..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <hr>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i> Save All Vaccines
                    </button>
                    <a href="?r=modules/vaccine/index&patient_id=<?= $patient_id ?>" class="btn btn-secondary">
                        <i class="fa-solid fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template for new vaccine row -->
<template id="vaccineRowTemplate">
    <div class="vaccine-row card mb-3 border">
        <div class="card-header bg-light py-2">
            <div class="d-flex justify-content-between align-items-center">
                <strong><i class="fa-solid fa-vial me-1"></i> Vaccine #<span class="row-number"></span></strong>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                    <i class="fa-solid fa-trash"></i> Remove
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Vaccine Name <span class="text-danger">*</span></label>
                    <select name="vaccine_name[]" class="form-select vaccine-select" required>
                        <option value="">Select Vaccine...</option>
                        <?php foreach ($vaccine_options as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Dose Schedule</label>
                    <input type="text" name="dose_schedule[]" class="form-control dose-schedule" 
                           placeholder="e.g., 0 month, 1 month, 6 month">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Dose Given Date</label>
                    <input type="date" name="dose_given_date[]" class="form-control">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Next Dose Date</label>
                    <input type="date" name="next_dose_date[]" class="form-control">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Batch No</label>
                    <input type="text" name="batch_no[]" class="form-control" placeholder="Enter batch number">
                </div>
                
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks[]" class="form-control" rows="2" placeholder="Any additional remarks..."></textarea>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
    .vaccine-row {
        transition: all 0.3s ease;
    }
    .vaccine-row:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .remove-row {
        transition: all 0.2s ease;
    }
    .remove-row:hover {
        transform: scale(1.05);
    }
</style>

<script>
    // Vaccine schedule mapping
    const vaccineSchedules = {
        'Hepatitis B vaccine': '0 month → 1 month → 6 month',
        'Pneumococcal conjugate vaccine (PCV-13)': '1 amp IM stat',
        'Pneumococcal polysaccharide vaccine (PPSV23)': '1 amp IM stat (Every 5 years) [2 months after PCV-13]',
        'Influenza vaccine': '1 amp IM stat (Every year)'
    };
    
    let rowCount = 1;
    
    // Function to update row numbers
    function updateRowNumbers() {
        const rows = document.querySelectorAll('.vaccine-row');
        rows.forEach((row, index) => {
            const rowNumberSpan = row.querySelector('.row-number');
            if (rowNumberSpan) {
                rowNumberSpan.textContent = index + 1;
            }
            
            // Show/hide remove button based on row count
            const removeBtn = row.querySelector('.remove-row');
            if (removeBtn) {
                if (rows.length === 1) {
                    removeBtn.style.display = 'none';
                } else {
                    removeBtn.style.display = 'inline-flex';
                }
            }
        });
        rowCount = rows.length;
    }
    
    // Function to auto-populate dose schedule
    function setupVaccineSelectListener(selectElement) {
        selectElement.addEventListener('change', function() {
            const row = this.closest('.vaccine-row');
            const doseScheduleInput = row.querySelector('.dose-schedule');
            const selectedVaccine = this.value;
            
            if (vaccineSchedules[selectedVaccine]) {
                doseScheduleInput.value = vaccineSchedules[selectedVaccine];
            } else {
                doseScheduleInput.value = '';
            }
        });
    }
    
    // Add new vaccine row
    document.getElementById('addMoreVaccine').addEventListener('click', function() {
        const template = document.getElementById('vaccineRowTemplate');
        const newRow = template.content.cloneNode(true);
        
        // Get the container
        const container = document.getElementById('vaccineRows');
        container.appendChild(newRow);
        
        // Setup the new row's select listener
        const newRowElement = container.lastElementChild;
        const newSelect = newRowElement.querySelector('.vaccine-select');
        setupVaccineSelectListener(newSelect);
        
        // Setup remove button for new row
        const removeBtn = newRowElement.querySelector('.remove-row');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                newRowElement.remove();
                updateRowNumbers();
            });
        }
        
        updateRowNumbers();
    });
    
    // Setup existing row's select listeners and remove buttons
    document.querySelectorAll('.vaccine-select').forEach(function(select) {
        setupVaccineSelectListener(select);
    });
    
    document.querySelectorAll('.remove-row').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = this.closest('.vaccine-row');
            if (document.querySelectorAll('.vaccine-row').length > 1) {
                row.remove();
                updateRowNumbers();
            }
        });
    });
    
    // Initialize row numbers
    updateRowNumbers();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>