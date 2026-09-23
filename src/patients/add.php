<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files with error checking
$required_files = [
    'db' => __DIR__.'/../includes/db.php',
    'auth' => __DIR__.'/../includes/auth.php',
    'permissions' => __DIR__.'/../includes/permissions.php',
    'audit' => __DIR__.'/../includes/audit.php',
    'helpers' => __DIR__.'/../includes/helpers.php'
];

foreach ($required_files as $name => $path) {
    if (file_exists($path)) {
        require_once $path;
    } else {
        die("Required file not found: {$name}.php at {$path}");
    }
}

// Check if user is logged in
if (!function_exists('require_login')) {
    die('Authentication system not loaded properly.');
}
require_login();

// Check permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'button.patient.add')) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'You do not have permission to add patients.'
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $dob = $_POST['dob'] ?: null;
    
    // Calculate age if DOB provided, otherwise use manually entered age
    $age = null;
    if ($dob) {
        // If DOB is provided, calculate age from it
        if (function_exists('calc_age')) {
            $age = calc_age($dob);
        } else {
            // Fallback age calculation
            $birthDate = new DateTime($dob);
            $today = new DateTime('today');
            $age = $birthDate->diff($today)->y;
        }
    } elseif (!empty($_POST['age']) && is_numeric($_POST['age'])) {
        // If DOB not provided but age is provided manually, use that
        $age = (int)$_POST['age'];
    }
    
    // Calculate BMI
    $bmi = null;
    if (!empty($_POST['height_cm']) && !empty($_POST['weight_kg']) && $_POST['height_cm'] > 0) {
        $height_m = $_POST['height_cm'] / 100;
        $bmi = round($_POST['weight_kg'] / ($height_m * $height_m), 1);
    }
    
    // Map form field names to database column names
    $perm_upazila_id = !empty($_POST['perm_upazila_id']) ? $_POST['perm_upazila_id'] : (!empty($_POST['perm_thana_id']) ? $_POST['perm_thana_id'] : null);
    $pres_upazila_id = !empty($_POST['pres_upazila_id']) ? $_POST['pres_upazila_id'] : (!empty($_POST['pres_thana_id']) ? $_POST['pres_thana_id'] : null);
    
    $perm_division_id = !empty($_POST['perm_division_id']) ? $_POST['perm_division_id'] : null;
    $perm_district_id = !empty($_POST['perm_district_id']) ? $_POST['perm_district_id'] : null;
    $pres_division_id = !empty($_POST['pres_division_id']) ? $_POST['pres_division_id'] : null;
    $pres_district_id = !empty($_POST['pres_district_id']) ? $_POST['pres_district_id'] : null;
    
    // Get current user ID
    $current_user_id = null;
    if (function_exists('user')) {
        $user_data = user();
        $current_user_id = $user_data['id'] ?? $_SESSION['user_id'] ?? null;
    } else {
        $current_user_id = $_SESSION['user_id'] ?? null;
    }
    
    // Prepare SQL statement
    $sql = "INSERT INTO patients
        (name, age, sex, dob, height_cm, weight_kg, bmi, nationality, religion, national_id, 
         occupation, education, contact_number, email,
         perm_address, perm_division_id, perm_district_id, perm_upazila_id, 
         pres_address, pres_division_id, pres_district_id, pres_upazila_id,
         father_husband_name, father_husband_occupation, mother_name, mother_occupation, 
         ibd_reg_no, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
    
    $params = [
        $_POST['name'] ?? '',
        $age,  // This will be either calculated from DOB or manually entered
        $_POST['sex'] ?? '',
        $dob,
        !empty($_POST['height_cm']) ? $_POST['height_cm'] : null,
        !empty($_POST['weight_kg']) ? $_POST['weight_kg'] : null,
        $bmi,
        !empty($_POST['nationality']) ? $_POST['nationality'] : 'Bangladesh',
        !empty($_POST['religion']) ? $_POST['religion'] : null,
        !empty($_POST['national_id']) ? $_POST['national_id'] : null,
        !empty($_POST['occupation']) ? $_POST['occupation'] : null,
        !empty($_POST['education']) ? $_POST['education'] : null,
        !empty($_POST['contact_number']) ? $_POST['contact_number'] : null,
        !empty($_POST['email']) ? $_POST['email'] : null,
        !empty($_POST['perm_address']) ? $_POST['perm_address'] : null,
        $perm_division_id,
        $perm_district_id,
        $perm_upazila_id,
        !empty($_POST['pres_address']) ? $_POST['pres_address'] : null,
        $pres_division_id,
        $pres_district_id,
        $pres_upazila_id,
        !empty($_POST['father_husband_name']) ? $_POST['father_husband_name'] : null,
        !empty($_POST['father_husband_occupation']) ? $_POST['father_husband_occupation'] : null,
        !empty($_POST['mother_name']) ? $_POST['mother_name'] : null,
        !empty($_POST['mother_occupation']) ? $_POST['mother_occupation'] : null,
        !empty($_POST['ibd_reg_no']) ? $_POST['ibd_reg_no'] : null,
        $current_user_id
    ];
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            $pid = $pdo->lastInsertId();
            
            // Log the activity
            if (function_exists('audit')) {
                audit($pdo, 'patient.create', 'patients', $pid, ['name' => $_POST['name'] ?? '']);
            }
            
            // Set success message
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'text' => 'Patient added successfully!'
            ];
            
            header('Location: ?r=patients/profile&id=' . $pid);
            exit;
        } else {
            $error = "Failed to save patient data";
            error_log("PDO Error: " . print_r($stmt->errorInfo(), true));
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Patient insert error: " . $e->getMessage());
    }
}

