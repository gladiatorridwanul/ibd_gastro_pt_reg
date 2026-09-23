<?php
require_once __DIR__.'/../includes/db.php'; require_once __DIR__.'/../includes/auth.php'; require_once __DIR__.'/../includes/audit.php';
if (!user() || user()['role_type']!=='Admin') { http_response_code(403); die('Access denied. Admin only.'); }
include __DIR__.'/../templates/header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv'])) {
  if (!csrf_verify($_POST['_csrf'] ?? '')) die('Invalid CSRF');
  $truncate = isset($_POST['truncate']);
  try {
    $pdo->beginTransaction();
    if ($truncate) {
      $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
      $pdo->exec('TRUNCATE thanas');
      $pdo->exec('TRUNCATE districts');
      $pdo->exec('TRUNCATE divisions');
      $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    $tmp = $_FILES['csv']['tmp_name'];
    $rows = 0; $divCount=0; $distCount=0; $thanaCount=0;
    if (($h = fopen($tmp,'r'))!==false) {
      $header = fgetcsv($h); if (!$header) throw new Exception('Empty CSV');
      foreach ($header as &$hh) { $hh = strtolower(trim($hh)); }
      $idxDiv = array_search('division', $header);
      $idxDist = array_search('district', $header);
      $idxThana = array_search('thana', $header);
      if ($idxDiv===false || $idxDist===false || $idxThana===false) throw new Exception('CSV must have headers: division,district,thana');

      $selDiv = $pdo->prepare('SELECT id FROM divisions WHERE name=?');
      $insDiv = $pdo->prepare('INSERT INTO divisions(name) VALUES (?)');
      $selDist = $pdo->prepare('SELECT id FROM districts WHERE division_id=? AND name=?');
      $insDist = $pdo->prepare('INSERT INTO districts(division_id,name) VALUES (?,?)');
      $selTh = $pdo->prepare('SELECT id FROM thanas WHERE district_id=? AND name=?');
      $insTh = $pdo->prepare('INSERT INTO thanas(district_id,name) VALUES (?,?)');

      while(($r = fgetcsv($h))!==false){
        $rows++;
        $div = trim($r[$idxDiv] ?? '');
        $dis = trim($r[$idxDist] ?? '');
        $tha = trim($r[$idxThana] ?? '');
        if ($div==='') continue;
        $selDiv->execute([$div]); $divId = $selDiv->fetchColumn();
        if (!$divId) { $insDiv->execute([$div]); $divId = $pdo->lastInsertId(); $divCount++; }
        if ($dis!=='') {
          $selDist->execute([$divId, $dis]); $distId = $selDist->fetchColumn();
          if (!$distId) { $insDist->execute([$divId,$dis]); $distId = $pdo->lastInsertId(); $distCount++; }
          if ($tha!=='') {
            $selTh->execute([$distId,$tha]); $thId = $selTh->fetchColumn();
            if (!$thId) { $insTh->execute([$distId,$tha]); $thanaCount++; }
          }
        }
      }
      fclose($h);
    }
    $pdo->commit();
    $msg = "<div class='alert alert-success'>Imported $rows rows. Added Divisions: $divCount, Districts: $distCount, Thanas: $thanaCount.</div>";
    audit($pdo,'tools.geo_import','geo',null,['rows'=>$rows,'divisions'=>$divCount,'districts'=>$distCount,'thanas'=>$thanaCount]);
  } catch (Exception $e) {
    $pdo->rollBack();
    $msg = "<div class='alert alert-danger'>Error: ".htmlspecialchars($e->getMessage())."</div>";
  }
}
?>
<h5><i class="fa-solid fa-database"></i> Geo Import: Divisions → Districts → Thanas</h5>
<?=$msg?>
<div class="alert alert-info small">Upload a CSV with headers: <code>division,district,thana</code>. You can download the template below.</div>
<form method="post" enctype="multipart/form-data" class="row g-3">
  <input type="hidden" name="_csrf" value="<?=htmlspecialchars(csrf_token())?>">
  <div class="col-md-6"><input type="file" name="csv" accept=".csv" class="form-control" required></div>
  <div class="col-md-3 d-flex align-items-center gap-2">
    <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="truncate"> Truncate existing geo tables</label>
  </div>
  <div class="col-md-3"><button class="btn btn-primary"><i class="fa-solid fa-upload"></i> Import</button></div>
</form>
<p class="mt-2"><a class="btn btn-outline-secondary btn-sm" href="<?=$base?>/assets/sample/bd_thanas_template.csv"><i class="fa-solid fa-download"></i> Download CSV template</a></p>
<?php include __DIR__.'/../templates/footer.php'; ?>
