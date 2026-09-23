<?php
// db.php - Database connection file

// Define the correct config path - relative to this file
$configPath = __DIR__ . '/../../config/config.php';

// Check if config exists
if (!file_exists($configPath)) {
    // Try alternative path
    $configPath = __DIR__ . '/../config/config.php';
}

// If still not found, try absolute path
if (!file_exists($configPath)) {
    $configPath = '/home/ibdgastroliverbd/public_html/config/config.php';
}

// Final check
if (!file_exists($configPath)) {
    die('Configuration file not found. Please check config path.');
}

// Load configuration
$config = require $configPath;

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host={$config['database']['host']};dbname={$config['database']['name']};charset={$config['database']['charset']}",
        $config['database']['user'],
        $config['database']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Make config available globally
global $config;