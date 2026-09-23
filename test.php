<?php
echo "<h2>PMRMS Installation Test</h2>";

// Show current paths
echo "<h3>Server Information:</h3>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Check if .htaccess is working
echo "<h3>.htaccess Test:</h3>";
echo "<a href='public/'>Go to public folder</a><br>";
echo "<a href='public/index.php'>Go to index.php directly</a><br>";

// Test config file
$config_file = __DIR__ . '/config/config.php';
if (file_exists($config_file)) {
    echo "<h3>Config File:</h3>";
    echo "✓ config.php found<br>";
    
    $config = require $config_file;
    echo "Base URL: " . ($config['app']['base_url'] ?? 'Not set') . "<br>";
    echo "Debug Mode: " . (($config['app']['debug'] ?? false) ? 'ON' : 'OFF') . "<br>";
    
    // Test database connection
    echo "<h3>Database Test:</h3>";
    try {
        $pdo = new PDO(
            "mysql:host={$config['database']['host']};dbname={$config['database']['name']}",
            $config['database']['user'],
            $config['database']['pass']
        );
        echo "✓ Database connection successful<br>";
    } catch (PDOException $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "<h3 style='color:red;'>✗ config.php not found at: $config_file</h3>";
}

// Check important files
echo "<h3>Required Files:</h3>";
$files_to_check = [
    '/public/index.php',
    '/src/includes/auth.php',
    '/src/includes/helpers.php',
    '/src/includes/permissions.php',
    '/src/includes/audit.php',
    '/.htaccess',
    '/public/.htaccess'
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . $file;
    if (file_exists($full_path)) {
        echo "✓ $file exists<br>";
    } else {
        echo "✗ $file NOT found<br>";
    }
}