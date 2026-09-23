<?php
/**
 * view_attachment.php - Secure file viewer for attachments
 * Place this file in the root directory (/pmrms/view_attachment.php)
 */

require_once __DIR__ . '/src/includes/db.php';
require_once __DIR__ . '/src/includes/auth.php';
require_once __DIR__ . '/src/includes/permissions.php';
require_once __DIR__ . '/src/includes/helpers.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get file ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    die('Invalid file ID');
}

// Require login
require_login();

try {
    // Get file information from database
    $stmt = $pdo->prepare("
        SELECT a.*, p.name as patient_name 
        FROM patient_attachments a
        JOIN patients p ON p.id = a.patient_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        die('File not found in database');
    }

    // Check permission (user can view if they have permission or it's their patient)
    $user = user();
    $has_permission = has_permission($pdo, 'button.patient.view');
    
    if (!$has_permission && $file['patient_id'] != ($user['id'] ?? 0)) {
        http_response_code(403);
        die('Access denied');
    }

    // Construct the full file path - try different possible locations
    $possible_paths = [
        __DIR__ . '/' . $file['file_path'],                    // /pmrms/uploads/patient_X/file.pdf
        __DIR__ . '/public/' . $file['file_path'],             // /pmrms/public/uploads/patient_X/file.pdf
        __DIR__ . '/uploads/patient_' . $file['patient_id'] . '/' . $file['file_name'],  // Direct path
    ];

    $file_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $file_path = $path;
            break;
        }
    }

    if (!$file_path) {
        // Log the error for debugging
        error_log("File not found. Tried paths: " . implode(', ', $possible_paths));
        http_response_code(404);
        die('File not found on server. Please check with administrator.');
    }

    // Get file mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);

    // Set headers for file display
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    header('Pragma: public');

    // Output the file
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log("Error viewing attachment: " . $e->getMessage());
    http_response_code(500);
    die('An error occurred while trying to view the file.');
}