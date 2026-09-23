<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header to return JSON
header('Content-Type: application/json');

// Load database connection
require_once dirname(__DIR__) . '/includes/db.php';

// Check if division_id is provided
if (isset($_GET['division_id']) && !empty($_GET['division_id'])) {
    $division_id = (int)$_GET['division_id'];
    
    try {
        // Query districts for the selected division
        $stmt = $pdo->prepare("SELECT id, name FROM districts WHERE division_id = ? ORDER BY name");
        $stmt->execute([$division_id]);
        $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return districts as JSON
        echo json_encode($districts);
    } catch (PDOException $e) {
        // Return error message
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    // Return empty array if no division_id provided
    echo json_encode([]);
}