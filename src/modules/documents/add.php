<?php
/**
 * modules/documents/add.php - Upload Patient Document
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

if (!has_permission($pdo, 'button.module.documents.add')) {
    $_SESSION['error'] = 'You do not have permission to upload documents';
    header('Location: ?r=patients/manage');
    exit;
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/../../../logs/php_errors.log');

// Create logs directory if it doesn't exist
$log_dir = __DIR__.'/../../../logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
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

// Define upload directory - CORRECT PATH (no 'public' in the path)
$upload_base = __DIR__.'/../../../uploads/';
$upload_dir = $upload_base . 'patient_' . $patient_id . '/';

// Create base uploads directory if it doesn't exist
if (!file_exists($upload_base)) {
    if (!mkdir($upload_base, 0777, true)) {
        error_log("Failed to create base upload directory: " . $upload_base);
        $_SESSION['error'] = 'System configuration error: Unable to create upload directory';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
    }
}

// Create patient directory if it doesn't exist
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        error_log("Failed to create patient directory: " . $upload_dir);
        $_SESSION['error'] = 'Unable to create patient upload directory';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
    }
}

// Check if directory is writable
if (!is_writable($upload_dir)) {
    // Attempt to fix permissions
    chmod($upload_dir, 0777);
    if (!is_writable($upload_dir)) {
        error_log("Upload directory not writable: " . $upload_dir);
        $_SESSION['error'] = 'Upload directory is not writable. Please check permissions.';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
    }
}

// Allowed file types
$allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size = 1 * 1024 * 1024; // 1MB in bytes

$error = '';
$success = '';

// Function to sanitize filename
function sanitizeFilename($filename) {
    // Remove any path information
    $filename = basename($filename);
    // Replace any non-alphanumeric characters (except dots and dashes)
    $filename = preg_replace('/[^a-zA-Z0-9\-\.]/', '_', $filename);
    // Limit length
    return substr($filename, 0, 100);
}

// Function to get file icon class
function getFileIconClass($extension) {
    return match(strtolower($extension)) {
        'pdf' => 'fa-file-pdf',
        'jpg', 'jpeg', 'png', 'gif', 'bmp' => 'fa-file-image',
        'doc', 'docx' => 'fa-file-word',
        'xls', 'xlsx' => 'fa-file-excel',
        'txt' => 'fa-file-lines',
        default => 'fa-file'
    };
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF verification
        if (!csrf_verify($_POST['_csrf'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please refresh the page and try again.');
        }
        
        $description = trim($_POST['description'] ?? '');
        
        // Check if file was uploaded
        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception('Please select a file to upload');
        }
        
        // Check for upload errors
        switch ($_FILES['attachment']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File exceeds maximum size limit of 1MB');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('File was only partially uploaded');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception('Temporary folder missing');
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception('Failed to write file to disk');
            case UPLOAD_ERR_EXTENSION:
                throw new Exception('File upload stopped by extension');
            default:
                throw new Exception('Unknown upload error (code: ' . $_FILES['attachment']['error'] . ')');
        }
        
        $file = $_FILES['attachment'];
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $original_name = sanitizeFilename($file['name']);
        
        // Validate file name is not empty after sanitization
        if (empty($original_name)) {
            throw new Exception('Invalid file name');
        }
        
        // Get file extension
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('Only PDF, JPG, JPEG, and PNG files are allowed. Received: ' . $file_ext);
        }
        
        // Validate file size
        if ($file_size > $max_file_size) {
            throw new Exception('File size must be less than 1MB. Your file: ' . formatFileSize($file_size));
        }
        
        // Additional security: verify image files are actually images
        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            $image_info = getimagesize($file_tmp);
            if ($image_info === false) {
                throw new Exception('Invalid image file');
            }
        }
        
        // Generate unique filename
        $timestamp = time();
        $unique_id = uniqid();
        $new_filename = $unique_id . '_' . $timestamp . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        // Check if file already exists (shouldn't with uniqid, but just in case)
        if (file_exists($upload_path)) {
            $new_filename = $unique_id . '_' . $timestamp . '_' . rand(1000, 9999) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file_tmp, $upload_path)) {
            throw new Exception('Failed to move uploaded file. Check directory permissions.');
        }
        
        // Set correct file permissions
        chmod($upload_path, 0644);
        
        // Get mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $upload_path);
        finfo_close($finfo);
        
        // CORRECT RELATIVE PATH - no 'public' in the path
        $relative_path = 'uploads/patient_' . $patient_id . '/' . $new_filename;
        
        // Check if table exists
        $table_check = $pdo->query("SHOW TABLES LIKE 'patient_attachments'");
        if ($table_check->rowCount() == 0) {
            throw new Exception('Attachments table does not exist. Please run the database setup first.');
        }
        
        // Get current user ID
        $current_user = user();
        $user_id = $current_user['id'] ?? null;
        
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO patient_attachments 
            (patient_id, file_name, original_name, file_path, file_size, file_type, mime_type, description, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $patient_id,
            $new_filename,
            $original_name,
            $relative_path,
            $file_size,
            $file_ext,
            $mime_type,
            $description,
            $user_id
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Database error: ' . ($errorInfo[2] ?? 'Unknown error'));
        }
        
        $attach_id = $pdo->lastInsertId();
        
        // Audit log
        if (function_exists('audit')) {
            audit($pdo, 'document.upload', 'patient_attachments', $attach_id, [
                'patient_id' => $patient_id,
                'file_name' => $original_name,
                'file_size' => $file_size,
                'file_type' => $file_ext
            ]);
        }
        
        $_SESSION['success'] = 'File uploaded successfully!';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Upload error for patient {$patient_id}: " . $e->getMessage());
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
    --red: #e74c3c;
    --orange: #f39c12;
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
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.alert-success {
    background-color: #d4edda;
    border-left: 4px solid var(--green);
    color: #155724;
}
.alert-danger {
    background-color: #f8d7da;
    border-left: 4px solid var(--red);
    color: #721c24;
}
.alert i {
    font-size: 1.3rem;
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
.form-control::placeholder {
    color: var(--black);
    opacity: 0.5;
    font-style: italic;
}

/* File Input Styling */
.form-control[type="file"] {
    padding: 8px 12px;
    cursor: pointer;
}
.form-control[type="file"]::file-selector-button {
    background-color: var(--blue);
    color: var(--white);
    border: none;
    border-radius: 4px;
    padding: 8px 16px;
    margin-right: 16px;
    font-family: Cambria, serif;
    cursor: pointer;
    transition: background-color 0.2s;
}
.form-control[type="file"]::file-selector-button:hover {
    background-color: #357ABD;
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
}
.btn-primary:hover:not(:disabled) {
    background-color: #357ABD;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(74,144,226,0.2);
}
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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

