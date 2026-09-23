<?php
/**
 * vaccine/delete.php - Delete vaccine record
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_login();

$vaccine_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// Validate vaccine ID
if ($vaccine_id <= 0) {
    $_SESSION['error'] = "Invalid vaccine record ID.";
    header("Location: ?r=modules/vaccine/index&patient_id={$patient_id}");
    exit;
}

// Get vaccine record before deletion for audit and reference
$stmt = $pdo->prepare("SELECT * FROM patient_vaccines WHERE id = ?");
$stmt->execute([$vaccine_id]);
$vaccine = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vaccine) {
    $_SESSION['error'] = "Vaccine record not found.";
    header("Location: ?r=modules/vaccine/index&patient_id={$patient_id}");
    exit;
}

// If patient_id not provided in URL, get it from the vaccine record
if ($patient_id <= 0) {
    $patient_id = $vaccine['patient_id'];
}

// Check permission - verify user has delete permission for vaccine module
if (!has_permission($pdo, 'button.module.vaccine.delete')) {
    $_SESSION['error'] = "You do not have permission to delete vaccine records.";
    header("Location: ?r=modules/vaccine/index&patient_id={$patient_id}");
    exit;
}

// Start transaction for safe deletion
$pdo->beginTransaction();

try {
    // Delete reminders associated with this vaccine first (foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM vaccine_reminders WHERE vaccine_id = ?");
    $reminder_result = $stmt->execute([$vaccine_id]);
    
    // Delete the vaccine record
    $stmt = $pdo->prepare("DELETE FROM patient_vaccines WHERE id = ?");
    $vaccine_result = $stmt->execute([$vaccine_id]);
    
    if ($vaccine_result) {
        // Commit transaction
        $pdo->commit();
        
        // Audit log the deletion
        if (function_exists('audit')) {
            audit($pdo, 'vaccine.delete', 'patient_vaccines', $vaccine_id, array(
                'patient_id' => $patient_id,
                'patient_name' => $vaccine['patient_id'], // Name will be fetched separately if needed
                'vaccine_name' => $vaccine['vaccine_name'],
                'dose_schedule' => $vaccine['dose_schedule'],
                'dose_given_date' => $vaccine['dose_given_date'],
                'next_dose_date' => $vaccine['next_dose_date'],
                'batch_no' => $vaccine['batch_no'],
                'batch_id' => $vaccine['batch_id'],
                'status' => $vaccine['status'],
                'deleted_by' => $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null
            ));
        }
        
        $_SESSION['success'] = "Vaccine record deleted successfully.";
    } else {
        throw new Exception("Failed to delete vaccine record.");
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to delete vaccine record: " . $e->getMessage();
}

// Redirect back to vaccine list
header("Location: ?r=modules/vaccine/index&patient_id={$patient_id}");
exit;
?>