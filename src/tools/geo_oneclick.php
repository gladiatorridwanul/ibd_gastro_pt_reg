<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/audit.php';
if (!user() || user()['role_type']!=='Admin') { http_response_code(403); die('Access denied. Admin only.'); }
include __DIR__.'/../templates/header.php';

$RAW = [
  'divisions' => 'https://raw.githubusercontent.com/m-nahid/bangladesh-divisions-districts-thanas-database/refs/heads/main/divisions.csv',
  'districts' => 'https://raw.githubusercontent.com/m-nahid/bangladesh-divisions-districts-thanas-database/refs/heads/main/districts.csv',
  'thanas'    => 'https://raw.githubusercontent.com/m-nahid/bangladesh-divisions-districts-thanas-database/refs/heads/main/thanas.csv',
  'upazilas'  => 'https://raw.githubusercontent.com/m-nahid/bangladesh-divisions-districts-thanas-database/refs/heads/main/upazilas.csv',
];

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['fetch'])) {
  if (!csrf_verify($_POST['_csrf'] ?? '')) die('Invalid CSRF');
  $truncate = isset($_POST['truncate']);
  $use_upazila = isset($_POST['use_upazila']);

  function http_get_csv($url) {
    $ctx = stream_context_create(['http' => ['timeout' => 45, 'header' => "User-Agent: PMRMS/2.1\r\n"]]);
    $csv = @file_get_contents($url, false, $ctx);
    if ($csv===false) throw new Exception('Fetch failed: '.$url);
    $tmp = tmpfile(); fwrite($tmp, $csv); fseek($tmp, 0); return $tmp;
  }

  try {
    $pdo->beginTransaction();
    if ($truncate) {
      $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
      $pdo->exec('TRUNCATE thanas');
      $pdo->exec('TRUNCATE districts');
      $pdo->exec('TRUNCATE divisions');
      $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    // Divisions
    $divMap = []; $selDiv=$pdo->prepare('SELECT id FROM divisions WHERE name=?'); $insDiv=$pdo->prepare('INSERT INTO divisions(name) VALUES (?)');
    $fh = http_get_csv($RAW['divisions']); $header = fgetcsv($fh);
    while(($r=fgetcsv($fh))!==false){ $name=trim($r[1]??''); if(!$name) continue; $selDiv->execute([$name]); $id=$selDiv->fetchColumn(); if(!$id){$insDiv->execute([$name]); $id=$pdo->lastInsertId();} $divMap[$r[0]]=$id; }
    fclose($fh);

    // Districts
    $distMap=[]; $selDis=$pdo->prepare('SELECT id FROM districts WHERE division_id=? AND name=?'); $insDis=$pdo->prepare('INSERT INTO districts(division_id,name) VALUES (?,?)');
    $fh = http_get_csv($RAW['districts']); $header=fgetcsv($fh);
    while(($r=fgetcsv($fh))!==false){ $srcDiv=$r[1]??''; $name=trim($r[2]??''); if(!$name||empty($divMap[$srcDiv])) continue; $divId=$divMap[$srcDiv]; $selDis->execute([$divId,$name]); $id=$selDis->fetchColumn(); if(!$id){$insDis->execute([$divId,$name]); $id=$pdo->lastInsertId();} $distMap[$r[0]]=$id; }
    fclose($fh);

    // Thanas or Upazilas → store into thanas table
    $srcKey = $use_upazila ? 'upazilas' : 'thanas';
    $selTh=$pdo->prepare('SELECT id FROM thanas WHERE district_id=? AND name=?'); $insTh=$pdo->prepare('INSERT INTO thanas(district_id,name) VALUES (?,?)'); $added=0;
    $fh = http_get_csv($RAW[$srcKey]); $header=fgetcsv($fh);
    while(($r=fgetcsv($fh))!==false){ $srcDist=$r[1]??''; $name=trim($r[2]??''); if(!$name||empty($distMap[$srcDist])) continue; $distId=$distMap[$srcDist]; $selTh->execute([$distId,$name]); $id=$selTh->fetchColumn(); if(!$id){$insTh->execute([$distId,$name]); $added++;} }
    fclose($fh);

    $pdo->commit();
    $msg = "<div class='alert alert-success'>Imported successfully from <code>".$srcKey.".csv</code>. Added entries: ".$added.".</div>";
    audit($pdo,'tools.geo_oneclick','geo',$added,['src'=>$srcKey]);
  } catch (Exception $e) {
    $pdo->rollBack();
    $msg = "<div class='alert alert-danger'>".htmlspecialchars($e->getMessage())."</div>";
  }
}
?>
<h5><i class="fa-solid fa-cloud-arrow-down"></i> One‑Click Bangladesh Geo Import</h5>
<?=$msg?>
<div class="alert alert-info small">
  Fetches Division → District → Thana/Upazila from a public GitHub CSV dataset. Cross‑check with BBS/HDX COD‑AB when needed.
</div>
<form method="post" class="row g-3">
  <input type="hidden" name="_csrf" value="<?=htmlspecialchars(csrf_token())?>">
  <div class="col-md-5 d-flex align-items-center gap-2">
    <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="truncate"> Truncate existing geo tables before import</label>
  </div>
  <div class="col-md-4 d-flex align-items-center gap-2">
    <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="use_upazila"> Use Upazila list instead of Thana list</label>
  </div>
  <div class="col-md-3">
    <button name="fetch" value="1" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-down"></i> Fetch & Import</button>
  </div>
</form>
<p class="mt-2"><a class="btn btn-outline-secondary btn-sm" href="?r=tools/geo_import"><i class="fa-solid fa-database"></i> Manual CSV Importer</a></p>
<?php include __DIR__.'/../templates/footer.php'; ?>
