<?php
/**
 * export_patients.php - Export Patient Profile Data to CSV/Excel
 * Place this file in the ROOT directory (/pmrms/export_patients.php)
 * This exports complete patient information from the patients table
 */

// Fix the path to include files from src directory
require_once __DIR__ . '/src/includes/db.php';
require_once __DIR__ . '/src/includes/auth.php';
require_once __DIR__ . '/src/includes/permissions.php';
require_once __DIR__ . '/src/includes/helpers.php';
require_once __DIR__ . '/src/includes/audit.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Direct session check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    header('Location: index.php?r=login');
    exit;
}

// Check permission
if (!isset($pdo) || !has_permission($pdo, 'export.excel')) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. You do not have permission to export data.');
}

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'show';

// Handle export action
if ($action === 'export') {
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    $export_type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    exportPatientData($pdo, $format, $export_type, $patient_id, $date_from, $date_to, $search_query);
    exit;
}

// Handle single patient export
if ($action === 'export_single' && isset($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
    exportPatientData($pdo, 'csv', 'single', $patient_id, '', '', '');
    exit;
}

// Otherwise, show the export options page
showExportOptionsPage($pdo);
exit;

/**
 * Main export function - Fetches patient data and outputs CSV/Excel file
 */
function exportPatientData($pdo, $format, $export_type, $patient_id, $date_from, $date_to, $search_query) {
    // Set memory limit for large exports
    ini_set('memory_limit', '512M');
    set_time_limit(300);
    
    // Build the SQL query to fetch patient data
    $sql = "SELECT 
        p.id AS patient_id,
        p.ibd_reg_no AS ibd_registration_number,
        p.name AS patient_name,
        p.age AS age,
        CASE 
            WHEN p.sex = 'M' THEN 'Male' 
            WHEN p.sex = 'F' THEN 'Female' 
            ELSE 'Not Specified'
        END AS gender,
        p.dob AS date_of_birth,
        p.height_cm AS height_cm,
        p.weight_kg AS weight_kg,
        p.bmi AS bmi,
        COALESCE(p.nationality, 'Bangladeshi') AS nationality,
        COALESCE(p.religion, 'Not Specified') AS religion,
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
    WHERE 1=1";
    
    $params = [];
    
    // Apply filters based on export type
    switch ($export_type) {
        case 'single':
            $sql .= " AND p.id = ?";
            $params[] = $patient_id;
            break;
            
        case 'filtered':
            if (!empty($date_from)) {
                $sql .= " AND DATE(p.created_at) >= ?";
                $params[] = $date_from;
            }
            if (!empty($date_to)) {
                $sql .= " AND DATE(p.created_at) <= ?";
                $params[] = $date_to;
            }
            if (!empty($search_query)) {
                $sql .= " AND (p.name LIKE ? OR p.id LIKE ? OR p.contact_number LIKE ? OR p.ibd_reg_no LIKE ?)";
                $search_param = "%{$search_query}%";
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
            }
            break;
            
        case 'all':
        default:
            // No additional filters - export all patients
            break;
    }
    
    $sql .= " ORDER BY p.id DESC";
    
    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if there is data to export
    if (empty($patients)) {
        $_SESSION['error'] = "No patient data found to export.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Audit the export
    if (function_exists('audit')) {
        audit($pdo, 'export.excel', 'patients', null, [
            'record_count' => count($patients),
            'export_type' => $export_type,
            'date_range' => $date_from . ' to ' . $date_to
        ]);
    }
    
    // Generate filename
    $timestamp = date('Ymd_His');
    $filename = "pmrms_patients_export_{$timestamp}";
    
    if ($export_type == 'single') {
        $filename .= "_patient_{$patient_id}";
    } elseif ($export_type == 'filtered') {
        $filename .= "_filtered";
    } else {
        $filename .= "_all";
    }
    
    // Export based on format
    if ($format === 'csv') {
        exportAsCSV($patients, $filename);
    } else {
        exportAsExcelHTML($patients, $filename);
    }
}

/**
 * Export data as CSV file with UTF-8 BOM for Excel compatibility
 */
function exportAsCSV($patients, $filename) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel to handle special characters correctly
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Define column headers
    $headers = [
        'SL No',
        'Patient ID',
        'IBD Registration No',
        'Patient Name',
        'Age',
        'Gender',
        'Date of Birth',
        'Height (cm)',
        'Weight (kg)',
        'BMI',
        'Nationality',
        'Religion',
        'National ID',
        'Occupation',
        'Education',
        'Contact Number',
        'Email Address',
        'Permanent Address',
        'Permanent Division',
        'Permanent District',
        'Permanent Upazila/Thana',
        'Present Address',
        'Present Division',
        'Present District',
        'Present Upazila/Thana',
        'Father/Husband Name',
        'Father/Husband Occupation',
        'Mother Name',
        'Mother Occupation',
        'Registered By',
        'Registration Date'
    ];
    
    // Write headers to CSV
    fputcsv($output, $headers);
    
    // Write data rows
    $sl_no = 1;
    foreach ($patients as $patient) {
        $row = [
            $sl_no++,
            formatExportValue($patient['patient_id']),
            formatExportValue($patient['ibd_registration_number']),
            formatExportValue($patient['patient_name']),
            formatExportValue($patient['age']),
            formatExportValue($patient['gender']),
            formatExportValue($patient['date_of_birth']),
            formatExportValue($patient['height_cm']),
            formatExportValue($patient['weight_kg']),
            formatExportValue($patient['bmi']),
            formatExportValue($patient['nationality']),
            formatExportValue($patient['religion']),
            formatExportValue($patient['national_id']),
            formatExportValue($patient['occupation']),
            formatExportValue($patient['education']),
            formatExportValue($patient['contact_number']),
            formatExportValue($patient['email']),
            formatExportValue($patient['permanent_address']),
            formatExportValue($patient['permanent_division']),
            formatExportValue($patient['permanent_district']),
            formatExportValue($patient['permanent_upazila']),
            formatExportValue($patient['present_address']),
            formatExportValue($patient['present_division']),
            formatExportValue($patient['present_district']),
            formatExportValue($patient['present_upazila']),
            formatExportValue($patient['father_husband_name']),
            formatExportValue($patient['father_husband_occupation']),
            formatExportValue($patient['mother_name']),
            formatExportValue($patient['mother_occupation']),
            formatExportValue($patient['registered_by']),
            formatExportValue($patient['registration_date'])
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
}

/**
 * Export data as Excel HTML format (fallback)
 */
function exportAsExcelHTML($patients, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Patient Data Export</title>';
    echo '<style>
        th { background-color: #4A90E2; color: #FFFFFF; font-weight: bold; border: 1px solid #FFFFFF; padding: 8px; text-align: center; }
        td { border: 1px solid #CCCCCC; padding: 6px; }
        .header-title { font-size: 16px; font-weight: bold; text-align: center; background-color: #4A90E2; color: #FFFFFF; padding: 10px; }
        .subtitle { font-size: 12px; text-align: center; background-color: #F2F4F8; padding: 8px; }
    </style>';
    echo '</head>';
    echo '<body>';
    
    // Header
    echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><td colspan="100%" class="header-title">PATIENT PROFILE DATA EXPORT</td></tr>';
    echo '<tr><td colspan="100%" class="subtitle">Export Date: ' . date('Y-m-d H:i:s') . ' | Total Records: ' . count($patients) . '</td></tr>';
    echo '</table><br>';
    
    // Data table
    echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    echo '<thead><tr>';
    echo '<th>SL No</th>';
    echo '<th>Patient ID</th>';
    echo '<th>IBD Reg No</th>';
    echo '<th>Patient Name</th>';
    echo '<th>Age</th>';
    echo '<th>Gender</th>';
    echo '<th>DOB</th>';
    echo '<th>Height</th>';
    echo '<th>Weight</th>';
    echo '<th>BMI</th>';
    echo '<th>Nationality</th>';
    echo '<th>Religion</th>';
    echo '<th>NID</th>';
    echo '<th>Occupation</th>';
    echo '<th>Education</th>';
    echo '<th>Contact</th>';
    echo '<th>Email</th>';
    echo '<th>Perm Address</th>';
    echo '<th>Perm Division</th>';
    echo '<th>Perm District</th>';
    echo '<th>Perm Upazila</th>';
    echo '<th>Pres Address</th>';
    echo '<th>Pres Division</th>';
    echo '<th>Pres District</th>';
    echo '<th>Pres Upazila</th>';
    echo '<th>Father/Husband</th>';
    echo '<th>Father Occupation</th>';
    echo '<th>Mother Name</th>';
    echo '<th>Mother Occupation</th>';
    echo '<th>Registered By</th>';
    echo '<th>Reg Date</th>';
    echo '</tr></thead><tbody>';
    
    $sl_no = 1;
    foreach ($patients as $patient) {
        echo '<tr>';
        echo '<td style="text-align: center;">' . $sl_no++ . '</td>';
        echo '<td style="text-align: center;">#' . formatExportValue($patient['patient_id']) . '</td>';
        echo '<td>' . formatExportValue($patient['ibd_registration_number']) . '</td>';
        echo '<td>' . formatExportValue($patient['patient_name']) . '</td>';
        echo '<td style="text-align: center;">' . formatExportValue($patient['age']) . '</td>';
        echo '<td style="text-align: center;">' . formatExportValue($patient['gender']) . '</td>';
        echo '<td style="text-align: center;">' . formatExportValue($patient['date_of_birth']) . '</td>';
        echo '<td style="text-align: center;">' . formatExportValue($patient['height_cm']) . '</td>';
        echo '<td style="text-align: center;">' . formatExportValue($patient['weight_kg']) . '</td>';
        echo '<td style="text-align: center;">' . formatExportValue($patient['bmi']) . '</td>';
        echo '<td>' . formatExportValue($patient['nationality']) . '</td>';
        echo '<td>' . formatExportValue($patient['religion']) . '</td>';
        echo '<td>' . formatExportValue($patient['national_id']) . '</td>';
        echo '<td>' . formatExportValue($patient['occupation']) . '</td>';
        echo '<td>' . formatExportValue($patient['education']) . '</td>';
        echo '<td>' . formatExportValue($patient['contact_number']) . '</td>';
        echo '<td>' . formatExportValue($patient['email']) . '</td>';
        echo '<td>' . formatExportValue($patient['permanent_address']) . '</td>';
        echo '<td>' . formatExportValue($patient['permanent_division']) . '</td>';
        echo '<td>' . formatExportValue($patient['permanent_district']) . '</td>';
        echo '<td>' . formatExportValue($patient['permanent_upazila']) . '</td>';
        echo '<td>' . formatExportValue($patient['present_address']) . '</td>';
        echo '<td>' . formatExportValue($patient['present_division']) . '</td>';
        echo '<td>' . formatExportValue($patient['present_district']) . '</td>';
        echo '<td>' . formatExportValue($patient['present_upazila']) . '</td>';
        echo '<td>' . formatExportValue($patient['father_husband_name']) . '</td>';
        echo '<td>' . formatExportValue($patient['father_husband_occupation']) . '</td>';
        echo '<td>' . formatExportValue($patient['mother_name']) . '</td>';
        echo '<td>' . formatExportValue($patient['mother_occupation']) . '</td>';
        echo '<td>' . formatExportValue($patient['registered_by']) . '</td>';
        echo '<td style="text-align: center;">' . formatExportValue($patient['registration_date']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '<br><table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><td class="subtitle"><strong>Generated by:</strong> ' . htmlspecialchars($_SESSION['user_name'] ?? 'System') . ' | <strong>Total Records:</strong> ' . count($patients) . ' | <strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</td></tr>';
    echo '</table>';
    echo '</body></html>';
}

/**
 * Format export value - replace empty values with "Null"
 */
function formatExportValue($value) {
    if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00' || $value === 'Not Specified') {
        return 'Null';
    }
    return $value;
}

/**
 * Display the export options page with download buttons
 */
function showExportOptionsPage($pdo) {
    // Get statistics
    $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    
    // Get last 30 days count
    $last_30_days = $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    
    // Get last 7 days count
    $last_7_days = $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    
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
            
            * {
                font-family: Cambria, serif;
            }
            
            body {
                background-color: var(--ash);
            }
            
            .export-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
            }
            
            .export-card {
                max-width: 1000px;
                width: 100%;
                background-color: var(--white);
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                overflow: hidden;
            }
            
            .export-header {
                background: linear-gradient(135deg, var(--blue) 0%, #357ABD 100%);
                padding: 30px;
                text-align: center;
                color: var(--white);
            }
            
            .export-header .logo-icon {
                width: 70px;
                height: 70px;
                background-color: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 15px;
            }
            
            .export-header .logo-icon i {
                font-size: 2rem;
                color: var(--white);
            }
            
            .export-header h2 {
                font-weight: 600;
                margin-bottom: 8px;
                color: var(--white);
            }
            
            .export-header p {
                opacity: 0.9;
                margin: 0;
                color: var(--white);
            }
            
            .export-body {
                padding: 30px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .stat-card {
                background-color: var(--ash);
                border-radius: 10px;
                padding: 20px;
                text-align: center;
                transition: all 0.2s;
            }
            
            .stat-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            
            .stat-number {
                font-size: 2rem;
                font-weight: 700;
                color: var(--blue);
            }
            
            .stat-label {
                color: var(--black);
                opacity: 0.7;
                font-size: 0.85rem;
                margin-top: 5px;
            }
            
            .btn-download {
                background-color: var(--blue);
                border: none;
                border-radius: 8px;
                padding: 14px 24px;
                color: var(--white);
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                text-decoration: none;
                transition: all 0.2s;
                cursor: pointer;
                font-size: 1rem;
            }
            
            .btn-download:hover {
                background-color: #357ABD;
                color: var(--white);
                transform: translateY(-2px);
            }
            
            .btn-download-large {
                width: 100%;
                justify-content: center;
                padding: 16px;
                font-size: 1.1rem;
            }
            
            .btn-outline {
                background-color: transparent;
                border: 2px solid var(--blue);
                color: var(--blue);
            }
            
            .btn-outline:hover {
                background-color: var(--blue);
                color: var(--white);
            }
            
            .section-title {
                font-size: 1.1rem;
                font-weight: 600;
                color: var(--black);
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid var(--blue);
                display: inline-block;
            }
            
            .filter-section {
                background-color: var(--ash);
                border-radius: 10px;
                padding: 25px;
                margin-top: 25px;
            }
            
            .form-label {
                font-weight: 500;
                color: var(--black);
                margin-bottom: 8px;
            }
            
            .form-control, .form-select {
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 10px 14px;
                font-family: Cambria, serif;
            }
            
            .form-control:focus, .form-select:focus {
                border-color: var(--blue);
                box-shadow: 0 0 0 3px rgba(74,144,226,0.1);
            }
            
            .btn-filter {
                background-color: var(--blue);
                border: none;
                border-radius: 8px;
                padding: 12px 24px;
                color: var(--white);
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .btn-filter:hover {
                background-color: #357ABD;
            }
            
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--blue);
                text-decoration: none;
                margin-top: 20px;
            }
            
            .back-link:hover {
                text-decoration: underline;
            }
            
            hr {
                border-color: var(--ash);
                margin: 25px 0;
            }
            
            @media (max-width: 768px) {
                .export-body {
                    padding: 20px;
                }
                .stats-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }
        </style>
    </head>
    <body>
        <div class="export-container">
            <div class="export-card">
                <div class="export-header">
                    <div class="logo-icon">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <h2>Export Patient Data</h2>
                    <p>Download complete patient records in CSV format for Excel</p>
                </div>
                
                <div class="export-body">
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($total_patients) ?></div>
                            <div class="stat-label">Total Patients</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($last_30_days) ?></div>
                            <div class="stat-label">Last 30 Days</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($last_7_days) ?></div>
                            <div class="stat-label">Last 7 Days</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">CSV</div>
                            <div class="stat-label">Excel Compatible</div>
                        </div>
                    </div>
                    
                    <!-- Quick Export Buttons -->
                    <h5 class="section-title"><i class="fa-solid fa-bolt me-2" style="color: var(--blue);"></i> Quick Export</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <a href="?action=export&format=csv&type=all" class="btn-download btn-download-large">
                                <i class="fa-solid fa-users"></i> Export All Patients (<?= number_format($total_patients) ?>)
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="?action=export&format=csv&type=filtered&date_from=<?= date('Y-m-d', strtotime('-30 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn-download btn-download-large">
                                <i class="fa-solid fa-calendar-week"></i> Last 30 Days (<?= number_format($last_30_days) ?>)
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="?action=export&format=csv&type=filtered&date_from=<?= date('Y-m-d', strtotime('-7 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn-download btn-download-large">
                                <i class="fa-solid fa-calendar-day"></i> Last 7 Days (<?= number_format($last_7_days) ?>)
                            </a>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Filtered Export Section -->
                    <h5 class="section-title"><i class="fa-solid fa-sliders-h me-2" style="color: var(--blue);"></i> Custom Filtered Export</h5>
                    <div class="filter-section">
                        <form method="get" action="">
                            <input type="hidden" name="action" value="export">
                            <input type="hidden" name="format" value="csv">
                            <input type="hidden" name="type" value="filtered">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fa-regular fa-calendar me-1"></i> Date From</label>
                                    <input type="date" name="date_from" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fa-regular fa-calendar me-1"></i> Date To</label>
                                    <input type="date" name="date_to" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label"><i class="fa-solid fa-magnifying-glass me-1"></i> Search Patient</label>
                                    <input type="text" name="q" class="form-control" placeholder="Search by Name, Patient ID, Contact Number, or IBD Registration No...">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn-filter">
                                        <i class="fa-solid fa-download"></i> Export Filtered Data
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Navigation Links -->
                    <div class="text-center mt-4">
                        <a href="?r=dashboard" class="back-link">
                            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                        </a>
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        <a href="?r=patients/manage" class="back-link">
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