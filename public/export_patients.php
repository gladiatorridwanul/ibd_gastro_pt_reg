<?php
/**
 * export_patients.php - Complete Patient Data Export to CSV/Excel
 * This file should be in the PUBLIC folder (/pmrms/public/export_patients.php)
 * Two Export Options: 
 *   1. Basic Patient Information (Demographics only)
 *   2. Basic & Clinical Information (Complete medical records including Vaccines)
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database and auth files from src directory
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/permissions.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/includes/audit.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?r=login');
    exit;
}

// Check permission
if (!isset($pdo) || !has_permission($pdo, 'export.excel')) {
    die('Access denied. You do not have permission to export data.');
}

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'show';
$export_section = isset($_GET['section']) ? $_GET['section'] : 'basic'; // 'basic' or 'clinical'

// Handle export action
if ($action === 'export') {
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    $export_type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $export_section = isset($_GET['section']) ? $_GET['section'] : 'basic';
    $patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if ($export_section === 'basic') {
        exportBasicPatientData($pdo, $format, $export_type, $patient_id, $date_from, $date_to, $search_query);
    } else {
        exportCompleteClinicalData($pdo, $format, $export_type, $patient_id, $date_from, $date_to, $search_query);
    }
    exit;
}

// Otherwise, show the export options page with sidebar
showExportOptionsPage($pdo);
exit;

/**
 * Export Basic Patient Information Only (Demographics)
 */
function exportBasicPatientData($pdo, $format, $export_type, $patient_id, $date_from, $date_to, $search_query) {
    // Set memory limit for large exports
    ini_set('memory_limit', '512M');
    set_time_limit(300);
    
    // Build query for basic patient information
    $sql = "SELECT 
        p.id AS patient_id,
        p.ibd_reg_no AS ibd_registration_number,
        p.name AS patient_name,
        p.age AS age,
        CASE WHEN p.sex = 'M' THEN 'Male' WHEN p.sex = 'F' THEN 'Female' ELSE 'Null' END AS gender,
        p.dob AS date_of_birth,
        p.height_cm AS height_cm,
        p.weight_kg AS weight_kg,
        p.bmi AS bmi,
        COALESCE(p.nationality, 'Null') AS nationality,
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
    WHERE 1=1";
    
    $params = [];
    
    // Apply filters
    if ($export_type === 'single' && $patient_id > 0) {
        $sql .= " AND p.id = ?";
        $params[] = $patient_id;
    } elseif ($export_type === 'filtered') {
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
    }
    $sql .= " ORDER BY p.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($patients)) {
        $_SESSION['error'] = "No patient data found to export.";
        header('Location: ?r=exports');
        exit;
    }
    
    // Audit the export
    if (function_exists('audit')) {
        audit($pdo, 'export.excel', 'patients', null, [
            'record_count' => count($patients),
            'export_type' => $export_type,
            'export_section' => 'basic'
        ]);
    }
    
    // Generate filename
    $timestamp = date('Ymd_His');
    $filename = "pmrms_basic_patient_export_{$timestamp}";
    if ($export_type == 'single') {
        $filename .= "_patient_{$patient_id}";
    } elseif ($export_type == 'filtered') {
        $filename .= "_filtered";
    } else {
        $filename .= "_all";
    }
    
    // Define headers for Basic Export
    $headers = [
        'SL No', 'Patient ID', 'IBD Registration No', 'Patient Name', 'Age', 'Gender',
        'Date of Birth', 'Height (cm)', 'Weight (kg)', 'BMI', 'Nationality', 'Religion',
        'National ID', 'Occupation', 'Education', 'Contact Number', 'Email',
        'Permanent Address', 'Permanent Division', 'Permanent District', 'Permanent Upazila',
        'Present Address', 'Present Division', 'Present District', 'Present Upazila',
        'Father/Husband Name', 'Father/Husband Occupation', 'Mother Name', 'Mother Occupation',
        'Registered By', 'Registration Date'
    ];
    
    exportAsCSV($patients, $filename, $headers);
}

/**
 * Export Complete Clinical Data (All Sections including Vaccines)
 */
