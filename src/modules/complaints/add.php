<?php
/**
 * modules/complaints/add.php - Add Complaint Record
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fix the path - go up two levels to reach src/includes/
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/permissions.php';

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

if (!has_permission($pdo, 'button.module.complaints.add')) {
    $_SESSION['error'] = 'You do not have permission to add complaint records';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: ?r=modules/complaints/add&patient_id=' . $patient_id);
        exit;
    }
    
    $sql = "INSERT INTO complaints (
        patient_id, 
        abdominal_pain, pain_type, 
        diarrhea, diarrhoea_details,
        blood_in_stool, blood_details,
        mucus_in_stool, mucus_details, frequency_per_day,
        weight_loss, weight_loss_kg, weight_loss_period,
        fever, fever_duration, fever_grade,
        sub_acute_intestinal_obstruction, sub_acute_intestinal_obstruction_details,
        relapses_count, last_one_year,
        extraintestinal_type, arthritis_details, uveitis_details, skin_details, extraintestinal_other_details,
        hospitalization, hospitalization_date, hospitalization_reason, hospitalization_reason_other,
        past_surgery, past_surgery_date, past_surgery_details,
        family_history_ibd, family_history_type, family_history_relations,
        comorbid_type, dm_details, htn_details, hypothyroidism_details, comorbid_other_details,
        cancer_history, cancer_specify
    ) VALUES (
        :patient_id,
        :abdominal_pain, :pain_type,
        :diarrhea, :diarrhoea_details,
        :blood_in_stool, :blood_details,
        :mucus_in_stool, :mucus_details, :frequency_per_day,
        :weight_loss, :weight_loss_kg, :weight_loss_period,
        :fever, :fever_duration, :fever_grade,
        :sub_acute_intestinal_obstruction, :sub_acute_intestinal_obstruction_details,
        :relapses_count, :last_one_year,
        :extraintestinal_type, :arthritis_details, :uveitis_details, :skin_details, :extraintestinal_other_details,
        :hospitalization, :hospitalization_date, :hospitalization_reason, :hospitalization_reason_other,
        :past_surgery, :past_surgery_date, :past_surgery_details,
        :family_history_ibd, :family_history_type, :family_history_relations,
        :comorbid_type, :dm_details, :htn_details, :hypothyroidism_details, :comorbid_other_details,
        :cancer_history, :cancer_specify
    )";
    
    // Prepare parameters with proper null handling
    $params = [
        'patient_id' => $patient_id,
        'abdominal_pain' => !empty($_POST['abdominal_pain']) ? $_POST['abdominal_pain'] : null,
        'pain_type' => !empty($_POST['pain_type']) ? $_POST['pain_type'] : null,
        'diarrhea' => !empty($_POST['diarrhea']) ? $_POST['diarrhea'] : null,
        'diarrhoea_details' => !empty($_POST['diarrhoea_details']) ? $_POST['diarrhoea_details'] : null,
        'blood_in_stool' => !empty($_POST['blood_in_stool']) ? $_POST['blood_in_stool'] : null,
        'blood_details' => !empty($_POST['blood_details']) ? $_POST['blood_details'] : null,
        'mucus_in_stool' => !empty($_POST['mucus_in_stool']) ? $_POST['mucus_in_stool'] : null,
        'mucus_details' => !empty($_POST['mucus_details']) ? $_POST['mucus_details'] : null,
        'frequency_per_day' => !empty($_POST['frequency_per_day']) ? (int)$_POST['frequency_per_day'] : null,
        'weight_loss' => !empty($_POST['weight_loss']) ? $_POST['weight_loss'] : null,
        'weight_loss_kg' => !empty($_POST['weight_loss_kg']) ? (float)$_POST['weight_loss_kg'] : null,
        'weight_loss_period' => !empty($_POST['weight_loss_period']) ? $_POST['weight_loss_period'] : null,
        'fever' => !empty($_POST['fever']) ? $_POST['fever'] : null,
        'fever_duration' => !empty($_POST['fever_duration']) ? $_POST['fever_duration'] : null,
        'fever_grade' => !empty($_POST['fever_grade']) ? $_POST['fever_grade'] : null,
        'sub_acute_intestinal_obstruction' => !empty($_POST['sub_acute_intestinal_obstruction']) ? $_POST['sub_acute_intestinal_obstruction'] : null,
        'sub_acute_intestinal_obstruction_details' => !empty($_POST['sub_acute_intestinal_obstruction_details']) ? $_POST['sub_acute_intestinal_obstruction_details'] : null,
        'relapses_count' => !empty($_POST['relapses_count']) ? (int)$_POST['relapses_count'] : null,
        'last_one_year' => !empty($_POST['last_one_year']) ? $_POST['last_one_year'] : null,
        'extraintestinal_type' => !empty($_POST['extraintestinal_type']) ? $_POST['extraintestinal_type'] : null,
        'arthritis_details' => !empty($_POST['arthritis_details']) ? $_POST['arthritis_details'] : null,
        'uveitis_details' => !empty($_POST['uveitis_details']) ? $_POST['uveitis_details'] : null,
        'skin_details' => !empty($_POST['skin_details']) ? $_POST['skin_details'] : null,
        'extraintestinal_other_details' => !empty($_POST['extraintestinal_other_details']) ? $_POST['extraintestinal_other_details'] : null,
        'hospitalization' => !empty($_POST['hospitalization']) ? $_POST['hospitalization'] : null,
        'hospitalization_date' => !empty($_POST['hospitalization_date']) ? $_POST['hospitalization_date'] : null,
        'hospitalization_reason' => !empty($_POST['hospitalization_reason']) ? $_POST['hospitalization_reason'] : null,
        'hospitalization_reason_other' => !empty($_POST['hospitalization_reason_other']) ? $_POST['hospitalization_reason_other'] : null,
        'past_surgery' => !empty($_POST['past_surgery']) ? $_POST['past_surgery'] : null,
        'past_surgery_date' => !empty($_POST['past_surgery_date']) ? $_POST['past_surgery_date'] : null,
        'past_surgery_details' => !empty($_POST['past_surgery_details']) ? $_POST['past_surgery_details'] : null,
        'family_history_ibd' => !empty($_POST['family_history_ibd']) ? $_POST['family_history_ibd'] : null,
        'family_history_type' => !empty($_POST['family_history_type']) ? $_POST['family_history_type'] : null,
        'family_history_relations' => !empty($_POST['family_history_relations']) ? $_POST['family_history_relations'] : null,
        'comorbid_type' => !empty($_POST['comorbid_type']) ? $_POST['comorbid_type'] : null,
        'dm_details' => !empty($_POST['dm_details']) ? $_POST['dm_details'] : null,
        'htn_details' => !empty($_POST['htn_details']) ? $_POST['htn_details'] : null,
        'hypothyroidism_details' => !empty($_POST['hypothyroidism_details']) ? $_POST['hypothyroidism_details'] : null,
        'comorbid_other_details' => !empty($_POST['comorbid_other_details']) ? $_POST['comorbid_other_details'] : null,
        'cancer_history' => !empty($_POST['cancer_history']) ? $_POST['cancer_history'] : null,
        'cancer_specify' => !empty($_POST['cancer_specify']) ? $_POST['cancer_specify'] : null
    ];

    // Debug: Log the SQL and parameters
    error_log("Complaints Add SQL: " . $sql);
    error_log("Complaints Add Params: " . print_r($params, true));

    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            $errorInfo = $pdo->errorInfo();
            error_log("Prepare failed: " . print_r($errorInfo, true));
            throw new Exception("Database prepare failed");
        }
        
        $result = $stmt->execute($params);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("Execute failed: " . print_r($errorInfo, true));
            throw new Exception("Database execute failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }
        
        $new_id = $pdo->lastInsertId();
        error_log("New complaint ID: " . $new_id);
        
        // Commit transaction
        $pdo->commit();
        
        if (function_exists('audit')) {
            audit($pdo, 'complaints.add', 'complaints', $new_id, ['patient_id' => $patient_id]);
        }
        
        $_SESSION['success'] = 'Complaint record added successfully';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error in complaint add: " . $e->getMessage());
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        // Stay on the form page to show error
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
/* Color Variables */
:root {
    --white: #FFFFFF;
    --ash: #F2F4F8;
    --blue: #4A90E2;
    --green: #2ECC71;
    --red: #e74c3c;
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
    background-color: var(--white);
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
.form-check-input:checked {
    background-color: var(--blue);
    border-color: var(--blue);
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

/* Button Styles */
.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 12px 24px;
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
    padding: 12px 24px;
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

/* Conditional Fields */
.conditional-field {
    transition: all 0.2s ease;
    margin-top: 10px;
    padding: 15px;
    background-color: var(--ash);
    border-radius: 6px;
    border-left: 3px solid var(--blue);
}

/* Nested Conditional Fields */
.nested-field {
    margin-top: 10px;
    padding: 15px;
    background-color: var(--white);
    border-radius: 6px;
    border-left: 3px solid var(--green);
}

/* Responsive */
@media (max-width: 768px) {
    .two-column, .three-column {
        grid-template-columns: 1fr;
    }
}

/* Patient Info Badge */
.patient-badge {
    background-color: var(--ash);
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid var(--blue);
    display: flex;
    align-items: center;
    gap: 10px;
}
.patient-badge strong {
    color: var(--blue);
}
.patient-badge i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 8px;
    border-radius: 50%;
    margin-right: 8px;
}

/* Serial Number */
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

/* Info Note */
.info-note {
    background-color: #d1ecf1;
    border-left: 4px solid #17a2b8;
    color: #0c5460;
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.info-note i {
    color: #17a2b8 !important;
    margin-right: 8px;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h6><i class="fa-solid fa-stethoscope"></i>Add Complaints</h6>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?=$patient_id?>">Profile</a></li>
                    <li class="breadcrumb-item active">Add Complaints</li>
                </ol>
            </nav>
        </div>
        <div>
            <span class="text-muted">
                <i class="fa-regular fa-calendar me-1" style="color: var(--blue);"></i><?=date('l, F j, Y')?>
            </span>
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

    <!-- Info Note -->
    <div class="info-note">
        <i class="fa-solid fa-info-circle"></i>
        <strong>Note:</strong> All fields are optional. For Extraintestinal Manifestations and Comorbid Conditions, select "Yes" to show additional options.
    </div>

    <!-- Patient Info Badge -->
    <div class="patient-badge">
        <i class="fa-solid fa-user"></i>
        <div>
            <strong>Patient:</strong> <?=htmlspecialchars($pat['name'])?> (ID: <?=$pat['id']?>) | 
            <strong>Sex:</strong> <?=$pat['sex'] == 'M' ? 'Male' : 'Female'?> |
            <strong>DOB:</strong> <?=htmlspecialchars($pat['dob'] ?? 'Not provided')?>
        </div>
    </div>

    <!-- Main Form -->
    <form method="post" id="complaintForm">
        <input type="hidden" name="_csrf" value="<?=htmlspecialchars(csrf_token())?>">

        <!-- TWO COLUMN LAYOUT - Core Symptoms & General Symptoms -->
        <div class="two-column">
            <!-- LEFT COLUMN - Core Symptoms -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fa-solid fa-heart-pulse"></i>
                    <h6>Core Symptoms</h6>
                </div>
                <div class="section-body">
                    <div class="row g-3">
                        <!-- 1. Abdominal Pain -->
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">1</span>
                                <strong style="color: var(--black);">Abdominal Pain</strong>
                            </div>
                            <select class="form-select conditional-select" name="abdominal_pain" data-target="#pain_type_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="pain_type_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Pain Type</label>
                                <select class="form-select" name="pain_type">
                                    <option value="">Select</option>
                                    <option value="Mild">Mild</option>
                                    <option value="Moderate">Moderate</option>
                                    <option value="Severe">Severe</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. Diarrhea -->
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">2</span>
                                <strong style="color: var(--black);">Diarrhea</strong>
                            </div>
                            <select class="form-select conditional-select" name="diarrhea" data-target="#diarrhea_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="diarrhea_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Details</label>
                                <input type="text" class="form-control" name="diarrhoea_details" placeholder="Specify details">
                            </div>
                        </div>

                        <!-- 3. Blood in Stool -->
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">3</span>
                                <strong style="color: var(--black);">Blood in Stool</strong>
                            </div>
                            <select class="form-select conditional-select" name="blood_in_stool" data-target="#blood_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="blood_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Details</label>
                                <input type="text" class="form-control" name="blood_details" placeholder="Specify details">
                            </div>
                        </div>

                        <!-- 4. Mucus in Stool -->
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">4</span>
                                <strong style="color: var(--black);">Mucus in Stool</strong>
                            </div>
                            <select class="form-select conditional-select" name="mucus_in_stool" data-target="#mucus_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="mucus_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Frequency/Day</label>
                                <input type="number" class="form-control" name="frequency_per_day" min="0" placeholder="Times per day">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN - General Symptoms -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fa-solid fa-temperature-high"></i>
                    <h6>General Symptoms</h6>
                </div>
                <div class="section-body">
                    <div class="row g-3">
                        <!-- 5. Weight Loss -->
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">5</span>
                                <strong style="color: var(--black);">Weight Loss</strong>
                            </div>
                            <select class="form-select conditional-select" name="weight_loss" data-target="#weight_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="weight_section" class="conditional-field" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Amount (kg)</label>
                                        <input type="number" step="0.1" class="form-control" name="weight_loss_kg" placeholder="kg">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Period</label>
                                        <input type="text" class="form-control" name="weight_loss_period" placeholder="e.g., 3 months">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Fever -->
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">6</span>
                                <strong style="color: var(--black);">Fever</strong>
                            </div>
                            <select class="form-select conditional-select" name="fever" data-target="#fever_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="fever_section" class="conditional-field" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Duration</label>
                                        <input type="text" class="form-control" name="fever_duration" placeholder="e.g., 3 days">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Grade</label>
                                        <select class="form-select" name="fever_grade">
                                            <option value="">Select</option>
                                            <option value="High">High</option>
                                            <option value="Low">Low</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 7. Sub-acute Intestinal Obstructions - NEW FIELD -->
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">7</span>
                                <strong style="color: var(--black);">Sub-acute Intestinal Obstructions</strong>
                            </div>
                            <select class="form-select conditional-select" name="sub_acute_intestinal_obstruction" data-target="#sub_acute_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="sub_acute_section" class="conditional-field" style="display: none;">
                                <label class="form-label">Details</label>
                                <input type="text" class="form-control" name="sub_acute_intestinal_obstruction_details" placeholder="Specify details (frequency, severity, etc.)">
                            </div>
                        </div>

                        <!-- 8. Relapses -->
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">8</span>
                                <strong style="color: var(--black);">Relapses</strong>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" name="relapses_count" min="0" placeholder="Total count">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control" name="last_one_year" placeholder="Last year">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 9. Extraintestinal Manifestations -->
        <div class="section-card">
            <div class="section-header">
                <i class="fa-solid fa-eye"></i>
                <h6>9. Extraintestinal Manifestations</h6>
            </div>
            <div class="section-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Has Extraintestinal Manifestations?</label>
                        <select class="form-select nested-conditional-select" name="has_extraintestinal" data-target="#extraintestinal_type_section">
                            <option value="">Select</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div id="extraintestinal_type_section" class="col-md-12 conditional-field" style="display: none;">
                        <label class="form-label">Select Type</label>
                        <select class="form-select nested-conditional-select" name="extraintestinal_type" data-target-container="#extraintestinal_details_container">
                            <option value="">Select Type</option>
                            <option value="Arthritis">Arthritis</option>
                            <option value="Uveitis">Uveitis</option>
                            <option value="Skin">Skin</option>
                            <option value="Other">Other</option>
                        </select>
                        
                        <div id="extraintestinal_details_container" class="mt-3">
                            <!-- Arthritis Details -->
                            <div id="arthritis_details_section" class="nested-field" style="display: none;">
                                <label class="form-label">Arthritis Details</label>
                                <input type="text" class="form-control" name="arthritis_details" placeholder="Enter arthritis details">
                            </div>
                            
                            <!-- Uveitis Details -->
                            <div id="uveitis_details_section" class="nested-field" style="display: none;">
                                <label class="form-label">Uveitis Details</label>
                                <input type="text" class="form-control" name="uveitis_details" placeholder="Enter uveitis details">
                            </div>
                            
                            <!-- Skin Details -->
                            <div id="skin_details_section" class="nested-field" style="display: none;">
                                <label class="form-label">Skin Details</label>
                                <input type="text" class="form-control" name="skin_details" placeholder="Enter skin details">
                            </div>
                            
                            <!-- Other Details -->
                            <div id="other_details_section" class="nested-field" style="display: none;">
                                <label class="form-label">Other Details</label>
                                <input type="text" class="form-control" name="extraintestinal_other_details" placeholder="Specify other details">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hospitalization & Surgery -->
        <div class="two-column">
            <!-- 10. Hospitalization History -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fa-solid fa-hospital"></i>
                    <h6>10. Hospitalization History</h6>
                </div>
                <div class="section-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">10</span>
                                <strong style="color: var(--black);">Ever Hospitalized?</strong>
                            </div>
                            <select class="form-select conditional-select" name="hospitalization" data-target="#hospitalization_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="hospitalization_section" class="conditional-field" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="hospitalization_date">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Reason</label>
                                        <select class="form-select" name="hospitalization_reason">
                                            <option value="">Select</option>
                                            <option value="Relapse">Relapse</option>
                                            <option value="Surgery">Surgery</option>
                                            <option value="Others">Others</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control" name="hospitalization_reason_other" placeholder="If others, specify">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 11. Past Surgery -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fa-solid fa-scalpel"></i>
                    <h6>11. Past Surgery</h6>
                </div>
                <div class="section-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">11</span>
                                <strong style="color: var(--black);">Past Surgery?</strong>
                            </div>
                            <select class="form-select conditional-select" name="past_surgery" data-target="#surgery_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="surgery_section" class="conditional-field" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="past_surgery_date">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Details</label>
                                        <input type="text" class="form-control" name="past_surgery_details" placeholder="Surgery details">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Family History & Comorbidities -->
        <div class="two-column">
            <!-- 12. Family History -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fa-solid fa-family"></i>
                    <h6>12. Family History</h6>
                </div>
                <div class="section-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2">
                                <span class="serial-number">12</span>
                                <strong style="color: var(--black);">Family History of IBD?</strong>
                            </div>
                            <select class="form-select conditional-select" name="family_history_ibd" data-target="#family_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            <div id="family_section" class="conditional-field" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Type</label>
                                        <input type="text" class="form-control" name="family_history_type" placeholder="UC/CD">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Relation</label>
                                        <input type="text" class="form-control" name="family_history_relations" placeholder="Mother, Father, etc.">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 13. Comorbid Conditions -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fa-solid fa-notes-medical"></i>
                    <h6>13. Comorbid Conditions</h6>
                </div>
                <div class="section-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Has Comorbid Conditions?</label>
                            <select class="form-select nested-conditional-select" name="has_comorbid" data-target="#comorbid_type_section">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div id="comorbid_type_section" class="col-md-12 conditional-field" style="display: none;">
                            <label class="form-label">Select Type</label>
                            <select class="form-select nested-conditional-select" name="comorbid_type" data-target-container="#comorbid_details_container">
                                <option value="">Select Type</option>
                                <option value="DM">DM</option>
                                <option value="HTN">HTN</option>
                                <option value="Hypothyroidism">Hypothyroidism</option>
                                <option value="Others">Others</option>
                            </select>
                            
                            <div id="comorbid_details_container" class="mt-3">
                                <!-- DM Details -->
                                <div id="dm_details_section" class="nested-field" style="display: none;">
                                    <label class="form-label">DM Details</label>
                                    <input type="text" class="form-control" name="dm_details" placeholder="Enter DM details">
                                </div>
                                
                                <!-- HTN Details -->
                                <div id="htn_details_section" class="nested-field" style="display: none;">
                                    <label class="form-label">HTN Details</label>
                                    <input type="text" class="form-control" name="htn_details" placeholder="Enter HTN details">
                                </div>
                                
                                <!-- Hypothyroidism Details -->
                                <div id="hypothyroidism_details_section" class="nested-field" style="display: none;">
                                    <label class="form-label">Hypothyroidism Details</label>
                                    <input type="text" class="form-control" name="hypothyroidism_details" placeholder="Enter thyroid details">
                                </div>
                                
                                <!-- Others Details -->
                                <div id="comorbid_other_details_section" class="nested-field" style="display: none;">
                                    <label class="form-label">Other Details</label>
                                    <input type="text" class="form-control" name="comorbid_other_details" placeholder="Specify other conditions">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 14. Cancer History -->
        <div class="section-card">
            <div class="section-header">
                <i class="fa-solid fa-disease"></i>
                <h6>14. Cancer History</h6>
            </div>
            <div class="section-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <span class="serial-number">14</span>
                            <strong style="color: var(--black);">History of Cancer?</strong>
                        </div>
                        <select class="form-select conditional-select" name="cancer_history" data-target="#cancer_section">
                            <option value="">Select</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div id="cancer_section" class="col-md-8 conditional-field" style="display: none;">
                        <label class="form-label">Specify</label>
                        <input type="text" class="form-control" name="cancer_specify" placeholder="Cancer type and details">
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="d-flex gap-3 mt-4">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Complaints
            </button>
            <a href="?r=patients/profile&id=<?=$patient_id?>" class="btn-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Conditional field toggling for basic Yes/No fields
function toggleConditionalField(select) {
    const targetId = select.getAttribute('data-target');
    if (!targetId) return;
    
    const targetField = document.querySelector(targetId);
    if (!targetField) return;
    
    const show = (select.value === 'Yes');
    targetField.style.display = show ? 'block' : 'none';
    
    // Clear fields if hidden
    if (!show) {
        targetField.querySelectorAll('input, select, textarea').forEach(field => {
            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = false;
            } else {
                field.value = '';
            }
        });
    }
}

// Nested conditional field toggling for Extraintestinal and Comorbid
function toggleNestedConditionalField(select) {
    const targetId = select.getAttribute('data-target');
    if (!targetId) return;
    
    const targetField = document.querySelector(targetId);
    if (!targetField) return;
    
    const show = (select.value === 'Yes');
    targetField.style.display = show ? 'block' : 'none';
    
    // Clear all nested fields if hidden
    if (!show) {
        // Clear all selects and inputs in the target field
        targetField.querySelectorAll('select').forEach(s => {
            if (s !== select) s.value = '';
        });
        targetField.querySelectorAll('input').forEach(i => i.value = '');
        
        // Hide all detail sections
        const container = targetField.querySelector('[data-target-container]')?.getAttribute('data-target-container');
        if (container) {
            document.querySelectorAll(`${container} .nested-field`).forEach(f => {
                f.style.display = 'none';
            });
        }
    }
}

// Handle type selection for Extraintestinal and Comorbid
function handleTypeSelection(select) {
    const containerId = select.getAttribute('data-target-container');
    if (!containerId) return;
    
    const container = document.querySelector(containerId);
    if (!container) return;
    
    // Hide all detail sections first
    container.querySelectorAll('.nested-field').forEach(field => {
        field.style.display = 'none';
    });
    
    // Show the selected type's detail section
    const selectedValue = select.value;
    if (selectedValue) {
        let sectionId = '';
        switch(selectedValue) {
            case 'Arthritis':
                sectionId = '#arthritis_details_section';
                break;
            case 'Uveitis':
                sectionId = '#uveitis_details_section';
                break;
            case 'Skin':
                sectionId = '#skin_details_section';
                break;
            case 'Other':
                sectionId = '#other_details_section';
                break;
            case 'DM':
                sectionId = '#dm_details_section';
                break;
            case 'HTN':
                sectionId = '#htn_details_section';
                break;
            case 'Hypothyroidism':
                sectionId = '#hypothyroidism_details_section';
                break;
            case 'Others':
                sectionId = '#comorbid_other_details_section';
                break;
        }
        if (sectionId) {
            const section = document.querySelector(sectionId);
            if (section) {
                section.style.display = 'block';
            }
        }
    }
}

// Initialize all conditional selects
document.addEventListener('DOMContentLoaded', function() {
    // Basic conditional selects (Yes/No)
    document.querySelectorAll('.conditional-select').forEach(select => {
        select.addEventListener('change', () => toggleConditionalField(select));
        toggleConditionalField(select);
    });
    
    // Nested conditional selects (Yes/No for Extraintestinal and Comorbid)
    document.querySelectorAll('.nested-conditional-select[data-target]').forEach(select => {
        if (select.name === 'has_extraintestinal' || select.name === 'has_comorbid') {
            select.addEventListener('change', () => toggleNestedConditionalField(select));
            toggleNestedConditionalField(select);
        }
    });
    
    // Type selection handlers
    document.querySelectorAll('select[name="extraintestinal_type"], select[name="comorbid_type"]').forEach(select => {
        select.addEventListener('change', () => handleTypeSelection(select));
    });
});

// Form validation
document.getElementById('complaintForm')?.addEventListener('submit', function(e) {
    // Optional validation - all fields are optional
    return true;
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>