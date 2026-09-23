<?php
/**
 * modules/pregnancy/add.php - Add Pregnancy History
 * Styled to match the dashboard design
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

if (!has_permission($pdo, 'button.module.pregnancy.add')) {
    $_SESSION['error'] = 'You do not have permission to add pregnancy records';
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
        header('Location: ?r=modules/pregnancy/add&patient_id=' . $patient_id);
        exit;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO pregnancies (
            patient_id, 
            pregnancy_outcome,
            pregnancy_outcome_notes,
            mode_of_delivery,
            mode_of_delivery_notes,
            history, 
            abortion, 
            abortion_details, 
            congenital_disorder
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $patient_id,
            !empty($_POST['pregnancy_outcome']) ? $_POST['pregnancy_outcome'] : null,
            !empty($_POST['pregnancy_outcome_notes']) ? trim($_POST['pregnancy_outcome_notes']) : null,
            !empty($_POST['mode_of_delivery']) ? $_POST['mode_of_delivery'] : null,
            !empty($_POST['mode_of_delivery_notes']) ? trim($_POST['mode_of_delivery_notes']) : null,
            !empty($_POST['history']) ? trim($_POST['history']) : null,
            !empty($_POST['abortion']) ? $_POST['abortion'] : null,
            !empty($_POST['abortion_details']) ? trim($_POST['abortion_details']) : null,
            !empty($_POST['congenital_disorder']) ? trim($_POST['congenital_disorder']) : null
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
            audit($pdo, 'pregnancy.add', 'pregnancies', $new_id, ['patient_id' => $patient_id]);
        }
        
        $_SESSION['success'] = "Pregnancy record added successfully";
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error adding pregnancy record: " . $e->getMessage());
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: ?r=modules/pregnancy/add&patient_id=' . $patient_id);
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
    font-size: 1.1rem;
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
    font-size: 1rem;
}
.form-card .card-header h6 i {
    color: var(--white) !important;
    background: linear-gradient(135deg, var(--blue), var(--green));
    padding: 5px;
    border-radius: 6px;
    margin-right: 8px;
}
.form-card .card-body {
    padding: 24px;
}
.form-card .card-footer {
    background-color: var(--white);
    border-top: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 0 0 8px 8px;
}

/* Form Elements */
.form-label {
    font-size: 0.85rem;
    margin-bottom: 6px;
    color: var(--black);
    font-weight: 500;
    letter-spacing: 0.3px;
}
.form-control, .form-select {
    font-family: Cambria, serif;
    font-size: 0.9rem;
    padding: 0.6rem 0.75rem;
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
textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* Serial Number Style */
.serial-number {
    display: inline-block;
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, var(--blue), var(--green));
    color: var(--white);
    border-radius: 50%;
    text-align: center;
    line-height: 24px;
    font-size: 13px;
    margin-right: 8px;
    font-weight: normal;
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
    padding: 6px 14px;
    color: var(--blue);
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
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

/* Info Box */
.info-box {
    background-color: var(--ash);
    border-left: 4px solid var(--blue);
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 24px;
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

/* Conditional Field */
.conditional-field {
    transition: all 0.2s ease;
    margin-top: 10px;
    padding-left: 32px;
}

/* Input Group */
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

/* Disabled field styling */
.form-control:disabled, .form-select:disabled {
    background-color: var(--ash);
    opacity: 0.7;
    cursor: not-allowed;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h6><i class="fa-solid fa-baby"></i> Add Pregnancy History — <?= htmlspecialchars($pat['name']) ?></h6>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Manage Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?= $patient_id ?>">Patient Profile</a></li>
                    <li class="breadcrumb-item active">Add Pregnancy</li>
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
        <strong>Note:</strong> Fill in the pregnancy history details. All fields are optional unless marked required.
    </div>

    <!-- Main Form -->
    <form method="post" class="form-card">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        
        <div class="card-header">
            <h6><i class="fa-solid fa-person-pregnant"></i> Pregnancy Information</h6>
        </div>
        
        <div class="card-body">
            <!-- NEW: 1. Pregnancy Outcome -->
            <div class="mb-4">
                <div class="section-header">
                    <span class="serial-number">1</span>
                    <strong>Pregnancy Outcome</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <select class="form-select conditional-select" name="pregnancy_outcome" data-target="#pregnancy_outcome_notes_section">
                            <option value="">Select Outcome</option>
                            <option value="Normal">Normal</option>
                            <option value="Abortion">Abortion</option>
                            <option value="Premature Delivery">Premature Delivery</option>
                            <option value="Still Birth">Still Birth</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div id="pregnancy_outcome_notes_section" class="col-md-12 conditional-field" style="display: none;">
                        <label class="form-label">Additional Notes</label>
                        <input type="text" class="form-control" name="pregnancy_outcome_notes" placeholder="Enter additional notes about pregnancy outcome...">
                    </div>
                </div>
            </div>

            <!-- NEW: 2. Mode of Delivery -->
            <div class="mb-4">
                <div class="section-header">
                    <span class="serial-number">2</span>
                    <strong>Mode of Delivery</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <select class="form-select conditional-select" name="mode_of_delivery" data-target="#mode_of_delivery_notes_section">
                            <option value="">Select Mode</option>
                            <option value="NVD">NVD (Normal Vaginal Delivery)</option>
                            <option value="LUCS">LUCS (Lower Uterine Caesarean Section)</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div id="mode_of_delivery_notes_section" class="col-md-12 conditional-field" style="display: none;">
                        <label class="form-label">Additional Notes</label>
                        <input type="text" class="form-control" name="mode_of_delivery_notes" placeholder="Enter additional notes about mode of delivery...">
                    </div>
                </div>
            </div>

            <!-- 3. History (Full Width) - Updated serial number -->
            <div class="mb-4">
                <div class="section-header">
                    <span class="serial-number">3</span>
                    <strong>Pregnancy History</strong>
                </div>
                <textarea class="form-control" name="history" rows="4" 
                          placeholder="Enter detailed pregnancy history including gravida, para, live births, miscarriages, etc."></textarea>
                <small class="text-muted">Include gravida (G), para (P), live births, abortions, etc.</small>
            </div>

            <!-- Two Column Layout for remaining fields -->
            <div class="two-col-grid">
                <!-- Left Column -->
                <div>
                    <!-- 4. Abortion -->
                    <div class="mb-3">
                        <div class="section-header">
                            <span class="serial-number">4</span>
                            <strong>Abortion History</strong>
                        </div>
                        <select class="form-select" name="abortion" id="abortion">
                            <option value="">Select</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- 5. Abortion Details (Conditional) -->
                    <div class="mb-3">
                        <div class="section-header">
                            <span class="serial-number">5</span>
                            <strong>Abortion Details</strong>
                        </div>
                        <input type="text" class="form-control" name="abortion_details" 
                               id="abortion_details" placeholder="e.g., spontaneous, induced, trimester"
                               disabled>
                        <small class="text-muted">Specify details if applicable</small>
                    </div>
                </div>
            </div>

            <!-- 6. Congenital Disorder (Full Width) -->
            <div class="mb-3">
                <div class="section-header">
                    <span class="serial-number">6</span>
                    <strong>Congenital Disorders</strong>
                </div>
                <input type="text" class="form-control" name="congenital_disorder" 
                       placeholder="e.g., neural tube defects, heart defects, genetic conditions">
                <small class="text-muted">Any congenital disorders in pregnancy or offspring</small>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Pregnancy Record
            </button>
            <a class="btn-secondary" href="?r=patients/profile&id=<?= $patient_id ?>">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
(function() {
    // Conditional field toggling for Pregnancy Outcome and Mode of Delivery
    function toggleConditionalField(select) {
        const targetId = select.getAttribute('data-target');
        if (!targetId) return;
        
        const targetField = document.querySelector(targetId);
        if (!targetField) return;
        
        const show = (select.value !== '' && select.value !== null);
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
    
    // Show/hide abortion details based on selection
    const abortionSelect = document.getElementById('abortion');
    const abortionDetails = document.getElementById('abortion_details');
    
    if (abortionSelect && abortionDetails) {
        function toggleAbortionDetails() {
            if (abortionSelect.value === 'Yes') {
                abortionDetails.disabled = false;
                abortionDetails.focus();
            } else {
                abortionDetails.disabled = true;
                abortionDetails.value = '';
            }
        }
        
        // Initial state
        toggleAbortionDetails();
        
        // Add event listener
        abortionSelect.addEventListener('change', toggleAbortionDetails);
    }
})();
</script>

<?php include __DIR__.'/../../templates/footer.php'; ?>