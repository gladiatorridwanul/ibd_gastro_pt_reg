<?php
/**
 * modules/documents/delete.php - Delete Patient Document
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

$id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

if ($id <= 0 || $patient_id <= 0) {
    $_SESSION['error'] = 'Invalid request parameters';
    header('Location: ?r=patients/manage');
    exit;
}

// Check permission
if (!has_permission($pdo, 'button.module.documents.delete')) {
    $_SESSION['error'] = 'You do not have permission to delete documents';
    header('Location: ?r=patients/profile&id=' . $patient_id);
    exit;
}

try {
    // Get file information before deletion
    $stmt = $pdo->prepare("SELECT * FROM patient_attachments WHERE id=? AND patient_id=?");
    $stmt->execute([$id, $patient_id]);
    $file = $stmt->fetch();

    if (!$file) {
        throw new Exception('File not found');
    }

    // Define file path
    $file_path = __DIR__ . '/../../../' . $file['file_path'];

    // Start transaction
    $pdo->beginTransaction();

    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM patient_attachments WHERE id=? AND patient_id=?");
    $stmt->execute([$id, $patient_id]);

    // Delete physical file if it exists
    if (file_exists($file_path)) {
        if (!unlink($file_path)) {
            throw new Exception('Failed to delete physical file');
        }
    }

    // Check if directory is empty, if yes, remove it
    $dir = dirname($file_path);
    if (is_dir($dir) && count(scandir($dir)) == 2) { // 2 = . and ..
        rmdir($dir);
    }

    // Commit transaction
    $pdo->commit();

    // Audit log
    if (function_exists('audit')) {
        audit($pdo, 'document.delete', 'patient_attachments', $id, [
            'patient_id' => $patient_id,
            'file_name' => $file['original_name']
        ]);
    }

    $_SESSION['success'] = 'File deleted successfully';

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = 'Error deleting file: ' . $e->getMessage();
    error_log("Delete error: " . $e->getMessage());
}

header('Location: ?r=patients/profile&id=' . $patient_id);
exit;
?>