// Get divisions for dropdown
$divisions = [];
try {
    $divisions = $pdo->query("SELECT id, name FROM divisions ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching divisions: " . $e->getMessage());
}

// Common occupation list
$occupations = [
    'Service', 'Business', 'Teacher', 'Doctor', 'Engineer', 'Lawyer', 
    'Farmer', 'Laborer', 'Driver', 'Housewife', 'Student', 'Retired', 
    'Unemployed', 'Others'
];

// Education levels
$education_levels = [
    'Primary', 'Secondary',
    'Higher Secondary', 'Tertiary', 'No Formal Education'
];

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
        <title>Add Patient - PMRMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --white: #FFFFFF;
                --ash: #F2F4F8;
                --blue: #4A90E2;
                --black: #000000;
            }
            body { background-color: var(--ash); font-family: Cambria, serif; }
            .container-fluid { padding: 20px; }
        </style>
    </head>
    <body>
        <div class="container-fluid">
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

/* Section Dividers */
.section-divider {
    margin: 24px 0 16px;
    border-top: 1px solid var(--ash);
}
.section-title {
    color: var(--black);
    font-weight: 600;
    margin-bottom: 16px;
    font-size: 1rem;
}
.section-title i {
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
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--black);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
}
.btn-outline-secondary i {
    color: var(--blue) !important;
}

/* BMI Display */
.bmi-calculator {
    background-color: var(--ash);
    padding: 12px;
    border-radius: 6px;
    margin-top: 8px;
}
.bmi-value {
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 4px;
    display: inline-block;
    color: var(--white);
}
.bmi-underweight { background-color: var(--blue); color: var(--white); }
.bmi-normal { background-color: var(--blue); color: var(--white); }
.bmi-overweight { background-color: var(--blue); color: var(--white); }
.bmi-obese { background-color: var(--blue); color: var(--white); }

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

/* Row Spacing */
.row.g-3 {
    --bs-gutter-y: 1rem;
}

/* Alert */
.alert-danger {
    background-color: #f8d7da;
    border-left: 4px solid #dc3545;
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    color: #721c24;
}

