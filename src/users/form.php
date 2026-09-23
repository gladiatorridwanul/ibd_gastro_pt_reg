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
require_once __DIR__.'/../includes/audit.php';
require_once __DIR__.'/../includes/helpers.php';

require_login();

// Check permission
if (!function_exists('has_permission')) {
    die('Permission system not loaded properly.');
}

if (!has_permission($pdo, 'menu.users')) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'You do not have permission to access user management.'
    ];
    header('Location: ?r=dashboard');
    exit;
}

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

// Define flash function if it doesn't exist
if (!function_exists('flash')) {
    function flash($key, $msg = null) {
        if ($msg !== null) {
            $_SESSION['flash'][$key] = $msg;
            return;
        }
        return $_SESSION['flash'][$key] ?? null;
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle delete
if (isset($_GET['delete']) && $id) {
    if (!has_permission($pdo, 'button.user.delete')) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'You do not have permission to delete users.'
        ];
        header('Location: ?r=users');
        exit;
    }
    
    try {
        // Check if trying to delete Admin
        $check = $pdo->prepare("SELECT role_type FROM users WHERE id=?");
        $check->execute([$id]);
        $user = $check->fetch();
        
        if ($user && $user['role_type'] == 'Admin') {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'text' => 'Cannot delete Admin user'
            ];
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            
            if (function_exists('audit')) {
                audit($pdo, 'user.delete', 'users', $id, null);
            }
            
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'text' => 'User deleted successfully'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error deleting user: " . $e->getMessage());
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Database error occurred.'
        ];
    }
    
    header('Location: ?r=users');
    exit;
}

// Get all roles for dropdown
$roles = [];
try {
    $roles = $pdo->query("SELECT name FROM roles ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
    $roles = ['Admin', 'Doctor', 'Medical Staff', 'Receptionist', 'Viewer'];
}

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($id) {
        if (!has_permission($pdo, 'button.user.edit')) {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'text' => 'You do not have permission to edit users.'
            ];
            header('Location: ?r=users');
            exit;
        }
    } else {
        if (!has_permission($pdo, 'button.user.create')) {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'text' => 'You do not have permission to create users.'
            ];
            header('Location: ?r=users');
            exit;
        }
    }
    
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Invalid CSRF token'
        ];
        header('Location: ?r=users/form' . ($id ? '&id=' . $id : ''));
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_type = $_POST['role_type'] ?? 'Doctor';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    
    // Validate
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (!$id && empty($password)) {
        $errors[] = 'Password is required for new users';
    }
    
    // Check email uniqueness
    if (empty($errors)) {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $id ?: 0]);
            if ($check->fetch()) {
                $errors[] = 'Email already exists';
            }
        } catch (PDOException $e) {
            error_log("Error checking email: " . $e->getMessage());
            $errors[] = 'Database error occurred';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($id) {
                // Update existing user
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role_type = ?, is_active = ?, password_hash = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role_type, $is_active, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role_type = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role_type, $is_active, $id]);
                }
                
                if (function_exists('audit')) {
                    audit($pdo, 'user.update', 'users', $id, ['name' => $name, 'email' => $email]);
                }
                
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'User updated successfully'
                ];
            } else {
                // Create new user
                $hash = password_hash($password ?: 'ChangeMe123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, role_type, is_active, password_hash) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $role_type, $is_active, $hash]);
                $new_id = $pdo->lastInsertId();
                
                if (function_exists('audit')) {
                    audit($pdo, 'user.create', 'users', $new_id, ['name' => $name, 'email' => $email]);
                }
                
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'User created successfully'
                ];
            }
            
            header('Location: ?r=users');
            exit;
        } catch (PDOException $e) {
            error_log("Error saving user: " . $e->getMessage());
            $errors[] = 'Database error occurred';
        }
    }
}

// Load user data for editing
$data = ['name' => '', 'email' => '', 'role_type' => 'Doctor', 'is_active' => 1];
if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if (!$data) {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'text' => 'User not found'
            ];
            header('Location: ?r=users');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Database error occurred'
        ];
        header('Location: ?r=users');
        exit;
    }
}

include __DIR__.'/../templates/header.php';
?>

<style>
/* Color Variables */
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

