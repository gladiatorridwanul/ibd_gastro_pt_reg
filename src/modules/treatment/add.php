<?php
/**
 * modules/treatment/add.php - Add Systemic Treatment
 * Handles multiple drug entries with conditional fields
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/audit.php';
require_once __DIR__.'/../../includes/helpers.php';
require_once __DIR__.'/../../includes/permissions.php';

// Define CSRF functions if they don't exist
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Helper function to convert Yes/No to 1/0 for integer fields
function yes_no_to_int($value) {
    if ($value === 'Yes' || $value === '1' || $value === 1) {
        return 1;
    }
    return 0; // Default to 0 for 'No' or empty
}

// Require login first
require_login();

// Check permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'button.module.treatment.add')) {
    $_SESSION['error'] = 'You do not have permission to add treatment records';
    header('Location: ?r=patients/manage');
    exit;
}

$patient_id = (int)($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) {
    http_response_code(400);
    $_SESSION['error'] = 'Invalid patient ID';
    header('Location: ?r=patients/manage');
    exit;
}

// Fetch patient heading
try {
    $stmt = $pdo->prepare("SELECT id, name, sex, dob FROM patients WHERE id=?");
    $stmt->execute([$patient_id]);
    $pat = $stmt->fetch();
    if (!$pat) {
        http_response_code(404);
        $_SESSION['error'] = 'Patient not found';
        header('Location: ?r=patients/manage');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching patient: " . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred';
    header('Location: ?r=patients/manage');
    exit;
}

// Drug list options
$drug_options = [
    'Mesalamine/Sulfasalazine',
    'Azathioprine',
    'Corticosteroids',
    'Immunosuppressants',
    'Biologics',
    'Others'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: ?r=modules/treatment/add&patient_id=' . $patient_id);
        exit;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Handle multiple drug entries
        $drug_names = $_POST['drug_name'] ?? [];
        $custom_drug_names = $_POST['custom_drug_name'] ?? [];
        $start_dates = $_POST['start_date'] ?? [];
        $current_doses = $_POST['current_dose'] ?? [];
        $maximum_doses = $_POST['maximum_dose'] ?? [];
        $side_effects = $_POST['side_effects'] ?? [];
        
        $success_count = 0;
        $last_insert_id = null;
        
        for ($i = 0; $i < count($drug_names); $i++) {
            if (empty($drug_names[$i])) continue;
            
            // Handle custom drug name for "Others" option
            $drug_name = $drug_names[$i];
            $custom_drug = ($drug_name === 'Others' && !empty($custom_drug_names[$i])) ? $custom_drug_names[$i] : null;
            
            // FIXED: Convert Yes/No to integers for integer fields
            $local_treatment = $_POST['local_treatment'] ?? 'No';
            $local_treatment_int = yes_no_to_int($local_treatment);
            
            $steroid_dependency = $_POST['steroid_dependency'] ?? 'No';
            $steroid_dependency_int = yes_no_to_int($steroid_dependency);
            
            $steroid_resistant = $_POST['steroid_resistant'] ?? 'No';
            $steroid_resistant_int = yes_no_to_int($steroid_resistant);
            
            $ho_att = $_POST['ho_att'] ?? 'No';
            $ho_att_int = yes_no_to_int($ho_att);
            
            $nsaids_last_4_weeks = $_POST['nsaids_last_4_weeks'] ?? 'No';
            $nsaids_last_4_weeks_int = yes_no_to_int($nsaids_last_4_weeks);
            
            $sql = "INSERT INTO treatments (
                patient_id, drug_name, custom_drug_name, start_date, current_dose, maximum_dose, side_effects,
                local_treatment, local_treatment_specify,
                antibiotics_quinolone, antibiotics_metronidazole, antibiotics_others,
                other_therapy,
                calcium, vitamin_d, vitamin_b12, iron_supplement, probiotics, nutritional_supplements,
                steroid_dependency, steroid_dependency_details,
                steroid_resistant, steroid_resistant_details,
                ho_att, att_specify_from, att_specify_to, att_total_months,
                nsaids_last_4_weeks, nsaids_details,
                alt_meds, alt_meds_type, alt_meds_duration, alt_meds_other_details
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?
            )";
            
            $params = [
                $patient_id,
                $drug_name,
                $custom_drug,
                !empty($start_dates[$i]) ? $start_dates[$i] : null,
                !empty($current_doses[$i]) ? $current_doses[$i] : null,
                !empty($maximum_doses[$i]) ? $maximum_doses[$i] : null,
                !empty($side_effects[$i]) ? $side_effects[$i] : null,
                
                // FIXED: Use integer values instead of strings
                $local_treatment_int,
                !empty($_POST['local_treatment_specify']) ? $_POST['local_treatment_specify'] : null,
                
                !empty($_POST['antibiotics_quinolone']) ? $_POST['antibiotics_quinolone'] : null,
                !empty($_POST['antibiotics_metronidazole']) ? $_POST['antibiotics_metronidazole'] : null,
                !empty($_POST['antibiotics_others']) ? $_POST['antibiotics_others'] : null,
                
                !empty($_POST['other_therapy']) ? $_POST['other_therapy'] : null,
                
                isset($_POST['calcium']) ? 1 : 0,
                isset($_POST['vitamin_d']) ? 1 : 0,
                isset($_POST['vitamin_b12']) ? 1 : 0,
                isset($_POST['iron_supplement']) ? 1 : 0,
                isset($_POST['probiotics']) ? 1 : 0,
                isset($_POST['nutritional_supplements']) ? 1 : 0,
                
                // FIXED: Use integer values instead of strings
                $steroid_dependency_int,
                !empty($_POST['steroid_dependency_details']) ? $_POST['steroid_dependency_details'] : null,
                
                $steroid_resistant_int,
                !empty($_POST['steroid_resistant_details']) ? $_POST['steroid_resistant_details'] : null,
                
                $ho_att_int,
                !empty($_POST['att_specify_from']) ? $_POST['att_specify_from'] : null,
                !empty($_POST['att_specify_to']) ? $_POST['att_specify_to'] : null,
                !empty($_POST['att_total_months']) ? $_POST['att_total_months'] : null,
                
                $nsaids_last_4_weeks_int,
                !empty($_POST['nsaids_details']) ? $_POST['nsaids_details'] : null,
                
                isset($_POST['alt_meds']) ? 1 : 0,
                !empty($_POST['alt_meds_type']) ? $_POST['alt_meds_type'] : null,
                !empty($_POST['alt_meds_duration']) ? $_POST['alt_meds_duration'] : null,
                !empty($_POST['alt_meds_other_details']) ? $_POST['alt_meds_other_details'] : null
            ];
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success_count++;
                $last_insert_id = $pdo->lastInsertId();
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        if ($success_count > 0) {
            if (function_exists('audit')) {
                audit($pdo, 'treatment.add', 'treatments', $last_insert_id, [
                    'patient_id' => $patient_id, 
                    'count' => $success_count
                ]);
            }
            
            $_SESSION['success'] = $success_count . ' treatment record(s) added successfully';
            header('Location: ?r=patients/profile&id=' . $patient_id);
            exit;
        } else {
            throw new Exception('No valid drug entries found');
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error adding treatment: " . $e->getMessage());
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        // Stay on the form page to show error
    }
}

include __DIR__.'/../../templates/header.php';
?>

<style>
/* Color Variables */
:root {
    --white: #FFFFFF;
    --ash: #F2F4F8;
    --blue: #4A90E2;
    --green: #2ECC71;
    --red: #dc3545;
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
.breadcrumb-item a {
    color: var(--blue);
    text-decoration: none;
}
.breadcrumb-item.active {
    color: var(--black);
    opacity: 0.6;
}

/* Alert Messages */
.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
}
.alert-danger {
    background-color: #f8d7da;
    border-left: 4px solid var(--red);
    color: #721c24;
}
.alert-danger i {
    color: var(--red) !important;
}
.alert-success {
    background-color: #d4edda;
    border-left: 4px solid var(--green);
    color: #155724;
}
.alert-success i {
    color: var(--green) !important;
}

