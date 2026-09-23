<?php
// view.php — Patient details (Section One)

// Database connection (assumes $pdo is available)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection not available');
}

// Expect ?id=...
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Invalid patient id');
}

// --- DB: fetch patient with address joins ---
$sql = "SELECT p.*,
               dv.name  AS perm_div,
               dd.name  AS perm_dist,
               up.name  AS perm_upazila,
               dv2.name AS pres_div,
               dd2.name AS pres_dist,
               up2.name AS pres_upazila,
               u.name   AS created_by_name
        FROM patients p
        LEFT JOIN divisions dv  ON dv.id  = p.perm_division_id
        LEFT JOIN districts dd  ON dd.id  = p.perm_district_id
        LEFT JOIN upazilas up   ON up.id  = p.perm_upazila_id
        LEFT JOIN divisions dv2 ON dv2.id = p.pres_division_id
        LEFT JOIN districts dd2 ON dd2.id = p.pres_district_id
        LEFT JOIN upazilas up2  ON up2.id = p.pres_upazila_id
        LEFT JOIN users u       ON u.id   = p.created_by
        WHERE p.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die('Patient not found');
}

// Calculate BMI if height and weight are available
$bmi = null;
$bmi_category = '';
if (!empty($p['height_cm']) && !empty($p['weight_kg']) && $p['height_cm'] > 0) {
    $height_m = $p['height_cm'] / 100;
    $bmi = round($p['weight_kg'] / ($height_m * $height_m), 1);
    
    if ($bmi < 18.5) $bmi_category = 'Underweight';
    else if ($bmi >= 18.5 && $bmi < 25) $bmi_category = 'Normal';
    else if ($bmi >= 25 && $bmi < 30) $bmi_category = 'Overweight';
    else $bmi_category = 'Obese';
}

include __DIR__ . '/../templates/header.php';
?>

<style>
/* Color Variables - Solid Colors Only */
:root {
    --white: #FFFFFF;
    --ash: #F2F4F8;
    --blue: #4A90E2;
    --black: #000000;
}

/* Page Styles */
* {
    font-family: Cambria, serif;
}
.content-wrapper {
    color: var(--black);
}

/* Page Header */
.page-header {
    margin-bottom: 24px;
}
.page-header h4 {
    color: var(--black);
    font-weight: 600;
}
.page-header h4 i {
    color: var(--blue) !important;
}
.breadcrumb {
    background: transparent;
    padding: 0;
}
.breadcrumb-item.active {
    color: var(--black);
    opacity: 0.6;
}
.breadcrumb-item a {
    color: var(--blue);
    text-decoration: none;
}

/* Detail Cards */
.detail-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 20px;
}
.detail-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.detail-card .card-header h5,
.detail-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.detail-card .card-header h5 i,
.detail-card .card-header h6 i {
    color: var(--blue) !important;
}
.detail-card .card-body {
    padding: 20px;
}

/* Info Rows */
.info-row {
    margin-bottom: 12px;
    display: flex;
    align-items: flex-start;
}
.info-label {
    font-weight: 600;
    color: var(--black);
    width: 150px;
    flex-shrink: 0;
    opacity: 0.8;
}
.info-value {
    color: var(--black);
    flex: 1;
}

/* BMI Badge */
.bmi-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 500;
    margin-left: 10px;
    font-size: 0.85rem;
}
.bmi-underweight { background-color: var(--ash); color: var(--black); }
.bmi-normal { background-color: var(--blue); color: var(--white); }
.bmi-overweight { background-color: var(--ash); color: var(--black); }
.bmi-obese { background-color: var(--blue); color: var(--white); }

/* Address Display */
.address-display {
    background-color: var(--ash);
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
}
.address-display p {
    margin-bottom: 4px;
    color: var(--black);
}
.address-display i {
    color: var(--blue) !important;
    margin-right: 8px;
}

/* Button Styles */
.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: Cambria, serif;
}
.btn-primary:hover {
    background-color: #357ABD;
    border-color: #357ABD;
    color: var(--white);
}
.btn-primary i {
    color: var(--white) !important;
}
.btn-outline-primary {
    background-color: transparent;
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--blue);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: Cambria, serif;
}
.btn-outline-primary:hover {
    background-color: var(--blue);
    color: var(--white);
}
.btn-outline-primary:hover i {
    color: var(--white) !important;
}
.btn-outline-primary i {
    color: var(--blue) !important;
}
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--black);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: Cambria, serif;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
    color: var(--black);
}
.btn-outline-secondary i {
    color: var(--blue) !important;
}

/* Divider */
hr {
    border-top: 1px solid var(--ash);
    margin: 20px 0;
    opacity: 1;
}