/* Responsive */
@media (max-width: 768px) {
    .btn-primary, .btn-secondary {
        width: 100%;
        margin-bottom: 8px;
    }
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h5><i class="fa-solid fa-user-plus"></i>Add New Patient</h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Manage Patients</a></li>
                    <li class="breadcrumb-item active">Add Patient</li>
                </ol>
            </nav>
        </div>
        <div>
            <span class="text-muted">
                <i class="fa-regular fa-calendar me-1" style="color: var(--blue);"></i><?=date('l, F j, Y')?>
            </span>
        </div>
    </div>

    <!-- Patient Info Badge -->
    <div class="patient-badge">
        <i class="fa-solid fa-id-card"></i>
        <strong>New Patient Registration</strong> — Patient ID will be auto-generated
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation me-2" style="color: #dc3545;"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Main Form -->
    <form method="post" id="patientForm">
        <input type="hidden" name="_csrf" value="<?=htmlspecialchars(csrf_token())?>">

        <!-- Patient Profile Section -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-user"></i>Patient Profile</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">IBD Registration No.</label>
                        <input type="text" name="ibd_reg_no" class="form-control" placeholder="Enter registration number">
                        <small class="form-text">Optional unique identifier</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Full name">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" id="age" class="form-control" placeholder="Enter age" min="0" max="150">
                        <small class="form-text">OR use DOB below</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sex <span class="text-danger">*</span></label>
                        <select name="sex" class="form-select" required>
                            <option value="">Select</option>
                            <option value="M">Male</option>
                            <option value="F">Female</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" id="dob" class="form-control">
                        <small class="form-text">Auto-calculates age</small>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" name="height_cm" id="height_cm" step="0.01" class="form-control" placeholder="cm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" name="weight_kg" id="weight_kg" step="0.01" class="form-control" placeholder="kg">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">BMI</label>
                        <input type="text" id="bmi_display" class="form-control" readonly placeholder="Auto calculated">
                        <input type="hidden" name="bmi" id="bmi_hidden">
                    </div>
                    <div class="col-md-4">
                        <div id="bmi_category" class="bmi-calculator mt-2" style="display: none;"></div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Nationality</label>
                        <input type="text" name="nationality" class="form-control" value="Bangladesh">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Religion</label>
                        <select name="religion" class="form-select">
                            <option value="">Select</option>
                            <option>Islam</option>
                            <option>Hinduism</option>
                            <option>Buddhism</option>
                            <option>Christianity</option>
                            <option>Others</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">National ID</label>
                        <input type="text" name="national_id" class="form-control" placeholder="NID number">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Occupation</label>
                        <select name="occupation" class="form-select">
                            <option value="">Select Occupation</option>
                            <?php foreach($occupations as $occ): ?>
                                <option value="<?=htmlspecialchars($occ)?>"><?=htmlspecialchars($occ)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Education</label>
                        <select name="education" class="form-select">
                            <option value="">Select Education</option>
                            <?php foreach($education_levels as $edu): ?>
                                <option value="<?=htmlspecialchars($edu)?>"><?=htmlspecialchars($edu)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" placeholder="01XXXXXXXXX">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="example@email.com">
                    </div>
                </div>
            </div>
        </div>

        <!-- Permanent Address Section -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-location-dot"></i>Permanent Address</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Address/House/Road</label>
                        <input type="text" name="perm_address" id="perm_address" class="form-control" placeholder="House No, Road No, Village/Area">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Division <span class="text-danger">*</span></label>
                        <select name="perm_division_id" id="perm_division" class="form-select" required>
                            <option value="">Select Division</option>
                            <?php foreach($divisions as $d): ?>
                                <option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">District <span class="text-danger">*</span></label>
                        <select name="perm_district_id" id="perm_district" class="form-select" required disabled>
                            <option value="">Select Division First</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Upazila/Thana</label>
                        <select name="perm_upazila_id" id="perm_upazila" class="form-select" disabled>
                            <option value="">Select District First</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Present Address Section -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-location-dot"></i>Present Address</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Address/House/Road</label>
                        <input type="text" name="pres_address" id="pres_address" class="form-control" placeholder="House No, Road No, Village/Area">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Division</label>
                        <select name="pres_division_id" id="pres_division" class="form-select">
                            <option value="">Select Division</option>
                            <?php foreach($divisions as $d): ?>
                                <option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">District</label>
                        <select name="pres_district_id" id="pres_district" class="form-select" disabled>
                            <option value="">Select Division First</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Upazila/Thana</label>
                        <select name="pres_upazila_id" id="pres_upazila" class="form-select" disabled>
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

        <!-- Family Information Section -->
        <div class="form-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-family"></i>Family Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Father/Husband Name</label>
                        <input type="text" name="father_husband_name" class="form-control" placeholder="Full name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Occupation</label>
                        <select name="father_husband_occupation" class="form-select">
                            <option value="">Select Occupation</option>
                            <?php foreach($occupations as $occ): ?>
                                <option value="<?=htmlspecialchars($occ)?>"><?=htmlspecialchars($occ)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mother Name</label>
                        <input type="text" name="mother_name" class="form-control" placeholder="Full name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Occupation</label>
                        <select name="mother_occupation" class="form-select">
                            <option value="">Select Occupation</option>
                            <?php foreach($occupations as $occ): ?>
                                <option value="<?=htmlspecialchars($occ)?>"><?=htmlspecialchars($occ)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="d-flex gap-3 mt-4">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Patient
            </button>
            <a href="?r=patients/manage" class="btn-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
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
        
        // Show BMI category
        let category = '';
        let categoryClass = '';
        
        if (bmiRounded < 18.5) {
            category = 'Underweight';
            categoryClass = 'bmi-underweight';
        } else if (bmiRounded >= 18.5 && bmiRounded < 25) {
            category = 'Normal weight';
            categoryClass = 'bmi-normal';
        } else if (bmiRounded >= 25 && bmiRounded < 30) {
            category = 'Overweight';
            categoryClass = 'bmi-overweight';
        } else {
            category = 'Obese';
            categoryClass = 'bmi-obese';
        }
        
        const bmiDiv = document.getElementById('bmi_category');
        bmiDiv.style.display = 'block';
        bmiDiv.innerHTML = '<span class="bmi-value ' + categoryClass + '">BMI: ' + bmiRounded + ' - ' + category + '</span>';
    } else {
        document.getElementById('bmi_display').value = '';
        document.getElementById('bmi_hidden').value = '';
        document.getElementById('bmi_category').style.display = 'none';
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const dobField = document.getElementById('dob');
    const ageField = document.getElementById('age');
    
    if (dobField) {
        dobField.addEventListener('change', function(e) {
            // When DOB changes, calculate and set age, and clear manual age field
            const calculatedAge = calculateAge(e.target.value);
            if (calculatedAge) {
                ageField.value = calculatedAge;
                ageField.readOnly = true; // Make age read-only when DOB is used
                ageField.style.backgroundColor = 'var(--ash)';
            }
        });
    }
    
    if (ageField) {
        ageField.addEventListener('focus', function() {
            // When user focuses on age field, they can enter manually
            // Clear DOB if they want to enter age manually
            if (dobField && dobField.value) {
                if (confirm('Do you want to enter age manually? This will clear the date of birth.')) {
                    dobField.value = '';
                    ageField.readOnly = false;
                    ageField.style.backgroundColor = '';
                }
            }
        });
        
        ageField.addEventListener('input', function() {
            // When manually entering age, clear DOB if it exists
            if (dobField && dobField.value && this.value) {
                dobField.value = '';
            }
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
function loadDistricts(divisionId, districtSelect, upazilaSelect) {
    if (!districtSelect || !upazilaSelect) return;
    
    // Clear and disable dropdowns
    districtSelect.empty().prop('disabled', true);
    upazilaSelect.empty().prop('disabled', true);
    upazilaSelect.append('<option value="">Select District First</option>');
    
    if (!divisionId) {
        districtSelect.append('<option value="">Select Division First</option>');
        return;
    }
    
    // Show loading
    districtSelect.append('<option value="">Loading districts...</option>');
    
    // Make AJAX call
    $.ajax({
        url: '/api/districts.php',
        method: 'GET',
        data: { division_id: divisionId },
        dataType: 'json',
        timeout: 5000,
        success: function(data) {
            // Clear loading message
            districtSelect.empty();
            
            if (data && data.length > 0) {
                districtSelect.append('<option value="">Select District</option>');
                $.each(data, function(index, district) {
                    districtSelect.append('<option value="' + district.id + '">' + district.name + '</option>');
                });
                districtSelect.prop('disabled', false);
            } else {
                districtSelect.append('<option value="">No districts found</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading districts:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            districtSelect.empty().append('<option value="">Error loading districts</option>');
        }
    });
}

// Function to load upazilas
function loadUpazilas(districtId, upazilaSelect) {
    if (!upazilaSelect) return;
    
    // Clear and disable
    upazilaSelect.empty().prop('disabled', true);
    
    if (!districtId) {
        upazilaSelect.append('<option value="">Select District First</option>');
        return;
    }
    
    // Show loading
    upazilaSelect.append('<option value="">Loading upazilas...</option>');
    
    // Make AJAX call
    $.ajax({
        url: '/api/upazilas.php',
        method: 'GET',
        data: { district_id: districtId },
        dataType: 'json',
        timeout: 5000,
        success: function(data) {
            // Clear loading message
            upazilaSelect.empty();
            
            if (data && data.length > 0) {
                upazilaSelect.append('<option value="">Select Upazila/Thana</option>');
                $.each(data, function(index, upazila) {
                    upazilaSelect.append('<option value="' + upazila.id + '">' + upazila.name + '</option>');
                });
                upazilaSelect.prop('disabled', false);
            } else {
                upazilaSelect.append('<option value="">No upazilas found</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading upazilas:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            upazilaSelect.empty().append('<option value="">Error loading upazilas</option>');
        }
    });
}

$(document).ready(function() {
    console.log('Document ready, initializing dropdown handlers');
    
    // Permanent address handlers
    $('#perm_division').on('change', function() {
        console.log('Permanent division changed to:', $(this).val());
        loadDistricts($(this).val(), $('#perm_district'), $('#perm_upazila'));
    });

    $('#perm_district').on('change', function() {
        console.log('Permanent district changed to:', $(this).val());
        loadUpazilas($(this).val(), $('#perm_upazila'));
    });

    // Present address handlers
    $('#pres_division').on('change', function() {
        console.log('Present division changed to:', $(this).val());
        loadDistricts($(this).val(), $('#pres_district'), $('#pres_upazila'));
    });

    $('#pres_district').on('change', function() {
        console.log('Present district changed to:', $(this).val());
        loadUpazilas($(this).val(), $('#pres_upazila'));
    });

    // Copy from permanent address
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

<?php
// Include footer
$footerPath = __DIR__ . '/../templates/footer.php';
if (file_exists($footerPath)) {
    include $footerPath;
} else {
    echo '</div></body></html>';
}
?>