<?php 
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config
$configPath = __DIR__ . '/../../config/config.php';
if (file_exists($configPath)) {
    $config = require $configPath;
} else {
    $config = ['app' => ['base_url' => '']];
}
$base = $config['app']['base_url'] ?? ''; 

// Get current user for display
$current_user = null;
if (function_exists('user')) {
    $current_user = user();
}

$current_route = $_GET['r'] ?? 'dashboard';

// Get due follow-ups count
$due_count = 0;
if (isset($pdo) && function_exists('has_permission') && has_permission($pdo, 'menu.patients.followup')) {
    try {
        $due_count = $pdo->query("SELECT COUNT(*) FROM followups WHERE DATE(followup_at) <= DATE(NOW())")->fetchColumn();
    } catch (Exception $e) {
        // Silently fail
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PMRMS - Patient Medical Record Management System</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?=$base?>/assets/css/app.css" rel="stylesheet">
<style>
    /* Color Variables - Solid Colors Only */
    :root {
        --white: #FFFFFF;
        --ash: #F2F4F8;
        --blue: #4A90E2;
        --black: #000000;
    }

    /* Global Styles */
    * {
        font-family: Cambria, serif;
    }

    /* Background Colors */
    .bg-white { background-color: var(--white) !important; }
    .bg-ash { background-color: var(--ash) !important; }
    .bg-blue { background-color: var(--blue) !important; }

    /* Text Colors */
    .text-black { color: var(--black) !important; }
    .text-blue { color: var(--blue) !important; }

    /* Icon Colors */
    .icon-blue {
        color: var(--blue) !important;
    }

    /* Sidebar Styles */
    #sidebar {
        min-width: 260px;
        height: 100vh;
        overflow-y: auto;
        background-color: var(--white);
        border-right: 1px solid var(--ash);
        transition: all 0.3s;
        box-shadow: 2px 0 10px rgba(0,0,0,0.02);
        display: flex;
        flex-direction: column;
    }
    #sidebar.d-none {
        margin-left: -260px;
    }
    .sidebar-header {
        padding: 20px 16px;
        border-bottom: 2px solid var(--blue);
        background-color: var(--white);
    }
    .sidebar-header .logo-icon {
        width: 40px;
        height: 40px;
        background-color: var(--ash);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sidebar-header .logo-icon i {
        color: var(--blue) !important;
        font-size: 1.5rem;
    }
    .sidebar-header .logo-text {
        color: var(--black);
        font-weight: 600;
        font-size: 1.2rem;
    }
    .sidebar-header .logo-subtext {
        color: var(--black);
        opacity: 0.6;
        font-size: 0.75rem;
    }
    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 16px 12px;
    }
    .nav-section {
        margin-bottom: 20px;
    }
    .nav-section-title {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--black);
        opacity: 0.5;
        margin-bottom: 10px;
        padding-left: 8px;
    }
    .nav-item {
        margin-bottom: 2px;
    }
    .nav-link-custom {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 6px;
        color: var(--black);
        transition: all 0.2s;
        text-decoration: none;
        font-size: 0.95rem;
    }
    .nav-link-custom:hover {
        background-color: var(--ash);
        color: var(--black);
    }
    .nav-link-custom.active {
        background-color: var(--blue);
        color: var(--white);
    }
    .nav-link-custom.active i {
        color: var(--white) !important;
    }
    .nav-link-custom i {
        width: 20px;
        text-align: center;
        color: var(--blue);
        transition: color 0.2s;
    }
    .nav-link-custom:hover i {
        color: var(--blue);
    }
    .nav-link-custom .badge {
        margin-left: auto;
        background-color: var(--ash);
        color: var(--black);
        font-size: 0.65rem;
        padding: 3px 6px;
        border-radius: 4px;
    }

    /* Sidebar Footer */
    .sidebar-footer {
        padding: 16px;
        border-top: 1px solid var(--ash);
        background-color: var(--white);
        text-align: center;
    }
    .footer-copyright {
        font-size: 0.75rem;
        color: var(--black);
        opacity: 0.6;
        margin-bottom: 4px;
    }
    .footer-version {
        font-size: 0.7rem;
        color: var(--blue);
        font-weight: 600;
    }

    /* Top Navigation Bar */
    .top-nav {
        background-color: var(--white);
        border-bottom: 1px solid var(--ash);
        padding: 12px 20px;
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    .toggle-btn {
        background: transparent;
        border: 1px solid var(--ash);
        border-radius: 6px;
        padding: 8px 12px;
        color: var(--black);
        transition: all 0.2s;
    }
    .toggle-btn:hover {
        background-color: var(--ash);
    }
    .toggle-btn i {
        color: var(--blue) !important;
    }
    .page-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--black);
        margin-left: 12px;
    }
    .user-menu {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 12px;
        background-color: var(--ash);
        border-radius: 6px;
        cursor: pointer;
    }
    .user-avatar {
        width: 32px;
        height: 32px;
        background-color: var(--white);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--blue);
    }
    .user-avatar i {
        color: var(--blue) !important;
        font-size: 0.9rem;
    }
    .user-details {
        line-height: 1.2;
    }
    .user-name {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--black);
    }
    .user-role {
        font-size: 0.7rem;
        color: var(--black);
        opacity: 0.6;
    }

    /* Main Content Area */
    .main-content {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow-y: auto;
        background-color: var(--white);
    }
    .content-wrapper {
        padding: 20px;
        background-color: var(--white);
        flex: 1;
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
        width: 6px;
    }
    ::-webkit-scrollbar-track {
        background: var(--ash);
    }
    ::-webkit-scrollbar-thumb {
        background: var(--blue);
        border-radius: 3px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: var(--blue);
        opacity: 0.8;
    }

    /* Dropdown Menu */
    .dropdown-menu {
        border: 1px solid var(--ash);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border-radius: 6px;
        padding: 8px 0;
    }
    .dropdown-item {
        font-family: Cambria, serif;
        color: var(--black);
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    .dropdown-item:hover {
        background-color: var(--ash);
    }
    .dropdown-item i {
        color: var(--blue) !important;
        width: 18px;
        text-align: center;
    }
    .dropdown-divider {
        border-top-color: var(--ash);
    }

    /* Breadcrumb */
    .breadcrumb {
        background: transparent;
        padding: 0;
        margin: 0;
    }
    .breadcrumb-item a {
        color: var(--blue);
        text-decoration: none;
    }
    .breadcrumb-item.active {
        color: var(--black);
        opacity: 0.6;
    }
    .breadcrumb-item + .breadcrumb-item::before {
        color: var(--ash);
    }
</style>
</head>
<body class="bg-white">
<div class="d-flex">
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header d-flex align-items-center gap-3">
            <div class="logo-icon">
                <i class="fa-solid fa-hospital-user"></i>
            </div>
            <div>
                <div class="logo-text">PMRMS</div>
                <div class="logo-subtext">Patient Management System</div>
            </div>
        </div>

        <!-- Sidebar Content -->
        <div class="sidebar-content">
            <!-- Dashboard -->
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <ul class="list-unstyled">
                    <li class="nav-item">
                        <a class="nav-link-custom <?= $current_route == 'dashboard' ? 'active' : '' ?>" href="?r=dashboard">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Patient Management -->
<?php if (isset($pdo) && function_exists('has_permission') && has_permission($pdo, 'menu.patients')): ?>
<div class="nav-section">
    <div class="nav-section-title">Patient Management</div>
    <ul class="list-unstyled">
        <?php if (has_permission($pdo, 'menu.patients.add') || has_permission($pdo, 'button.patient.add')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= (strpos($current_route, 'patients/add') !== false) ? 'active' : '' ?>" href="?r=patients/add">
                <i class="fa-solid fa-user-plus"></i>
                <span>Add Patient</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (has_permission($pdo, 'menu.patients.manage')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= (strpos($current_route, 'patients/manage') !== false) ? 'active' : '' ?>" href="?r=patients/manage">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Manage Patients</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (has_permission($pdo, 'menu.patients.followup')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= (strpos($current_route, 'patients/followup') !== false) ? 'active' : '' ?>" href="?r=patients/followup">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Follow-up Patients</span>
                <?php if ($due_count > 0): ?>
                <span class="badge"><?= $due_count ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Administration -->
<?php if (isset($pdo) && function_exists('has_permission') && (has_permission($pdo, 'menu.users') || 
          has_permission($pdo, 'menu.roles') || 
          has_permission($pdo, 'menu.audit'))): ?>
<div class="nav-section">
    <div class="nav-section-title">Administration</div>
    <ul class="list-unstyled">
        <?php if (has_permission($pdo, 'menu.users')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= (strpos($current_route, 'users') !== false && $current_route != 'users/roles' && $current_route != 'users/role_permissions' && $current_route != 'users/user_permissions') ? 'active' : '' ?>" href="?r=users">
                <i class="fa-solid fa-users-gear"></i>
                <span>User Management</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (has_permission($pdo, 'menu.roles')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= (strpos($current_route, 'users/roles') !== false) ? 'active' : '' ?>" href="?r=users/roles">
                <i class="fa-solid fa-key"></i>
                <span>Role Management</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (has_permission($pdo, 'menu.audit')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= (strpos($current_route, 'audit') !== false && $current_route != 'audit/active') ? 'active' : '' ?>" href="?r=audit">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Audit Trail</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (has_permission($pdo, 'menu.audit.sessions')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= ($current_route == 'audit/active') ? 'active' : '' ?>" href="?r=audit/active">
                <i class="fa-solid fa-user-clock"></i>
                <span>Active Sessions</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Reports & Exports -->
<?php if (isset($pdo) && function_exists('has_permission') && (has_permission($pdo, 'menu.reports') || has_permission($pdo, 'export.excel'))): ?>
<div class="nav-section">
    <div class="nav-section-title">Reports & Exports</div>
    <ul class="list-unstyled">
        <?php if (has_permission($pdo, 'menu.reports')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= (strpos($current_route, 'reports') !== false) ? 'active' : '' ?>" href="?r=reports">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Reports</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (has_permission($pdo, 'export.excel')): ?>
        <li class="nav-item">
            <a class="nav-link-custom <?= ($current_route == 'exports') ? 'active' : '' ?>" href="?r=exports">
                <i class="fa-solid fa-file-excel"></i>
                <span>Export to Excel</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="footer-copyright">
                <i class="fa-regular fa-copyright"></i> <?=date('Y')?> DarkBangla
            </div>
            <div class="footer-version">
                Version 2.0.1
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Top Navigation Bar -->
        <nav class="top-nav d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="toggle-btn" id="toggleSidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <span class="page-title">
                    <?php
                    $titles = [
                        'dashboard' => 'Dashboard',
                        'patients/add' => 'Add Patient',
                        'patients/manage' => 'Manage Patients',
                        'patients/followup' => 'Follow-up Patients',
                        'patients/profile' => 'Patient Profile',
                        'patients/view' => 'Patient Details',
                        'patients/edit' => 'Edit Patient',
                        'users' => 'User Management',
                        'users/form' => 'User Form',
                        'users/roles' => 'Role Management',
                        'users/role_permissions' => 'Role Permissions',
                        'users/user_permissions' => 'User Permissions',
                        'audit' => 'Audit Trail',
                        'audit/active' => 'Active Users',
                        'reports' => 'Reports',
                        'exports' => 'Export Data',
                        'profile' => 'My Profile',
                    ];
                    
                    echo $titles[$current_route] ?? 'Dashboard';
                    ?>
                </span>
            </div>

            <div class="user-menu">
                <!-- User Dropdown -->
                <div class="dropdown">
                    <div class="user-info" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">
                            <i class="fa-regular fa-user"></i>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?=htmlspecialchars($current_user['name'] ?? 'User')?></div>
                            <div class="user-role"><?=htmlspecialchars($current_user['role_type'] ?? '')?></div>
                        </div>
                        <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem; color: var(--black); opacity: 0.6;"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="?r=profile"><i class="fa-regular fa-id-card me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="?r=logout" onclick="return confirm('Logout?')"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <main class="content-wrapper">