function exportCompleteClinicalData($pdo, $format, $export_type, $patient_id, $date_from, $date_to, $search_query) {
    // Set memory limit for large exports
    ini_set('memory_limit', '512M');
    set_time_limit(300);
    
    // Get all patients based on filters
    $patient_sql = "SELECT id, name, ibd_reg_no FROM patients WHERE 1=1";
    $patient_params = [];
    
    if ($export_type === 'single' && $patient_id > 0) {
        $patient_sql .= " AND id = ?";
        $patient_params[] = $patient_id;
    } elseif ($export_type === 'filtered') {
        if (!empty($date_from)) {
            $patient_sql .= " AND DATE(created_at) >= ?";
            $patient_params[] = $date_from;
        }
        if (!empty($date_to)) {
            $patient_sql .= " AND DATE(created_at) <= ?";
            $patient_params[] = $date_to;
        }
        if (!empty($search_query)) {
            $patient_sql .= " AND (name LIKE ? OR id LIKE ? OR contact_number LIKE ? OR ibd_reg_no LIKE ?)";
            $search_param = "%{$search_query}%";
            $patient_params[] = $search_param;
            $patient_params[] = $search_param;
            $patient_params[] = $search_param;
            $patient_params[] = $search_param;
        }
    }
    $patient_sql .= " ORDER BY id DESC";
    
    $patient_stmt = $pdo->prepare($patient_sql);
    $patient_stmt->execute($patient_params);
    $patients = $patient_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($patients)) {
        $_SESSION['error'] = "No patient data found to export.";
        header('Location: ?r=exports');
        exit;
    }
    
    // Fetch all related data for each patient
    $all_export_data = [];
    
    foreach ($patients as $patient) {
        $pid = $patient['id'];
        
        // Get all data sections
        $basic_info = getPatientBasicInfo($pdo, $pid);
        $ibd_diagnosis = getIBDDiagnosis($pdo, $pid);
        $complaints = getComplaints($pdo, $pid);
        $socioeconomic = getSocioeconomicHistory($pdo, $pid);
        $pregnancy = getPregnancyHistory($pdo, $pid);
        $treatment = getTreatmentHistory($pdo, $pid);
        $investigations = getInvestigations($pdo, $pid);
        $followups = getFollowups($pdo, $pid);
        $vaccines = getVaccineHistory($pdo, $pid); // NEW: Vaccine module data
        
        // Merge all data
        $export_row = array_merge(
            $basic_info, $ibd_diagnosis, $complaints, $socioeconomic,
            $pregnancy, $treatment, $investigations, $followups, $vaccines
        );
        
        $all_export_data[] = $export_row;
    }
    
    // Audit the export
    if (function_exists('audit')) {
        audit($pdo, 'export.excel', 'patients', null, [
            'record_count' => count($all_export_data),
            'export_type' => $export_type,
            'export_section' => 'clinical'
        ]);
    }
    
    // Generate filename
    $timestamp = date('Ymd_His');
    $filename = "pmrms_clinical_patient_export_{$timestamp}";
    if ($export_type == 'single') {
        $filename .= "_patient_{$patient_id}";
    } elseif ($export_type == 'filtered') {
        $filename .= "_filtered";
    } else {
        $filename .= "_all";
    }
    
    // Define headers for Clinical Export (with Vaccine columns added)
    $headers = [
        // Patient Basic Information (31 columns)
        'SL No', 'Patient ID', 'IBD Registration No', 'Patient Name', 'Age', 'Gender',
        'Date of Birth', 'Height (cm)', 'Weight (kg)', 'BMI', 'Nationality', 'Religion',
        'National ID', 'Occupation', 'Education', 'Contact Number', 'Email',
        'Permanent Address', 'Permanent Division', 'Permanent District', 'Permanent Upazila',
        'Present Address', 'Present Division', 'Present District', 'Present Upazila',
        'Father/Husband Name', 'Father/Husband Occupation', 'Mother Name', 'Mother Occupation',
        'Registered By', 'Registration Date',
        
        // IBD Diagnosis (12 columns)
        'IBD Diagnosis', 'IBD Onset Date', 'IBD Diagnosis Date', 'Patient Type',
        'Diagnostic Criteria', 'UC Location', 'CD Location', 'Upper GI Involvement',
        'CD Behavior', 'Perianal Disease', 'Resident Last 3 Months', 'Out of Country Visits',
        
        // Complaints (30 columns)
        'Abdominal Pain', 'Pain Type', 'Diarrhea', 'Diarrhoea Details', 'Blood in Stool',
        'Blood Details', 'Mucus in Stool', 'Mucus Details', 'Stool Frequency/Day',
        'Weight Loss', 'Weight Loss (kg)', 'Weight Loss Duration', 'Fever', 'Fever Duration',
        'Fever Grade', 'Sub-acute Intestinal Obstruction', 'Sub-acute Intestinal Obstruction Details',
        'Relapses Count', 'Last Relapse Year', 'Extraintestinal Manifestations',
        'Hospitalization Required', 'Hospitalization Reason', 'Past Surgery', 'Past Surgery Details',
        'Family History IBD', 'Family History Type', 'Comorbidities', 'Cancer History',
        'Cancer Specify', 'Harvey-Bradshaw Index', 'Partial Mayo Score', 'Full Mayo Score',
        
        // Socioeconomic History (7 columns)
        'Smoking Status', 'Smoking Duration', 'Alcohol Consumption', 'Alcohol Duration',
        'Number of Children', 'Total Family Members', 'Monthly Income (Taka)',
        
        // Pregnancy History (8 columns - updated with new fields)
        'Pregnancy Outcome', 'Pregnancy Outcome Notes', 'Mode of Delivery', 'Mode of Delivery Notes',
        'Pregnancy History', 'Abortion History', 'Abortion Details', 'Congenital Disorder',
        
        // Treatment (20 columns)
        'Treatment Drug Name', 'Custom Drug Name', 'Treatment Start Date', 'Current Dose',
        'Maximum Dose', 'Side Effects', 'Local Treatment', 'Local Treatment Specify',
        'Antibiotics Quinolone', 'Antibiotics Metronidazole', 'Calcium Supplement',
        'Vitamin D Supplement', 'Vitamin B12 Supplement', 'Iron Supplement', 'Probiotics',
        'Nutritional Supplements', 'Steroid Dependency', 'Steroid Resistant',
        'Alternative Medicine', 'Alternative Medicine Type',
        
        // Investigations (17 columns)
        'CBC Hemoglobin', 'ESR', 'TLC', 'DLC', 'Platelets', 'CRP', 'Serum Albumin',
        'Fecal Calprotectin', 'Upper GIT Findings', 'Endoscopy Findings', 'Colonoscopy Findings',
        'Ileoscopy Findings', 'Histopathology Findings', 'USG Abdomen', 'CT Scan Findings',
        'Enterography Findings', 'Enteroscopy Findings',
        
        // Follow-ups (4 columns)
        'Follow-up Dates (Latest 5)', 'Follow-up Descriptions', 'Follow-up Treatments', 'Total Follow-ups',
        
        // Vaccine Module (12 columns) - NEW
        'Vaccine Names', 'Vaccine Dose Numbers', 'Vaccine Dose Schedules', 'Vaccine Dose Given Dates',
        'Vaccine Next Dose Dates', 'Vaccine Batch Numbers', 'Vaccine Statuses', 'Vaccine Remarks',
        'Vaccine Types', 'Vaccine Schedule Descriptions', 'Vaccine Administered By', 'Vaccine Batch ID'
    ];
    
    exportCompleteAsCSV($all_export_data, $filename, $headers);
}

