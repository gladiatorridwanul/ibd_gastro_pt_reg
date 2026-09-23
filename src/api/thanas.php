<?php
require_once __DIR__.'/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_GET['district_id']) || !is_numeric($_GET['district_id'])) {
    echo json_encode([]);
    exit;
}

$district_id = (int)$_GET['district_id'];
$stmt = $pdo->prepare("SELECT id, name FROM thanas WHERE district_id = ? ORDER BY name");
$stmt->execute([$district_id]);
$thanas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($thanas);