<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../includes/helpers.php';

require_login();

$user = user();

// FIXED: Check if login_time exists in session
$login_time = $_SESSION['login_time'] ?? null;

// Get user sessions
$sessions = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY login_time DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching sessions: " . $e->getMessage());
}

include __DIR__.'/../templates/header.php';
?>

<style>
/* Color Variables - Matching dashboard design */
:root {
    --white: #FFFFFF;
    --ash: #F2F4F8;
    --blue: #4A90E2;
    --green: #2ECC71;
    --red: #e74c3c;
    --black: #000000;
}

/* Profile Card Styling */
.profile-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}
.profile-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(74,144,226,0.1);
}
.profile-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
}
.profile-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
    font-size: 1rem;
}
.profile-card .card-header h6 i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 6px;
    border-radius: 6px;
    margin-right: 8px;
}
.profile-card .card-body {
    padding: 20px;
}

/* Profile Info Items */
.info-item {
    padding: 8px 0;
    border-bottom: 1px solid var(--ash);
}
.info-item:last-child {
    border-bottom: none;
}
.info-label {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}
.info-value {
    color: var(--black);
    font-size: 1rem;
    font-weight: 500;
}

/* Role Badge */
.role-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    background-color: var(--ash);
    color: var(--black);
}
.role-badge.admin {
    background-color: var(--blue);
    color: var(--white);
}

/* Table Styling */
.table-container {
    overflow-x: auto;
}
.sessions-table {
    width: 100%;
    border-collapse: collapse;
}
.sessions-table thead th {
    background-color: var(--ash);
    color: var(--black);
    font-weight: 600;
    font-size: 0.9rem;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 2px solid var(--blue);
}
.sessions-table tbody td {
    color: var(--black);
    padding: 12px 16px;
    border-bottom: 1px solid var(--ash);
}
.sessions-table tbody tr:hover {
    background-color: var(--ash);
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}
.status-badge.active {
    background-color: var(--green);
    color: var(--white);
}
.status-badge.inactive {
    background-color: var(--ash);
    color: var(--black);
}

/* Button Styling */
.btn-danger {
    background-color: var(--red);
    border: 1px solid var(--red);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
    cursor: pointer;
    border: none;
}
.btn-danger:hover {
    background-color: #c0392b;
}
.btn-danger i {
    color: var(--white) !important;
}
</style>

<div class="row">
    <!-- Left Column - Profile Information -->
    <div class="col-md-4">
        <div class="profile-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-user"></i>Profile Information</h6>
            </div>
            <div class="card-body">
                <div class="info-item">
                    <div class="info-label">Name</div>
                    <div class="info-value"><?=htmlspecialchars($user['name'])?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?=htmlspecialchars($user['email'])?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Role</div>
                    <div class="info-value">
                        <span class="role-badge <?= $user['role_type'] == 'Admin' ? 'admin' : '' ?>">
                            <?=htmlspecialchars($user['role_type'])?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Last Login</div>
                    <div class="info-value">
                        <?php 
                        if ($login_time) {
                            echo date('M d, Y h:i A', $login_time);
                        } else {
                            echo 'First login';
                        }
                        ?>
                    </div>
                </div>
                
                <hr style="border-color: var(--ash); margin: 16px 0;">
                
                <a href="?r=logout" class="btn-danger w-100" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fa-solid fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Right Column - Recent Sessions -->
    <div class="col-md-8">
        <div class="profile-card">
            <div class="card-header">
                <h6><i class="fa-solid fa-clock"></i>Recent Sessions</h6>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($sessions)): ?>
                <div class="table-container">
                    <table class="sessions-table">
                        <thead>
                            <tr>
                                <th>Login Time</th>
                                <th>Last Activity</th>
                                <th>IP Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $s): ?>
                            <tr>
                                <td><?= function_exists('format_datetime') ? format_datetime($s['login_time']) : date('M d, Y h:i A', strtotime($s['login_time'])) ?></td>
                                <td><?= function_exists('format_datetime') ? format_datetime($s['last_activity']) : date('M d, Y h:i A', strtotime($s['last_activity'])) ?></td>
                                <td><small><?= htmlspecialchars($s['ip'] ?? 'N/A') ?></small></td>
                                <td>
                                    <?php if (empty($s['logout_time'])): ?>
                                        <span class="status-badge active">
                                            <i class="fa-solid fa-circle me-1" style="font-size: 0.5rem;"></i>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge inactive">
                                            <i class="fa-solid fa-circle-check me-1"></i>Completed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fa-solid fa-clock" style="color: var(--blue); opacity: 0.3; font-size: 3rem; margin-bottom: 12px;"></i>
                    <p style="color: var(--black); opacity: 0.6;">No session history found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>