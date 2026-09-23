<?php
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/audit.php';

function ensure_drugs_table($pdo){
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS drugs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(255) NOT NULL UNIQUE,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  } catch (Exception $e) { /* ignore */ }
}

ensure_drugs_table($pdo);

$pid = (int)($_GET['patient_id'] ?? 0);
$backUrl = $pid > 0 ? ('?r=modules/treatment/add&patient_id='.$pid) : '?r=patients/manage';
$backText = $pid > 0 ? 'Back to Treatment Add' : 'Back to Patients';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_verify($_POST['_csrf'] ?? '')) die('Invalid CSRF');
  $name = trim($_POST['name'] ?? '');
  $pid = (int)($_POST['patient_id'] ?? $pid);
  if ($name!=='') {
    try { $pdo->prepare("INSERT INTO drugs(name,is_active) VALUES(?,1)")->execute([$name]); audit($pdo,'drugs.add','drugs',null,['name'=>$name]); }
    catch(Exception $e) { /* ignore duplicates */ }
  }
  header('Location: ?r=modules/drugs/index&patient_id='.$pid); exit;
}

$rows = [];
try { $rows = $pdo->query("SELECT id,name,is_active,created_at FROM drugs ORDER BY is_active DESC, name")->fetchAll(); }
catch (Exception $e) { ensure_drugs_table($pdo); $rows = $pdo->query("SELECT id,name,is_active,created_at FROM drugs ORDER BY is_active DESC, name")->fetchAll(); }

include __DIR__.'/../../templates/header.php';
?>
<h6 class="mb-3"><i class="fa-solid fa-capsules"></i> Manage Drug List</h6>
<div class="card shadow-sm mb-3"><div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="_csrf" value="<?=htmlspecialchars(csrf_token())?>">
    <input type="hidden" name="patient_id" value="<?= (int)$pid ?>">
    <div class="col-md-6"><input name="name" class="form-control" placeholder="New drug name" required></div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Drug</button>
      <a class="btn btn-outline-secondary" href="<?= $backUrl ?>"><?=$backText?></a>
    </div>
  </form>
</div></div>
<div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>#</th><th>Name</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
  <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= $r['is_active']?'Active':'Inactive' ?></td>
        <td><span class="small text-muted"><?= htmlspecialchars($r['created_at']) ?></span></td>
        <td class="d-flex gap-2">
          <?php if ($r['is_active']): ?>
            <a class="btn btn-sm btn-outline-danger" onclick="return confirm('Deactivate this drug?')" href="?r=modules/drugs/delete&id=<?= (int)$r['id'] ?>&patient_id=<?= (int)$pid ?>">Deactivate</a>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-success" href="?r=modules/drugs/restore&id=<?= (int)$r['id'] ?>&patient_id=<?= (int)$pid ?>">Activate</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table></div>
<?php include __DIR__.'/../../templates/footer.php'; ?>
