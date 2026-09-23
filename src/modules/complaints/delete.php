<?php
/**
 * modules/complaints/delete.php - Delete Complaint Record
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

if (!has_permission($pdo, 'button.module.complaints.delete')) {
    $_SESSION['error'] = 'You do not have permission to delete complaint records';
    header('Location: ?r=patients/manage');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

if ($id <= 0 || $patient_id <= 0) { 
    $_SESSION['error'] = "Invalid request parameters";
    header('Location: ?r=patients/manage');
    exit;
}

try {
    // Verify the complaint exists and belongs to the patient
    $check = $pdo->prepare("SELECT id FROM complaints WHERE id = ? AND patient_id = ?");
    $check->execute([$id, $patient_id]);

    if (!$check->fetch()) {
        $_SESSION['error'] = "Complaint record not found";
        header('Location: ?r=patients/profile&id=' . $patient_id);
        exit;
    }

    // Delete the record
    $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = ? AND patient_id = ?");
    $result = $stmt->execute([$id, $patient_id]);

    if ($result && $stmt->rowCount() > 0) {
        if (function_exists('audit')) {
            audit($pdo, 'complaints.delete', 'complaints', $id, ['patient_id' => $patient_id]);
        }
        $_SESSION['success'] = "Complaint record deleted successfully";
    } else {
        $_SESSION['error'] = "Failed to delete complaint record";
    }

} catch (PDOException $e) {
    error_log("Error deleting complaint: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred";
}

header('Location: ?r=patients/profile&id=' . $patient_id);
exit;
?>