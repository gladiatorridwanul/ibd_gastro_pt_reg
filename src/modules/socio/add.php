<?php
/**
 * modules/socio/add.php - Add Socioeconomic History
 * Updated with Smoking options: Current, Former, Never
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

if (!has_permission($pdo, 'button.module.socio.add')) {
    $_SESSION['error'] = 'You do not have permission to add socioeconomic records';
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

// Fetch patient details
try {
    $stmt = $pdo->prepare("SELECT id, name FROM patients WHERE id=?");
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: ?r=modules/socio/add&patient_id=' . $patient_id);
        exit;
    }
    
    // Validate required fields
    $errors = [];
    if (empty($_POST['smoking'])) {
        $errors[] = 'Smoking status is required';
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: ?r=modules/socio/add&patient_id=' . $patient_id);
        exit;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO socioeconomic_histories (
            patient_id, 
            smoking, 
            smoking_duration, 
            alcohol, 
            alcohol_duration, 
            children_count, 
            family_members_total, 
            monthly_income_taka
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $patient_id,
            $_POST['smoking'] ?? null,
            !empty($_POST['smoking_duration']) ? $_POST['smoking_duration'] : null,
            !empty($_POST['alcohol']) ? $_POST['alcohol'] : null,
            !empty($_POST['alcohol_duration']) ? $_POST['alcohol_duration'] : null,
            !empty($_POST['children_count']) ? (int)$_POST['children_count'] : null,
            !empty($_POST['family_members_total']) ? (int)$_POST['family_members_total'] : null,
            !empty($_POST['monthly_income_taka']) ? (float)$_POST['monthly_income_taka'] : null,
        ];
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to insert record');
        }
        
        $new_id = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        if (function_exists('audit')) {
            audit($pdo, 'socio.add', 'socioeconomic_histories', $new_id, ['patient_id' => $patient_id]);
        }
        
        $_SESSION['success'] = "Socioeconomic history added successfully";
        header('Location: ?r=patients/profile&id=' . $patient_id); 
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error adding socioeconomic record: " . $e->getMessage());
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: ?r=modules/socio/add&patient_id=' . $patient_id);
        exit;
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
    --red: #dc3545;
}

/* Global Styles */
* {
    font-family: Cambria, serif;
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
    background: linear-gradient(135deg, var(--blue), var(--green));
    padding: 6px;
    border-radius: 6px;
    margin-right: 8px;
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

/* Form Card */
.form-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
}
.form-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.form-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.form-card .card-header h6 i {
    color: var(--white) !important;
    background: linear-gradient(135deg, var(--blue), var(--green));
    padding: 5px;
    border-radius: 6px;
    margin-right: 8px;
}
.form-card .card-body {
    padding: 20px;
}
.form-card .card-footer {
    background-color: var(--white);
    border-top: 1px solid var(--ash);
    padding: 16px 20px;
}

/* Form Elements */
.form-label {
    font-size: 0.85rem;
    margin-bottom: 4px;
    color: var(--black);
    font-weight: 500;
}
.form-control, .form-select {
    font-family: Cambria, serif;
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--ash);
    border-radius: 6px;
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
.input-group-text {
    background-color: var(--ash);
    border: 1px solid var(--ash);
    color: var(--blue);
    font-family: Cambria, serif;
}

/* Serial Number Style */
.serial-number {
    display: inline-block;
    width: 22px;
    height: 22px;
    background: linear-gradient(135deg, var(--blue), var(--green));
    color: var(--white);
    border-radius: 50%;
    text-align: center;
    line-height: 22px;
    font-size: 12px;
    margin-right: 8px;
}
.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}
.section-header strong {
    font-size: 0.95rem;
    color: var(--black);
}

/* Required Field Indicator */
.text-danger {
    color: var(--red) !important;
    margin-left: 4px;
}

/* Button Styles */
.btn-primary {
    background: linear-gradient(135deg, var(--blue), var(--green));
    border: none;
    border-radius: 6px;
    padding: 10px 24px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.3s;
    font-family: Cambria, serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(74,144,226,0.3);
}
.btn-primary i {
    color: var(--white) !important;
}
.btn-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 24px;
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
    border-radius: 4px;
    padding: 4px 12px;
    color: var(--blue);
    font-size: 0.8rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-outline-primary:hover {
    background-color: var(--blue);
    color: var(--white);
}
.btn-outline-primary:hover i {
    color: var(--white) !important;
}

