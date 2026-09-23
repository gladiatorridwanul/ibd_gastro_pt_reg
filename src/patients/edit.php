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
require_once __DIR__.'/../includes/audit.php';
require_once __DIR__.'/../includes/helpers.php';

// Check if user is logged in
if (!function_exists('require_login')) {
    die('Authentication system not loaded properly.');
}
require_login();

// Check permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'button.patient.edit')) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'You do not have permission to edit patients.'
    ];
    header('Location: ?r=patients/manage');
    exit;
}

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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { 
    http_response_code(400); 
    die('Invalid patient id'); 
}

// Dropdown data - UPDATED to match add.php
$country_options = [
    'Bangladesh','India','Pakistan','Nepal','Bhutan','Sri Lanka','Maldives','Afghanistan','Myanmar','China','Japan','South Korea','Malaysia','Singapore','Thailand','Indonesia','Philippines','United Arab Emirates','Saudi Arabia','Qatar','Kuwait','Oman','Bahrain','United Kingdom','United States','Canada','Australia','New Zealand'
];
$religion_options = ['', 'Islam','Hinduism','Buddhism','Christianity','Others'];

// UPDATED occupation list to match add.php
$occupation_options = [
    'Service', 'Business', 'Teacher', 'Doctor', 'Engineer', 'Lawyer', 
    'Farmer', 'Laborer', 'Driver', 'Housewife', 'Student', 'Retired', 
    'Unemployed', 'Others'
];

// UPDATED education levels to match add.php
$education_options = [
    'Primary', 'Secondary',
    'Higher Secondary', 'Tertiary', 'No Formal Education'
];

// Fetch patient
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) { 
        http_response_code(404); 
        die('Patient not found'); 
    }
} catch (PDOException $e) {
    error_log("Error fetching patient: " . $e->getMessage());
    die('Database error');
}

// Fetch all divisions
try {
    $divisions = $pdo->query("SELECT id, name FROM divisions ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching divisions: " . $e->getMessage());
    $divisions = [];
}

// Fetch saved district and upazila names for display
$perm_district_name = '';
$perm_upazila_name = '';
$pres_district_name = '';
$pres_upazila_name = '';