/* Page Header */
.page-header {
    margin-bottom: 24px;
}
.page-header h4 {
    color: var(--black);
    font-weight: 600;
}
.page-header h4 i {
    color: var(--white) !important;
    background-color: var(--blue);
    padding: 8px;
    border-radius: 8px;
    margin-right: 10px;
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

/* Form Card */
.form-card {
    background-color: var(--white);
    border: 1px solid var(--ash);
    border-radius: 8px;
    margin-bottom: 24px;
}
.form-card .card-header {
    background-color: var(--white);
    border-bottom: 1px solid var(--ash);
    padding: 16px 20px;
    border-radius: 8px 8px 0 0;
}
.form-card .card-header h6 {
    color: var(--black);
    font-weight: 600;
    margin: 0;
}
.form-card .card-header h6 i {
    color: var(--blue) !important;
}
.form-card .card-body {
    padding: 20px;
}

/* Form Elements */
.form-label {
    color: var(--black);
    font-weight: 500;
    margin-bottom: 6px;
    font-size: 0.9rem;
}
.form-label .text-danger {
    color: var(--blue) !important;
    opacity: 0.8;
}
.form-control, .form-select {
    font-family: Cambria, serif;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 12px;
    color: var(--black);
    background-color: var(--white);
    transition: all 0.2s;
}
.form-control:focus, .form-select:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
}
.form-control::placeholder {
    color: var(--black);
    opacity: 0.4;
}
.form-text {
    color: var(--black);
    opacity: 0.6;
    font-size: 0.8rem;
    margin-top: 4px;
}
.form-check-input:checked {
    background-color: var(--blue);
    border-color: var(--blue);
}

/* Buttons */
.btn-primary {
    background-color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--white);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
}
.btn-primary:hover {
    background-color: #357ABD;
}
.btn-primary i {
    color: var(--white) !important;
}
.btn-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 10px 20px;
    color: var(--black);
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-secondary:hover {
    background-color: var(--ash);
}
.btn-secondary i {
    color: var(--blue) !important;
}
.btn-outline-secondary {
    background-color: transparent;
    border: 1px solid var(--ash);
    border-radius: 6px;
    padding: 8px 16px;
    color: var(--black);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
}
.btn-outline-secondary:hover {
    background-color: var(--ash);
}
.btn-outline-secondary i {
    color: var(--blue) !important;
}

/* Alert */
.alert {
    background-color: #f8d7da;
    border-left: 4px solid #dc3545;
    color: #721c24;
    border-radius: 4px;
    padding: 12px 16px;
    margin-bottom: 20px;
}
.alert i {
    color: #dc3545 !important;
}
.alert ul {
    margin-bottom: 0;
    padding-left: 20px;
}
</style>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fa-solid fa-user-pen"></i> <?= $id ? 'Edit' : 'Add' ?> User</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="?r=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="?r=users">User Management</a></li>
                    <li class="breadcrumb-item active"><?= $id ? 'Edit' : 'Add' ?> User</li>
                </ol>
            </nav>
        </div>
        <a href="?r=users" class="btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- User Form -->
    <div class="form-card">
        <div class="card-header">
            <h6><i class="fa-regular fa-circle-user me-2"></i><?= $id ? 'Edit' : 'New' ?> User Information</h6>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required 
                               value="<?= htmlspecialchars($data['name']) ?>" placeholder="Enter full name">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required 
                               value="<?= htmlspecialchars($data['email']) ?>" placeholder="user@example.com">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role_type" class="form-select" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= htmlspecialchars($role) ?>" <?= $data['role_type'] == $role ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" <?= $data['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Active Account</label>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Password <?= $id ? '<small class="text-muted">(leave blank to keep current)</small>' : '' ?></label>
                        <input type="password" name="password" class="form-control" <?= !$id ? 'required' : '' ?> 
                               placeholder="<?= !$id ? 'Enter password' : 'New password (optional)' ?>">
                        <?php if (!$id): ?>
                            <small class="form-text">Default will be 'ChangeMe123' if left blank</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-save"></i> <?= $id ? 'Update' : 'Create' ?> User
                    </button>
                    <a href="?r=users" class="btn-secondary">
                        <i class="fa-solid fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>