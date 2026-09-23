<?php
/**
 * patients/profile.php - Full Patient Profile
 * Displays all patient information and clinical modules
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
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/permissions.php';

// Check login
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Invalid patient ID');
}

// Function to check if module has data
function hasModuleData($pdo, $table, $patient_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if table exists
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if a value is not empty and should be displayed
function shouldDisplay($value) {
    return !empty($value) && $value !== 'N/A' && $value !== '0000-00-00' && $value !== '0000-00-00 00:00:00';
}

// Function to calculate BMI from height and weight
function calculateBMI($height_cm, $weight_kg) {
    if ($height_cm > 0 && $weight_kg > 0) {
        $height_m = $height_cm / 100;
        $bmi = $weight_kg / ($height_m * $height_m);
        return round($bmi, 1);
    }
    return null;
}

// Function to get BMI category
function getBMICategory($bmi) {
    if ($bmi === null) return null;
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi >= 18.5 && $bmi < 25) return 'Normal weight';
    if ($bmi >= 25 && $bmi < 30) return 'Overweight';
    return 'Obese';
}

// Function to format date and time
function formatDateTime($datetime) {
    if (empty($datetime)) return 'N/A';
    $date = new DateTime($datetime);
    return $date->format('M d, Y - h:i A');
}

// Function to format treatment drug list
function formatTreatmentDrugs($drug_name, $custom_drug) {
    if ($drug_name === 'Others' && !empty($custom_drug)) {
        return $custom_drug . ' (Others)';
    }
    return $drug_name;
}

// Function to get file icon class
function getFileIconClass($file_type) {
    return match(strtolower($file_type)) {
        'pdf' => 'fa-file-pdf',
        'jpg', 'jpeg', 'png', 'gif', 'bmp' => 'fa-file-image',
        'doc', 'docx' => 'fa-file-word',
        'xls', 'xlsx' => 'fa-file-excel',
        'txt' => 'fa-file-lines',
        default => 'fa-file'
    };
}

// Pull patient core + geo names for both permanent and present addresses
$stmt = $pdo->prepare("SELECT p.*, 
  dv.name AS perm_div, dd.name AS perm_dist, up.name AS perm_upazila,
  dv2.name AS pres_div, dd2.name AS pres_dist, up2.name AS pres_upazila,
  u.name AS created_by_name
FROM patients p
LEFT JOIN divisions dv  ON dv.id  = p.perm_division_id
LEFT JOIN districts dd  ON dd.id  = p.perm_district_id
LEFT JOIN upazilas up   ON up.id  = p.perm_upazila_id
LEFT JOIN divisions dv2 ON dv2.id = p.pres_division_id
LEFT JOIN districts dd2 ON dd2.id = p.pres_district_id
LEFT JOIN upazilas up2  ON up2.id = p.pres_upazila_id
LEFT JOIN users    u    ON u.id   = p.created_by
WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); die('Patient not found'); }

// Calculate BMI
$bmi = calculateBMI($p['height_cm'], $p['weight_kg']);
$bmi_category = getBMICategory($bmi);

// Check which modules have data
$has_ibd = hasModuleData($pdo, 'ibd_diagnoses', $id);
$has_complaints = hasModuleData($pdo, 'complaints', $id);
$has_socio = hasModuleData($pdo, 'socioeconomic_histories', $id);
$has_treatment = hasModuleData($pdo, 'treatments', $id);
$has_followup = hasModuleData($pdo, 'followups', $id);
$has_pregnancy = hasModuleData($pdo, 'pregnancies', $id);
$has_investigations = hasModuleData($pdo, 'investigations', $id);
$has_currenthistory = hasModuleData($pdo, 'current_histories', $id);
$has_drughistory = hasModuleData($pdo, 'drug_histories', $id);
$has_documents = tableExists($pdo, 'patient_attachments') ? hasModuleData($pdo, 'patient_attachments', $id) : false;
$has_vaccine = hasModuleData($pdo, 'patient_vaccines', $id);

include __DIR__.'/../templates/header.php';
?>

<style>
/* Color Variables - Solid Colors Only */
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
.breadcrumb-item.active {
    color: var(--black);
    opacity: 0.6;
}
.breadcrumb-item a {
    color: var(--blue);
    text-decoration: none;
}
.page-header .btn i {
    color: var(--white) !important;
}

/* Detail Cards */
.detail-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
}
.detail-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.detail-card .card-header h5,
.detail-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.detail-card .card-header h5 i,
.detail-card .card-header h6 i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 6px;
    border-radius: 6px;
    margin-right: 8px;
}
.detail-card .card-body {
    padding: 20px;
}

/* Module Cards */
.module-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
    transition: all 0.2s ease;
}
.module-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}
.module-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 14px 20px;
    border-radius: 8px 8px 0 0;
}
.module-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.module-card .card-header h6 i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 6px;
    border-radius: 6px;
    margin-right: 8px;
}
.module-card .card-body {
    padding: 16px;
    max-height: 450px;
    overflow-y: auto;
}

/* Section Cards (for documents and vaccine) */
.section-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
    transition: all 0.2s ease;
}
.section-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}
.section-card .section-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 14px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-card .section-header.blue {
    border-bottom: 2px solid var(--blue);
}
.section-card .section-header i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 8px;
    border-radius: 6px;
    font-size: 1rem;
}
.section-card .section-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
    font-size: 1rem;
}
.section-card .section-body {
    padding: 20px;
}

/* Entry Cards */
.entry-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 6px;
    margin-bottom: 12px;
    padding: 12px;
    position: relative;
    transition: all 0.2s ease;
}
.entry-card:last-child {
    margin-bottom: 0;
}
.entry-card:hover {
    border-color: var(--blue);
    background-color: var(--ash);
}
.entry-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--ash);
}
.entry-date {
    font-size: 0.75rem;
    color: var(--black);
    opacity: 0.6;
    background-color: var(--ash);
    padding: 3px 8px;
    border-radius: 4px;
}
.entry-date i {
    color: var(--blue) !important;
    margin-right: 4px;
}