/* Section Card */
.section-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.section-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.section-header {
    padding: 16px 20px;
    border-bottom: 2px solid var(--blue);
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-header i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 8px;
    border-radius: 8px;
    font-size: 1rem;
}
.section-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
    font-size: 1.1rem;
}
.section-body {
    padding: 20px;
}

/* Serial Number */
.serial-number {
    display: inline-block;
    width: 24px;
    height: 24px;
    background-color: var(--blue);
    color: var(--white);
    border-radius: 50%;
    text-align: center;
    line-height: 24px;
    font-size: 12px;
    font-weight: 600;
    margin-right: 8px;
}

/* Table Styles */
.drug-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.drug-table thead th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    padding: 10px;
    border-bottom: 2px solid var(--blue);
    font-size: 0.85rem;
}
.drug-table tbody td {
    padding: 8px;
    border-bottom: 1px solid var(--ash);
    vertical-align: middle;
}
.drug-table tbody tr:hover {
    background-color: var(--ash);
}
.drug-table input, .drug-table select {
    width: 100%;
    padding: 6px;
    border: 1px solid var(--ash);
    border-radius: 4px;
    font-family: Cambria, serif;
}
.drug-table .delete-row {
    color: var(--red);
    cursor: pointer;
    text-align: center;
    font-size: 1.1rem;
}
.drug-table .delete-row:hover {
    opacity: 0.8;
}

