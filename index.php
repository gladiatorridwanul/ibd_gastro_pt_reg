<?php
/**
 * PMRMS Main Entry Point
 * Redirects to public folder
 */

// Define base path
define('ROOT_PATH', __DIR__);

// Simple redirect to public folder
header('Location: public/');
exit;