<?php
/**
 * view_attachment.php - Secure file viewer for attachments
 * Alternative location in public directory
 */

require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/permissions.php';
require_once __DIR__ . '/../src/includes/helpers.php';

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
        die('File not found');
    }

    // Check permission
    $user = user();
    $has_permission = has_permission($pdo, 'button.patient.view');
    
    if (!$has_permission && $file['patient_id'] != ($user['id'] ?? 0)) {
        http_response_code(403);
        die('Access denied');
    }

    // Construct file path - going up one level from public directory
    $file_path = __DIR__ . '/../' . $file['file_path'];

    if (!file_exists($file_path)) {
        error_log("File not found: " . $file_path);
        http_response_code(404);
        die('File not found on server');
    }

    // Get file mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);

    // Set headers
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: public, max-age=86400');
    
    // Output file
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log("Error viewing attachment: " . $e->getMessage());
    http_response_code(500);
    die('An error occurred while viewing the file.');
}