<?php
/**
 * modules/followup/delete.php - Delete Follow-up Record
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

if (!has_permission($pdo, 'button.module.followup.delete')) {
    $_SESSION['error'] = 'You do not have permission to delete follow-up records';
    header('Location: ?r=patients/profile&id=' . $patient_id);
    exit;
}

// Get parameters
$id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

// Validate input
if ($id <= 0 || $patient_id <= 0) {
    $_SESSION['error'] = "Invalid request parameters";
    header('Location: ?r=patients/manage');
    exit;
}

try {
    // Verify the record exists and belongs to the patient
    $check = $pdo->prepare("SELECT id, followup_at FROM followups WHERE id = ? AND patient_id = ?");
    $check->execute([$id, $patient_id]);
    $record = $check->fetch();

    if (!$record) {
        $_SESSION['error'] = "Follow-up record not found or does not belong to this patient";
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
    }

    // Delete the record
    $stmt = $pdo->prepare("DELETE FROM followups WHERE id = ? AND patient_id = ?");
    $result = $stmt->execute([$id, $patient_id]);

    if ($result && $stmt->rowCount() > 0) {
        // Audit the deletion with detailed information
        if (function_exists('audit')) {
            audit($pdo, 'followup.delete', 'followups', $id, [
                'patient_id' => $patient_id,
                'followup_date' => $record['followup_at'],
                'deleted_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        $_SESSION['success'] = "Follow-up record deleted successfully";
    } else {
        $_SESSION['error'] = "Failed to delete follow-up record";
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    error_log("Delete error in followup module: " . $e->getMessage());
}

// Redirect back to patient profile
header('Location: ?r=patients/profile&id=' . $patient_id);
exit;
?>