<?php
/**
 * modules/ibd/add.php - Add IBD Diagnosis Record
 * Handles creation of new IBD diagnosis records with proper handling for ENUM fields
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

// Require login first
require_login();

// Check permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'button.module.ibd.add')) {
    $_SESSION['error'] = 'You do not have permission to add IBD records';
    header('Location: ?r=patients/manage');
    exit;
}

// Get patient ID
$patient_id = (int)($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) {
    http_response_code(400);
    $_SESSION['error'] = 'Invalid patient ID';
    header('Location: ?r=patients/manage');
    exit;
}

// Fetch patient details
try {
    $stmt = $pdo->prepare("SELECT id, name, sex, dob FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
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

// ============================================
// HELPER FUNCTION FOR NULL HANDLING
// ============================================

/**
 * Convert empty string to null for database insertion
 * ENUM columns that accept NULL should receive NULL instead of empty string
 * 
 * @param mixed $value The value to check
 * @return mixed Returns null if value is empty string, otherwise the original value
 */
function null_if_empty($value) {
    if ($value === '' || $value === null) {
        return null;
    }
    return $value;
}

/**
 * Process checkbox array to comma-separated string or null
 * 
 * @param array|null $values Array of checkbox values
 * @return string|null Comma-separated string or null
 */
function process_checkbox_array($values) {
    if (empty($values) || !is_array($values)) {
        return null;
    }
    return implode(',', $values);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: ?r=modules/ibd/add&patient_id=' . $patient_id);
        exit;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Process multiple selections - convert arrays to comma-separated strings
        $diagnosis = process_checkbox_array($_POST['diagnosis'] ?? null);
        $other_diagnosis = null_if_empty($_POST['other_diagnosis'] ?? null);
        $diagnostic_criteria = process_checkbox_array($_POST['diagnostic_criteria'] ?? null);
        $uc_location = process_checkbox_array($_POST['uc_location'] ?? null);
        $cd_location = process_checkbox_array($_POST['cd_location'] ?? null);
        $cd_behavior = process_checkbox_array($_POST['cd_behavior'] ?? null);
        $upper_gi = process_checkbox_array($_POST['upper_gi'] ?? null);
        
        // CRITICAL FIX: For ENUM fields that accept NULL, pass NULL not empty string
        $perianal_disease = null_if_empty($_POST['perianal_disease'] ?? null);
        $resident_3m = null_if_empty($_POST['resident_3m'] ?? null);
        $out_of_country_visits = null_if_empty($_POST['out_of_country_visits'] ?? null);
        
        // Handle date fields - empty strings to NULL (dates can be NULL)
        $onset_date = null_if_empty($_POST['onset_date'] ?? null);
        $diagnosis_date = null_if_empty($_POST['diagnosis_date'] ?? null);
        
        // Handle text fields - empty strings to NULL
        $patient_type = null_if_empty($_POST['patient_type'] ?? null);
        $perianal_other = null_if_empty($_POST['perianal_other'] ?? null);
        $resident_other = null_if_empty($_POST['resident_other'] ?? null);
        $out_country_other = null_if_empty($_POST['out_country_other'] ?? null);
        
        // Build INSERT query
        $sql = "INSERT INTO ibd_diagnoses (
            patient_id, 
            diagnosis, 
            other_diagnosis,
            onset_date, 
            diagnosis_date, 
            patient_type,
            diagnostic_criteria, 
            uc_location, 
            cd_location_set, 
            upper_gi, 
            cd_behavior,
            perianal_disease, 
            perianal_other, 
            resident_3m, 
            resident_other,
            out_of_country_visits, 
            out_country_other,
            created_at
        ) VALUES (
            :patient_id, 
            :diagnosis, 
            :other_diagnosis,
            :onset_date, 
            :diagnosis_date, 
            :patient_type,
            :diagnostic_criteria, 
            :uc_location, 
            :cd_location_set, 
            :upper_gi, 
            :cd_behavior,
            :perianal_disease, 
            :perianal_other, 
            :resident_3m, 
            :resident_other,
            :out_of_country_visits, 
            :out_country_other,
            NOW()
        )";

        $params = [
            'patient_id' => $patient_id,
            'diagnosis' => $diagnosis,
            'other_diagnosis' => $other_diagnosis,
            'onset_date' => $onset_date,
            'diagnosis_date' => $diagnosis_date,
            'patient_type' => $patient_type,
            'diagnostic_criteria' => $diagnostic_criteria,
            'uc_location' => $uc_location,
            'cd_location_set' => $cd_location,
            'upper_gi' => $upper_gi,
            'cd_behavior' => $cd_behavior,
            'perianal_disease' => $perianal_disease,
            'perianal_other' => $perianal_other,
            'resident_3m' => $resident_3m,
            'resident_other' => $resident_other,
            'out_of_country_visits' => $out_of_country_visits,
            'out_country_other' => $out_country_other
        ];

        // Log parameters for debugging
        error_log("IBD Insert Params: " . print_r($params, true));

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to insert record');
        }
        
        $id = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        // Audit the addition
        if (function_exists('audit')) {
            audit($pdo, 'ibd.add', 'ibd_diagnoses', $id, [
                'patient_id' => $patient_id,
                'diagnosis' => $diagnosis ?? 'Not specified',
                'other_diagnosis' => $other_diagnosis ?? null
            ]);
        }
        
        $_SESSION['success'] = 'IBD diagnosis record added successfully';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log the full error
        error_log("IBD Add PDO Error: " . $e->getMessage());
        error_log("SQL State: " . ($e->errorInfo[0] ?? 'N/A'));
        error_log("Error Code: " . ($e->errorInfo[1] ?? 'N/A'));
        error_log("Error Message: " . ($e->errorInfo[2] ?? 'N/A'));
        
        // Check for specific error about L4
        if (strpos($e->getMessage(), 'L4') !== false) {
            $_SESSION['error'] = 'The database needs to be updated to support L4. Please run: ALTER TABLE ibd_diagnoses MODIFY cd_location_set set(\'L1\',\'L2\',\'L3\',\'L4\') DEFAULT NULL;';
        } elseif (strpos($e->getMessage(), '1265') !== false || strpos($e->getMessage(), 'Data truncated') !== false) {
            $_SESSION['error'] = 'Invalid value for one of the dropdown fields. Please select Yes or No, or leave blank.';
        } elseif (strpos($e->getMessage(), '1048') !== false || strpos($e->getMessage(), 'cannot be null') !== false) {
            $_SESSION['error'] = 'Some required fields cannot be empty. Please check your selections.';
        } else {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("IBD Add General Error: " . $e->getMessage());
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
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
    --black: #000000;
}

