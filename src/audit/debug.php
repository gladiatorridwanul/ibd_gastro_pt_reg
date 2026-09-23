<?php
echo "Testing includes...<br>";

require_once __DIR__.'/../includes/db.php';
echo "db.php loaded - PDO exists: " . (isset($pdo) ? 'Yes' : 'No') . "<br>";

require_once __DIR__.'/../includes/auth.php';
echo "auth.php loaded<br>";

echo "Functions defined:<br>";
echo "- getAllActiveSessions: " . (function_exists('getAllActiveSessions') ? 'Yes' : 'No') . "<br>";
echo "- require_login: " . (function_exists('require_login') ? 'Yes' : 'No') . "<br>";
echo "- user: " . (function_exists('user') ? 'Yes' : 'No') . "<br>";

if (function_exists('getAllActiveSessions')) {
    echo "Attempting to call getAllActiveSessions...<br>";
    try {
        $sessions = getAllActiveSessions($pdo);
        echo "Success! Found " . count($sessions) . " active sessions.<br>";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

echo "Debug complete.";