.btn-add-row {
    background-color: var(--blue);
    color: var(--white);
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    font-family: Cambria, serif;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 20px;
}
.btn-add-row:hover {
    background-color: #357ABD;
}

/* Form Elements */
.form-label {
    color: var(--black);
    font-weight: 500;
    margin-bottom: 6px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
}
.form-control, .form-select {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 12px;
    color: var(--black);
    background-color: var(--white);
    transition: all 0.2s;
}
.form-control:focus, .form-select:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
}

/* Checkbox Group */
.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    padding: 15px;
    background-color: var(--ash);
    border-radius: 8px;
}
.checkbox-group .form-check {
    display: flex;
    align-items: center;
    gap: 5px;
}
.checkbox-group .form-check-input {
    width: 16px;
    height: 16px;
    cursor: pointer;
}
.checkbox-group .form-check-input:checked {
    background-color: var(--blue);
    border-color: var(--blue);
}

/* Conditional Fields */
.conditional-field {
    margin-top: 10px;
    padding: 15px;
    background-color: var(--ash);
    border-radius: 6px;
    transition: all 0.2s ease;
    border-left: 3px solid var(--blue);
}

/* Two Column Layout */
.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Three Column Layout */
.three-column {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

/* Patient Info Badge */
.patient-badge {
    background-color: var(--ash);
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid var(--blue);
}
.patient-badge strong {
    color: var(--blue);
}
.patient-badge i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 4px;
    border-radius: 4px;
    margin-right: 8px;
}

