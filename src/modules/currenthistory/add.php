
<?php
// modules/currenthistory/add.php

// Resolve app root: .../pmrms/src
$appRoot = dirname(__DIR__, 2);

// Core includes
require_once $appRoot . '/includes/db.php';
require_once $appRoot . '/includes/auth.php';
require_once $appRoot . '/includes/helpers.php';

// Start session for CSRF
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Expect: ?r=modules/currenthistory/add&patient_id=#
$patient_id = (int)($_GET['patient_id'] ?? 0);

// Ensure patient exists
$stmt = $pdo->prepare("SELECT id, name FROM patients WHERE id=?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();
if (!$patient) { http_response_code(404); die('Patient not found'); }

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors = [];
$old = [
  'chief_complaint'     => '',
  'onset_date'          => '',
  'duration_text'       => '',
  'course'              => '',
  'aggravating_factors' => '',
  'relieving_factors'   => '',
  'associated_symptoms' => '',
  'red_flags'           => '',
  'general_condition'   => '',
  'notes'               => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF verify
  $posted_token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
    $errors[] = 'Invalid request token. Please try again.';
  }

  // Collect & trim
  $old['chief_complaint']     = trim($_POST['chief_complaint'] ?? '');
  $old['onset_date']          = trim($_POST['onset_date'] ?? '');
  $old['duration_text']       = trim($_POST['duration_text'] ?? '');
  $old['course']              = trim($_POST['course'] ?? '');
  $old['aggravating_factors'] = trim($_POST['aggravating_factors'] ?? '');
  $old['relieving_factors']   = trim($_POST['relieving_factors'] ?? '');
  $old['associated_symptoms'] = trim($_POST['associated_symptoms'] ?? '');
  $old['red_flags']           = trim($_POST['red_flags'] ?? '');
  $old['general_condition']   = trim($_POST['general_condition'] ?? '');
  $old['notes']               = trim($_POST['notes'] ?? '');

  // Validation
  if ($old['chief_complaint'] === '') {
    $errors[] = 'Chief complaint is required.';
  }
  if ($old['onset_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['onset_date'])) {
    $errors[] = 'Onset Date must be in YYYY-MM-DD format.';
  }

  // Persist
  if (!$errors) {
    $stmt = $pdo->prepare("
      INSERT INTO current_histories
      (patient_id, chief_complaint, onset_date, duration_text, course, aggravating_factors, relieving_factors,
       associated_symptoms, red_flags, general_condition, notes, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
      $patient_id,
      $old['chief_complaint'],
      $old['onset_date'] ?: null,
      $old['duration_text'] ?: null,
      $old['course'] ?: null,
      $old['aggravating_factors'] ?: null,
      $old['relieving_factors'] ?: null,
      $old['associated_symptoms'] ?: null,
      $old['red_flags'] ?: null,
      $old['general_condition'] ?: null,
      $old['notes'] ?: null,
    ]);

    // Rotate CSRF and redirect to Full Patient View (IMPORTANT: use '&', not '&amp;')
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: ?r=patients/profile&id=" . $patient_id);
    exit;
  }
}

// Include header (only after possible redirect above)
include $appRoot . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="mb-0"><i class="fa-solid fa-notes-medical"></i> Add Current History (<?=htmlspecialchars($patient['name'])?>)</h5>
  <div>
     <a href="?r=patients/profile&id=<?=$patient_id?>" class="btn btn-sm btn-outline-primary">
      <i class="fa-solid fa-rotate-left"></i> Back to Full Profile
    </a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body small">
    <?php if ($errors): ?>
      <div class="alert alert-danger py-2">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?=htmlspecialchars($e)?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Chief Complaint <span class="text-danger">*</span></label>
          <input type="text" name="chief_complaint" class="form-control form-control-sm" value="<?=htmlspecialchars($old['chief_complaint'])?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Onset Date</label>
          <input type="date" name="onset_date" class="form-control form-control-sm" value="<?=htmlspecialchars($old['onset_date'])?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Duration</label>
          <input type="text" name="duration_text" class="form-control form-control-sm" placeholder="e.g., 3 weeks" value="<?=htmlspecialchars($old['duration_text'])?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Course</label>
          <input type="text" name="course" class="form-control form-control-sm" placeholder="intermittent/progressive/etc." value="<?=htmlspecialchars($old['course'])?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Aggravating Factors</label>
          <input type="text" name="aggravating_factors" class="form-control form-control-sm" value="<?=htmlspecialchars($old['aggravating_factors'])?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Relieving Factors</label>
          <input type="text" name="relieving_factors" class="form-control form-control-sm" value="<?=htmlspecialchars($old['relieving_factors'])?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Associated Symptoms</label>
          <input type="text" name="associated_symptoms" class="form-control form-control-sm" placeholder="e.g., fever, weight loss" value="<?=htmlspecialchars($old['associated_symptoms'])?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Red Flags</label>
          <input type="text" name="red_flags" class="form-control form-control-sm" placeholder="e.g., bleeding, severe pain" value="<?=htmlspecialchars($old['red_flags'])?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">General Condition</label>
          <input type="text" name="general_condition" class="form-control form-control-sm" placeholder="e.g., stable, ill-looking" value="<?=htmlspecialchars($old['general_condition'])?>">
        </div>
        <div class="col-12">
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="4" class="form-control form-control-sm"><?=htmlspecialchars($old['notes'])?></textarea>
        </div>

        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Save</button>
          <a href="?r=patients/profile&id=<?=$patient_id?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>

<?php
// Include footer
include $appRoot . '/templates/footer.php';
