<?php
/**
 * modules/followup/add.php - Add Follow-up Record
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

if (!has_permission($pdo, 'button.module.followup.add')) {
    $_SESSION['error'] = 'You do not have permission to add follow-up records';
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
        header('Location: ?r=modules/followup/add&patient_id=' . $patient_id);
        exit;
    }
    
    $when = $_POST['followup_at'] ?: null;
    // Convert datetime-local format to MySQL datetime
    if ($when && strpos($when, 'T') !== false) { 
        $when = str_replace('T', ' ', $when) . ':00'; 
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO followups (patient_id, followup_at, description, treatment) VALUES (?, ?, ?, ?)";
        $params = [
            $patient_id, 
            $when, 
            !empty($_POST['description']) ? trim($_POST['description']) : null, 
            !empty($_POST['treatment']) ? trim($_POST['treatment']) : null
        ];
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to insert follow-up record');
        }
        
        $new_id = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        if (function_exists('audit')) {
            audit($pdo, 'followup.add', 'followups', $new_id, ['patient_id' => $patient_id]);
        }
        
        $_SESSION['success'] = "Follow-up record added successfully";
        header('Location: ?r=patients/profile&id=' . $patient_id); 
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error adding follow-up: " . $e->getMessage());
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

/* Date Time Input Styling */
input[type="datetime-local"] {
    font-family: Cambria, serif;
    padding: 0.6rem 0.75rem;
}

/* Current Date Info */
.current-datetime {
    background-color: var(--ash);
    padding: 8px 12px;
    border-radius: 4px;
    margin-top: 8px;
    font-size: 0.85rem;
    color: var(--black);
}
.current-datetime i {
    color: var(--blue) !important;
    margin-right: 6px;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h6><i class="fa-solid fa-calendar-check"></i> Add Follow-up — <?= htmlspecialchars($pat['name']) ?></h6>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Manage Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?= $patient_id ?>">Patient Profile</a></li>
                    <li class="breadcrumb-item active">Add Follow-up</li>
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
        <strong>Note:</strong> Schedule a follow-up appointment. Date and time are required.
    </div>

    <!-- Current Date/Time Display -->
    <div class="current-datetime mb-3">
        <i class="fa-regular fa-clock"></i>
        Current server time: <?= date('Y-m-d H:i:s') ?> | 
        <i class="fa-regular fa-calendar ms-2"></i>
        Timezone: <?= date_default_timezone_get() ?>
    </div>

    <!-- Main Form -->
    <form method="post" class="form-card">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        
        <div class="card-header">
            <h6><i class="fa-solid fa-calendar-plus"></i> Follow-up Details</h6>
        </div>
        
        <div class="card-body">
            <!-- 1. Date & Time (Full Width - Important Field) -->
            <div class="mb-4">
                <div class="section-header">
                    <span class="serial-number">1</span>
                    <strong>Follow-up Date & Time <span style="color: var(--blue);">*</span></strong>
                </div>
                <input type="datetime-local" class="form-control" name="followup_at" required 
                       value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>">
                <small class="text-muted">Select date and time for the follow-up appointment</small>
            </div>

            <!-- Two Column Layout for Description and Treatment -->
            <div class="two-col-grid">
                <!-- Left Column: Description -->
                <div class="mb-3">
                    <div class="section-header">
                        <span class="serial-number">2</span>
                        <strong>Description / Reason</strong>
                    </div>
                    <textarea class="form-control" name="description" rows="5" 
                              placeholder="Enter reason for follow-up, symptoms, concerns, etc."></textarea>
                    <small class="text-muted">Describe the purpose of this follow-up</small>
                </div>

                <!-- Right Column: Treatment -->
                <div class="mb-3">
                    <div class="section-header">
                        <span class="serial-number">3</span>
                        <strong>Treatment Plan</strong>
                    </div>
                    <textarea class="form-control" name="treatment" rows="5" 
                              placeholder="Enter treatment plan, medications, recommendations, etc."></textarea>
                    <small class="text-muted">Outline the treatment plan or recommendations</small>
                </div>
            </div>

            <!-- Quick Select Options -->
            <div class="mt-3">
                <div class="section-header">
                    <span class="serial-number" style="background: var(--ash); color: var(--blue);">⟳</span>
                    <strong>Quick Date Select</strong>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn-outline-primary btn-sm" onclick="setDate(7)">
                        <i class="fa-regular fa-calendar"></i> +7 days
                    </button>
                    <button type="button" class="btn-outline-primary btn-sm" onclick="setDate(14)">
                        <i class="fa-regular fa-calendar"></i> +14 days
                    </button>
                    <button type="button" class="btn-outline-primary btn-sm" onclick="setDate(30)">
                        <i class="fa-regular fa-calendar"></i> +30 days
                    </button>
                    <button type="button" class="btn-outline-primary btn-sm" onclick="setToday()">
                        <i class="fa-regular fa-calendar"></i> Today
                    </button>
                    <button type="button" class="btn-outline-primary btn-sm" onclick="setTomorrow()">
                        <i class="fa-regular fa-calendar"></i> Tomorrow
                    </button>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Follow-up
            </button>
            <a class="btn-secondary" href="?r=patients/profile&id=<?= $patient_id ?>">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Function to set date relative to today
function setDate(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    setDateTimeInput(date);
}

function setToday() {
    setDateTimeInput(new Date());
}

function setTomorrow() {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    setDateTimeInput(date);
}

function setDateTimeInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    
    const datetimeLocal = `${year}-${month}-${day}T${hours}:${minutes}`;
    const input = document.querySelector('input[name="followup_at"]');
    if (input) {
        input.value = datetimeLocal;
    }
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const followupAt = document.querySelector('input[name="followup_at"]').value;
            if (!followupAt) {
                e.preventDefault();
                alert('Please select a follow-up date and time');
            }
        });
    }
});
</script>

<?php include __DIR__.'/../../templates/footer.php'; ?>