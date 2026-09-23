<?php
/**
 * export_patients.php - Export Patient Data to CSV/Excel
 * Place this file in the ROOT directory (/pmrms/export_patients.php)
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database and auth files
require_once __DIR__ . '/src/includes/db.php';
require_once __DIR__ . '/src/includes/auth.php';
require_once __DIR__ . '/src/includes/permissions.php';
require_once __DIR__ . '/src/includes/audit.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?r=login');
    exit;
}

// Check permission
if (!isset($pdo) || !has_permission($pdo, 'export.excel')) {
    die('Access denied. You do not have permission to export data.');
}

// Get parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'show';

// Handle export
if ($action === 'export') {
    exportToCSV($pdo);
    exit;
}

// Show the export page
showExportPage($pdo);

/**
 * Export all patient data to CSV
 */
function exportToCSV($pdo) {
    // Fetch all patients with their details
    $sql = "SELECT 
        p.id AS patient_id,
        p.ibd_reg_no AS ibd_reg_no,
        p.name AS patient_name,
        p.age AS age,
        CASE WHEN p.sex = 'M' THEN 'Male' WHEN p.sex = 'F' THEN 'Female' ELSE 'Not Specified' END AS gender,
        p.dob AS date_of_birth,
        p.height_cm AS height,
        p.weight_kg AS weight,
        p.bmi AS bmi,
        COALESCE(p.nationality, 'Bangladeshi') AS nationality,
        COALESCE(p.religion, 'Null') AS religion,
        COALESCE(p.national_id, 'Null') AS national_id,
        COALESCE(p.occupation, 'Null') AS occupation,
        COALESCE(p.education, 'Null') AS education,
        COALESCE(p.contact_number, 'Null') AS contact_number,
        COALESCE(p.email, 'Null') AS email,
        COALESCE(p.perm_address, 'Null') AS permanent_address,
        COALESCE(dv.name, 'Null') AS permanent_division,
        COALESCE(dd.name, 'Null') AS permanent_district,
        COALESCE(up.name, 'Null') AS permanent_upazila,
        COALESCE(p.pres_address, 'Null') AS present_address,
        COALESCE(dv2.name, 'Null') AS present_division,
        COALESCE(dd2.name, 'Null') AS present_district,
        COALESCE(up2.name, 'Null') AS present_upazila,
        COALESCE(p.father_husband_name, 'Null') AS father_husband_name,
        COALESCE(p.father_husband_occupation, 'Null') AS father_husband_occupation,
        COALESCE(p.mother_name, 'Null') AS mother_name,
        COALESCE(p.mother_occupation, 'Null') AS mother_occupation,
        COALESCE(u.name, 'Null') AS registered_by,
        DATE_FORMAT(p.created_at, '%Y-%m-%d %H:%i:%s') AS registration_date
    FROM patients p
    LEFT JOIN divisions dv ON dv.id = p.perm_division_id
    LEFT JOIN districts dd ON dd.id = p.perm_district_id
    LEFT JOIN upazilas up ON up.id = p.perm_upazila_id
    LEFT JOIN divisions dv2 ON dv2.id = p.pres_division_id
    LEFT JOIN districts dd2 ON dd2.id = p.pres_district_id
    LEFT JOIN upazilas up2 ON up2.id = p.pres_upazila_id
    LEFT JOIN users u ON u.id = p.created_by
    ORDER BY p.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($patients)) {
        $_SESSION['error'] = "No patient data found to export.";
        header('Location: export_patients.php');
        exit;
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pmrms_patients_export_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Headers
    $headers = [
        'SL No', 'Patient ID', 'IBD Reg No', 'Patient Name', 'Age', 'Gender',
        'Date of Birth', 'Height (cm)', 'Weight (kg)', 'BMI', 'Nationality',
        'Religion', 'National ID', 'Occupation', 'Education', 'Contact Number',
        'Email', 'Permanent Address', 'Permanent Division', 'Permanent District',
        'Permanent Upazila', 'Present Address', 'Present Division', 'Present District',
        'Present Upazila', 'Father/Husband Name', 'Father/Husband Occupation',
        'Mother Name', 'Mother Occupation', 'Registered By', 'Registration Date'
    ];
    fputcsv($output, $headers);
    
    // Data rows
    $sl_no = 1;
    foreach ($patients as $patient) {
        $row = [
            $sl_no++,
            $patient['patient_id'],
            $patient['ibd_reg_no'],
            $patient['patient_name'],
            $patient['age'],
            $patient['gender'],
            $patient['date_of_birth'],
            $patient['height'],
            $patient['weight'],
            $patient['bmi'],
            $patient['nationality'],
            $patient['religion'],
            $patient['national_id'],
            $patient['occupation'],
            $patient['education'],
            $patient['contact_number'],
            $patient['email'],
            $patient['permanent_address'],
            $patient['permanent_division'],
            $patient['permanent_district'],
            $patient['permanent_upazila'],
            $patient['present_address'],
            $patient['present_division'],
            $patient['present_district'],
            $patient['present_upazila'],
            $patient['father_husband_name'],
            $patient['father_husband_occupation'],
            $patient['mother_name'],
            $patient['mother_occupation'],
            $patient['registered_by'],
            $patient['registration_date']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    
    // Log the export
    if (function_exists('audit')) {
        audit($pdo, 'export.excel', 'patients', null, ['record_count' => count($patients)]);
    }
}

/**
 * Show export options page
 */
function showExportPage($pdo) {
    // Get total patient count
    $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Export Patient Data - PMRMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --white: #FFFFFF;
                --ash: #F2F4F8;
                --blue: #4A90E2;
                --black: #000000;
            }
            * { font-family: Cambria, serif; }
            body { background-color: var(--ash); }
            .export-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
            .export-card { max-width: 800px; width: 100%; background: var(--white); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
            .export-header { background: var(--blue); padding: 30px; text-align: center; color: var(--white); }
            .export-header h2 { font-weight: 600; margin-bottom: 8px; }
            .export-body { padding: 30px; }
            .stat-card { background: var(--ash); border-radius: 10px; padding: 20px; text-align: center; margin-bottom: 25px; }
            .stat-number { font-size: 2rem; font-weight: 700; color: var(--blue); }
            .btn-download { background: var(--blue); border: none; border-radius: 8px; padding: 15px 30px; color: white; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; transition: all 0.2s; }
            .btn-download:hover { background: #357ABD; color: white; transform: translateY(-2px); }
            .btn-download-block { width: 100%; justify-content: center; }
            .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--blue); text-decoration: none; margin-top: 20px; }
            hr { border-color: var(--ash); margin: 25px 0; }
        </style>
    </head>
    <body>
        <div class="export-container">
            <div class="export-card">
                <div class="export-header">
                    <i class="fa-solid fa-file-excel fa-3x mb-3"></i>
                    <h2>Export Patient Data</h2>
                    <p>Download complete patient records in CSV format</p>
                </div>
                <div class="export-body">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($total_patients) ?></div>
                        <div class="stat-label">Total Patients in Database</div>
                    </div>
                    
                    <a href="?action=export" class="btn-download btn-download-block">
                        <i class="fa-solid fa-download"></i> Download All Patient Data (CSV)
                    </a>
                    
                    <hr>
                    
                    <div class="text-center">
                        <a href="index.php?r=dashboard" class="back-link">
                            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                        </a>
                        &nbsp;|&nbsp;
                        <a href="index.php?r=patients/manage" class="back-link">
                            <i class="fa-solid fa-users"></i> Manage Patients
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>