/* Button Styles */
.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    font-family: Cambria, serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    cursor: pointer;
    border: none;
}
.btn-primary:hover {
    background-color: #357ABD;
}
.btn-primary i {
    color: var(--white) !important;
}
.btn-secondary {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--black);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: Cambria, serif;
}
.btn-secondary:hover {
    background-color: var(--ash);
}
.btn-secondary i {
    color: var(--blue) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .two-column, .three-column {
        grid-template-columns: 1fr;
    }
    .drug-table {
        font-size: 0.8rem;
    }
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h5><i class="fa-solid fa-pills"></i>Systemic Treatment</h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?=$patient_id?>">Profile</a></li>
                    <li class="breadcrumb-item active">Add Treatment</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Display Error Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Display Success Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-check-circle me-2"></i>
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- Patient Info Badge -->
    <div class="patient-badge">
        <i class="fa-solid fa-user"></i>
        <strong>Patient:</strong> <?=htmlspecialchars($pat['name'])?> (ID: <?=$pat['id']?>) | 
        <strong>Sex:</strong> <?=$pat['sex'] == 'M' ? 'Male' : 'Female'?> |
        <strong>DOB:</strong> <?=htmlspecialchars($pat['dob'] ?? 'Not provided')?>
    </div>

    <!-- Main Form -->
    <form method="post" id="treatmentForm">
        <input type="hidden" name="_csrf" value="<?=htmlspecialchars(csrf_token())?>">

        <!-- PART ONE: Drug Table -->
        <div class="section-card">
            <div class="section-header">
                <i class="fa-solid fa-table"></i>
                <h6>Medication History</h6>
            </div>
            <div class="section-body">
                <button type="button" class="btn-add-row" id="addRowBtn">
                    <i class="fa-solid fa-plus"></i> Add New Drug
                </button>
                
                <table class="drug-table" id="drugTable">
                    <thead>
                        <tr>
                            <th style="width: 5%">SL No</th>
                            <th style="width: 20%">Drug Name</th>
                            <th style="width: 12%">Start Date</th>
                            <th style="width: 12%">Current Dose</th>
                            <th style="width: 12%">Maximum Dose</th>
                            <th style="width: 20%">Side Effects</th>
                            <th style="width: 5%"></th>
                        </tr>
                    </thead>
                    <tbody id="drugTableBody">
                        <!-- Rows will be added here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PART TWO: Treatment Details (Two Columns) -->
        <div class="two-column">
            <!-- LEFT COLUMN -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fa-solid fa-notes-medical"></i>
                    <h6>Treatment Details - Part A</h6>
                </div>
                <div class="section-body">
                    <!-- 1. Local Treatment -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">1</span>
                            <strong>Local Treatment</strong>
                        </div>
                        <div>
                            <select class="form-select conditional-select" name="local_treatment" data-target="#local_treatment_section">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                            <div id="local_treatment_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Specify</label>
                                <input type="text" class="form-control" name="local_treatment_specify" placeholder="Enter details">
                            </div>
                        </div>
                    </div>

                    <!-- 2. Antibiotics -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">2</span>
                            <strong>Antibiotics</strong>
                        </div>
                        <div class="mb-2">
                            <select class="form-select conditional-select" name="antibiotics_type" data-target="#antibiotics_section">
                                <option value="">Select Type</option>
                                <option value="Quinolone">Quinolone</option>
                                <option value="Metronidazole">Metronidazole</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div id="antibiotics_section" class="conditional-field" style="display: none;">
                            <label class="form-label">Specify Others</label>
                            <input type="text" class="form-control" name="antibiotics_others" placeholder="Enter antibiotic name">
                        </div>
                        <!-- Hidden fields for specific antibiotics -->
                        <input type="hidden" name="antibiotics_quinolone" id="antibiotics_quinolone">
                        <input type="hidden" name="antibiotics_metronidazole" id="antibiotics_metronidazole">
                    </div>

                    <!-- 3. Other Therapy -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">3</span>
                            <strong>Other Therapy</strong>
                        </div>
                        <input type="text" class="form-control" name="other_therapy" placeholder="Enter other therapy details">
                    </div>

                    <!-- 4. Supplements (Checkboxes) -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">4</span>
                            <strong>Supplements</strong>
                        </div>
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="calcium" id="calcium">
                                <label class="form-check-label" for="calcium">Calcium</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vitamin_d" id="vitamin_d">
                                <label class="form-check-label" for="vitamin_d">Vitamin D</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vitamin_b12" id="vitamin_b12">
                                <label class="form-check-label" for="vitamin_b12">Vitamin B12</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="iron_supplement" id="iron_supplement">
                                <label class="form-check-label" for="iron_supplement">Iron</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="probiotics" id="probiotics">
                                <label class="form-check-label" for="probiotics">Probiotics</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="nutritional_supplements" id="nutritional_supplements">
                                <label class="form-check-label" for="nutritional_supplements">Nutritional Supplements</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fa-solid fa-notes-medical"></i>
                    <h6>Treatment Details - Part B</h6>
                </div>
                <div class="section-body">
                    <!-- 5. Steroid Dependency -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">5</span>
                            <strong>Steroid Dependency</strong>
                        </div>
                        <div>
                            <select class="form-select conditional-select" name="steroid_dependency" data-target="#steroid_dependency_section">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                            <div id="steroid_dependency_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Details</label>
                                <input type="text" class="form-control" name="steroid_dependency_details" placeholder="Enter details">
                            </div>
                        </div>
                    </div>

                    <!-- 6. Steroid Resistant -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">6</span>
                            <strong>Steroid Resistant</strong>
                        </div>
                        <div>
                            <select class="form-select conditional-select" name="steroid_resistant" data-target="#steroid_resistant_section">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                            <div id="steroid_resistant_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Details</label>
                                <input type="text" class="form-control" name="steroid_resistant_details" placeholder="Enter details">
                            </div>
                        </div>
                    </div>

                    <!-- 7. H/O ATT -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">7</span>
                            <strong>H/O ATT</strong>
                        </div>
                        <div>
                            <select class="form-select conditional-select" name="ho_att" data-target="#ho_att_section">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                            <div id="ho_att_section" class="conditional-field" style="display: none;">
                                <div class="three-column">
                                    <div>
                                        <label class="form-label">From</label>
                                        <input type="text" class="form-control" name="att_specify_from" placeholder="Start date">
                                    </div>
                                    <div>
                                        <label class="form-label">To</label>
                                        <input type="text" class="form-control" name="att_specify_to" placeholder="End date">
                                    </div>
                                    <div>
                                        <label class="form-label">Duration (months)</label>
                                        <input type="text" class="form-control" name="att_total_months" placeholder="Total months">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 8. NSAIDs in last 4 weeks -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">8</span>
                            <strong>Use of NSAIDs in last 4 weeks</strong>
                        </div>
                        <div>
                            <select class="form-select conditional-select" name="nsaids_last_4_weeks" data-target="#nsaids_section">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                            <div id="nsaids_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Details</label>
                                <input type="text" class="form-control" name="nsaids_details" placeholder="Enter NSAID details">
                            </div>
                        </div>
                    </div>

                    <!-- 9. Alternative Medications -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">9</span>
                            <strong>Alternative Medications</strong>
                        </div>
                        <div>
                            <select class="form-select conditional-select" name="alt_meds_select" data-target="#alt_meds_section">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                            <div id="alt_meds_section" class="conditional-field" style="display: none;">
                                <div class="mb-2">
                                    <select class="form-select" name="alt_meds_type" id="alt_meds_type">
                                        <option value="">Select Type</option>
                                        <option value="Homeopathy">Homeopathy</option>
                                        <option value="Ayurvedic">Ayurvedic</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                                <div id="alt_meds_other_section" class="conditional-field" style="display: none;">
                                    <label class="form-label">Specify Other</label>
                                    <input type="text" class="form-control" name="alt_meds_other_details" placeholder="Enter details">
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">Duration</label>
                                    <input type="text" class="form-control" name="alt_meds_duration" placeholder="Treatment duration">
                                </div>
                                <input type="hidden" name="alt_meds" id="alt_meds_hidden" value="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Treatment
            </button>
            <a href="?r=patients/profile&id=<?=$patient_id?>" class="btn-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<!-- Template for drug row -->
<template id="drugRowTemplate">
    <tr>
        <td class="row-number"></td>
        <td>
            <select name="drug_name[]" class="drug-select">
                <option value="">Select Drug</option>
                <?php foreach ($drug_options as $drug): ?>
                <option value="<?= htmlspecialchars($drug) ?>"><?= htmlspecialchars($drug) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="custom_drug_name[]" class="form-control mt-1 custom-drug" placeholder="Enter drug name" style="display: none;">
        </td>
        <td><input type="date" name="start_date[]" class="form-control"></td>
        <td><input type="text" name="current_dose[]" class="form-control" placeholder="e.g., 50mg"></td>
        <td><input type="text" name="maximum_dose[]" class="form-control" placeholder="e.g., 100mg"></td>
        <td><input type="text" name="side_effects[]" class="form-control" placeholder="Side effects"></td>
        <td class="delete-row"><i class="fa-solid fa-trash"></i></td>
    </tr>
</template>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Drug table management
let rowCount = 0;

function addDrugRow() {
    const template = document.getElementById('drugRowTemplate');
    const clone = template.content.cloneNode(true);
    const tbody = document.getElementById('drugTableBody');
    
    rowCount++;
    const rowNumber = clone.querySelector('.row-number');
    rowNumber.textContent = rowCount;
    
    // Add event listener for drug select change
    const drugSelect = clone.querySelector('.drug-select');
    drugSelect.addEventListener('change', function() {
        const customField = this.closest('td').querySelector('.custom-drug');
        if (this.value === 'Others') {
            customField.style.display = 'block';
            customField.required = true;
        } else {
            customField.style.display = 'none';
            customField.required = false;
            customField.value = '';
        }
    });
    
    // Add delete functionality
    const deleteBtn = clone.querySelector('.delete-row');
    deleteBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to remove this drug?')) {
            this.closest('tr').remove();
            updateRowNumbers();
        }
    });
    
    tbody.appendChild(clone);
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('#drugTableBody tr');
    rowCount = rows.length;
    rows.forEach((row, index) => {
        row.querySelector('.row-number').textContent = index + 1;
    });
}

