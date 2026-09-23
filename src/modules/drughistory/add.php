
<?php
// modules/drughistory/add.php

// Resolve app root: .../pmrms/src
$appRoot = dirname(__DIR__, 2);

// Core includes
require_once $appRoot . '/includes/db.php';
require_once $appRoot . '/includes/auth.php';
require_once $appRoot . '/includes/helpers.php';

// Start session for CSRF
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Expect: ?r=modules/drughistory/add&patient_id=#
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

/**
 * Helpers
 */
function null_if_empty(string $v): ?string {
  $v = trim($v);
  return ($v === '') ? null : $v;
}

function is_valid_ymd(?string $date): bool {
  if ($date === null || $date === '') return true; // optional
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
  [$y, $m, $d] = array_map('intval', explode('-', $date));
  return checkdate($m, $d, $y);
}

// Will be used to repopulate JS list if POST fails
$old_rows_json = '[]';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF verify
  $posted_token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $posted_token)) {
    $errors[] = 'Invalid request token. Please try again.';
  }

  $drugs_json = $_POST['drugs_json'] ?? '';
  $rows = json_decode($drugs_json, true);

  if (!is_array($rows)) {
    $errors[] = 'Invalid data submitted. Please add drugs again.';
    $rows = [];
  }

  // Limit max rows to avoid abuse
  $MAX_ROWS = 200;
  if (count($rows) > $MAX_ROWS) {
    $errors[] = "Too many rows submitted. Maximum allowed is $MAX_ROWS.";
  }

  // Validate each row
  $clean_rows = [];
  foreach ($rows as $i => $r) {
    // Normalize array keys and strings
    $drug_name       = isset($r['drug_name']) ? trim((string)$r['drug_name']) : '';
    $indication      = isset($r['indication']) ? trim((string)$r['indication']) : '';
    $start_date      = isset($r['start_date']) ? trim((string)$r['start_date']) : '';
    $stop_date       = isset($r['stop_date']) ? trim((string)$r['stop_date']) : '';
    $max_dose        = isset($r['max_dose']) ? trim((string)$r['max_dose']) : '';
    $route           = isset($r['route']) ? trim((string)$r['route']) : '';
    $response        = isset($r['response']) ? trim((string)$r['response']) : '';
    $adverse_effects = isset($r['adverse_effects']) ? trim((string)$r['adverse_effects']) : '';

    // Required
    if ($drug_name === '') {
      $errors[] = "Row ".($i+1).": Drug name is required.";
    }

    // Date validation (optional but if present must be correct format & calendar date)
    if (!is_valid_ymd($start_date)) {
      $errors[] = "Row ".($i+1).": Start Date must be in valid YYYY-MM-DD format.";
    }
    if (!is_valid_ymd($stop_date)) {
      $errors[] = "Row ".($i+1).": Stop Date must be in valid YYYY-MM-DD format.";
    }

    // Truncate overly long fields to prevent DB issues (adjust lengths to your schema if needed)
    $truncate = function (?string $s, int $len) {
      if ($s === null) return null;
      return mb_substr($s, 0, $len);
    };

    $clean_rows[] = [
      'drug_name'       => $truncate($drug_name, 255),
      'indication'      => $truncate(null_if_empty($indication), 255),
      'start_date'      => null_if_empty($start_date),
      'stop_date'       => null_if_empty($stop_date),
      'max_dose'        => $truncate(null_if_empty($max_dose), 100),
      'route'           => $truncate(null_if_empty($route), 50),
      'response'        => $truncate(null_if_empty($response), 100),
      'adverse_effects' => $truncate(null_if_empty($adverse_effects), 255),
    ];
  }

  if (empty($rows)) {
    $errors[] = 'Please add at least one drug to the list.';
  }

  // If no errors, insert all rows as one transaction
  if (!$errors) {
    try {
      $pdo->beginTransaction();
      $ins = $pdo->prepare("
        INSERT INTO drug_histories
          (patient_id, drug_name, indication, start_date, stop_date, max_dose, route, response, adverse_effects, created_at)
        VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
      ");

      foreach ($clean_rows as $cr) {
        $ins->execute([
          $patient_id,
          $cr['drug_name'],
          $cr['indication'],
          $cr['start_date'],
          $cr['stop_date'],
          $cr['max_dose'],
          $cr['route'],
          $cr['response'],
          $cr['adverse_effects'],
        ]);
      }

      $pdo->commit();

      // Rotate CSRF and redirect back to Full Profile
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      header("Location: ?r=patients/profile&id=" . $patient_id);
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $errors[] = 'Failed to save drug history. Please try again.';
      // Optionally log $e->getMessage()
    }
  }

  // Keep user-entered rows to repopulate JS if there were errors
  $old_rows_json = json_encode($rows, JSON_UNESCAPED_UNICODE);
}

