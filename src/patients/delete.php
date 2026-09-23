<?php
require __DIR__.'/../includes/db.php'; 
require __DIR__.'/../includes/auth.php'; 
require __DIR__.'/../includes/permissions.php';
require __DIR__.'/../includes/audit.php';
require __DIR__.'/../includes/helpers.php';

// Check permission
require_permission($pdo, 'button.patient.delete');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'Invalid patient ID';
    header('Location: ?r=patients/manage');
    exit;
}

// Get patient name for audit log before deletion
$stmt = $pdo->prepare("SELECT name FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = 'Patient not found';
    header('Location: ?r=patients/manage');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete related records first (if any - though they should cascade)
    $tables = ['complaints', 'current_histories', 'drug_histories', 'followups', 
               'ibd_diagnoses', 'investigations', 'pregnancies', 'socioeconomic_histories', 
               'treatments'];
    
    foreach ($tables as $table) {
        // Check if table exists before deleting
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() > 0) {
            $pdo->prepare("DELETE FROM $table WHERE patient_id = ?")->execute([$id]);
        }
    }
    
    // Delete the patient
    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
    $stmt->execute([$id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Audit log
    audit($pdo, 'patient.delete', 'patients', $id, ['name' => $patient['name']]);
    
    $_SESSION['success'] = 'Patient deleted successfully';
    
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    error_log("Error deleting patient: " . $e->getMessage());
    $_SESSION['error'] = 'Error deleting patient: ' . $e->getMessage();
}

header('Location: ?r=patients/manage');
exit;