/**
 * Get Patient Basic Information
 */
function getPatientBasicInfo($pdo, $patient_id) {
    $sql = "SELECT 
        p.id AS patient_id,
        p.ibd_reg_no AS ibd_registration_number,
        p.name AS patient_name,
        p.age AS age,
        CASE WHEN p.sex = 'M' THEN 'Male' WHEN p.sex = 'F' THEN 'Female' ELSE 'Null' END AS gender,
        p.dob AS date_of_birth,
        p.height_cm AS height_cm,
        p.weight_kg AS weight_kg,
        p.bmi AS bmi,
        COALESCE(p.nationality, 'Null') AS nationality,
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
    WHERE p.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: [];
}

/**
 * Get IBD Diagnosis
 */
function getIBDDiagnosis($pdo, $patient_id) {
    $sql = "SELECT 
        COALESCE(diagnosis, 'Null') AS ibd_diagnosis,
        COALESCE(onset_date, 'Null') AS ibd_onset_date,
        COALESCE(diagnosis_date, 'Null') AS ibd_diagnosis_date,
        COALESCE(patient_type, 'Null') AS ibd_patient_type,
        COALESCE(diagnostic_criteria, 'Null') AS ibd_diagnostic_criteria,
        COALESCE(uc_location, 'Null') AS uc_location,
        COALESCE(cd_location_set, 'Null') AS cd_location,
        COALESCE(upper_gi, 'Null') AS upper_gi_involvement,
        COALESCE(cd_behavior, 'Null') AS cd_behavior,
        COALESCE(perianal_disease, 'Null') AS perianal_disease,
        COALESCE(resident_3m, 'Null') AS resident_last_3months,
        COALESCE(out_of_country_visits, 'Null') AS out_of_country_visits
    FROM ibd_diagnoses 
    WHERE patient_id = ? 
    ORDER BY id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $default = [
        'ibd_diagnosis' => 'Null', 'ibd_onset_date' => 'Null', 'ibd_diagnosis_date' => 'Null',
        'ibd_patient_type' => 'Null', 'ibd_diagnostic_criteria' => 'Null', 'uc_location' => 'Null',
        'cd_location' => 'Null', 'upper_gi_involvement' => 'Null', 'cd_behavior' => 'Null',
        'perianal_disease' => 'Null', 'resident_last_3months' => 'Null', 'out_of_country_visits' => 'Null'
    ];
    return $result ?: $default;
}

/**
 * Get Complaints (Updated with Sub-acute Intestinal Obstruction fields)
 */
function getComplaints($pdo, $patient_id) {
    $sql = "SELECT 
        COALESCE(abdominal_pain, 'Null') AS abdominal_pain,
        COALESCE(pain_type, 'Null') AS pain_type,
        COALESCE(diarrhea, 'Null') AS diarrhea,
        COALESCE(diarrhoea_details, 'Null') AS diarrhoea_details,
        COALESCE(blood_in_stool, 'Null') AS blood_in_stool,
        COALESCE(blood_details, 'Null') AS blood_details,
        COALESCE(mucus_in_stool, 'Null') AS mucus_in_stool,
        COALESCE(mucus_details, 'Null') AS mucus_details,
        COALESCE(frequency_per_day, 'Null') AS stool_frequency_per_day,
        COALESCE(weight_loss, 'Null') AS weight_loss,
        COALESCE(weight_loss_kg, 'Null') AS weight_loss_kg,
        COALESCE(weight_loss_duration, 'Null') AS weight_loss_duration,
        COALESCE(fever, 'Null') AS fever,
        COALESCE(fever_duration, 'Null') AS fever_duration,
        COALESCE(fever_grade, 'Null') AS fever_grade,
        COALESCE(sub_acute_intestinal_obstruction, 'Null') AS sub_acute_intestinal_obstruction,
        COALESCE(sub_acute_intestinal_obstruction_details, 'Null') AS sub_acute_intestinal_obstruction_details,
        COALESCE(relapses_count, 'Null') AS relapses_count,
        COALESCE(last_on_year, 'Null') AS last_relapse_year,
        COALESCE(extraintestinal_set, 'Null') AS extraintestinal_manifestations,
        COALESCE(hospitalization, 'Null') AS hospitalization_required,
        COALESCE(hospitalization_reason, 'Null') AS hospitalization_reason,
        COALESCE(past_surgery, 'Null') AS past_surgery,
        COALESCE(past_surgery_details, 'Null') AS past_surgery_details,
        COALESCE(family_history_ibd, 'Null') AS family_history_ibd,
        COALESCE(family_history_type, 'Null') AS family_history_type,
        COALESCE(comorbid_set, 'Null') AS comorbidities,
        COALESCE(cancer_history, 'Null') AS cancer_history,
        COALESCE(cancer_specify, 'Null') AS cancer_specify,
        COALESCE(hvi, 'Null') AS harvey_bradshaw_index,
        COALESCE(partial_mayo, 'Null') AS partial_mayo_score,
        COALESCE(mayo_score, 'Null') AS full_mayo_score
    FROM complaints 
    WHERE patient_id = ? 
    ORDER BY id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $default = [
        'abdominal_pain' => 'Null', 'pain_type' => 'Null', 'diarrhea' => 'Null',
        'diarrhoea_details' => 'Null', 'blood_in_stool' => 'Null', 'blood_details' => 'Null',
        'mucus_in_stool' => 'Null', 'mucus_details' => 'Null', 'stool_frequency_per_day' => 'Null',
        'weight_loss' => 'Null', 'weight_loss_kg' => 'Null', 'weight_loss_duration' => 'Null',
        'fever' => 'Null', 'fever_duration' => 'Null', 'fever_grade' => 'Null',
        'sub_acute_intestinal_obstruction' => 'Null', 'sub_acute_intestinal_obstruction_details' => 'Null',
        'relapses_count' => 'Null', 'last_relapse_year' => 'Null', 'extraintestinal_manifestations' => 'Null',
        'hospitalization_required' => 'Null', 'hospitalization_reason' => 'Null', 'past_surgery' => 'Null',
        'past_surgery_details' => 'Null', 'family_history_ibd' => 'Null', 'family_history_type' => 'Null',
        'comorbidities' => 'Null', 'cancer_history' => 'Null', 'cancer_specify' => 'Null',
        'harvey_bradshaw_index' => 'Null', 'partial_mayo_score' => 'Null', 'full_mayo_score' => 'Null'
    ];
    return $result ?: $default;
}