/* Global Styles */
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
.page-header h6 {
    color: var(--black);
    font-weight: 600;
}
.page-header h6 i {
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
    background-color: #f8d7da;
    border-left: 4px solid #dc3545;
    color: #721c24;
    border-radius: 4px;
    padding: 12px 16px;
    margin-bottom: 20px;
}
.alert i {
    color: #dc3545 !important;
}
.alert-success {
    background-color: #d4edda;
    border-left-color: #28a745;
    color: #155724;
}
.alert-success i {
    color: #28a745 !important;
}
.alert-info {
    background-color: #d1ecf1;
    border-left-color: #17a2b8;
    color: #0c5460;
}

/* Section Cards */
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
    border-bottom: 2px solid;
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-header.blue {
    border-bottom-color: var(--blue);
}
.section-header.green {
    border-bottom-color: var(--green);
}
.section-header i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 8px;
    border-radius: 8px;
    font-size: 1rem;
}
.section-header.green i {
    background-color: var(--green);
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

/* Serial Number Badge */
.serial-number {
    display: inline-block;
    width: 22px;
    height: 22px;
    background-color: var(--blue);
    color: var(--white);
    border-radius: 50%;
    text-align: center;
    line-height: 22px;
    font-size: 12px;
    margin-right: 8px;
    font-weight: 600;
}
.serial-number.green {
    background-color: var(--green);
}

/* Form Elements */
.form-label {
    color: var(--black);
    font-weight: 500;
    margin-bottom: 6px;
    font-size: 0.9rem;
}
.form-control, .form-select {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 12px;
    color: var(--black);
    background-color: var(--white);
    transition: all 0.2s;
}
.form-control:focus, .form-select:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
}
.form-control::placeholder {
    color: var(--black);
    opacity: 0.4;
}

