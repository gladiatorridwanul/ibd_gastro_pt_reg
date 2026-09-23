<?php
require_once __DIR__.'/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_GET['division_id']) || !is_numeric($_GET['division_id'])) {
    echo json_encode([]);
    exit;
}

$division_id = (int)$_GET['division_id'];
$stmt = $pdo->prepare("SELECT id, name FROM districts WHERE division_id = ? ORDER BY name");
$stmt->execute([$division_id]);
$districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($districts);