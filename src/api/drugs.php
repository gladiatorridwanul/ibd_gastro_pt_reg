<?php
require_once __DIR__.'/../includes/db.php';
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

header('Content-Type: text/html; charset=utf-8');
ensure_drugs_table($pdo);
try {
  $rows = $pdo->query("SELECT name FROM drugs WHERE is_active=1 ORDER BY name")->fetchAll();
  echo '<option value="">Select Drug</option>';
  foreach($rows as $r){
    $n = htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8');
    echo "<option value="$n">$n</option>";
  }
} catch (Exception $e) {
  echo '<option value="">(no drugs)</option>';
}