/**
 * Get Socioeconomic History
 */
function getSocioeconomicHistory($pdo, $patient_id) {
    $sql = "SELECT 
        COALESCE(smoking, 'Null') AS smoking_status,
        COALESCE(smoking_duration, 'Null') AS smoking_duration,
        COALESCE(alcohol, 'Null') AS alcohol_consumption,
        COALESCE(alcohol_duration, 'Null') AS alcohol_duration,
        COALESCE(children_count, 'Null') AS number_of_children,
        COALESCE(family_members_total, 'Null') AS total_family_members,
        COALESCE(monthly_income_taka, 'Null') AS monthly_income_taka
    FROM socioeconomic_histories 
    WHERE patient_id = ? 
    ORDER BY id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $default = [
        'smoking_status' => 'Null', 'smoking_duration' => 'Null', 'alcohol_consumption' => 'Null',
        'alcohol_duration' => 'Null', 'number_of_children' => 'Null', 'total_family_members' => 'Null',
        'monthly_income_taka' => 'Null'
    ];
    return $result ?: $default;
}

/**
 * Get Pregnancy History (Updated with new fields)
 */
function getPregnancyHistory($pdo, $patient_id) {
    $sql = "SELECT 
        COALESCE(pregnancy_outcome, 'Null') AS pregnancy_outcome,
        COALESCE(pregnancy_outcome_notes, 'Null') AS pregnancy_outcome_notes,
        COALESCE(mode_of_delivery, 'Null') AS mode_of_delivery,
        COALESCE(mode_of_delivery_notes, 'Null') AS mode_of_delivery_notes,
        COALESCE(history, 'Null') AS pregnancy_history,
        COALESCE(abortion, 'Null') AS abortion_history,
        COALESCE(abortion_details, 'Null') AS abortion_details,
        COALESCE(congenital_disorder, 'Null') AS congenital_disorder
    FROM pregnancies 
    WHERE patient_id = ? 
    ORDER BY id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $default = [
        'pregnancy_outcome' => 'Null', 'pregnancy_outcome_notes' => 'Null',
        'mode_of_delivery' => 'Null', 'mode_of_delivery_notes' => 'Null',
        'pregnancy_history' => 'Null', 'abortion_history' => 'Null',
        'abortion_details' => 'Null', 'congenital_disorder' => 'Null'
    ];
    return $result ?: $default;
}

/**
 * Get Treatment History
 */
function getTreatmentHistory($pdo, $patient_id) {
    $sql = "SELECT 
        COALESCE(drug_name, 'Null') AS treatment_drug_name,
        COALESCE(custom_drug_name, 'Null') AS custom_drug_name,
        COALESCE(start_date, 'Null') AS treatment_start_date,
        COALESCE(current_dose, 'Null') AS current_dose,
        COALESCE(maximum_dose, 'Null') AS maximum_dose,
        COALESCE(side_effects, 'Null') AS side_effects,
        COALESCE(local_treatment, 'Null') AS local_treatment,
        COALESCE(local_treatment_specify, 'Null') AS local_treatment_specify,
        COALESCE(antibiotics_quinolone, 'Null') AS antibiotics_quinolone,
        COALESCE(antibiotics_metronidazole, 'Null') AS antibiotics_metronidazole,
        COALESCE(calcium, 0) AS calcium_supplement,
        COALESCE(vitamin_d, 0) AS vitamin_d_supplement,
        COALESCE(vitamin_b12, 0) AS vitamin_b12_supplement,
        COALESCE(iron_supplement, 0) AS iron_supplement,
        COALESCE(probiotics, 0) AS probiotics,
        COALESCE(nutritional_supplements, 0) AS nutritional_supplements,
        COALESCE(steroid_dependency, 0) AS steroid_dependency,
        COALESCE(steroid_resistant, 'Null') AS steroid_resistant,
        COALESCE(alt_meds, 0) AS alternative_medicine,
        COALESCE(alt_meds_type, 'Null') AS alternative_medicine_type
    FROM treatments 
    WHERE patient_id = ? 
    ORDER BY id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $default = [
        'treatment_drug_name' => 'Null', 'custom_drug_name' => 'Null', 'treatment_start_date' => 'Null',
        'current_dose' => 'Null', 'maximum_dose' => 'Null', 'side_effects' => 'Null',
        'local_treatment' => 'Null', 'local_treatment_specify' => 'Null', 'antibiotics_quinolone' => 'Null',
        'antibiotics_metronidazole' => 'Null', 'calcium_supplement' => 'Null', 'vitamin_d_supplement' => 'Null',
        'vitamin_b12_supplement' => 'Null', 'iron_supplement' => 'Null', 'probiotics' => 'Null',
        'nutritional_supplements' => 'Null', 'steroid_dependency' => 'Null', 'steroid_resistant' => 'Null',
        'alternative_medicine' => 'Null', 'alternative_medicine_type' => 'Null'
    ];
    
    if ($result) {
        foreach ($result as $key => $value) {
            if ($value === 0) $result[$key] = 'No';
            if ($value === 1) $result[$key] = 'Yes';
        }
    }
    return $result ?: $default;
}

/**
 * Get Investigations
 */
