<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Admin Password Reset Tool</h1>";

// Load database connection
require_once __DIR__ . '/../src/includes/db.php';

// Check connection
if (!$pdo) {
    die("Database connection failed!");
}

// First, let's see what's in the users table
try {
    echo "<h2>Current Users in Database:</h2>";
    $stmt = $pdo->query("SELECT id, name, email, role_type, is_active FROM users");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . $user['role_type'] . "</td>";
            echo "<td>" . ($user['is_active'] ? '✅ Yes' : '❌ No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange;'>No users found in database!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? 'admin@example.com';
    $password = $_POST['password'] ?? 'admin123';
    $name = $_POST['name'] ?? 'System Administrator';
    
    // Hash the new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // Update existing user
            $sql = "UPDATE users SET 
                    password_hash = ?, 
                    name = ?, 
                    role_type = 'Admin', 
                    is_active = 1 
                    WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$hashedPassword, $name, $email]);
            
            if ($result) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
                echo "<h3>✅ Admin user updated successfully!</h3>";
                echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
                echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
                echo "<p><strong>Password Hash:</strong> " . $hashedPassword . "</p>";
                echo "</div>";
            }
        } else {
            // Insert new user
            $sql = "INSERT INTO users (name, email, password_hash, role_type, is_active) 
                    VALUES (?, ?, ?, 'Admin', 1)";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$name, $email, $hashedPassword]);
            
            if ($result) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
                echo "<h3>✅ New admin user created successfully!</h3>";
                echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
                echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
                echo "<p><strong>Password Hash:</strong> " . $hashedPassword . "</p>";
                echo "</div>";
            }
        }
        
        // Verify the password works
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h4>🔍 Verification Test:</h4>";
        
        // Fetch the user again
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $storedHash = $user['password_hash'];
            if (password_verify($password, $storedHash)) {
                echo "<p style='color: green;'>✓ Password verification SUCCESSFUL!</p>";
                echo "<p>Your login should now work with:</p>";
                echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
                echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Password verification FAILED!</p>";
                echo "<p>Stored hash: " . $storedHash . "</p>";
                echo "<p>New hash: " . $hashedPassword . "</p>";
            }
        }
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h3>❌ Error:</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Also check the database structure
try {
    echo "<h2>Database Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    // Table might not exist
}

?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    .container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h1 {
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
    }
    form {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
        margin: 20px 0;
    }
    label {
        display: block;
        margin: 10px 0 5px;
        font-weight: bold;
    }
    input[type="text"], input[type="email"], input[type="password"] {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    input[type="submit"] {
        background: #007bff;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }
    input[type="submit"]:hover {
        background: #0056b3;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0;
    }
    th {
        background: #007bff;
        color: white;
        padding: 10px;
    }
    td {
        padding: 8px;
        border: 1px solid #ddd;
    }
</style>

<div class="container">
    <h1>Reset Admin Password</h1>
    
    <form method="post">
        <label for="name">Full Name:</label>
        <input type="text" name="name" id="name" value="System Administrator" required>
        
        <label for="email">Email Address:</label>
        <input type="email" name="email" id="email" value="admin@example.com" required>
        
        <label for="password">New Password:</label>
        <input type="text" name="password" id="password" value="admin123" required>
        
        <input type="submit" value="Reset Admin Password">
    </form>
    
    <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px;">
        <h3>📝 Instructions:</h3>
        <ol>
            <li>Click the "Reset Admin Password" button above</li>
            <li>You should see a success message in green</li>
            <li>Go back to the login page and use the new credentials</li>
            <li>Default: admin@example.com / admin123</li>
        </ol>
    </div>
    
    <div style="margin-top: 20px; text-align: center;">
        <a href="?r=login" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Go to Login Page</a>
    </div>
</div>