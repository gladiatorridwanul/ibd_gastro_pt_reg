<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header to return JSON
header('Content-Type: application/json');

// Load database connection
require_once dirname(__DIR__) . '/includes/db.php';

// Check if district_id is provided
if (isset($_GET['district_id']) && !empty($_GET['district_id'])) {
    $district_id = (int)$_GET['district_id'];
    
    try {
        // Query upazilas for the selected district
        $stmt = $pdo->prepare("SELECT id, name FROM upazilas WHERE district_id = ? ORDER BY name");
        $stmt->execute([$district_id]);
        $upazilas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return upazilas as JSON
        echo json_encode($upazilas);
    } catch (PDOException $e) {
        // Return error message
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    // Return empty array if no district_id provided
    echo json_encode([]);
}