/* Data Rows */
.data-row {
    border-bottom: 1px dashed var(--ash);
    padding: 8px 0;
}
.data-row:last-child {
    border-bottom: none;
}
.data-label {
    font-weight: 600;
    color: var(--black);
    opacity: 0.8;
    font-size: 0.85rem;
}
.data-value {
    color: var(--black);
    font-size: 0.85rem;
}

/* Info Rows (for patient profile) */
.info-row {
    margin-bottom: 12px;
    display: flex;
    align-items: flex-start;
}
.info-label {
    font-weight: 600;
    color: var(--black);
    width: 140px;
    flex-shrink: 0;
    opacity: 0.8;
}
.info-value {
    color: var(--black);
    flex: 1;
}

/* BMI Badge */
.bmi-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 500;
    margin-left: 10px;
    font-size: 0.85rem;
}
.bmi-underweight { background-color: var(--ash); color: var(--black); }
.bmi-normal { background-color: var(--blue); color: var(--white); }
.bmi-overweight { background-color: var(--ash); color: var(--black); }
.bmi-obese { background-color: var(--blue); color: var(--white); }

/* Address Display */
.address-display {
    background-color: var(--ash);
    padding: 12px 16px;
    border-radius: 6px;
}
.address-display i {
    color: var(--blue) !important;
    margin-right: 8px;
}

/* Button Styles */
.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: Cambria, serif;
    font-size: 0.9rem;
}
.btn-primary:hover {
    background-color: #357ABD;
    color: var(--white);
}
.btn-primary i {
    color: var(--white) !important;
}
.btn-outline-primary {
    background-color: transparent;
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 6px 12px;
    color: var(--blue);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: Cambria, serif;
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
.btn-outline-danger {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 4px;
    padding: 4px 8px;
    color: var(--black);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: Cambria, serif;
    font-size: 0.8rem;
}
.btn-outline-danger:hover {
    background-color: var(--red);
    border-color: var(--red);
    color: var(--white);
}
.btn-outline-danger:hover i {
    color: var(--white) !important;
}
.btn-outline-danger i {
    color: var(--red) !important;
}
.btn-sm {
    padding: 4px 10px;
    font-size: 0.8rem;
}

/* Table Styles */
.table {
    width: 100%;
    border-collapse: collapse;
}
.table thead th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    padding: 10px;
    border-bottom: 2px solid var(--blue);
    font-size: 0.85rem;
}
.table tbody td {
    padding: 10px;
    border-bottom: 1px solid var(--ash);
    color: var(--black);
    font-size: 0.85rem;
}
.table tbody tr:hover {
    background-color: var(--ash);
}
.table-responsive {
    overflow-x: auto;
}

/* Drug Table inside Treatment Module */
.drug-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    font-size: 0.85rem;
}
.drug-table th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid var(--blue);
}
.drug-table td {
    padding: 8px;
    border-bottom: 1px solid var(--ash);
}
.drug-table tr:last-child td {
    border-bottom: none;
}

/* Section Title */
.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--black);
    margin: 24px 0 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--blue);
}
.section-title i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 4px;
    border-radius: 4px;
    margin-right: 8px;
}

/* Empty State */
.empty-message {
    text-align: center;
    padding: 30px 0;
    color: var(--black);
    opacity: 0.6;
}
.empty-message i {
    color: var(--blue) !important;
    opacity: 0.3;
    font-size: 2rem;
    margin-bottom: 8px;
}

/* Divider */
hr {
    border-top: 1px solid var(--ash);
    margin: 20px 0;
    opacity: 1;
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

/* Treatment Info Grid */
.treatment-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 10px;
}
.treatment-info-item {
    background-color: var(--ash);
    padding: 6px 10px;
    border-radius: 4px;
}