/* Info Box */
.info-box {
    background-color: var(--ash);
    border-left: 4px solid var(--blue);
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.info-box i {
    color: var(--blue) !important;
    margin-right: 8px;
}

/* Grid Layout */
.two-col-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .two-col-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h6><i class="fa-solid fa-people-roof"></i> Add Socioeconomic History — <?= htmlspecialchars($pat['name']) ?></h6>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Manage Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?= $patient_id ?>">Patient Profile</a></li>
                    <li class="breadcrumb-item active">Add Socioeconomic</li>
                </ol>
            </nav>
        </div>
        <a href="?r=patients/profile&id=<?= $patient_id ?>" class="btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Profile
        </a>
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

    <!-- Info Box -->
    <div class="info-box">
        <i class="fa-solid fa-info-circle"></i>
        <strong>Note:</strong> All fields except "Smoking Status" are optional. Fill in the information that is available.
    </div>

    <!-- Main Form -->
    <form method="post" class="form-card">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        
        <div class="card-header">
            <h6><i class="fa-solid fa-chart-simple"></i> Socioeconomic Information</h6>
        </div>
        
        <div class="card-body">
            <!-- Two Column Layout -->
            <div class="two-col-grid">
                <!-- Left Column -->
                <div>
                    <!-- 1. Smoking Status (Updated) - Required -->
                    <div class="mb-4">
                        <div class="section-header">
                            <span class="serial-number">1</span>
                            <strong>Smoking Status <span class="text-danger">*</span></strong>
                        </div>
                        <select class="form-select" name="smoking" required>
                            <option value="">Select smoking status</option>
                            <option value="Current">Current</option>
                            <option value="Former">Former</option>
                            <option value="Never">Never</option>
                        </select>
                    </div>

                    <!-- 2. Smoking Duration -->
                    <div class="mb-4">
                        <div class="section-header">
                            <span class="serial-number">2</span>
                            <strong>Smoking Duration</strong>
                        </div>
                        <input type="text" class="form-control" name="smoking_duration" 
                               placeholder="e.g., 5 years, 10 packs/year">
                        <small class="text-muted">Duration or intensity of smoking</small>
                    </div>

                    <!-- 3. Alcohol Consumption -->
                    <div class="mb-4">
                        <div class="section-header">
                            <span class="serial-number">3</span>
                            <strong>Alcohol Consumption</strong>
                        </div>
                        <select class="form-select" name="alcohol">
                            <option value="">Select</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>

                    <!-- 4. Alcohol Duration -->
                    <div class="mb-4">
                        <div class="section-header">
                            <span class="serial-number">4</span>
                            <strong>Alcohol Duration/Details</strong>
                        </div>
                        <input type="text" class="form-control" name="alcohol_duration" 
                               placeholder="e.g., occasional, 2 drinks/week">
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- 5. Number of Children -->
                    <div class="mb-4">
                        <div class="section-header">
                            <span class="serial-number">5</span>
                            <strong>Number of Children</strong>
                        </div>
                        <input type="number" class="form-control" name="children_count" 
                               min="0" placeholder="Enter number of children">
                    </div>

                    <!-- 6. Total Family Members -->
                    <div class="mb-4">
                        <div class="section-header">
                            <span class="serial-number">6</span>
                            <strong>Total Family Members</strong>
                        </div>
                        <input type="number" class="form-control" name="family_members_total" 
                               min="1" placeholder="Including patient">
                    </div>

                    <!-- 7. Monthly Income -->
                    <div class="mb-4">
                        <div class="section-header">
                            <span class="serial-number">7</span>
                            <strong>Monthly Income (Taka)</strong>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input type="number" step="0.01" class="form-control" 
                                   name="monthly_income_taka" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Socioeconomic History
            </button>
            <a class="btn-secondary" href="?r=patients/profile&id=<?= $patient_id ?>">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const smoking = document.querySelector('select[name="smoking"]').value;
            if (!smoking) {
                e.preventDefault();
                alert('Please select smoking status');
                return false;
            }
            
            // Validate numbers are positive
            const children = document.querySelector('input[name="children_count"]').value;
            if (children && parseInt(children) < 0) {
                e.preventDefault();
                alert('Number of children cannot be negative');
                return false;
            }
            
            const family = document.querySelector('input[name="family_members_total"]').value;
            if (family && parseInt(family) < 1) {
                e.preventDefault();
                alert('Total family members must be at least 1');
                return false;
            }
            
            const income = document.querySelector('input[name="monthly_income_taka"]').value;
            if (income && parseFloat(income) < 0) {
                e.preventDefault();
                alert('Monthly income cannot be negative');
                return false;
            }
        });
    }
});
</script>

<?php include __DIR__.'/../../templates/footer.php'; ?>