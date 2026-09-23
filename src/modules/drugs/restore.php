<?php
require_once __DIR__.'/../../includes/db.php'; require_once __DIR__.'/../../includes/auth.php'; require_once __DIR__.'/../../includes/audit.php';
$id=(int)($_GET['id']??0); $pid=(int)($_GET['patient_id']??0); if($id<=0){http_response_code(400);die('Invalid request');}
$pdo->prepare("UPDATE drugs SET is_active=1 WHERE id=? LIMIT 1")->execute([$id]);
audit($pdo,'drugs.activate','drugs',$id);
header('Location: ?r=modules/drugs/index'+($pid>0?'&patient_id=':'') . $pid);
