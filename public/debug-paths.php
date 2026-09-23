<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Path Debugging</h1>";

echo "<h2>Server Information:</h2>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "HTTP Host: " . $_SERVER['HTTP_HOST'] . "<br>";

echo "<h2>API URLs to Test:</h2>";
$base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
echo "Base URL: " . $base_url . "<br><br>";

$urls = [
    '/api/districts.php?division_id=1',
    '/public/api/districts.php?division_id=1',
    '/src/api/districts.php?division_id=1',
    '/api/districts.php',
    './api/districts.php?division_id=1'
];

foreach ($urls as $url) {
    $full_url = $base_url . $url;
    echo "<strong>Testing: " . $full_url . "</strong><br>";
    
    // Use file_get_contents with stream context to follow redirects
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    $content = @file_get_contents($full_url, false, $context);
    
    if ($content === false) {
        echo "❌ Failed to load<br>";
        echo "Error: " . error_get_last()['message'] . "<br>";
    } else {
        echo "✅ Loaded successfully<br>";
        echo "Response: " . substr($content, 0, 200) . (strlen($content) > 200 ? '...' : '') . "<br>";
        
        // Check if it's valid JSON
        $json = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✓ Valid JSON - Found " . count($json) . " items<br>";
        } else {
            echo "✗ Invalid JSON: " . json_last_error_msg() . "<br>";
        }
    }
    echo "<br>";
}

echo "<h2>File Existence Check:</h2>";
$paths = [
    '/home/ibdgastroliverbd/public_html/api/districts.php',
    '/home/ibdgastroliverbd/public_html/public/api/districts.php',
    '/home/ibdgastroliverbd/public_html/src/api/districts.php',
    __DIR__ . '/../api/districts.php',
    __DIR__ . '/api/districts.php'
];

foreach ($paths as $path) {
    echo $path . ": " . (file_exists($path) ? '✅ EXISTS' : '❌ NOT FOUND') . "<br>";
}