// Include header (robust path)
include $appRoot . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="mb-0"><i class="fa-solid fa-capsules"></i> Add Drug History (<?=htmlspecialchars($patient['name'])?>)</h5>
  <div>
    <a href="?r=profile&id=<?=$patient_id?>
      <i class="fa-solid fa-rotate-left"></i> Back to Full Profile
    </a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body small" id="addDrugPage" data-old-rows='<?=htmlspecialchars($old_rows_json, ENT_QUOTES)?>'>
    <?php if ($errors): ?>
      <div class="alert alert-danger py-2">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?=htmlspecialchars($e)?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate id="drugsForm">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
      <input type="hidden" name="drugs_json" id="drugs_json" value="[]">

      <!-- Row builder -->
      <div class="border rounded p-3 mb-3 bg-light">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Drug Name <span class="text-danger">*</span></label>
            <input type="text" id="f_drug_name" class="form-control form-control-sm" placeholder="e.g., Azathioprine">
          </div>
          <div class="col-md-4">
            <label class="form-label">Indication</label>
            <input type="text" id="f_indication" class="form-control form-control-sm">
          </div>

          <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" id="f_start_date" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label">Stop Date</label>
            <input type="date" id="f_stop_date" class="form-control form-control-sm">
          </div>

          <div class="col-md-3">
            <label class="form-label">Max Dose</label>
            <input type="text" id="f_max_dose" class="form-control form-control-sm" placeholder="e.g., 4.8 g/day">
          </div>
          <div class="col-md-3">
            <label class="form-label">Route</label>
            <input type="text" id="f_route" class="form-control form-control-sm" placeholder="PO/IV/SC/etc.">
          </div>
          <div class="col-md-3">
            <label class="form-label">Response</label>
            <input type="text" id="f_response" class="form-control form-control-sm" placeholder="Good/Partial/None">
          </div>
          <div class="col-md-12">
            <label class="form-label">Adverse Effects</label>
            <input type="text" id="f_adverse_effects" class="form-control form-control-sm">
          </div>

          <div class="col-12">
            <button type="button" id="btnAdd" class="btn btn-sm btn-success">
              <i class="fa-solid fa-plus"></i> Add to List
            </button>
          </div>
        </div>
      </div>

      <!-- The table preview -->
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-3">
          <thead class="table-light">
            <tr>
              <th style="width:18%">Drug Name</th>
              <th style="width:16%">Indication</th>
              <th style="width:10%">Start</th>
              <th style="width:10%">Stop</th>
              <th style="width:10%">Max Dose</th>
              <th style="width:8%">Route</th>
              <th style="width:10%">Response</th>
              <th>Adverse Effects</th>
              <th style="width:6%">Actions</th>
            </tr>
          </thead>
          <tbody id="listBody">
            <!-- rows rendered by JS -->
          </tbody>
        </table>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" id="btnSaveAll" class="btn btn-primary btn-sm" disabled>
          <i class="fa-solid fa-floppy-disk"></i> Save All
        </button>
        <a href="?r=patients/profile&id=<?=$patient_id?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  const MAX_ROWS = 200;
  const $ = (sel) => document.querySelector(sel);

  const form = $('#drugsForm');
  const addPage = $('#addDrugPage');
  const listBody = $('#listBody');
  const hiddenJson = $('#drugs_json');
  const btnAdd = $('#btnAdd');
  const btnSaveAll = $('#btnSaveAll');

  // Input fields
  const f = {
    drug_name: $('#f_drug_name'),
    indication: $('#f_indication'),
    start_date: $('#f_start_date'),
    stop_date: $('#f_stop_date'),
    max_dose: $('#f_max_dose'),
    route: $('#f_route'),
    response: $('#f_response'),
    adverse_effects: $('#f_adverse_effects')
  };

  let rows = [];

  // Load previous rows when validation failed server-side
  try {
    const oldRows = JSON.parse(addPage.getAttribute('data-old-rows') || '[]');
    if (Array.isArray(oldRows)) rows = oldRows;
  } catch(e) { /* ignore */ }

  function esc(s) {
    // Ensure string output safe for textContent
    return (s ?? '').toString();
  }

  function render() {
    listBody.innerHTML = '';
    rows.forEach((r, idx) => {
      const tr = document.createElement('tr');

      const cell = (txt) => {
        const td = document.createElement('td');
        td.textContent = esc(txt);
        return td;
      };

      tr.appendChild(cell(r.drug_name));
      tr.appendChild(cell(r.indication));
      tr.appendChild(cell(r.start_date));
      tr.appendChild(cell(r.stop_date));
      tr.appendChild(cell(r.max_dose));
      tr.appendChild(cell(r.route));
      tr.appendChild(cell(r.response));
      tr.appendChild(cell(r.adverse_effects));

      const tdAct = document.createElement('td');
      const btnDel = document.createElement('button');
      btnDel.type = 'button';
      btnDel.className = 'btn btn-sm btn-outline-danger';
      btnDel.innerHTML = '<i class="fa-solid fa-trash"></i>';
      btnDel.addEventListener('click', () => {
        rows.splice(idx, 1);
        render();
      });
      tdAct.appendChild(btnDel);
      tr.appendChild(tdAct);

      listBody.appendChild(tr);
    });

    btnSaveAll.disabled = rows.length === 0;
    hiddenJson.value = JSON.stringify(rows);
  }

  function isValidDateYMD(s) {
    if (!s) return true; // optional
    return /^\d{4}-\d{2}-\d{2}$/.test(s);
  }

  function addFromForm() {
    const r = {
      drug_name: f.drug_name.value.trim(),
      indication: f.indication.value.trim(),
      start_date: f.start_date.value.trim(),
      stop_date: f.stop_date.value.trim(),
      max_dose: f.max_dose.value.trim(),
      route: f.route.value.trim(),
      response: f.response.value.trim(),
      adverse_effects: f.adverse_effects.value.trim()
    };

    if (!r.drug_name) {
      alert('Drug name is required.');
      f.drug_name.focus();
      return;
    }
    if (!isValidDateYMD(r.start_date)) {
      alert('Start Date must be YYYY-MM-DD');
      f.start_date.focus();
      return;
    }
    if (!isValidDateYMD(r.stop_date)) {
      alert('Stop Date must be YYYY-MM-DD');
      f.stop_date.focus();
      return;
    }
    if (rows.length >= MAX_ROWS) {
      alert('Too many rows. Please save and continue.');
      return;
    }

    rows.push(r);
    // clear inputs for next entry
    Object.values(f).forEach(inp => inp.value = '');
    f.drug_name.focus();
    render();
  }

  btnAdd.addEventListener('click', addFromForm);

  // Keyboard UX: Enter in "Adverse Effects" adds row
  f.adverse_effects.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addFromForm();
    }
  });

  // Before submit, ensure rows JSON is bound
  form.addEventListener('submit', (e) => {
    if (rows.length === 0) {
      e.preventDefault();
      alert('Please add at least one drug to the list.');
      return;
    }
    hiddenJson.value = JSON.stringify(rows);
  });

  render();
})();
</script>

<?php
// Include footer (robust path)
include $appRoot . '/templates/footer.php';