/* File Info Box */
.file-info {
    background-color: var(--ash);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
}
.file-info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    padding: 5px 0;
}
.file-info-item:last-child {
    margin-bottom: 0;
}
.file-info-item i {
    color: var(--blue);
    width: 24px;
    font-size: 1.2rem;
}
.file-info-item span {
    color: var(--black);
    font-size: 0.95rem;
}
.file-info-item strong {
    color: var(--blue);
    font-weight: 600;
}

/* File Preview */
.file-preview {
    margin-top: 20px;
    padding: 15px;
    background-color: var(--ash);
    border-radius: 8px;
    display: none;
}
.file-preview.show {
    display: block;
}
.file-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.file-preview .file-name {
    margin-top: 10px;
    font-size: 0.9rem;
    color: var(--black);
    word-break: break-all;
}

/* Loading Spinner */
.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid var(--white);
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .file-info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h6><i class="fa-solid fa-file-upload"></i>Upload Document</h6>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Patients</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/profile&id=<?=$patient_id?>">Profile</a></li>
                    <li class="breadcrumb-item active">Upload Document</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Patient Info Badge -->
    <div class="patient-badge">
        <i class="fa-solid fa-user"></i>
        <strong>Patient:</strong> <?=htmlspecialchars($pat['name'])?> (ID: <?=$pat['id']?>)
    </div>

    <!-- File Info Card -->
    <div class="file-info">
        <div class="file-info-item">
            <i class="fa-solid fa-file-circle-check"></i>
            <span><strong>Allowed formats:</strong> PDF, JPG, JPEG, PNG</span>
        </div>
        <div class="file-info-item">
            <i class="fa-solid fa-weight-scale"></i>
            <span><strong>Maximum size:</strong> 1MB</span>
        </div>
        <div class="file-info-item">
            <i class="fa-solid fa-shield-halved"></i>
            <span><strong>Secure storage:</strong> Files are stored securely</span>
        </div>
        <div class="file-info-item">
            <i class="fa-solid fa-folder-open"></i>
            <span><strong>Upload location:</strong> /uploads/patient_<?=$patient_id?>/</span>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($error): ?>
    <div class="alert alert-danger" id="errorAlert">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" id="successAlert">
        <i class="fa-solid fa-circle-check"></i>
        <span><?= $_SESSION['success']; unset($_SESSION['success']); ?></span>
    </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="section-card">
        <div class="section-header">
            <i class="fa-solid fa-cloud-upload-alt"></i>
            <h6>Upload New Document</h6>
        </div>
        <div class="section-body">
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                
                <div class="mb-3">
                    <label class="form-label">Select File <span class="text-danger">*</span></label>
                    <input type="file" name="attachment" id="fileInput" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small class="text-muted" id="fileHelp" style="display: block; margin-top: 5px;">Choose a file to upload</small>
                    <small class="text-danger" id="fileError" style="display: none;"></small>
                </div>
                
                <!-- File Preview -->
                <div class="file-preview" id="filePreview">
                    <div id="previewContent"></div>
                    <div class="file-name" id="fileName"></div>
                    <div class="file-name" id="fileSize"></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Enter a description for this document (e.g., 'Blood Test Report', 'Prescription', etc.)"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fa-solid fa-cloud-upload-alt"></i> Upload File
                    </button>
                    <a href="?r=patients/profile&id=<?= $patient_id ?>" class="btn-secondary">
                        <i class="fa-solid fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Client-side file validation and preview