if (!empty($p['perm_district_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM districts WHERE id = ?");
        $stmt->execute([$p['perm_district_id']]);
        $perm_district_name = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching district: " . $e->getMessage());
    }
}

if (!empty($p['perm_upazila_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM upazilas WHERE id = ?");
        $stmt->execute([$p['perm_upazila_id']]);
        $perm_upazila_name = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching upazila: " . $e->getMessage());
    }
}

if (!empty($p['pres_district_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM districts WHERE id = ?");
        $stmt->execute([$p['pres_district_id']]);
        $pres_district_name = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching district: " . $e->getMessage());
    }
}

if (!empty($p['pres_upazila_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM upazilas WHERE id = ?");
        $stmt->execute([$p['pres_upazila_id']]);
        $pres_upazila_name = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching upazila: " . $e->getMessage());
    }
}

// Calculate BMI for display
$bmi = null;
$bmi_category = '';
if ($p['height_cm'] > 0 && $p['weight_kg'] > 0) {
    $height_m = $p['height_cm'] / 100;
    $bmi = round($p['weight_kg'] / ($height_m * $height_m), 1);
    if ($bmi < 18.5) $bmi_category = 'Underweight';
    else if ($bmi >= 18.5 && $bmi < 25) $bmi_category = 'Normal';
    else if ($bmi >= 25 && $bmi < 30) $bmi_category = 'Overweight';
    else $bmi_category = 'Obese';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) die('Invalid CSRF');
    
    $dob = $_POST['dob'] ?: null; 
    $age = null;
    
    if ($dob) {
        if (function_exists('calc_age')) {
            $age = calc_age($dob);
        } else {
            $birthDate = new DateTime($dob);
            $today = new DateTime('today');
            $age = $birthDate->diff($today)->y;
        }
    } else {
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    }
    
    // Calculate BMI
    $bmi_val = null;
    if (!empty($_POST['height_cm']) && !empty($_POST['weight_kg']) && $_POST['height_cm'] > 0) {
        $height_m = $_POST['height_cm'] / 100;
        $bmi_val = round($_POST['weight_kg'] / ($height_m * $height_m), 1);
    }
    
    // Convert empty values to NULL for foreign keys
    $perm_division_id = !empty($_POST['perm_division_id']) ? $_POST['perm_division_id'] : null;
    $perm_district_id = !empty($_POST['perm_district_id']) ? $_POST['perm_district_id'] : null;
    $perm_upazila_id = !empty($_POST['perm_upazila_id']) ? $_POST['perm_upazila_id'] : null;
    
    $pres_division_id = !empty($_POST['pres_division_id']) ? $_POST['pres_division_id'] : null;
    $pres_district_id = !empty($_POST['pres_district_id']) ? $_POST['pres_district_id'] : null;
    $pres_upazila_id = !empty($_POST['pres_upazila_id']) ? $_POST['pres_upazila_id'] : null;
    
    $sql = "UPDATE patients SET
        name=?, age=?, sex=?, dob=?, height_cm=?, weight_kg=?, bmi=?, nationality=?, religion=?, 
        national_id=?, occupation=?, education=?, contact_number=?, email=?,
        perm_address=?, perm_division_id=?, perm_district_id=?, perm_upazila_id=?,
        pres_address=?, pres_division_id=?, pres_district_id=?, pres_upazila_id=?,
        father_husband_name=?, father_husband_occupation=?, mother_name=?, mother_occupation=?
        WHERE id=?";
    
    $params = [
        trim($_POST['name'] ?? ''),
        $age,
        $_POST['sex'] ?? null,
        $dob,
        !empty($_POST['height_cm']) ? $_POST['height_cm'] : null,
        !empty($_POST['weight_kg']) ? $_POST['weight_kg'] : null,
        $bmi_val,
        $_POST['nationality'] ?: 'Bangladesh',
        $_POST['religion'] ?: null,
        !empty($_POST['national_id']) ? $_POST['national_id'] : null,
        $_POST['occupation'] ?: null,
        $_POST['education'] ?: null,
        !empty($_POST['contact_number']) ? $_POST['contact_number'] : null,
        !empty($_POST['email']) ? $_POST['email'] : null,
        $_POST['perm_address'] ?: null,
        $perm_division_id,
        $perm_district_id,
        $perm_upazila_id,
        $_POST['pres_address'] ?: null,
        $pres_division_id,
        $pres_district_id,
        $pres_upazila_id,
        $_POST['father_husband_name'] ?: null,
        $_POST['father_husband_occupation'] ?: null,
        $_POST['mother_name'] ?: null,
        $_POST['mother_occupation'] ?: null,
        $id
    ];
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            if (function_exists('audit')) {
                audit($pdo, 'patient.update', 'patients', $id, ['name' => $_POST['name']]);
            }
            
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'text' => 'Patient updated successfully!'
            ];
            
            header('Location: ?r=patients/profile&id=' . $id);
            exit;
        } else {
            $error = "Failed to update patient data";
            error_log("PDO Error: " . print_r($stmt->errorInfo(), true));
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Patient update error: " . $e->getMessage());
    }
}

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

/* Form Cards */
.form-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.form-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.form-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
}
.form-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.form-card .card-header h6 i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 6px;
    border-radius: 6px;
    margin-right: 8px;
}
.form-card .card-body {
    padding: 20px;
}

