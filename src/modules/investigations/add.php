<?php
/**
 * modules/investigations/add.php - Add Investigation Record
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

if (!has_permission($pdo, 'button.module.investigations.add')) {
    $_SESSION['error'] = 'You do not have permission to add investigation records';
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
        header('Location: ?r=modules/investigations/add&patient_id=' . $patient_id);
        exit;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO investigations (
            patient_id, 
            cbc_hb, cbc_esr, cbc_tlc, dlc, platelets,
            crp, s_albumin, fecal_calprotectin,
            upper_git, endoscopy,
            colonoscopy, ileoscopy, enteroscopy,
            histopathology,
            usg, ct_scan, enterography, endoscopic_ultrasound,
            others
        ) VALUES (
            :patient_id,
            :cbc_hb, :cbc_esr, :cbc_tlc, :dlc, :platelets,
            :crp, :s_albumin, :fecal_calprotectin,
            :upper_git, :endoscopy,
            :colonoscopy, :ileoscopy, :enteroscopy,
            :histopathology,
            :usg, :ct_scan, :enterography, :endoscopic_ultrasound,
            :others
        )";
        
        $params = [
            'patient_id' => $patient_id,
            'cbc_hb' => !empty($_POST['cbc_hb']) ? trim($_POST['cbc_hb']) : null,
            'cbc_esr' => !empty($_POST['cbc_esr']) ? trim($_POST['cbc_esr']) : null,
            'cbc_tlc' => !empty($_POST['cbc_tlc']) ? trim($_POST['cbc_tlc']) : null,
            'dlc' => !empty($_POST['dlc']) ? trim($_POST['dlc']) : null,
            'platelets' => !empty($_POST['platelets']) ? trim($_POST['platelets']) : null,
            'crp' => !empty($_POST['crp']) ? trim($_POST['crp']) : null,
            's_albumin' => !empty($_POST['s_albumin']) ? trim($_POST['s_albumin']) : null,
            'fecal_calprotectin' => !empty($_POST['fecal_calprotectin']) ? trim($_POST['fecal_calprotectin']) : null,
            'upper_git' => !empty($_POST['upper_git']) ? trim($_POST['upper_git']) : null,
            'endoscopy' => !empty($_POST['endoscopy']) ? trim($_POST['endoscopy']) : null,
            'colonoscopy' => !empty($_POST['colonoscopy']) ? trim($_POST['colonoscopy']) : null,
            'ileoscopy' => !empty($_POST['ileoscopy']) ? trim($_POST['ileoscopy']) : null,
            'enteroscopy' => !empty($_POST['enteroscopy']) ? trim($_POST['enteroscopy']) : null,
            'histopathology' => !empty($_POST['histopathology']) ? trim($_POST['histopathology']) : null,
            'usg' => !empty($_POST['usg']) ? trim($_POST['usg']) : null,
            'ct_scan' => !empty($_POST['ct_scan']) ? trim($_POST['ct_scan']) : null,
            'enterography' => !empty($_POST['enterography']) ? trim($_POST['enterography']) : null,
            'endoscopic_ultrasound' => !empty($_POST['endoscopic_ultrasound']) ? trim($_POST['endoscopic_ultrasound']) : null,
            'others' => !empty($_POST['others']) ? trim($_POST['others']) : null
        ];

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to insert investigation record');
        }
        
        $new_id = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        if (function_exists('audit')) {
            audit($pdo, 'investigations.add', 'investigations', $new_id, ['patient_id' => $patient_id]);
        }
        
        $_SESSION['success'] = 'Investigation record added successfully';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error adding investigation: " . $e->getMessage());
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

/* Grid Layouts */
.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.three-column {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}
.four-column {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .two-column, .three-column, .four-column {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h6><i class="fa-solid fa-microscope"></i>Add Investigations</h6>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?=$patient_id?>">Profile</a></li>
                    <li class="breadcrumb-item active">Add Investigations</li>
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

    <!-- Patient Info Badge -->
    <div class="patient-badge">
        <i class="fa-solid fa-user"></i>
        <strong>Patient:</strong> <?=htmlspecialchars($pat['name'])?> (ID: <?=$pat['id']?>) | 
        <strong>Sex:</strong> <?=$pat['sex'] == 'M' ? 'Male' : 'Female'?> |
        <strong>DOB:</strong> <?=htmlspecialchars($pat['dob'] ?? 'Not provided')?>
    </div>

    <!-- Main Form -->
    <form method="post" id="investigationsForm">
        <input type="hidden" name="_csrf" value="<?=htmlspecialchars(csrf_token())?>">

        <!-- SECTION 1: Hematology - Four Column -->
        <div class="section-card">
            <div class="section-header blue">
                <i class="fa-solid fa-droplet"></i>
                <h6>Hematology</h6>
            </div>
            <div class="section-body">
                <div class="four-column">
                    <div>
                        <label class="form-label">CBC: Hb (g/dl)</label>
                        <input class="form-control" name="cbc_hb" placeholder="Hemoglobin value">
                    </div>
                    <div>
                        <label class="form-label">ESR (mm in 1st hr)</label>
                        <input class="form-control" name="cbc_esr" placeholder="ESR value">
                    </div>
                    <div>
                        <label class="form-label">TLC</label>
                        <input class="form-control" name="cbc_tlc" placeholder="Total Leukocyte Count">
                    </div>
                    <div>
                        <label class="form-label">DLC</label>
                        <input class="form-control" name="dlc" placeholder="Differential Count">
                    </div>
                    <div>
                        <label class="form-label">Platelets</label>
                        <input class="form-control" name="platelets" placeholder="Platelet count">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: Biochemistry - Three Column -->
        <div class="section-card">
            <div class="section-header green">
                <i class="fa-solid fa-flask"></i>
                <h6>Biochemistry</h6>
            </div>
            <div class="section-body">
                <div class="three-column">
                    <div>
                        <label class="form-label">CRP</label>
                        <input class="form-control" name="crp" placeholder="C-Reactive Protein">
                    </div>
                    <div>
                        <label class="form-label">S. Albumin</label>
                        <input class="form-control" name="s_albumin" placeholder="Serum Albumin">
                    </div>
                    <div>
                        <label class="form-label">Fecal Calprotectin</label>
                        <input class="form-control" name="fecal_calprotectin" placeholder="Fecal Calprotectin">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: Upper GI & Endoscopy - Two Column -->
        <div class="section-card">
            <div class="section-header blue">
                <i class="fa-solid fa-stethoscope"></i>
                <h6>Upper GI & Endoscopy</h6>
            </div>
            <div class="section-body">
                <div class="two-column">
                    <div>
                        <label class="form-label">Upper GIT</label>
                        <input class="form-control" name="upper_git" placeholder="Upper GI findings">
                    </div>
                    <div>
                        <label class="form-label">Endoscopy</label>
                        <input class="form-control" name="endoscopy" placeholder="Endoscopy findings">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: Colonoscopy, Ileoscopy & Enteroscopy - Three Column -->
        <div class="section-card">
            <div class="section-header green">
                <i class="fa-solid fa-scope"></i>
                <h6>Lower GI Endoscopy</h6>
            </div>
            <div class="section-body">
                <div class="three-column">
                    <div>
                        <label class="form-label">Colonoscopy</label>
                        <input class="form-control" name="colonoscopy" placeholder="Colonoscopy findings">
                    </div>
                    <div>
                        <label class="form-label">Ileoscopy</label>
                        <input class="form-control" name="ileoscopy" placeholder="Ileoscopy findings">
                    </div>
                    <div>
                        <label class="form-label">Enteroscopy</label>
                        <input class="form-control" name="enteroscopy" placeholder="Enteroscopy findings">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 5: Pathology -->
        <div class="section-card">
            <div class="section-header blue">
                <i class="fa-solid fa-microscope"></i>
                <h6>Pathology</h6>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-md-12">
                        <label class="form-label">Histopathology</label>
                        <input class="form-control" name="histopathology" placeholder="Histopathology report">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 6: Imaging - Three Column -->
        <div class="section-card">
            <div class="section-header green">
                <i class="fa-solid fa-x-ray"></i>
                <h6>Imaging</h6>
            </div>
            <div class="section-body">
                <div class="three-column">
                    <div>
                        <label class="form-label">USG</label>
                        <input class="form-control" name="usg" placeholder="Ultrasound findings">
                    </div>
                    <div>
                        <label class="form-label">CT Scan</label>
                        <input class="form-control" name="ct_scan" placeholder="CT findings">
                    </div>
                    <div>
                        <label class="form-label">Enterography (MRE/CTE)</label>
                        <input class="form-control" name="enterography" placeholder="MR or CT Enterography">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 7: Advanced Endoscopic Imaging -->
        <div class="section-card">
            <div class="section-header blue">
                <i class="fa-solid fa-wave-square"></i>
                <h6>Advanced Endoscopic Imaging</h6>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Endoscopic Ultrasound (EUS)</label>
                        <input class="form-control" name="endoscopic_ultrasound" placeholder="EUS findings">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 8: Other -->
        <div class="section-card">
            <div class="section-header green">
                <i class="fa-solid fa-ellipsis"></i>
                <h6>Other Investigations</h6>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-md-12">
                        <label class="form-label">Others</label>
                        <input class="form-control" name="others" placeholder="Any other investigations">
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card-footer">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Investigations
            </button>
            <a href="?r=patients/profile&id=<?=$patient_id?>" class="btn-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Form validation (optional)
document.getElementById('investigationsForm')?.addEventListener('submit', function(e) {
    // At least one field should be filled? Optional validation
    return true;
});
</script>

<?php include __DIR__.'/../../templates/footer.php'; ?>