document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const file = this.files[0];
    const maxSize = 1 * 1024 * 1024; // 1MB
    const fileHelp = document.getElementById('fileHelp');
    const fileError = document.getElementById('fileError');
    const submitBtn = document.getElementById('submitBtn');
    const filePreview = document.getElementById('filePreview');
    const previewContent = document.getElementById('previewContent');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    // Reset
    fileHelp.style.display = 'block';
    fileError.style.display = 'none';
    fileError.innerHTML = '';
    submitBtn.disabled = false;
    filePreview.classList.remove('show');
    
    if (file) {
        fileHelp.style.display = 'none';
        
        // Check file size
        if (file.size > maxSize) {
            fileError.style.display = 'block';
            fileError.innerHTML = '❌ File size exceeds 1MB limit. Your file: ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
            submitBtn.disabled = true;
            return;
        }
        
        // Check file type
        const allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
        const fileExt = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(fileExt)) {
            fileError.style.display = 'block';
            fileError.innerHTML = '❌ Only PDF, JPG, JPEG, and PNG files are allowed. Received: .' + fileExt;
            submitBtn.disabled = true;
            return;
        }
        
        // Show file preview for images
        if (['jpg', 'jpeg', 'png'].includes(fileExt)) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewContent.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                filePreview.classList.add('show');
            };
            reader.readAsDataURL(file);
        } else if (fileExt === 'pdf') {
            previewContent.innerHTML = '<i class="fa-regular fa-file-pdf" style="font-size: 48px; color: var(--blue);"></i>';
            filePreview.classList.add('show');
        }
        
        // Display file name and size
        fileName.innerHTML = '📄 ' + file.name;
        fileSize.innerHTML = '📦 ' + (file.size / 1024).toFixed(1) + ' KB';
    }
});

// Form submission with loading state
document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    
    submitBtn.innerHTML = '<span class="spinner"></span> Uploading...';
    submitBtn.disabled = true;
});

// Auto-hide success message after 3 seconds
<?php if (isset($_SESSION['success'])): ?>
setTimeout(function() {
    const alert = document.getElementById('successAlert');
    if (alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }
}, 3000);
<?php endif; ?>
</script>

<?php include __DIR__.'/../../templates/footer.php'; ?>