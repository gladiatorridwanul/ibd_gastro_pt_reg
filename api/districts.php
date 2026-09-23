<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header
header('Content-Type: application/json');

// Load database connection - adjust path as needed
$possible_paths = [
    __DIR__ . '/../../src/includes/db.php',
    __DIR__ . '/../src/includes/db.php',
    __DIR__ . '/includes/db.php'
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    echo json_encode(['error' => 'Database connection file not found']);
    exit;
}

// Check if division_id is provided
if (isset($_GET['division_id']) && !empty($_GET['division_id'])) {
    $division_id = (int)$_GET['division_id'];
    
    try {
        // Query districts
        $stmt = $pdo->prepare("SELECT id, name FROM districts WHERE division_id = ? ORDER BY name");
        $stmt->execute([$division_id]);
        $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($districts);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No division_id provided']);
}