function getInvestigations($pdo, $patient_id) {
    $sql = "SELECT 
        COALESCE(cbc_hb, 'Null') AS cbc_hemoglobin,
        COALESCE(cbc_esr, 'Null') AS esr,
        COALESCE(cbc_tlc, 'Null') AS tlc,
        COALESCE(dlc, 'Null') AS dlc,
        COALESCE(platelets, 'Null') AS platelets,
        COALESCE(crp, 'Null') AS crp,
        COALESCE(s_albumin, 'Null') AS serum_albumin,
        COALESCE(fecal_calprotectin, 'Null') AS fecal_calprotectin,
        COALESCE(upper_git, 'Null') AS upper_git_findings,
        COALESCE(endoscopy, 'Null') AS endoscopy_findings,
        COALESCE(colonoscopy, 'Null') AS colonoscopy_findings,
        COALESCE(ileoscopy, 'Null') AS ileoscopy_findings,
        COALESCE(histopathology, 'Null') AS histopathology_findings,
        COALESCE(usg, 'Null') AS usg_abdomen,
        COALESCE(ct_scan, 'Null') AS ct_scan_findings,
        COALESCE(enterography, 'Null') AS enterography_findings,
        COALESCE(enteroscopy, 'Null') AS enteroscopy_findings
    FROM investigations 
    WHERE patient_id = ? 
    ORDER BY id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $default = [
        'cbc_hemoglobin' => 'Null', 'esr' => 'Null', 'tlc' => 'Null', 'dlc' => 'Null',
        'platelets' => 'Null', 'crp' => 'Null', 'serum_albumin' => 'Null', 'fecal_calprotectin' => 'Null',
        'upper_git_findings' => 'Null', 'endoscopy_findings' => 'Null', 'colonoscopy_findings' => 'Null',
        'ileoscopy_findings' => 'Null', 'histopathology_findings' => 'Null', 'usg_abdomen' => 'Null',
        'ct_scan_findings' => 'Null', 'enterography_findings' => 'Null', 'enteroscopy_findings' => 'Null'
    ];
    return $result ?: $default;
}

/**
 * Get Follow-ups (Latest 5 as concatenated string)
 */
function getFollowups($pdo, $patient_id) {
    $sql = "SELECT 
        DATE_FORMAT(followup_at, '%Y-%m-%d') AS followup_date,
        COALESCE(description, 'Null') AS followup_description,
        COALESCE(treatment, 'Null') AS followup_treatment
    FROM followups 
    WHERE patient_id = ? 
    ORDER BY followup_at DESC LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($followups)) {
        return [
            'followup_dates' => 'Null',
            'followup_descriptions' => 'Null',
            'followup_treatments' => 'Null',
            'total_followups' => '0'
        ];
    }
    
    $dates = [];
    $descriptions = [];
    $treatments = [];
    
    foreach ($followups as $f) {
        $dates[] = $f['followup_date'];
        $descriptions[] = $f['followup_description'];
        $treatments[] = $f['followup_treatment'];
    }
    
    return [
        'followup_dates' => implode(' | ', $dates),
        'followup_descriptions' => implode(' | ', $descriptions),
        'followup_treatments' => implode(' | ', $treatments),
        'total_followups' => count($followups)
    ];
}

/**
 * Get Vaccine History for a patient (supports multiple vaccines per patient)
 * Groups vaccines by batch_id for entries added together
 */
function getVaccineHistory($pdo, $patient_id) {
    $sql = "SELECT 
        pv.vaccine_name,
        pv.dose_number,
        pv.dose_schedule,
        DATE_FORMAT(pv.dose_given_date, '%Y-%m-%d') AS dose_given_date,
        DATE_FORMAT(pv.next_dose_date, '%Y-%m-%d') AS next_dose_date,
        pv.batch_no,
        pv.status,
        pv.remarks,
        vm.vaccine_type,
        vm.schedule_description,
        u.name AS administered_by_name,
        pv.batch_id,
        pv.created_at
    FROM patient_vaccines pv
    LEFT JOIN vaccine_master vm ON vm.vaccine_name = pv.vaccine_name
    LEFT JOIN users u ON u.id = pv.administered_by
    WHERE pv.patient_id = ?
    ORDER BY pv.created_at DESC, pv.id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($vaccines)) {
        return [
            'vaccine_names' => 'Null',
            'vaccine_dose_numbers' => 'Null',
            'vaccine_dose_schedules' => 'Null',
            'vaccine_dose_given_dates' => 'Null',
            'vaccine_next_dose_dates' => 'Null',
            'vaccine_batch_numbers' => 'Null',
            'vaccine_statuses' => 'Null',
            'vaccine_remarks' => 'Null',
            'vaccine_types' => 'Null',
            'vaccine_schedule_descriptions' => 'Null',
            'vaccine_administered_by' => 'Null',
            'vaccine_batch_ids' => 'Null'
        ];
    }
    
    // Build arrays for each field (supporting multiple vaccines)
    $names = [];
    $dose_numbers = [];
    $dose_schedules = [];
    $dose_given_dates = [];
    $next_dose_dates = [];
    $batch_numbers = [];
    $statuses = [];
    $remarks = [];
    $types = [];
    $schedule_descs = [];
    $admin_by = [];
    $batch_ids = [];
    
    foreach ($vaccines as $v) {
        $names[] = formatExportValue($v['vaccine_name']);
        $dose_numbers[] = formatExportValue($v['dose_number'] ?? '-');
        $dose_schedules[] = formatExportValue($v['dose_schedule'] ?? '-');
        $dose_given_dates[] = formatExportValue($v['dose_given_date'] ?? '-');
        $next_dose_dates[] = formatExportValue($v['next_dose_date'] ?? '-');
        $batch_numbers[] = formatExportValue($v['batch_no'] ?? '-');
        $statuses[] = formatExportValue($v['status'] ?? 'Pending');
        $remarks[] = formatExportValue($v['remarks'] ?? '-');
        $types[] = formatExportValue($v['vaccine_type'] ?? '-');
        $schedule_descs[] = formatExportValue($v['schedule_description'] ?? '-');
        $admin_by[] = formatExportValue($v['administered_by_name'] ?? '-');
        $batch_ids[] = formatExportValue($v['batch_id'] ?? '-');
    }
    
    return [
        'vaccine_names' => implode(' | ', $names),
        'vaccine_dose_numbers' => implode(' | ', $dose_numbers),
        'vaccine_dose_schedules' => implode(' | ', $dose_schedules),
        'vaccine_dose_given_dates' => implode(' | ', $dose_given_dates),
        'vaccine_next_dose_dates' => implode(' | ', $next_dose_dates),
        'vaccine_batch_numbers' => implode(' | ', $batch_numbers),
        'vaccine_statuses' => implode(' | ', $statuses),
        'vaccine_remarks' => implode(' | ', $remarks),
        'vaccine_types' => implode(' | ', $types),
        'vaccine_schedule_descriptions' => implode(' | ', $schedule_descs),
        'vaccine_administered_by' => implode(' | ', $admin_by),
        'vaccine_batch_ids' => implode(' | ', $batch_ids)
    ];
}

