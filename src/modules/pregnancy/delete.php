<?php
/**
 * Delete Handler for Pregnancy Module
 * Deletes a pregnancy record and redirects back to patient profile
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

// Get parameters
$id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

// Validate input
if ($id <= 0 || $patient_id <= 0) {
    $_SESSION['error'] = 'Invalid request parameters';
    header('Location: ?r=patients/manage');
    exit;
}

// Check permission - using correct pregnancy-specific permission code
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'button.module.pregnancy.delete')) {
    $_SESSION['error'] = 'You do not have permission to delete pregnancy records';
    header('Location: ?r=patients/profile&id=' . $patient_id);
    exit;
}

try {
    // First, verify the pregnancy record exists and belongs to the patient
    $check = $pdo->prepare("SELECT id FROM pregnancies WHERE id = ? AND patient_id = ?");
    $check->execute([$id, $patient_id]);
    $record = $check->fetch();

    if (!$record) {
        $_SESSION['error'] = 'Pregnancy record not found or does not belong to this patient';
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
    }

    // Delete the record
    $stmt = $pdo->prepare("DELETE FROM pregnancies WHERE id = ? AND patient_id = ?");
    $result = $stmt->execute([$id, $patient_id]);

    if ($result && $stmt->rowCount() > 0) {
        // Audit the deletion
        if (function_exists('audit')) {
            audit($pdo, 'pregnancy.delete', 'pregnancies', $id, [
                'patient_id' => $patient_id
            ]);
        }

        $_SESSION['success'] = 'Pregnancy record deleted successfully';
    } else {
        $_SESSION['error'] = 'Failed to delete pregnancy record';
    }

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    error_log("Delete error in pregnancy module: " . $e->getMessage());
}

// Always redirect back to patient profile
header('Location: ?r=patients/profile&id=' . $patient_id);
exit;
?>