// Initialize with one row
document.addEventListener('DOMContentLoaded', function() {
    addDrugRow();
});

document.getElementById('addRowBtn').addEventListener('click', addDrugRow);

// Conditional field toggling
function toggleConditionalField(select) {
    const targetId = select.getAttribute('data-target');
    if (!targetId) return;
    
    const targetField = document.querySelector(targetId);
    if (!targetField) return;
    
    const show = (select.value === 'Yes' || select.value === 'Others' || 
                  (select.id === 'alt_meds_type' && select.value !== ''));
    
    targetField.style.display = show ? 'block' : 'none';
    
    // Handle antibiotics mapping
    if (select.name === 'antibiotics_type') {
        const quinolone = document.getElementById('antibiotics_quinolone');
        const metronidazole = document.getElementById('antibiotics_metronidazole');
        const others = document.querySelector('input[name="antibiotics_others"]');
        
        quinolone.value = (select.value === 'Quinolone') ? 'Quinolone' : '';
        metronidazole.value = (select.value === 'Metronidazole') ? 'Metronidazole' : '';
        
        if (select.value !== 'Others' && others) {
            others.value = '';
        }
    }
    
    // Handle alt_meds hidden field
    if (select.name === 'alt_meds_select') {
        document.getElementById('alt_meds_hidden').value = (select.value === 'Yes') ? 1 : 0;
        
        // If "No" is selected, clear all alt meds fields
        if (select.value === 'No') {
            const altMedsType = document.getElementById('alt_meds_type');
            const altMedsDuration = document.querySelector('input[name="alt_meds_duration"]');
            const altMedsOther = document.querySelector('input[name="alt_meds_other_details"]');
            
            if (altMedsType) altMedsType.value = '';
            if (altMedsDuration) altMedsDuration.value = '';
            if (altMedsOther) altMedsOther.value = '';
            document.getElementById('alt_meds_other_section').style.display = 'none';
        }
    }
}