/* Document Icons */
.file-icon {
    font-size: 1.2rem;
    margin-right: 8px;
}
.file-icon.pdf { color: #dc3545; }
.file-icon.image { color: var(--blue); }
.file-icon.word { color: #2b5797; }
.file-icon.excel { color: #217346; }

/* Vaccine Status Badges */
.vaccine-status-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
}
.status-completed { background-color: #d4edda; color: #155724; }
.status-pending { background-color: #fff3cd; color: #856404; }
.status-overdue { background-color: #f8d7da; color: #721c24; }
.status-scheduled { background-color: #d1ecf1; color: #0c5460; }

@media (max-width: 768px) {
    .two-column, .three-column {
        grid-template-columns: 1fr;
    }
    .treatment-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h5><i class="fa-solid fa-id-card-clip"></i>Full Profile: <?=htmlspecialchars($p['name'])?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Manage Patients</a></li>
                    <li class="breadcrumb-item active">Full Profile</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="?r=patients/view&id=<?=$id?>" class="btn-outline-primary btn-sm">
                <i class="fa-solid fa-eye"></i> Section One
            </a>
            <a href="?r=patients/edit&id=<?=$id?>" class="btn-primary btn-sm">
                <i class="fa-solid fa-pen"></i> Edit
            </a>
            <a href="?r=patients/manage" class="btn-outline-primary btn-sm">
                <i class="fa-solid fa-table-list"></i> Manage
            </a>
        </div>
    </div>

    <!-- ====== Section A: Patient's Profile ====== -->
    <div class="detail-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-user"></i>Patient Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Patient ID:</span>
                        <span class="info-value">#<?= htmlspecialchars($p['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IBD Reg No:</span>
                        <span class="info-value"><?= htmlspecialchars($p['ibd_reg_no'] ?: 'Not assigned', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?= htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sex:</span>
                        <span class="info-value">
                            <?php if (($p['sex'] ?? '') == 'M'): ?>
                                <i class="fa-solid fa-mars" style="color: var(--blue);"></i> Male
                            <?php else: ?>
                                <i class="fa-solid fa-venus" style="color: var(--blue);"></i> Female
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date of Birth:</span>
                        <span class="info-value"><?= htmlspecialchars($p['dob'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?= !empty($p['dob']) ? htmlspecialchars(calc_age($p['dob'])) : 'N/A' ?> years</span>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Height:</span>
                        <span class="info-value"><?= htmlspecialchars($p['height_cm'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?> cm</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Weight:</span>
                        <span class="info-value"><?= htmlspecialchars($p['weight_kg'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?> kg</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">BMI:</span>
                        <span class="info-value">
                            <?php if ($bmi): ?>
                                <?= htmlspecialchars($bmi) ?>
                                <span class="bmi-badge bmi-<?= strtolower(str_replace(' ', '', $bmi_category)) ?>"><?= $bmi_category ?></span>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nationality:</span>
                        <span class="info-value"><?= htmlspecialchars($p['nationality'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Religion:</span>
                        <span class="info-value"><?= htmlspecialchars($p['religion'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">NID:</span>
                        <span class="info-value"><?= htmlspecialchars($p['national_id'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?= htmlspecialchars($p['occupation'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Education:</span>
                        <span class="info-value"><?= htmlspecialchars($p['education'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Contact Number:</span>
                        <span class="info-value"><?= htmlspecialchars($p['contact_number'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($p['email'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6">
                    <h6 class="mb-2" style="color: var(--black); font-weight: 600;"><i class="fa-solid fa-location-dot" style="color: var(--blue); margin-right: 8px;"></i>Permanent Address</h6>
                    <div class="address-display">
                        <?php 
                        $perm_parts = [];
                        if (!empty($p['perm_address'])) $perm_parts[] = htmlspecialchars($p['perm_address']);
                        if (!empty($p['perm_upazila'])) $perm_parts[] = htmlspecialchars($p['perm_upazila']);
                        if (!empty($p['perm_dist'])) $perm_parts[] = htmlspecialchars($p['perm_dist']);
                        if (!empty($p['perm_div'])) $perm_parts[] = htmlspecialchars($p['perm_div']);
                        echo !empty($perm_parts) ? '<p class="mb-0">' . implode(', ', $perm_parts) . '</p>' : '<p class="mb-0">Not provided</p>';
                        ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="mb-2" style="color: var(--black); font-weight: 600;"><i class="fa-solid fa-location-dot" style="color: var(--blue); margin-right: 8px;"></i>Present Address</h6>
                    <div class="address-display">
                        <?php 
                        $pres_parts = [];
                        if (!empty($p['pres_address'])) $pres_parts[] = htmlspecialchars($p['pres_address']);
                        if (!empty($p['pres_upazila'])) $pres_parts[] = htmlspecialchars($p['pres_upazila']);
                        if (!empty($p['pres_dist'])) $pres_parts[] = htmlspecialchars($p['pres_dist']);
                        if (!empty($p['pres_div'])) $pres_parts[] = htmlspecialchars($p['pres_div']);
                        echo !empty($pres_parts) ? '<p class="mb-0">' . implode(', ', $pres_parts) . '</p>' : '<p class="mb-0">Not provided</p>';
                        ?>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="info-row">
                        <span class="info-label">Father/Husband:</span>
                        <span class="info-value"><?= htmlspecialchars($p['father_husband_name'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-row">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?= htmlspecialchars($p['father_husband_occupation'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-row">
                        <span class="info-label">Mother:</span>
                        <span class="info-value"><?= htmlspecialchars($p['mother_name'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-row">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?= htmlspecialchars($p['mother_occupation'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="info-row">
                        <span class="info-label">Created At:</span>
                        <span class="info-value"><?= formatDateTime($p['created_at'] ?? '') ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-row">
                        <span class="info-label">Created By:</span>
                        <span class="info-value"><?= htmlspecialchars($p['created_by_name'] ?: 'System', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== Section B: Clinical Modules (Two Columns) ====== -->
    <h6 class="section-title"><i class="fa-solid fa-notes-medical"></i>Clinical Records</h6>
    
    <div class="two-column">
        <!-- Left Column -->
        <div>
            <!-- IBD Diagnosis Module -->
            <div class="module-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-dna"></i>IBD Diagnosis</h6>
                    <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.ibd.add')): ?>
                    <a class="btn-outline-primary btn-sm" href="?r=modules/ibd/add&patient_id=<?=$id?>">
                        <i class="fa-solid fa-plus"></i> Add New
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($has_ibd): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM ibd_diagnoses WHERE patient_id=? ORDER BY created_at DESC LIMIT 2"); 
                        $stmt->execute([$id]); 
                        $rows = $stmt->fetchAll(); 
                        ?>
                        <?php foreach($rows as $r): ?>
                            <div class="entry-card">
                                <div class="entry-header">
                                    <span class="entry-date"><i class="fa-regular fa-calendar"></i> <?=formatDateTime($r['created_at'] ?? '')?></span>
                                </div>
                                <div class="row small g-2">
                                    <?php if (shouldDisplay($r['diagnosis'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Diagnosis:</span> <span class="data-value"><?=htmlspecialchars($r['diagnosis'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['patient_type'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Type:</span> <span class="data-value"><?=htmlspecialchars($r['patient_type'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['onset_date'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Onset:</span> <span class="data-value"><?=htmlspecialchars($r['onset_date'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['diagnosis_date'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Dx Date:</span> <span class="data-value"><?=htmlspecialchars($r['diagnosis_date'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['uc_location'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">UC:</span> <span class="data-value"><?=htmlspecialchars($r['uc_location'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['cd_location_set'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">CD Loc:</span> <span class="data-value"><?=htmlspecialchars($r['cd_location_set'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['upper_gi'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Upper GI:</span> <span class="data-value"><?=htmlspecialchars($r['upper_gi'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['cd_behavior'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Behavior:</span> <span class="data-value"><?=htmlspecialchars($r['cd_behavior'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['perianal_disease'])): ?>
                                    <div class="col-12 data-row">
                                        <span class="data-label">Perianal:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['perianal_disease'])?>
                                            <?php if (shouldDisplay($r['perianal_other'])): ?> (<?=htmlspecialchars($r['perianal_other'])?>)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.ibd.delete')): ?>
                                <div class="text-end mt-2">
                                    <a class="btn-outline-danger btn-sm" href="?r=modules/ibd/delete&id=<?=htmlspecialchars($r['id'])?>&patient_id=<?=$id?>" onclick="return confirm('Are you sure you want to delete this entry?');">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fa-solid fa-dna"></i>
                            <p>No IBD Diagnosis entries found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Socioeconomic History Module -->
            <div class="module-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-people-roof"></i>Socioeconomic History</h6>
                    <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.socio.add')): ?>
                    <a class="btn-outline-primary btn-sm" href="?r=modules/socio/add&patient_id=<?=$id?>">
                        <i class="fa-solid fa-plus"></i> Add New
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($has_socio): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM socioeconomic_histories WHERE patient_id=? ORDER BY created_at DESC LIMIT 2"); 
                        $stmt->execute([$id]); 
                        $rows = $stmt->fetchAll(); 
                        ?>
                        <?php foreach($rows as $r): ?>
                            <div class="entry-card">
                                <div class="entry-header">
                                    <span class="entry-date"><i class="fa-regular fa-calendar"></i> <?=formatDateTime($r['created_at'] ?? '')?></span>
                                </div>
                                <div class="row small g-2">
                                    <?php if (shouldDisplay($r['smoking'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Smoking:</span> <span class="data-value"><?=htmlspecialchars($r['smoking'])?> <?= shouldDisplay($r['smoking_duration']) ? '(' . htmlspecialchars($r['smoking_duration']) . ')' : '' ?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['alcohol'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Alcohol:</span> <span class="data-value"><?=htmlspecialchars($r['alcohol'])?> <?= shouldDisplay($r['alcohol_duration']) ? '(' . htmlspecialchars($r['alcohol_duration']) . ')' : '' ?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['children_count'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Children:</span> <span class="data-value"><?=htmlspecialchars($r['children_count'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['family_members_total'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Family Members:</span> <span class="data-value"><?=htmlspecialchars($r['family_members_total'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['monthly_income_taka'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Income:</span> <span class="data-value"><?=htmlspecialchars($r['monthly_income_taka'])?> Tk</span></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.socio.delete')): ?>
                                <div class="text-end mt-2">
                                    <a href="?r=modules/socio/delete&id=<?=htmlspecialchars($r['id'])?>&patient_id=<?=$id?>" class="btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this entry?');">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fa-solid fa-people-roof"></i>
                            <p>No Socioeconomic History entries found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Treatment Module -->
            <div class="module-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-pills"></i>Systemic Treatment</h6>
                    <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.treatment.add')): ?>
                    <a class="btn-outline-primary btn-sm" href="?r=modules/treatment/add&patient_id=<?=$id?>">
                        <i class="fa-solid fa-plus"></i> Add New
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($has_treatment): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM treatments WHERE patient_id=? ORDER BY created_at DESC LIMIT 2"); 
                        $stmt->execute([$id]); 
                        $rows = $stmt->fetchAll(); 
                        ?>
                        <?php foreach($rows as $r): ?>
                            <div class="entry-card">
                                <div class="entry-header">
                                    <span class="entry-date"><i class="fa-regular fa-calendar"></i> <?=formatDateTime($r['created_at'] ?? '')?></span>
                                </div>
                                
                                <!-- Drug Table - Only show if at least one drug field has data -->
                                <?php if (shouldDisplay($r['drug_name']) || shouldDisplay($r['custom_drug_name']) || shouldDisplay($r['start_date']) || shouldDisplay($r['current_dose']) || shouldDisplay($r['maximum_dose']) || shouldDisplay($r['side_effects'])): ?>
                                <table class="drug-table">
                                    <thead>
                                        <tr>
                                            <th>Drug Name</th>
                                            <th>Start Date</th>
                                            <th>Current Dose</th>
                                            <th>Max Dose</th>
                                            <th>Side Effects</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?= htmlspecialchars(formatTreatmentDrugs($r['drug_name'] ?? '', $r['custom_drug_name'] ?? '')) ?></td>
                                            <td><?= shouldDisplay($r['start_date']) ? htmlspecialchars($r['start_date']) : '-' ?></td>
                                            <td><?= shouldDisplay($r['current_dose']) ? htmlspecialchars($r['current_dose']) : '-' ?></td>
                                            <td><?= shouldDisplay($r['maximum_dose']) ? htmlspecialchars($r['maximum_dose']) : '-' ?></td>
                                            <td><?= shouldDisplay($r['side_effects']) ? htmlspecialchars($r['side_effects']) : '-' ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                                
                                <!-- Treatment Details Grid - Only show fields with data -->
                                <div class="treatment-info-grid">
                                    <?php if (shouldDisplay($r['local_treatment']) && $r['local_treatment'] !== 'No'): ?>
                                    <div class="treatment-info-item"><span class="data-label">Local Tx:</span> <?=htmlspecialchars($r['local_treatment'])?><?= shouldDisplay($r['local_treatment_specify']) ? ' (' . htmlspecialchars($r['local_treatment_specify']) . ')' : '' ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['antibiotics_quinolone']) || shouldDisplay($r['antibiotics_metronidazole']) || shouldDisplay($r['antibiotics_others'])): ?>
                                    <div class="treatment-info-item">
                                        <span class="data-label">Antibiotics:</span>
                                        <?php 
                                        $abx = [];
                                        if (shouldDisplay($r['antibiotics_quinolone'])) $abx[] = 'Quinolone';
                                        if (shouldDisplay($r['antibiotics_metronidazole'])) $abx[] = 'Metronidazole';
                                        if (shouldDisplay($r['antibiotics_others'])) $abx[] = htmlspecialchars($r['antibiotics_others']);
                                        echo implode(', ', $abx);
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['other_therapy'])): ?>
                                    <div class="treatment-info-item"><span class="data-label">Other Tx:</span> <?=htmlspecialchars($r['other_therapy'])?></div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $supplements = [];
                                    if (!empty($r['calcium']) && $r['calcium'] == 1) $supplements[] = 'Calcium';
                                    if (!empty($r['vitamin_d']) && $r['vitamin_d'] == 1) $supplements[] = 'Vitamin D';
                                    if (!empty($r['vitamin_b12']) && $r['vitamin_b12'] == 1) $supplements[] = 'Vitamin B12';
                                    if (!empty($r['iron_supplement']) && $r['iron_supplement'] == 1) $supplements[] = 'Iron';
                                    if (!empty($r['probiotics']) && $r['probiotics'] == 1) $supplements[] = 'Probiotics';
                                    if (!empty($r['nutritional_supplements']) && $r['nutritional_supplements'] == 1) $supplements[] = 'Nutritional';
                                    if (!empty($supplements)): 
                                    ?>
                                    <div class="treatment-info-item"><span class="data-label">Supplements:</span> <?= implode(', ', $supplements) ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['steroid_dependency']) && $r['steroid_dependency'] !== 'No'): ?>
                                    <div class="treatment-info-item"><span class="data-label">Steroid Dep:</span> <?=htmlspecialchars($r['steroid_dependency'])?><?= shouldDisplay($r['steroid_dependency_details']) ? ' (' . htmlspecialchars($r['steroid_dependency_details']) . ')' : '' ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['steroid_resistant']) && $r['steroid_resistant'] !== 'No'): ?>
                                    <div class="treatment-info-item"><span class="data-label">Steroid Res:</span> <?=htmlspecialchars($r['steroid_resistant'])?><?= shouldDisplay($r['steroid_resistant_details']) ? ' (' . htmlspecialchars($r['steroid_resistant_details']) . ')' : '' ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['ho_att']) && $r['ho_att'] !== 'No'): ?>
                                    <div class="treatment-info-item"><span class="data-label">H/O ATT:</span> <?=shouldDisplay($r['att_specify_from']) ? htmlspecialchars($r['att_specify_from']) : ''?> → <?=shouldDisplay($r['att_specify_to']) ? htmlspecialchars($r['att_specify_to']) : ''?> (<?=shouldDisplay($r['att_total_months']) ? htmlspecialchars($r['att_total_months']) : ''?> months)</div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['nsaids_last_4_weeks']) && $r['nsaids_last_4_weeks'] !== 'No'): ?>
                                    <div class="treatment-info-item"><span class="data-label">NSAIDs:</span> Yes<?= shouldDisplay($r['nsaids_details']) ? ' (' . htmlspecialchars($r['nsaids_details']) . ')' : '' ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($r['alt_meds']) && $r['alt_meds'] == 1): ?>
                                    <div class="treatment-info-item"><span class="data-label">Alt Meds:</span> <?=htmlspecialchars($r['alt_meds_type'] ?? '')?><?= shouldDisplay($r['alt_meds_duration']) ? ' - ' . htmlspecialchars($r['alt_meds_duration']) : '' ?><?= shouldDisplay($r['alt_meds_other_details']) ? ' (' . htmlspecialchars($r['alt_meds_other_details']) . ')' : '' ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.treatment.delete')): ?>
                                <div class="text-end mt-2">
                                    <a href="?r=modules/treatment/delete&id=<?=htmlspecialchars($r['id'])?>&patient_id=<?=$id?>" class="btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this entry?');">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fa-solid fa-pills"></i>
                            <p>No Treatment entries found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Follow-up Module -->
            <div class="module-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-calendar-check"></i>Follow-up</h6>
                    <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.followup.add')): ?>
                    <a class="btn-outline-primary btn-sm" href="?r=modules/followup/add&patient_id=<?=$id?>">
                        <i class="fa-solid fa-plus"></i> Add New
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($has_followup): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM followups WHERE patient_id=? ORDER BY followup_at DESC LIMIT 2"); 
                        $stmt->execute([$id]); 
                        $rows = $stmt->fetchAll(); 
                        ?>
                        <?php foreach($rows as $r): ?>
                            <div class="entry-card">
                                <div class="entry-header">
                                    <span class="entry-date"><i class="fa-regular fa-calendar"></i> <?=formatDateTime($r['created_at'] ?? '')?></span>
                                </div>
                                <div class="small">
                                    <?php if (shouldDisplay($r['followup_at'])): ?>
                                    <div class="data-row"><span class="data-label">Follow-up Date:</span> <span class="data-value"><?=formatDateTime($r['followup_at'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['description'])): ?>
                                    <div class="data-row"><span class="data-label">Description:</span> <span class="data-value"><?=nl2br(htmlspecialchars($r['description']))?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['treatment'])): ?>
                                    <div class="data-row"><span class="data-label">Treatment:</span> <span class="data-value"><?=nl2br(htmlspecialchars($r['treatment']))?></span></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.followup.delete')): ?>
                                <div class="text-end mt-2">
                                    <a href="?r=modules/followup/delete&id=<?=htmlspecialchars($r['id'])?>&patient_id=<?=$id?>" class="btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this entry?');">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fa-solid fa-calendar-check"></i>
                            <p>No Follow-up entries found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Complaints Module -->
            <div class="module-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-stethoscope"></i>Complaints</h6>
                    <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.complaints.add')): ?>
                    <a class="btn-outline-primary btn-sm" href="?r=modules/complaints/add&patient_id=<?=$id?>">
                        <i class="fa-solid fa-plus"></i> Add New
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($has_complaints): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM complaints WHERE patient_id=? ORDER BY created_at DESC LIMIT 2"); 
                        $stmt->execute([$id]); 
                        $rows = $stmt->fetchAll(); 
                        ?>
                        <?php foreach($rows as $r): ?>
                            <div class="entry-card">
                                <div class="entry-header">
                                    <span class="entry-date"><i class="fa-regular fa-calendar"></i> <?=formatDateTime($r['created_at'] ?? '')?></span>
                                </div>
                                <div class="row small g-2">
                                    <?php if (shouldDisplay($r['abdominal_pain'])): ?>
                                    <div class="col-6 data-row">
                                        <span class="data-label">Abdominal Pain:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['abdominal_pain'])?>
                                            <?php if (shouldDisplay($r['pain_type'])): ?> (<?=htmlspecialchars($r['pain_type'])?>)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['diarrhea'])): ?>
                                    <div class="col-6 data-row">
                                        <span class="data-label">Diarrhea:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['diarrhea'])?>
                                            <?php if (shouldDisplay($r['diarrhoea_details'])): ?> (<?=htmlspecialchars($r['diarrhoea_details'])?>)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['blood_in_stool'])): ?>
                                    <div class="col-6 data-row">
                                        <span class="data-label">Blood in stool:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['blood_in_stool'])?>
                                            <?php if (shouldDisplay($r['blood_details'])): ?> (<?=htmlspecialchars($r['blood_details'])?>)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['mucus_in_stool'])): ?>
                                    <div class="col-6 data-row">
                                        <span class="data-label">Mucus in stool:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['mucus_in_stool'])?>
                                            <?php if (shouldDisplay($r['mucus_details'])): ?> (<?=htmlspecialchars($r['mucus_details'])?>)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['frequency_per_day'])): ?>
                                    <div class="col-6 data-row"><span class="data-label">Frequency/day:</span> <span class="data-value"><?=htmlspecialchars($r['frequency_per_day'])?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['weight_loss'])): ?>
                                    <div class="col-6 data-row">
                                        <span class="data-label">Weight loss:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['weight_loss'])?>
                                            <?php if (shouldDisplay($r['weight_loss_kg'])): ?> (<?=htmlspecialchars($r['weight_loss_kg'])?> kg<?php endif; ?>
                                            <?php if (shouldDisplay($r['weight_loss_period'])): ?> over <?=htmlspecialchars($r['weight_loss_period'])?>)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['fever'])): ?>
                                    <div class="col-6 data-row">
                                        <span class="data-label">Fever:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['fever'])?>
                                            <?php if (shouldDisplay($r['fever_duration'])): ?> (<?=htmlspecialchars($r['fever_duration'])?><?php endif; ?>
                                            <?php if (shouldDisplay($r['fever_grade'])): ?> - <?=htmlspecialchars($r['fever_grade'])?> grade)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['relapses_count'])): ?>
                                    <div class="col-6 data-row">
                                        <span class="data-label">Relapses:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['relapses_count'])?>
                                            <?php if (shouldDisplay($r['last_one_year'])): ?> (Last: <?=htmlspecialchars($r['last_one_year'])?>)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['extraintestinal_type'])): ?>
                                    <div class="col-12 data-row">
                                        <span class="data-label">Extraintestinal:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['extraintestinal_type'])?>
                                            <?php if ($r['extraintestinal_type'] == 'Arthritis' && shouldDisplay($r['arthritis_details'])): ?> - <?=htmlspecialchars($r['arthritis_details'])?><?php endif; ?>
                                            <?php if ($r['extraintestinal_type'] == 'Uveitis' && shouldDisplay($r['uveitis_details'])): ?> - <?=htmlspecialchars($r['uveitis_details'])?><?php endif; ?>
                                            <?php if ($r['extraintestinal_type'] == 'Skin' && shouldDisplay($r['skin_details'])): ?> - <?=htmlspecialchars($r['skin_details'])?><?php endif; ?>
                                            <?php if ($r['extraintestinal_type'] == 'Other' && shouldDisplay($r['extraintestinal_other_details'])): ?> - <?=htmlspecialchars($r['extraintestinal_other_details'])?><?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['hospitalization'])): ?>
                                    <div class="col-12 data-row">
                                        <span class="data-label">Hospitalization:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['hospitalization'])?>
                                            <?php if (shouldDisplay($r['hospitalization_date'])): ?> [<?=htmlspecialchars($r['hospitalization_date'])?>]<?php endif; ?>
                                            <?php if (shouldDisplay($r['hospitalization_reason'])): ?> <?=htmlspecialchars($r['hospitalization_reason'])?><?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['past_surgery'])): ?>
                                    <div class="col-12 data-row">
                                        <span class="data-label">Past Surgery:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['past_surgery'])?>
                                            <?php if (shouldDisplay($r['past_surgery_date'])): ?> [<?=htmlspecialchars($r['past_surgery_date'])?>]<?php endif; ?>
                                            <?php if (shouldDisplay($r['past_surgery_details'])): ?> <?=htmlspecialchars($r['past_surgery_details'])?><?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['family_history_ibd'])): ?>
                                    <div class="col-12 data-row">
                                        <span class="data-label">Family History IBD:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['family_history_ibd'])?>
                                            <?php if (shouldDisplay($r['family_history_type'])): ?> [<?=htmlspecialchars($r['family_history_type'])?>]<?php endif; ?>
                                            <?php if (shouldDisplay($r['family_history_relations'])): ?> <?=htmlspecialchars($r['family_history_relations'])?><?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['comorbid_type'])): ?>
                                    <div class="col-12 data-row">
                                        <span class="data-label">Comorbid:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['comorbid_type'])?>
                                            <?php if ($r['comorbid_type'] == 'DM' && shouldDisplay($r['dm_details'])): ?> - <?=htmlspecialchars($r['dm_details'])?><?php endif; ?>
                                            <?php if ($r['comorbid_type'] == 'HTN' && shouldDisplay($r['htn_details'])): ?> - <?=htmlspecialchars($r['htn_details'])?><?php endif; ?>
                                            <?php if ($r['comorbid_type'] == 'Hypothyroidism' && shouldDisplay($r['hypothyroidism_details'])): ?> - <?=htmlspecialchars($r['hypothyroidism_details'])?><?php endif; ?>
                                            <?php if ($r['comorbid_type'] == 'Others' && shouldDisplay($r['comorbid_other_details'])): ?> - <?=htmlspecialchars($r['comorbid_other_details'])?><?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['cancer_history'])): ?>
                                    <div class="col-12 data-row">
                                        <span class="data-label">Cancer:</span> 
                                        <span class="data-value"><?=htmlspecialchars($r['cancer_history'])?>
                                            <?php if (shouldDisplay($r['cancer_specify'])): ?> (<?=htmlspecialchars($r['cancer_specify'])?>)<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.complaints.delete')): ?>
                                <div class="text-end mt-2">
                                    <a class="btn-outline-danger btn-sm" href="?r=modules/complaints/delete&id=<?=htmlspecialchars($r['id'])?>&patient_id=<?=$id?>" onclick="return confirm('Are you sure you want to delete this entry?');">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fa-solid fa-stethoscope"></i>
                            <p>No Complaints entries found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pregnancy Module -->
            <div class="module-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-baby"></i>Pregnancy</h6>
                    <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.pregnancy.add')): ?>
                    <a class="btn-outline-primary btn-sm" href="?r=modules/pregnancy/add&patient_id=<?=$id?>">
                        <i class="fa-solid fa-plus"></i> Add New
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($has_pregnancy): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM pregnancies WHERE patient_id=? ORDER BY created_at DESC LIMIT 2"); 
                        $stmt->execute([$id]); 
                        $rows = $stmt->fetchAll(); 
                        ?>
                        <?php foreach($rows as $r): ?>
                            <div class="entry-card">
                                <div class="entry-header">
                                    <span class="entry-date"><i class="fa-regular fa-calendar"></i> <?=formatDateTime($r['created_at'] ?? '')?></span>
                                </div>
                                <div class="small">
                                    <?php if (shouldDisplay($r['history'])): ?>
                                    <div class="data-row"><span class="data-label">History:</span> <span class="data-value"><?=nl2br(htmlspecialchars($r['history']))?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['abortion'])): ?>
                                    <div class="data-row"><span class="data-label">Abortion:</span> <span class="data-value"><?=htmlspecialchars($r['abortion'])?> <?= shouldDisplay($r['abortion_details']) ? '(' . htmlspecialchars($r['abortion_details']) . ')' : '' ?></span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (shouldDisplay($r['congenital_disorder'])): ?>
                                    <div class="data-row"><span class="data-label">Congenital:</span> <span class="data-value"><?=htmlspecialchars($r['congenital_disorder'])?></span></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.pregnancy.delete')): ?>
                                <div class="text-end mt-2">
                                    <a href="?r=modules/pregnancy/delete&id=<?=htmlspecialchars($r['id'])?>&patient_id=<?=$id?>" class="btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this entry?');">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fa-solid fa-baby"></i>
                            <p>No Pregnancy entries found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Investigations Module -->
            <div class="module-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-microscope"></i>Investigations</h6>
                    <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.investigations.add')): ?>
                    <a class="btn-outline-primary btn-sm" href="?r=modules/investigations/add&patient_id=<?=$id?>">
                        <i class="fa-solid fa-plus"></i> Add New
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($has_investigations): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM investigations WHERE patient_id=? ORDER BY created_at DESC LIMIT 2"); 
                        $stmt->execute([$id]); 
                        $rows = $stmt->fetchAll(); 
                        ?>
                        <?php foreach($rows as $r): ?>
                            <div class="entry-card">
                                <div class="entry-header">
                                    <span class="entry-date"><i class="fa-regular fa-calendar"></i> <?=formatDateTime($r['created_at'] ?? '')?></span>
                                </div>
                                <div class="row small g-2">
                                    <?php if (shouldDisplay($r['cbc_hb'])): ?><div class="col-4 data-row"><span class="data-label">Hb:</span> <span class="data-value"><?=htmlspecialchars($r['cbc_hb'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['cbc_esr'])): ?><div class="col-4 data-row"><span class="data-label">ESR:</span> <span class="data-value"><?=htmlspecialchars($r['cbc_esr'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['cbc_tlc'])): ?><div class="col-4 data-row"><span class="data-label">TLC:</span> <span class="data-value"><?=htmlspecialchars($r['cbc_tlc'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['dlc'])): ?><div class="col-4 data-row"><span class="data-label">DLC:</span> <span class="data-value"><?=htmlspecialchars($r['dlc'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['platelets'])): ?><div class="col-4 data-row"><span class="data-label">Platelets:</span> <span class="data-value"><?=htmlspecialchars($r['platelets'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['crp'])): ?><div class="col-4 data-row"><span class="data-label">CRP:</span> <span class="data-value"><?=htmlspecialchars($r['crp'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['s_albumin'])): ?><div class="col-4 data-row"><span class="data-label">Albumin:</span> <span class="data-value"><?=htmlspecialchars($r['s_albumin'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['fecal_calprotectin'])): ?><div class="col-4 data-row"><span class="data-label">Calprotectin:</span> <span class="data-value"><?=htmlspecialchars($r['fecal_calprotectin'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['upper_git'])): ?><div class="col-4 data-row"><span class="data-label">Upper GIT:</span> <span class="data-value"><?=htmlspecialchars($r['upper_git'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['endoscopy'])): ?><div class="col-4 data-row"><span class="data-label">Endoscopy:</span> <span class="data-value"><?=htmlspecialchars($r['endoscopy'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['colonoscopy'])): ?><div class="col-4 data-row"><span class="data-label">Colonoscopy:</span> <span class="data-value"><?=htmlspecialchars($r['colonoscopy'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['ileoscopy'])): ?><div class="col-4 data-row"><span class="data-label">Ileoscopy:</span> <span class="data-value"><?=htmlspecialchars($r['ileoscopy'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['enteroscopy'])): ?><div class="col-4 data-row"><span class="data-label">Enteroscopy:</span> <span class="data-value"><?=htmlspecialchars($r['enteroscopy'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['histopathology'])): ?><div class="col-4 data-row"><span class="data-label">Histopathology:</span> <span class="data-value"><?=htmlspecialchars($r['histopathology'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['usg'])): ?><div class="col-4 data-row"><span class="data-label">USG:</span> <span class="data-value"><?=htmlspecialchars($r['usg'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['ct_scan'])): ?><div class="col-4 data-row"><span class="data-label">CT Scan:</span> <span class="data-value"><?=htmlspecialchars($r['ct_scan'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['enterography'])): ?><div class="col-4 data-row"><span class="data-label">Enterography:</span> <span class="data-value"><?=htmlspecialchars($r['enterography'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['endoscopic_ultrasound'])): ?><div class="col-4 data-row"><span class="data-label">EUS:</span> <span class="data-value"><?=htmlspecialchars($r['endoscopic_ultrasound'])?></span></div><?php endif; ?>
                                    <?php if (shouldDisplay($r['others'])): ?><div class="col-4 data-row"><span class="data-label">Others:</span> <span class="data-value"><?=htmlspecialchars($r['others'])?></span></div><?php endif; ?>
                                </div>
                                <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.investigations.delete')): ?>
                                <div class="text-end mt-2">
                                    <a href="?r=modules/investigations/delete&id=<?=htmlspecialchars($r['id'])?>&patient_id=<?=$id?>" class="btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this entry?');">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fa-solid fa-microscope"></i>
                            <p>No Investigations entries found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== Vaccine Module (New Section) ====== -->
    <h6 class="section-title"><i class="fa-solid fa-syringe"></i>Vaccine Records</h6>
    
    <div class="section-card">
        <div class="section-header blue">
            <i class="fa-solid fa-syringe"></i>
            <h6>Vaccination History</h6>
            <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.vaccine.add')): ?>
            <a href="?r=modules/vaccine/add&patient_id=<?= $id ?>" class="btn-primary btn-sm" style="margin-left: auto;">
                <i class="fa-solid fa-plus"></i> Add Vaccine
            </a>
            <?php endif; ?>
        </div>
        <div class="section-body">
            <?php if ($has_vaccine): ?>
                <?php
                $stmt = $pdo->prepare("
                    SELECT pv.*, u.name as administered_by_name 
                    FROM patient_vaccines pv
                    LEFT JOIN users u ON u.id = pv.administered_by
                    WHERE pv.patient_id = ? 
                    ORDER BY FIELD(pv.vaccine_name, 'Hepatitis B vaccine', 'Pneumococcal conjugate vaccine (PCV-13)', 'Pneumococcal polysaccharide vaccine (PPSV23)', 'Influenza vaccine'), pv.id ASC
                ");
                $stmt->execute([$id]);
                $vaccines = $stmt->fetchAll();
                ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Vaccine Name</th>
                                <th>Dose Schedule</th>
                                <th>Dose Given Date</th>
                                <th>Next Dose Date</th>
                                <th>Batch No</th>
                                <th>Administered By</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vaccines as $vaccine): 
                                $status_class = '';
                                switch($vaccine['status']) {
                                    case 'Completed': $status_class = 'status-completed'; break;
                                    case 'Pending': $status_class = 'status-pending'; break;
                                    case 'Overdue': $status_class = 'status-overdue'; break;
                                    case 'Scheduled': $status_class = 'status-scheduled'; break;
                                    default: $status_class = 'status-pending';
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($vaccine['vaccine_name']) ?></td>
                                <td><?= htmlspecialchars($vaccine['dose_schedule'] ?: '-') ?></td>
                                <td><?= $vaccine['dose_given_date'] ? date('d-m-Y', strtotime($vaccine['dose_given_date'])) : '-' ?></td>
                                <td>
                                    <?php if ($vaccine['next_dose_date']): ?>
                                        <?= date('d-m-Y', strtotime($vaccine['next_dose_date'])) ?>
                                        <?php if (strtotime($vaccine['next_dose_date']) < time() && $vaccine['status'] != 'Completed'): ?>
                                            <span class="badge bg-danger ms-1">Overdue</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($vaccine['batch_no'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($vaccine['administered_by_name'] ?: '-') ?></td>
                                <td><span class="vaccine-status-badge <?= $status_class ?>"><?= htmlspecialchars($vaccine['status'] ?: 'Pending') ?></span></td>
                                <td><?= htmlspecialchars($vaccine['remarks'] ?: '-') ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.vaccine.edit')): ?>
                                        <a href="?r=modules/vaccine/edit&id=<?= $vaccine['id'] ?>&patient_id=<?= $id ?>" class="btn-outline-primary btn-sm" title="Edit">
                                            <i class="fa-solid fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.vaccine.delete')): ?>
                                        <a href="?r=modules/vaccine/delete&id=<?= $vaccine['id'] ?>&patient_id=<?= $id ?>" 
                                           class="btn-outline-primary btn-sm" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this vaccine record?')">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <a href="?r=modules/vaccine/index&patient_id=<?= $id ?>" class="btn-outline-primary btn-sm">
                        View All <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-message">
                    <i class="fa-solid fa-syringe"></i>
                    <p>No vaccine records found for this patient.</p>
                    <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.vaccine.add')): ?>
                    <a href="?r=modules/vaccine/add&patient_id=<?= $id ?>" class="btn-primary btn-sm mt-2">
                        <i class="fa-solid fa-plus"></i> Add First Vaccine Record
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ====== Documents Section (Additional Module) ====== -->
    <h6 class="section-title"><i class="fa-solid fa-file"></i>Patient Documents</h6>
    
    <div class="section-card">
        <div class="section-header blue">
            <i class="fa-solid fa-file"></i>
            <h6>Documents</h6>
            <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.documents.add')): ?>
            <a href="?r=modules/documents/add&patient_id=<?= $id ?>" class="btn-primary btn-sm" style="margin-left: auto;">
                <i class="fa-solid fa-upload"></i> Upload
            </a>
            <?php endif; ?>
        </div>
        <div class="section-body">
            <?php if ($has_documents): ?>
                <?php
                $stmt = $pdo->prepare("SELECT * FROM patient_attachments WHERE patient_id=? ORDER BY created_at DESC");
                $stmt->execute([$id]);
                $documents = $stmt->fetchAll();
                ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Description</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): 
                                $icon = getFileIconClass($doc['file_type']);
                                $size_kb = round($doc['file_size'] / 1024, 1);
                            ?>
                            <tr>
                                <td>
                                    <i class="fa-regular <?= $icon ?> me-2" style="color: var(--blue);"></i>
                                    <?= htmlspecialchars($doc['original_name']) ?>
                                </td>
                                <td><?= strtoupper($doc['file_type']) ?></td>
                                <td><?= $size_kb ?> KB</td>
                                <td><?= htmlspecialchars($doc['description'] ?: '-') ?></td>
                                <td><?= date('M d, Y', strtotime($doc['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="?r=modules/documents/view&file_id=<?= $doc['id'] ?>" target="_blank" class="btn-outline-primary btn-sm" title="View">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if (function_exists('has_permission') && has_permission($pdo, 'button.module.documents.delete')): ?>
                                        <a href="?r=modules/documents/delete&id=<?= $doc['id'] ?>&patient_id=<?= $id ?>" 
                                           class="btn-outline-primary btn-sm" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this file?')">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-message">
                    <i class="fa-solid fa-file"></i>
                    <p>No documents uploaded yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>