<?php
/**
 * Universal Delete Handler for Clinical Modules
 * Deletes records from various clinical modules and redirects to patient profile
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
require_once __DIR__.'/../../includes/permissions.php';
require_once __DIR__.'/../../includes/helpers.php';

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

// Get the current module name from directory
$module = basename(dirname(__FILE__));

// Get parameters
$id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

// Validate input
if ($id <= 0 || $patient_id <= 0) {
    $_SESSION['error'] = 'Invalid request parameters';
    header('Location: ?r=patients/manage');
    exit;
}

// Map module names to actual table names and permission codes
$module_config = [
    'ibd' => [
        'table' => 'ibd_diagnoses',
        'permission' => 'button.module.ibd.delete',
        'label' => 'IBD diagnosis'
    ],
    'complaints' => [
        'table' => 'complaints',
        'permission' => 'button.module.complaints.delete',
        'label' => 'complaint'
    ],
    'socio' => [
        'table' => 'socioeconomic_histories',
        'permission' => 'button.module.socio.delete',
        'label' => 'socioeconomic record'
    ],
    'pregnancy' => [
        'table' => 'pregnancies',
        'permission' => 'button.module.pregnancy.delete',
        'label' => 'pregnancy record'
    ],
    'treatment' => [
        'table' => 'treatments',
        'permission' => 'button.module.treatment.delete',
        'label' => 'treatment record'
    ],
    'investigations' => [
        'table' => 'investigations',
        'permission' => 'button.module.investigations.delete',
        'label' => 'investigation record'
    ],
    'followup' => [
        'table' => 'followups',
        'permission' => 'button.module.followup.delete',
        'label' => 'follow-up record'
    ],
    'documents' => [
        'table' => 'patient_attachments',
        'permission' => 'button.module.documents.delete',
        'label' => 'document'
    ],
    'currenthistory' => [
        'table' => 'current_histories',
        'permission' => 'button.module.currenthistory.delete',
        'label' => 'current history record'
    ],
    'drughistory' => [
        'table' => 'drug_histories',
        'permission' => 'button.module.drughistory.delete',
        'label' => 'drug history record'
    ]
];

// Check if module exists in config
if (!isset($module_config[$module])) {
    $_SESSION['error'] = 'Invalid module: ' . htmlspecialchars($module);
    header('Location: ?r=patients/profile&id=' . $patient_id);
    exit;
}

$config = $module_config[$module];
$table = $config['table'];
$label = $config['label'];

// Check module-specific permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, $config['permission'])) {
    $_SESSION['error'] = 'You do not have permission to delete ' . $label . ' records';
    header('Location: ?r=patients/profile&id=' . $patient_id);
    exit;
}

try {
    // Verify table exists (optional safety check)
    $check_table = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($check_table->rowCount() == 0) {
        throw new Exception("Table '$table' does not exist");
    }

    // Verify the record exists and belongs to the patient
    $check = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? AND patient_id = ?");
    $check->execute([$id, $patient_id]);
    $record = $check->fetch();

    if (!$record) {
        $_SESSION['error'] = ucfirst($label) . ' not found or does not belong to this patient';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
    }

    // For documents, also delete the physical file
    if ($module === 'documents' && !empty($record['file_path'])) {
        $file_path = __DIR__ . '/../../../' . $record['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Delete the record
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ? AND patient_id = ?");
    $result = $stmt->execute([$id, $patient_id]);

    if ($result && $stmt->rowCount() > 0) {
        // Audit the deletion with relevant details
        $audit_details = [
            'patient_id' => $patient_id,
            'module' => $module,
            'deleted_at' => date('Y-m-d H:i:s')
        ];
        
        // Add module-specific details for better audit trail
        if ($module === 'followup' && isset($record['followup_at'])) {
            $audit_details['followup_date'] = $record['followup_at'];
        } elseif ($module === 'ibd' && isset($record['diagnosis'])) {
            $audit_details['diagnosis'] = $record['diagnosis'];
        } elseif ($module === 'documents' && isset($record['original_name'])) {
            $audit_details['file_name'] = $record['original_name'];
        } elseif ($module === 'treatment' && isset($record['drug_name'])) {
            $audit_details['drug_name'] = $record['drug_name'];
        } elseif ($module === 'complaints' && isset($record['abdominal_pain'])) {
            $audit_details['had_pain'] = $record['abdominal_pain'];
        }
        
        if (function_exists('audit')) {
            audit($pdo, $module . '.delete', $table, $id, $audit_details);
        }
        
        $_SESSION['success'] = ucfirst($label) . ' deleted successfully';
    } else {
        $_SESSION['error'] = 'Failed to delete ' . $label;
    }

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    error_log("Delete error in {$module}: " . $e->getMessage());
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    error_log("Delete error in {$module}: " . $e->getMessage());
}

// Redirect back to patient profile
header('Location: ?r=patients/profile&id=' . $patient_id);
exit;
?>