// Initialize conditional selects
document.querySelectorAll('.conditional-select').forEach(select => {
    select.addEventListener('change', () => toggleConditionalField(select));
    toggleConditionalField(select);
});

// Special handling for alternative medications type
document.getElementById('alt_meds_type')?.addEventListener('change', function() {
    const otherSection = document.getElementById('alt_meds_other_section');
    if (this.value === 'Others') {
        otherSection.style.display = 'block';
    } else {
        otherSection.style.display = 'none';
        document.querySelector('input[name="alt_meds_other_details"]').value = '';
    }
});

// Form validation
document.getElementById('treatmentForm')?.addEventListener('submit', function(e) {
    const drugRows = document.querySelectorAll('#drugTableBody tr');
    let hasDrug = false;
    let hasError = false;
    
    drugRows.forEach(row => {
        const drugSelect = row.querySelector('select[name="drug_name[]"]');
        const customField = row.querySelector('.custom-drug');
        
        if (drugSelect && drugSelect.value) {
            hasDrug = true;
            
            // Validate custom drug name for "Others" option
            if (drugSelect.value === 'Others' && (!customField.value || customField.value.trim() === '')) {
                alert('Please specify the drug name for "Others" option');
                hasError = true;
                customField.focus();
            }
        }
    });
    
    if (!hasDrug) {
        e.preventDefault();
        alert('Please add at least one medication');
        return false;
    }
    
    if (hasError) {
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

<?php include __DIR__.'/../../templates/footer.php'; ?>