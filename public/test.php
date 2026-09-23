<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test page working!<br>";
echo "Session status: " . (session_status() == PHP_SESSION_ACTIVE ? 'Active' : 'Not started') . "<br>";
phpinfo();