/**
 * Export data as CSV file
 */
function exportAsCSV($data, $filename, $headers) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    fputcsv($output, $headers);
    
    $sl_no = 1;
    foreach ($data as $row) {
        $csv_row = [$sl_no++];
        foreach ($row as $key => $value) {
            $csv_row[] = formatExportValue($value);
        }
        fputcsv($output, $csv_row);
    }
    
    fclose($output);
}

/**
 * Export complete data as CSV file
 */
function exportCompleteAsCSV($all_export_data, $filename, $headers) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    fputcsv($output, $headers);
    
    $sl_no = 1;
    foreach ($all_export_data as $row) {
        $csv_row = [$sl_no++];
        foreach ($row as $key => $value) {
            $csv_row[] = formatExportValue($value);
        }
        fputcsv($output, $csv_row);
    }
    
    fclose($output);
}

/**
 * Format export value - replace empty values with "Null"
 */
function formatExportValue($value) {
    if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return 'Null';
    }
    if ($value === '0' || $value === 0) return 'No';
    if ($value === '1' || $value === 1) return 'Yes';
    return $value;
}

/**
 * Display the export options page with sidebar and TWO download options
 */
function showExportOptionsPage($pdo) {
    // Get statistics
    $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $last_30_days = $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    $last_7_days = $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    
    // Get due follow-ups count for sidebar
    $due_count = 0;
    try {
        $due_count = $pdo->query("SELECT COUNT(*) FROM followups WHERE DATE(followup_at) <= DATE(NOW())")->fetchColumn();
    } catch (Exception $e) {
        // Silently fail
    }
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
            body { background-color: var(--white); }
            
            /* Sidebar Styles */
            #sidebar { min-width: 260px; height: 100vh; overflow-y: auto; background-color: var(--white); border-right: 1px solid var(--ash); transition: all 0.3s; display: flex; flex-direction: column; }
            .sidebar-header { padding: 20px 16px; border-bottom: 2px solid var(--blue); }
            .sidebar-header .logo-icon { width: 40px; height: 40px; background-color: var(--ash); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
            .sidebar-header .logo-icon i { color: var(--blue); font-size: 1.5rem; }
            .sidebar-header .logo-text { color: var(--black); font-weight: 600; font-size: 1.2rem; }
            .sidebar-header .logo-subtext { color: var(--black); opacity: 0.6; font-size: 0.75rem; }
            .sidebar-content { flex: 1; overflow-y: auto; padding: 16px 12px; }
            .nav-section { margin-bottom: 20px; }
            .nav-section-title { font-size: 0.7rem; text-transform: uppercase; color: var(--black); opacity: 0.5; margin-bottom: 10px; padding-left: 8px; }
            .nav-item { margin-bottom: 2px; }
            .nav-link-custom { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 6px; color: var(--black); text-decoration: none; font-size: 0.95rem; }
            .nav-link-custom:hover { background-color: var(--ash); }
            .nav-link-custom.active { background-color: var(--blue); color: var(--white); }
            .nav-link-custom.active i { color: var(--white) !important; }
            .nav-link-custom i { width: 20px; text-align: center; color: var(--blue); }
            .nav-link-custom .badge { margin-left: auto; background-color: var(--ash); color: var(--black); font-size: 0.65rem; padding: 3px 6px; border-radius: 4px; }
            .sidebar-footer { padding: 16px; border-top: 1px solid var(--ash); text-align: center; }
            .footer-copyright { font-size: 0.75rem; color: var(--black); opacity: 0.6; }
            .footer-version { font-size: 0.7rem; color: var(--blue); font-weight: 600; }
            
            /* Top Navigation */
            .top-nav { background-color: var(--white); border-bottom: 1px solid var(--ash); padding: 12px 20px; position: sticky; top: 0; z-index: 1000; }
            .toggle-btn { background: transparent; border: 1px solid var(--ash); border-radius: 6px; padding: 8px 12px; }
            .toggle-btn i { color: var(--blue); }
            .page-title { font-size: 1.1rem; font-weight: 600; color: var(--black); margin-left: 12px; }
            .user-menu { display: flex; align-items: center; gap: 10px; }
            .user-info { display: flex; align-items: center; gap: 10px; padding: 6px 12px; background-color: var(--ash); border-radius: 6px; cursor: pointer; }
            .user-avatar { width: 32px; height: 32px; background-color: var(--white); border-radius: 4px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--blue); }
            .user-avatar i { color: var(--blue); }
            .user-name { font-size: 0.85rem; font-weight: 600; color: var(--black); }
            .user-role { font-size: 0.7rem; color: var(--black); opacity: 0.6; }
            .main-content { flex-grow: 1; display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
            .content-wrapper { padding: 20px; flex: 1; }
            
            /* Export Page Styles */
            .export-card { max-width: 1200px; margin: 0 auto; background: var(--white); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
            .export-header { background: linear-gradient(135deg, var(--blue) 0%, #357ABD 100%); padding: 30px; text-align: center; color: var(--white); }
            .export-header .logo-icon { width: 70px; height: 70px; background-color: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
            .export-header .logo-icon i { font-size: 2rem; color: var(--white); }
            .export-header h2 { font-weight: 600; margin-bottom: 8px; }
            .export-body { padding: 30px; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .stat-card { background: var(--ash); border-radius: 10px; padding: 20px; text-align: center; }
            .stat-number { font-size: 2rem; font-weight: 700; color: var(--blue); }
            .stat-label { color: var(--black); opacity: 0.7; font-size: 0.85rem; }
            .section-title { font-size: 1.1rem; font-weight: 600; color: var(--black); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--blue); display: inline-block; }
            .filter-section { background: var(--ash); border-radius: 10px; padding: 25px; margin-top: 25px; }
            .form-control, .form-select { border: 1px solid #ddd; border-radius: 8px; padding: 10px 14px; }
            .form-control:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(74,144,226,0.1); }
            .btn-filter { background: var(--blue); border: none; border-radius: 8px; padding: 12px 24px; color: white; font-weight: 600; cursor: pointer; }
            .btn-filter:hover { background: #357ABD; }
            .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--blue); text-decoration: none; margin-top: 20px; }
            .back-link:hover { text-decoration: underline; }
            hr { border-color: var(--ash); margin: 25px 0; }
            .dropdown-menu { border: 1px solid var(--ash); border-radius: 6px; }
            .dropdown-item { font-family: Cambria, serif; color: var(--black); }
            .dropdown-item:hover { background-color: var(--ash); }
            
            /* Two Column Export Options */
            .export-options-row { display: flex; gap: 25px; margin-bottom: 30px; flex-wrap: wrap; }
            .export-option-col { flex: 1; min-width: 280px; }
            .export-type-card { background: var(--ash); border-radius: 12px; padding: 20px; height: 100%; transition: all 0.2s; }
            .export-type-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .export-type-title { font-size: 1.2rem; font-weight: 700; color: var(--blue); margin-bottom: 10px; }
            .export-type-desc { color: var(--black); opacity: 0.7; font-size: 0.85rem; margin-bottom: 15px; }
            .export-type-stats { font-size: 0.8rem; color: var(--black); margin-bottom: 15px; }
            .btn-basic { background: #28a745; border: none; border-radius: 8px; padding: 12px 20px; color: white; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
            .btn-basic:hover { background: #218838; transform: translateY(-2px); color: white; }
            .btn-clinical { background: #17a2b8; border: none; border-radius: 8px; padding: 12px 20px; color: white; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
            .btn-clinical:hover { background: #138496; transform: translateY(-2px); color: white; }
            
            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: var(--ash); }
            ::-webkit-scrollbar-thumb { background: var(--blue); border-radius: 3px; }
            @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr 1fr; } #sidebar { margin-left: -260px; } #sidebar.show { margin-left: 0; } .export-options-row { flex-direction: column; } }
        </style>
    </head>
    <body>
        <div class="d-flex">
            <!-- Sidebar -->
            <nav id="sidebar">
                <div class="sidebar-header d-flex align-items-center gap-3">
                    <div class="logo-icon"><i class="fa-solid fa-hospital-user"></i></div>
                    <div><div class="logo-text">PMRMS</div><div class="logo-subtext">Patient Management System</div></div>
                </div>
                <div class="sidebar-content">
                    <div class="nav-section">
                        <div class="nav-section-title">Main</div>
                        <ul class="list-unstyled"><li class="nav-item"><a class="nav-link-custom" href="?r=dashboard"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a></li></ul>
                    </div>
                    <div class="nav-section">
                        <div class="nav-section-title">Patient Management</div>
                        <ul class="list-unstyled">
                            <li class="nav-item"><a class="nav-link-custom" href="?r=patients/add"><i class="fa-solid fa-user-plus"></i><span>Add Patient</span></a></li>
                            <li class="nav-item"><a class="nav-link-custom" href="?r=patients/manage"><i class="fa-solid fa-magnifying-glass"></i><span>Manage Patients</span></a></li>
                            <li class="nav-item"><a class="nav-link-custom" href="?r=patients/followup"><i class="fa-solid fa-calendar-check"></i><span>Follow-up Patients</span><?php if ($due_count > 0): ?><span class="badge"><?= $due_count ?></span><?php endif; ?></a></li>
                        </ul>
                    </div>
                    <div class="nav-section">
                        <div class="nav-section-title">Administration</div>
                        <ul class="list-unstyled">
                            <li class="nav-item"><a class="nav-link-custom" href="?r=users"><i class="fa-solid fa-users-gear"></i><span>User Management</span></a></li>
                            <li class="nav-item"><a class="nav-link-custom" href="?r=users/roles"><i class="fa-solid fa-key"></i><span>Role Management</span></a></li>
                            <li class="nav-item"><a class="nav-link-custom" href="?r=audit"><i class="fa-solid fa-clipboard-list"></i><span>Audit Trail</span></a></li>
                            <li class="nav-item"><a class="nav-link-custom" href="?r=audit/active"><i class="fa-solid fa-user-clock"></i><span>Active Sessions</span></a></li>
                        </ul>
                    </div>
                    <div class="nav-section">
                        <div class="nav-section-title">Reports & Exports</div>
                        <ul class="list-unstyled">
                            <li class="nav-item"><a class="nav-link-custom" href="?r=reports"><i class="fa-solid fa-chart-pie"></i><span>Reports</span></a></li>
                            <li class="nav-item"><a class="nav-link-custom active" href="?r=exports"><i class="fa-solid fa-file-excel"></i><span>Export to Excel</span></a></li>
                        </ul>
                    </div>
                </div>
                <div class="sidebar-footer">
                    <div class="footer-copyright"><i class="fa-regular fa-copyright"></i> <?= date('Y') ?> DarkBangla</div>
                    <div class="footer-version">Version 2.0.1</div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <div class="main-content">
                <nav class="top-nav d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="toggle-btn" id="toggleSidebar"><i class="fa-solid fa-bars"></i></button>
                        <span class="page-title">Export Patient Data</span>
                    </div>
                    <div class="user-menu">
                        <div class="dropdown">
                            <div class="user-info" data-bs-toggle="dropdown">
                                <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
                                <div class="user-details">
                                    <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                                    <div class="user-role"><?= htmlspecialchars($_SESSION['role_type'] ?? '') ?></div>
                                </div>
                                <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem; opacity: 0.6;"></i>
                            </div>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="?r=profile"><i class="fa-regular fa-id-card me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="?r=logout" onclick="return confirm('Logout?')"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </nav>
                
                <main class="content-wrapper">
                    <div class="export-card">
                        <div class="export-header">
                            <div class="logo-icon"><i class="fa-solid fa-database"></i></div>
                            <h2>Export Patient Data</h2>
                            <p>Choose between Basic Information or Complete Clinical Data Export</p>
                        </div>
                        <div class="export-body">
                            <!-- Statistics -->
                            <div class="stats-grid">
                                <div class="stat-card"><div class="stat-number"><?= number_format($total_patients) ?></div><div class="stat-label">Total Patients</div></div>
                                <div class="stat-card"><div class="stat-number"><?= number_format($last_30_days) ?></div><div class="stat-label">Last 30 Days</div></div>
                                <div class="stat-card"><div class="stat-number"><?= number_format($last_7_days) ?></div><div class="stat-label">Last 7 Days</div></div>
                                <div class="stat-card"><div class="stat-number">2</div><div class="stat-label">Export Options</div></div>
                            </div>
                            
                            <!-- TWO SEPARATE EXPORT OPTIONS -->
                            <div class="export-options-row">
                                <!-- OPTION 1: Basic Patient Information -->
                                <div class="export-option-col">
                                    <div class="export-type-card">
                                        <div class="export-type-title"><i class="fa-solid fa-user me-2"></i> Basic Patient Information</div>
                                        <div class="export-type-desc">Export only patient demographic and contact information. Ideal for administrative and registration purposes.</div>
                                        <div class="export-type-stats"><i class="fa-regular fa-file-excel me-1"></i> 31 Columns | <i class="fa-regular fa-clock me-1"></i> Fast Export</div>
                                        <div class="d-flex flex-column gap-2">
                                            <a href="?r=exports&action=export&section=basic&format=csv&type=all" class="btn-basic"><i class="fa-solid fa-download"></i> Export All Patients (Basic)</a>
                                            <a href="?r=exports&action=export&section=basic&format=csv&type=filtered&date_from=<?= date('Y-m-d', strtotime('-30 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn-basic" style="background: #20c997;"><i class="fa-solid fa-calendar-week"></i> Last 30 Days (Basic)</a>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- OPTION 2: Basic & Clinical Information -->
                                <div class="export-option-col">
                                    <div class="export-type-card">
                                        <div class="export-type-title"><i class="fa-solid fa-stethoscope me-2"></i> Basic & Clinical Information</div>
                                        <div class="export-type-desc">Complete medical records including IBD Diagnosis, Complaints, Treatment, Investigations, Follow-ups, Vaccines and more.</div>
                                        <div class="export-type-stats"><i class="fa-regular fa-file-excel me-1"></i> 149+ Columns | <i class="fa-regular fa-clock me-1"></i> Comprehensive Export</div>
                                        <div class="d-flex flex-column gap-2">
                                            <a href="?r=exports&action=export&section=clinical&format=csv&type=all" class="btn-clinical"><i class="fa-solid fa-download"></i> Export All Patients (Clinical)</a>
                                            <a href="?r=exports&action=export&section=clinical&format=csv&type=filtered&date_from=<?= date('Y-m-d', strtotime('-30 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn-clinical"><i class="fa-solid fa-calendar-week"></i> Last 30 Days (Clinical)</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Custom Filtered Export Section -->
                            <h5 class="section-title"><i class="fa-solid fa-sliders-h me-2" style="color: var(--blue);"></i> Custom Filtered Export</h5>
                            <div class="filter-section">
                                <form method="get" action="">
                                    <input type="hidden" name="r" value="exports">
                                    <input type="hidden" name="action" value="export">
                                    <input type="hidden" name="format" value="csv">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <label class="form-label">Export Type</label>
                                            <select name="section" class="form-select">
                                                <option value="basic">Basic Patient Information Only</option>
                                                <option value="clinical">Basic & Clinical Information (Complete)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Date From</label>
                                            <input type="date" name="date_from" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Date To</label>
                                            <input type="date" name="date_to" class="form-control" value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Search Patient</label>
                                            <input type="text" name="q" class="form-control" placeholder="Search by Name, Patient ID, Contact Number, or IBD Registration No...">
                                        </div>
                                        <div class="col-12 text-end">
                                            <input type="hidden" name="type" value="filtered">
                                            <button type="submit" class="btn-filter"><i class="fa-solid fa-download"></i> Export Filtered Data</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Navigation Links -->
                            <div class="text-center mt-4">
                                <a href="?r=dashboard" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                <a href="?r=patients/manage" class="back-link"><i class="fa-solid fa-users"></i> Manage Patients</a>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>document.getElementById('toggleSidebar').addEventListener('click',function(){document.getElementById('sidebar').classList.toggle('show');});</script>
    </body>
    </html>
    <?php
}
?>