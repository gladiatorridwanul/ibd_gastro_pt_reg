<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
<?php
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

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['ok'=>false,'msg'=>'Method not allowed']); exit; }
if (!function_exists('csrf_verify')) { echo json_encode(['ok'=>false,'msg'=>'CSRF not available']); exit; }
if (!csrf_verify($_POST['_csrf'] ?? '')) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Invalid CSRF']); exit; }
$name = trim($_POST['name'] ?? '');
if ($name==='') { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Drug name required']); exit; }
ensure_drugs_table($pdo);
try {
  $stmt = $pdo->prepare("INSERT INTO drugs(name,is_active) VALUES(?,1)");
  $stmt->execute([$name]);
} catch (Exception $e) {
  // ignore duplicate errors
}
// Return refreshed <option> list
ob_start();
try {
  $rows = $pdo->query("SELECT name FROM drugs WHERE is_active=1 ORDER BY name")->fetchAll();
  echo '<option value="">Select Drug</option>';
  foreach($rows as $r){
    $n = htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8');
    $sel = ($n===$name)?' selected':'';
    echo "<option value="$n"$sel>$n</option>";
  }
} catch (Exception $e) { echo '<option value="">(no drugs)</option>'; }
$html = ob_get_clean();
echo json_encode(['ok'=>true,'options'=>$html,'selected'=>$name]);