/* Checkbox Group */
.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 15px 25px;
    align-items: center;
    background-color: var(--ash);
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 10px;
}
.checkbox-group .form-check {
    margin-right: 0;
    padding-left: 1.5rem;
}
.checkbox-group .form-check-input {
    margin-left: -1.5rem;
    width: 16px;
    height: 16px;
    margin-top: 0.2rem;
}
.checkbox-group .form-check-input:checked {
    background-color: var(--blue);
    border-color: var(--blue);
}
.checkbox-group .form-check-label {
    font-size: 0.9rem;
    color: var(--black);
}

/* Conditional Fields */
.conditional-field {
    transition: all 0.2s ease;
    margin-left: 10px;
}
.conditional-field .form-control {
    background-color: var(--white);
}

/* Flex Row */
.flex-row {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.flex-row .form-select,
.flex-row .form-control {
    flex: 1;
}
.w-auto {
    width: auto;
    min-width: 120px;
}

/* Button Styles */
.btn-primary {
    background-color: var(--blue);
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    font-family: Cambria, serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.btn-primary:hover {
    background-color: #357ABD;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(74,144,226,0.2);
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
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 4px;
    border-radius: 4px;
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
    gap: 6px;
    font-size: 0.85rem;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
}
.btn-outline-secondary i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 3px;
    border-radius: 4px;
}

/* Two Column Layout */
.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
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

/* Card Footer */
.card-footer {
    background-color: var(--white);
    border-top: 1px solid var(--ash);
    padding: 16px 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Required Field Indicator */
.required-field {
    color: #dc3545;
    margin-left: 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .two-column {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h6><i class="fa-solid fa-dna"></i>Add IBD Diagnosis</h6>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?= $patient_id ?>">Profile</a></li>
                    <li class="breadcrumb-item active">Add IBD</li>
                </ol>
            </nav>
        </div>
        <div>
            <span class="text-muted">
                <i class="fa-regular fa-calendar me-1" style="color: var(--blue);"></i><?= date('l, F j, Y') ?>
            </span>
        </div>
    </div>

    <!-- Display Error Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert">
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
        <strong>Patient:</strong> <?= htmlspecialchars($patient['name'] ?? '') ?> (ID: <?= $patient['id'] ?>) | 
        <strong>Sex:</strong> <?= ($patient['sex'] ?? '') == 'M' ? 'Male' : 'Female' ?> |
        <strong>DOB:</strong> <?= htmlspecialchars($patient['dob'] ?? 'Not provided') ?>
    </div>

    <!-- Info Note about optional fields -->
    <div class="alert alert-info" style="margin-bottom: 20px; padding: 10px 15px; border-radius: 4px;">
        <i class="fa-solid fa-info-circle me-2"></i>
        <strong>Note:</strong> Fields marked with <span class="required-field">*</span> are required. All other fields are optional - you can leave them blank.
    </div>

    <!-- Main Form -->
    <form method="post" id="ibdForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token() ?? '') ?>">

        <!-- TWO COLUMN LAYOUT - Diagnosis Section -->
        <div class="two-column">
            <!-- LEFT COLUMN - Basic Diagnosis Info -->
            <div class="section-card">
                <div class="section-header blue">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <h6>Diagnosis Information</h6>
                </div>
                <div class="section-body">
                    <!-- 1. Diagnosis - REQUIRED -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">1</span>
                            <strong style="color: var(--black);">Diagnosis <span class="required-field">*</span></strong>
                        </div>
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="diagnosis[]" value="Ulcerative colitis" id="dx_uc">
                                <label class="form-check-label" for="dx_uc">Ulcerative colitis</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="diagnosis[]" value="Crohn's disease" id="dx_cd">
                                <label class="form-check-label" for="dx_cd">Crohn's disease</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="diagnosis[]" value="Indeterminate" id="dx_ind">
                                <label class="form-check-label" for="dx_ind">Indeterminate</label>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Date of onset - OPTIONAL -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">2</span>
                            <strong style="color: var(--black);">Date of onset of symptoms</strong>
                        </div>
                        <input type="date" name="onset_date" class="form-control">
                    </div>

                    <!-- 3. Date of diagnosis - OPTIONAL -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">3</span>
                            <strong style="color: var(--black);">Date of diagnosis</strong>
                        </div>
                        <input type="date" name="diagnosis_date" class="form-control">
                    </div>

                    <!-- 4. Inpatient/Outpatient - OPTIONAL -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">4</span>
                            <strong style="color: var(--black);">Inpatient/Outpatient</strong>
                        </div>
                        <input type="text" name="patient_type" class="form-control" placeholder="Inpatient or Outpatient">
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN - Diagnostic Criteria -->
            <div class="section-card">
                <div class="section-header green">
                    <i class="fa-solid fa-check-double"></i>
                    <h6>Diagnostic Criteria</h6>
                </div>
                <div class="section-body">
                    <!-- 5. Diagnostics criteria - OPTIONAL -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number green">5</span>
                            <strong style="color: var(--black);">Diagnostics criteria</strong>
                        </div>
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="diagnostic_criteria[]" value="Clinical" id="crit_clinical">
                                <label class="form-check-label" for="crit_clinical">Clinical</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="diagnostic_criteria[]" value="Endoscopic" id="crit_endoscopic">
                                <label class="form-check-label" for="crit_endoscopic">Endoscopic</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="diagnostic_criteria[]" value="Radiologic" id="crit_radiologic">
                                <label class="form-check-label" for="crit_radiologic">Radiologic</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="diagnostic_criteria[]" value="Histologic" id="crit_histologic">
                                <label class="form-check-label" for="crit_histologic">Histologic</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TWO COLUMN LAYOUT - Disease Location -->
        <div class="two-column">
            <!-- LEFT COLUMN - UC Location -->
            <div class="section-card">
                <div class="section-header blue">
                    <i class="fa-solid fa-location-dot"></i>
                    <h6>UC Disease Location</h6>
                </div>
                <div class="section-body">
                    <!-- 6. UC disease location - OPTIONAL -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">6</span>
                            <strong style="color: var(--black);">UC disease location</strong>
                        </div>
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="uc_location[]" value="E1" id="uc_e1">
                                <label class="form-check-label" for="uc_e1">E1</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="uc_location[]" value="E2" id="uc_e2">
                                <label class="form-check-label" for="uc_e2">E2</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="uc_location[]" value="E3" id="uc_e3">
                                <label class="form-check-label" for="uc_e3">E3</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN - CD Location & Behavior -->
            <div class="section-card">
                <div class="section-header green">
                    <i class="fa-solid fa-map-pin"></i>
                    <h6>CD Disease Location & Behavior</h6>
                </div>
                <div class="section-body">
                    <!-- 7. CD disease location - OPTIONAL (with L4) -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number green">7</span>
                            <strong style="color: var(--black);">CD disease location</strong>
                        </div>
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cd_location[]" value="L1" id="cd_l1">
                                <label class="form-check-label" for="cd_l1">L1</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cd_location[]" value="L2" id="cd_l2">
                                <label class="form-check-label" for="cd_l2">L2</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cd_location[]" value="L3" id="cd_l3">
                                <label class="form-check-label" for="cd_l3">L3</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cd_location[]" value="L4" id="cd_l4">
                                <label class="form-check-label" for="cd_l4">L4</label>
                            </div>
                        </div>
                        
                    </div>

                    <!-- 8. Upper GI - OPTIONAL -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">8</span>
                            <strong style="color: var(--black);">Upper GI</strong>
                        </div>
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="upper_gi[]" value="Yes" id="ugi_yes">
                                <label class="form-check-label" for="ugi_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="upper_gi[]" value="No" id="ugi_no">
                                <label class="form-check-label" for="ugi_no">No</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="upper_gi[]" value="Isolated" id="ugi_isolated">
                                <label class="form-check-label" for="ugi_isolated">Isolated</label>
                            </div>
                        </div>
                    </div>

                    <!-- 9. CD disease behavior - OPTIONAL -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number green">9</span>
                            <strong style="color: var(--black);">CD disease behavior</strong>
                        </div>
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cd_behavior[]" value="B1" id="cd_b1">
                                <label class="form-check-label" for="cd_b1">B1</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cd_behavior[]" value="B2" id="cd_b2">
                                <label class="form-check-label" for="cd_b2">B2</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cd_behavior[]" value="B3" id="cd_b3">
                                <label class="form-check-label" for="cd_b3">B3</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TWO COLUMN LAYOUT - Additional Information -->
        <div class="two-column">
            <!-- LEFT COLUMN - Perianal & Resident -->
            <div class="section-card">
                <div class="section-header blue">
                    <i class="fa-solid fa-notes-medical"></i>
                    <h6>Additional Clinical Information</h6>
                </div>
                <div class="section-body">
                    <!-- 10. Perianal disease - OPTIONAL (can be left blank, will be saved as NULL) -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">10</span>
                            <strong style="color: var(--black);">Perianal disease</strong>
                        </div>
                        <div class="flex-row">
                            <select class="form-select w-auto conditional-select" name="perianal_disease" data-target="#perianal_details">
                                <option value="">-- Leave Blank --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="perianal_details" class="conditional-field flex-grow-1" style="display: none;">
                                <input type="text" class="form-control" name="perianal_other" placeholder="Details">
                            </div>
                        </div>
                        
                    </div>

                    <!-- 11. Resident in area - OPTIONAL (can be left blank, will be saved as NULL) -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">11</span>
                            <strong style="color: var(--black);">Resident in area ≥3 months</strong>
                        </div>
                        <div class="flex-row">
                            <select class="form-select w-auto conditional-select" name="resident_3m" data-target="#resident_details">
                                <option value="">-- Leave Blank --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="resident_details" class="conditional-field flex-grow-1" style="display: none;">
                                <input type="text" class="form-control" name="resident_other" placeholder="Details">
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN - Out of Country -->
            <div class="section-card">
                <div class="section-header green">
                    <i class="fa-solid fa-globe"></i>
                    <h6>Travel History</h6>
                </div>
                <div class="section-body">
                    <!-- 12. Out of country visits - OPTIONAL (can be left blank, will be saved as NULL) -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number green">12</span>
                            <strong style="color: var(--black);">Out of country visits</strong>
                        </div>
                        <div class="flex-row">
                            <select class="form-select w-auto conditional-select" name="out_of_country_visits" data-target="#outcountry_details">
                                <option value="">-- Leave Blank --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="outcountry_details" class="conditional-field flex-grow-1" style="display: none;">
                                <input type="text" class="form-control" name="out_country_other" placeholder="Countries / dates">
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>

        <!-- SEPARATE OTHER DIAGNOSIS FIELD - Added at the end of the form -->
        <div class="section-card">
            <div class="section-header blue">
                <i class="fa-solid fa-pen-to-square"></i>
                <h6>Other Diagnosis</h6>
            </div>
            <div class="section-body">
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <span class="serial-number">13</span>
                        <strong style="color: var(--black);">Other Diagnosis (If not listed above)</strong>
                    </div>
                    <textarea name="other_diagnosis" class="form-control" rows="3" placeholder="Please enter any other diagnosis not listed above..."></textarea>
                    <small class="text-muted">Optional field for any additional diagnosis information.</small>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card-footer">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save IBD Diagnosis
            </button>
            <a href="?r=patients/profile&id=<?= $patient_id ?>" class="btn-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
(function() {
    // Conditional field toggling for Yes/No dropdowns
    function toggleConditionalField(select) {
        const targetId = select.getAttribute('data-target');
        if (!targetId) return;
        
        const targetField = document.querySelector(targetId);
        if (!targetField) return;
        
        const show = (select.value === 'Yes');
        targetField.style.display = show ? 'block' : 'none';
        
        // Clear field if hidden
        if (!show) {
            const input = targetField.querySelector('input');
            if (input) input.value = '';
        }
    }

    // Initialize conditional selects
    document.querySelectorAll('.conditional-select').forEach(select => {
        select.addEventListener('change', () => toggleConditionalField(select));
        toggleConditionalField(select);
    });

    // Form validation - only require at least one diagnosis
    document.getElementById('ibdForm')?.addEventListener('submit', function(e) {
        // At least one diagnosis should be selected (this is the only required field)
        const diagnosisChecked = document.querySelectorAll('input[name="diagnosis[]"]:checked').length;
        if (diagnosisChecked === 0) {
            e.preventDefault();
            alert('Please select at least one diagnosis');
            return false;
        }
        
        // All other fields including Other Diagnosis are optional - no validation needed
        return true;
    });
})();
</script>

<?php include __DIR__.'/../../templates/footer.php'; ?>