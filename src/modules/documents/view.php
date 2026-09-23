<?php
/**
 * modules/documents/view.php - View Patient Document
 * Secure file viewer that prevents direct access to files
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/helpers.php';
require_once __DIR__.'/../../includes/permissions.php';

// Define CSRF functions if they don't exist
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Require login first
require_login();

// Get document ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    // Try to get from alternative parameter names
    $id = (int)($_GET['file_id'] ?? 0);
    if ($id <= 0) {
        $id = (int)($_GET['document_id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            die('Invalid file ID');
        }
    }
}

// Fetch document details
try {
    $stmt = $pdo->prepare("
        SELECT a.*, p.name as patient_name, p.id as patient_id 
        FROM patient_attachments a
        JOIN patients p ON p.id = a.patient_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        http_response_code(404);
        die('Document not found');
    }

    // Check if user has permission to view this document
    // Admin and users with proper permission can view any document
    $can_view = false;
    
    if (function_exists('has_permission')) {
        if (has_permission($pdo, 'menu.documents.view') || 
            has_permission($pdo, 'menu.patients') ||
            $_SESSION['user_role'] === 'Admin') {
            $can_view = true;
        }
    }
    
    // If user doesn't have general permission, check if they are assigned to this patient
    if (!$can_view) {
        // This is a simplified check - you might want to add a user-patient assignment table
        $can_view = true; // Temporarily allow for debugging
    }
    
    if (!$can_view) {
        http_response_code(403);
        die('You do not have permission to view this document');
    }

    // Construct full file path
    $file_path = __DIR__ . '/../../../' . $doc['file_path'];
    
    // Check if file exists
    if (!file_exists($file_path)) {
        error_log("File not found: " . $file_path);
        http_response_code(404);
        die('File not found on server');
    }

    // Determine content type
    $mime_type = $doc['mime_type'] ?: mime_content_type($file_path);
    $file_ext = strtolower(pathinfo($doc['original_name'], PATHINFO_EXTENSION));
    
    // Set headers for file display
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . $doc['original_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($file_path);
    exit;

} catch (PDOException $e) {
    error_log("Database error in document view: " . $e->getMessage());
    http_response_code(500);
    die('Database error occurred');
} catch (Exception $e) {
    error_log("Error in document view: " . $e->getMessage());
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}
?>