/* Form Elements */
.form-label {
    color: var(--black);
    font-weight: 500;
    margin-bottom: 6px;
    font-size: 0.9rem;
}
.form-label .text-danger {
    color: var(--blue) !important;
    opacity: 0.8;
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
.form-text {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.8rem;
    margin-top: 4px;
}

/* Input Groups */
.input-group-text {
    background-color: var(--ash);
    border: 1px solid var(--ash);
    border-radius: 6px 0 0 6px;
    color: var(--blue);
}
.input-group .form-control {
    border-left: none;
    border-radius: 0 6px 6px 0;
}
.input-group .form-control:focus {
    border-left: none;
}

/* BMI Display */
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
.current-bmi {
    background-color: var(--ash);
    padding: 10px 15px;
    border-radius: 6px;
    margin-top: 5px;
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
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary:hover {
    background-color: #357ABD;
    border-color: #357ABD;
    color: var(--white);
}
.btn-primary i {
    color: var(--white) !important;
}
.btn-secondary {
    background-color: transparent;
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
    color: var(--black);
}
.btn-secondary i {
    color: var(--blue) !important;
}
.btn-outline-primary {
    background-color: transparent;
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--blue);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: Cambria, serif;
    font-size: 0.9rem;
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
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 6px 12px;
    color: var(--black);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: Cambria, serif;
    font-size: 0.85rem;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
    color: var(--black);
}
.btn-outline-secondary i {
    color: var(--blue) !important;
}

/* Conditional Fields */
.conditional-field {
    transition: all 0.2s ease;
}

/* Row Spacing */
.row.g-3 {
    --bs-gutter-y: 1rem;
}

/* Alert */
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

/* Copy Button */
.copy-btn {
    background-color: var(--white);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--blue);
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.copy-btn:hover {
    background-color: var(--blue);
    color: var(--white);
}
.copy-btn:hover i {
    color: var(--white) !important;
}
.copy-btn i {
    color: var(--blue) !important;
}

/* Patient ID Badge */
.patient-id-badge {
    background-color: var(--ash);
    padding: 8px 16px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
}
.patient-id-badge i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 5px;
    border-radius: 50%;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h5><i class="fa-solid fa-user-pen"></i>Edit Patient: <?=htmlspecialchars($p['name'] ?? '')?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Manage Patients</a></li>
                    <li class="breadcrumb-item active">Edit Patient</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="?r=patients/view&id=<?=$id?>" class="btn-outline-primary btn-sm">
                <i class="fa-solid fa-eye"></i> View
            </a>
            <a href="?r=patients/profile&id=<?=$id?>" class="btn-outline-primary btn-sm">
                <i class="fa-solid fa-id-card-clip"></i> Full Profile
            </a>
            <a href="?r=patients/manage" class="btn-outline-secondary btn-sm">
                <i class="fa-solid fa-table-list"></i> Manage
            </a>
        </div>
    </div>

    <!-- Patient ID Badge -->
    <div class="patient-id-badge">
        <i class="fa-solid fa-id-card"></i>
        <span><strong>Patient ID:</strong> #<?= $p['id'] ?> | <strong>IBD Reg No:</strong> <?= htmlspecialchars($p['ibd_reg_no'] ?? 'Not assigned') ?></span>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?=htmlspecialchars($error)?>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <form method="post" id="patientForm">
        <input type="hidden" name="_csrf" value="<?=htmlspecialchars(csrf_token())?>">

        <!-- Basic Information Card -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-user"></i>Basic Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?=htmlspecialchars($p['name'] ?? '')?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Age</label>
                        <input id="age" name="age" type="number" class="form-control" value="<?=htmlspecialchars($p['age'] ?? '')?>">
                        <small class="form-text">Auto-updates from DOB</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sex <span class="text-danger">*</span></label>
                        <select name="sex" class="form-select" required>
                            <option value="M" <?=($p['sex'] ?? '')==='M'?'selected':''?>>Male</option>
                            <option value="F" <?=($p['sex'] ?? '')==='F'?'selected':''?>>Female</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth</label>
                        <input id="dob" name="dob" type="date" class="form-control" value="<?=htmlspecialchars($p['dob'] ?? '')?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Height (cm)</label>
                        <input id="height_cm" name="height_cm" type="number" step="0.01" class="form-control" value="<?=htmlspecialchars($p['height_cm'] ?? '')?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Weight (kg)</label>
                        <input id="weight_kg" name="weight_kg" type="number" step="0.01" class="form-control" value="<?=htmlspecialchars($p['weight_kg'] ?? '')?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">BMI</label>
                        <input id="bmi_display" type="text" class="form-control" readonly value="<?=$bmi ?? ''?>">
                        <input type="hidden" name="bmi" id="bmi_hidden" value="<?=$bmi ?? ''?>">
                    </div>
                    <div class="col-md-4">
                        <?php if ($bmi): ?>
                            <div class="current-bmi">
                                <strong>Current BMI:</strong> <?=$bmi?> 
                                <span class="bmi-badge bmi-<?=strtolower($bmi_category)?>"><?=$bmi_category?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Nationality</label>
                        <select name="nationality" class="form-select">
                            <?php foreach ($country_options as $c): ?>
                                <option value="<?=$c?>" <?=($c === ($p['nationality'] ?? 'Bangladesh')) ? 'selected' : ''?>><?=$c?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Religion</label>
                        <select name="religion" class="form-select">
                            <?php foreach ($religion_options as $r): ?>
                                <option value="<?=$r?>" <?=($r === ($p['religion'] ?? '')) ? 'selected' : ''?>><?=$r ?: 'Select'?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">National ID</label>
                        <input name="national_id" class="form-control" value="<?=htmlspecialchars($p['national_id'] ?? '')?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Occupation</label>
                        <select name="occupation" class="form-select">
                            <option value="">Select Occupation</option>
                            <?php foreach ($occupation_options as $o): ?>
                                <option value="<?=$o?>" <?=($o === ($p['occupation'] ?? '')) ? 'selected' : ''?>><?=$o?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Education</label>
                        <select name="education" class="form-select">
                            <option value="">Select Education</option>
                            <?php foreach ($education_options as $e): ?>
                                <option value="<?=$e?>" <?=($e === ($p['education'] ?? '')) ? 'selected' : ''?>><?=$e?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Contact Number</label>
                        <input name="contact_number" class="form-control" value="<?=htmlspecialchars($p['contact_number'] ?? '')?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Email Address</label>
                        <input name="email" type="email" class="form-control" value="<?=htmlspecialchars($p['email'] ?? '')?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Permanent Address Card -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-location-dot"></i>Permanent Address</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Address/House/Road</label>
                        <input name="perm_address" class="form-control" value="<?=htmlspecialchars($p['perm_address'] ?? '')?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Division <span class="text-danger">*</span></label>
                        <select name="perm_division_id" id="perm_division" class="form-select" required>
                            <option value="">Select Division</option>
                            <?php foreach($divisions as $d): ?>
                                <option value="<?=$d['id']?>" <?=($p['perm_division_id'] ?? '') == $d['id'] ? ' selected' : ''?>><?=htmlspecialchars($d['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">District <span class="text-danger">*</span></label>
                        <select name="perm_district_id" id="perm_district" class="form-select" required>
                            <option value="">Select Division First</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Upazila/Thana</label>
                        <select name="perm_upazila_id" id="perm_upazila" class="form-select">
                            <option value="">Select District First</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Present Address Card -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-location-dot"></i>Present Address</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Address/House/Road</label>
                        <input name="pres_address" id="pres_address" class="form-control" value="<?=htmlspecialchars($p['pres_address'] ?? '')?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Division</label>
                        <select name="pres_division_id" id="pres_division" class="form-select">
                            <option value="">Select Division</option>
                            <?php foreach($divisions as $d): ?>
                                <option value="<?=$d['id']?>" <?=($p['pres_division_id'] ?? '') == $d['id'] ? ' selected' : ''?>><?=htmlspecialchars($d['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">District</label>
                        <select name="pres_district_id" id="pres_district" class="form-select">
                            <option value="">Select Division First</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Upazila/Thana</label>
                        <select name="pres_upazila_id" id="pres_upazila" class="form-select">
                            <option value="">Select District First</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <button type="button" id="copyFromPermanent" class="copy-btn">
                        <i class="fa-solid fa-copy"></i> Copy from Permanent
                    </button>
                </div>
            </div>
        </div>

        <!-- Family Information Card -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-family"></i>Family Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Father/Husband Name</label>
                        <input name="father_husband_name" class="form-control" value="<?=htmlspecialchars($p['father_husband_name'] ?? '')?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Occupation</label>
                        <select name="father_husband_occupation" class="form-select">
                            <option value="">Select Occupation</option>
                            <?php foreach ($occupation_options as $o): ?>
                                <option value="<?=$o?>" <?=($o === ($p['father_husband_occupation'] ?? '')) ? 'selected' : ''?>><?=$o?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mother Name</label>
                        <input name="mother_name" class="form-control" value="<?=htmlspecialchars($p['mother_name'] ?? '')?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Occupation</label>
                        <select name="mother_occupation" class="form-select">
                            <option value="">Select Occupation</option>
                            <?php foreach ($occupation_options as $o): ?>
                                <option value="<?=$o?>" <?=($o === ($p['mother_occupation'] ?? '')) ? 'selected' : ''?>><?=$o?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
            <a href="?r=patients/profile&id=<?=$id?>" class="btn-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Saved values from database
var permDiv  = <?= (int)($p['perm_division_id'] ?? 0) ?>;
var permDist = <?= (int)($p['perm_district_id'] ?? 0) ?>;
var permUpazila = <?= (int)($p['perm_upazila_id'] ?? 0) ?>;
var presDiv  = <?= (int)($p['pres_division_id'] ?? 0) ?>;
var presDist = <?= (int)($p['pres_district_id'] ?? 0) ?>;
var presUpazila = <?= (int)($p['pres_upazila_id'] ?? 0) ?>;

console.log('Saved values:', {permDiv, permDist, permUpazila, presDiv, presDist, presUpazila});

// Age calculation from DOB
function calculateAge(dob) {
    if(!dob) return '';
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

// BMI Calculation
function calculateBMI() {
    const height = parseFloat(document.getElementById('height_cm').value);
    const weight = parseFloat(document.getElementById('weight_kg').value);
    
    if (height > 0 && weight > 0) {
        const heightM = height / 100;
        const bmi = weight / (heightM * heightM);
        const bmiRounded = Math.round(bmi * 10) / 10;
        
        document.getElementById('bmi_display').value = bmiRounded;
        document.getElementById('bmi_hidden').value = bmiRounded;
    } else {
        document.getElementById('bmi_display').value = '';
        document.getElementById('bmi_hidden').value = '';
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const dobField = document.getElementById('dob');
    if (dobField) {
        dobField.addEventListener('change', function(e) {
            document.getElementById('age').value = calculateAge(e.target.value);
        });
    }

    const heightField = document.getElementById('height_cm');
    const weightField = document.getElementById('weight_kg');
    
    if (heightField) {
        heightField.addEventListener('input', calculateBMI);
    }
    if (weightField) {
        weightField.addEventListener('input', calculateBMI);
    }
});

// Function to load districts
function loadDistricts(divisionId, districtSelect, upazilaSelect, preDist, preUpazila) {
    if (!districtSelect || !upazilaSelect) return;
    
    districtSelect.empty().prop('disabled', true);
    upazilaSelect.empty().prop('disabled', true);
    upazilaSelect.append('<option value="">Select District First</option>');
    
    if (!divisionId) {
        districtSelect.append('<option value="">Select Division First</option>');
        return;
    }
    
    districtSelect.append('<option value="">Loading districts...</option>');
    
    // FIXED: Use absolute path to API files in root directory
    var apiPath = '/api/districts.php';
    
    console.log('Loading districts from:', apiPath, 'for division:', divisionId);
    
    $.ajax({
        url: apiPath,
        method: 'GET',
        data: { division_id: divisionId },
        dataType: 'json',
        timeout: 10000,
        success: function(data) {
            console.log('Districts data received:', data);
            districtSelect.empty().prop('disabled', false);
            
            if (data && !data.error && data.length > 0) {
                districtSelect.append('<option value="">Select District</option>');
                $.each(data, function(index, district) {
                    districtSelect.append('<option value="' + district.id + '">' + district.name + '</option>');
                });
                
                if (preDist && preDist > 0) {
                    console.log('Setting district to:', preDist);
                    districtSelect.val(String(preDist));
                    
                    // Trigger change to load upazilas
                    setTimeout(function() {
                        loadUpazilas(preDist, upazilaSelect, preUpazila);
                    }, 100);
                }
            } else if (data.error) {
                console.error('API Error:', data.error);
                districtSelect.append('<option value="">Error loading districts: ' + data.error + '</option>');
            } else {
                districtSelect.append('<option value="">No districts found</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading districts:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            districtSelect.empty().append('<option value="">Error loading districts - API not accessible</option>');
        }
    });
}

// Function to load upazilas
function loadUpazilas(districtId, upazilaSelect, preUpazila) {
    if (!upazilaSelect) return;
    
    upazilaSelect.empty().prop('disabled', true);
    
    if (!districtId) {
        upazilaSelect.append('<option value="">Select District First</option>');
        return;
    }
    
    upazilaSelect.append('<option value="">Loading upazilas...</option>');
    
    // FIXED: Use absolute path to API files in root directory
    var apiPath = '/api/upazilas.php';
    
    console.log('Loading upazilas from:', apiPath, 'for district:', districtId);
    
    $.ajax({
        url: apiPath,
        method: 'GET',
        data: { district_id: districtId },
        dataType: 'json',
        timeout: 10000,
        success: function(data) {
            console.log('Upazilas data received:', data);
            upazilaSelect.empty().prop('disabled', false);
            
            if (data && !data.error && data.length > 0) {
                upazilaSelect.append('<option value="">Select Upazila/Thana</option>');
                $.each(data, function(index, upazila) {
                    upazilaSelect.append('<option value="' + upazila.id + '">' + upazila.name + '</option>');
                });
                
                if (preUpazila && preUpazila > 0) {
                    console.log('Setting upazila to:', preUpazila);
                    upazilaSelect.val(String(preUpazila));
                }
            } else if (data.error) {
                console.error('API Error:', data.error);
                upazilaSelect.append('<option value="">Error loading upazilas: ' + data.error + '</option>');
            } else {
                upazilaSelect.append('<option value="">No upazilas found</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading upazilas:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            upazilaSelect.empty().append('<option value="">Error loading upazilas - API not accessible</option>');
        }
    });
}

$(document).ready(function() {
    // Initialize with saved values
    if (permDiv) {
        loadDistricts(permDiv, $('#perm_district'), $('#perm_upazila'), permDist, permUpazila);
    }
    
    if (presDiv) {
        loadDistricts(presDiv, $('#pres_district'), $('#pres_upazila'), presDist, presUpazila);
    }
    
    // Permanent address handlers
    $('#perm_division').on('change', function() {
        console.log('Permanent division changed to:', $(this).val());
        loadDistricts($(this).val(), $('#perm_district'), $('#perm_upazila'), 0, 0);
    });
    
    $('#perm_district').on('change', function() {
        console.log('Permanent district changed to:', $(this).val());
        loadUpazilas($(this).val(), $('#perm_upazila'), 0);
    });
    
    // Present address handlers
    $('#pres_division').on('change', function() {
        console.log('Present division changed to:', $(this).val());
        loadDistricts($(this).val(), $('#pres_district'), $('#pres_upazila'), 0, 0);
    });
    
    $('#pres_district').on('change', function() {
        console.log('Present district changed to:', $(this).val());
        loadUpazilas($(this).val(), $('#pres_upazila'), 0);
    });
    
    // Copy from permanent
    $('#copyFromPermanent').on('click', function() {
        console.log('Copy from permanent clicked');
        $('#pres_address').val($('#perm_address').val());
        
        const permDivision = $('#perm_division').val();
        if (permDivision) {
            $('#pres_division').val(permDivision).trigger('change');
            
            // Wait for districts to load, then set district
            setTimeout(function() {
                const permDistrict = $('#perm_district').val();
                if (permDistrict) {
                    $('#pres_district').val(permDistrict).trigger('change');
                    
                    // Wait for upazilas to load, then set upazila
                    setTimeout(function() {
                        const permUpazila = $('#perm_upazila').val();
                        if (permUpazila) {
                            $('#pres_upazila').val(permUpazila);
                        }
                    }, 500);
                }
            }, 500);
        }
    });

    // Form validation
    $('#patientForm').on('submit', function(e) {
        const name = $('input[name="name"]').val();
        const sex = $('select[name="sex"]').val();
        const permDivision = $('#perm_division').val();
        
        if (!name || !name.trim()) {
            e.preventDefault();
            alert('Patient name is required');
            return false;
        }
        
        if (!sex) {
            e.preventDefault();
            alert('Please select gender');
            return false;
        }
        
        if (!permDivision) {
            e.preventDefault();
            alert('Please select permanent division');
            return false;
        }
        
        // Validate phone number if provided
        const contact = $('input[name="contact_number"]').val();
        if (contact && !/^01[3-9]\d{8}$/.test(contact)) {
            e.preventDefault();
            alert('Please enter a valid Bangladeshi phone number (01XXXXXXXXX)');
            return false;
        }
        
        // Validate email if provided
        const email = $('input[name="email"]').val();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return false;
        }
    });
});
</script>

<?php include __DIR__.'/../templates/footer.php'; ?>