/* Text colors */
.text-pink { color: var(--blue) !important; } /* Reused for female icon */
.text-info { color: var(--blue) !important; } /* Reused for male icon */
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fa-solid fa-eye me-2"></i>Patient Details</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard/index">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=patients/manage">Manage Patients</a></li>
                    <li class="breadcrumb-item active">Patient Details</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="?r=patients/edit&id=<?= $id ?>" class="btn-outline-primary">
                <i class="fa-solid fa-pen"></i> Edit
            </a>
            <a href="?r=patients/profile&id=<?= $id ?>" class="btn-outline-primary">
                <i class="fa-solid fa-id-card-clip"></i> Full Profile
            </a>
            <a href="?r=patients/manage" class="btn-outline-secondary">
                <i class="fa-solid fa-table-list"></i> Manage
            </a>
        </div>
    </div>

    <!-- Patient Information Card -->
    <div class="detail-card">
        <div class="card-header">
            <h5><i class="fa-solid fa-user me-2"></i><?= htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Patient ID:</span>
                        <span class="info-value">#<?= htmlspecialchars($p['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IBD Reg No:</span>
                        <span class="info-value"><?= htmlspecialchars($p['ibd_reg_no'] ?? 'Not assigned', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sex:</span>
                        <span class="info-value">
                            <?php if (($p['sex'] ?? '') == 'M'): ?>
                                <i class="fa-solid fa-mars text-info me-1"></i> Male
                            <?php else: ?>
                                <i class="fa-solid fa-venus text-pink me-1"></i> Female
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date of Birth:</span>
                        <span class="info-value"><?= htmlspecialchars($p['dob'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?= htmlspecialchars($p['age'] ?? 'Not calculated', ENT_QUOTES, 'UTF-8') ?> years</span>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Height:</span>
                        <span class="info-value"><?= htmlspecialchars($p['height_cm'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?> cm</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Weight:</span>
                        <span class="info-value"><?= htmlspecialchars($p['weight_kg'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?> kg</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">BMI:</span>
                        <span class="info-value">
                            <?php if ($bmi): ?>
                                <?= $bmi ?>
                                <span class="bmi-badge bmi-<?= strtolower($bmi_category) ?>"><?= $bmi_category ?></span>
                            <?php else: ?>
                                Not calculated
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nationality:</span>
                        <span class="info-value"><?= htmlspecialchars($p['nationality'] ?? 'Bangladesh', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Religion:</span>
                        <span class="info-value"><?= htmlspecialchars($p['religion'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">National ID:</span>
                        <span class="info-value"><?= htmlspecialchars($p['national_id'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?= htmlspecialchars($p['occupation'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Education:</span>
                        <span class="info-value"><?= htmlspecialchars($p['education'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Contact Number:</span>
                        <span class="info-value"><?= htmlspecialchars($p['contact_number'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($p['email'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Address Information Card -->
    <div class="detail-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-location-dot me-2"></i>Address Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="mb-2" style="color: var(--black); font-weight: 600;"><i class="fa-solid fa-location-dot me-2" style="color: var(--blue);"></i>Permanent Address</h6>
                    <div class="address-display">
                        <?php 
                        $perm_parts = [];
                        if (!empty($p['perm_address'])) $perm_parts[] = htmlspecialchars($p['perm_address'], ENT_QUOTES, 'UTF-8');
                        if (!empty($p['perm_upazila'])) $perm_parts[] = htmlspecialchars($p['perm_upazila'], ENT_QUOTES, 'UTF-8');
                        if (!empty($p['perm_dist'])) $perm_parts[] = htmlspecialchars($p['perm_dist'], ENT_QUOTES, 'UTF-8');
                        if (!empty($p['perm_div'])) $perm_parts[] = htmlspecialchars($p['perm_div'], ENT_QUOTES, 'UTF-8');
                        echo !empty($perm_parts) ? '<p class="mb-0">' . implode(', ', $perm_parts) . '</p>' : '<p class="mb-0">Not provided</p>';
                        ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6 class="mb-2" style="color: var(--black); font-weight: 600;"><i class="fa-solid fa-location-dot me-2" style="color: var(--blue);"></i>Present Address</h6>
                    <div class="address-display">
                        <?php 
                        $pres_parts = [];
                        if (!empty($p['pres_address'])) $pres_parts[] = htmlspecialchars($p['pres_address'], ENT_QUOTES, 'UTF-8');
                        if (!empty($p['pres_upazila'])) $pres_parts[] = htmlspecialchars($p['pres_upazila'], ENT_QUOTES, 'UTF-8');
                        if (!empty($p['pres_dist'])) $pres_parts[] = htmlspecialchars($p['pres_dist'], ENT_QUOTES, 'UTF-8');
                        if (!empty($p['pres_div'])) $pres_parts[] = htmlspecialchars($p['pres_div'], ENT_QUOTES, 'UTF-8');
                        echo !empty($pres_parts) ? '<p class="mb-0">' . implode(', ', $pres_parts) . '</p>' : '<p class="mb-0">Not provided</p>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Family Information Card -->
    <div class="detail-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-family me-2"></i>Family Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="info-row">
                        <span class="info-label">Father/Husband:</span>
                        <span class="info-value"><?= htmlspecialchars($p['father_husband_name'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-row">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?= htmlspecialchars($p['father_husband_occupation'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-row">
                        <span class="info-label">Mother:</span>
                        <span class="info-value"><?= htmlspecialchars($p['mother_name'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-row">
                        <span class="info-label">Occupation:</span>
                        <span class="info-value"><?= htmlspecialchars($p['mother_occupation'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information Card -->
    <div class="detail-card">
        <div class="card-header">
            <h6><i class="fa-solid fa-info-circle me-2"></i>System Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="info-row">
                        <span class="info-label">Created At:</span>
                        <span class="info-value"><?= htmlspecialchars($p['created_at'] ?? 'Not available', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-row">
                        <span class="info-label">Created By:</span>
                        <span class="info-value"><?= htmlspecialchars($p['created_by_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="mt-3">
        <a href="?r=patients/manage" class="btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Manage Patients
        </a>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>