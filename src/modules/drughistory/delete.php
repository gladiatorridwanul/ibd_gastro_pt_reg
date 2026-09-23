
<?php
// modules/drughistory/delete.php

// Resolve app root: .../pmrms/src
$appRoot = dirname(__DIR__, 2);

// Core includes
require_once $appRoot . '/includes/db.php';
require_once $appRoot . '/includes/auth.php';
require_once $appRoot . '/includes/helpers.php';

// Expect: ?r=modules/drughistory/delete&id=#&patient_id=#
$id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

if (!$id || !$patient_id) { http_response_code(400); die('Bad request'); }

// Ensure record belongs to patient
$stmt = $pdo->prepare("SELECT id FROM drug_histories WHERE id=? AND patient_id=?");
$stmt->execute([$id, $patient_id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); die('Record not found'); }

// Delete
$pdo->prepare("DELETE FROM drug_histories WHERE id=?")->execute([$id]);

header("Location: ?r=patients/full&id=" . $patient_id);
exit;
