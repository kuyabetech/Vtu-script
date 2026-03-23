<?php
// create_admin.php
require_once '../config.php';
require_once '../includes/db_connection.php';

echo "<h2>Create Admin User</h2>";

try {
    $db = Database::getInstance()->getConnection();
    
    // First, check if admin exists and delete if needed
    $check = $db->query("SELECT id FROM users WHERE username = 'admin'");
    if ($check->num_rows > 0) {
        echo "⚠️ Admin user already exists. Updating password...<br>";
        
        // Update existing admin
        $password = 'Admin@123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE users SET password = ?, status = 'active' WHERE username = 'admin'");
        $stmt->bind_param("s", $hashedPassword);
        
        if ($stmt->execute()) {
            echo "✅ Admin password updated successfully!<br>";
            echo "Username: admin<br>";
            echo "Password: Admin@123<br>";
        } else {
            echo "❌ Failed to update admin<br>";
        }
    } else {
        // Create new admin
        $username = 'admin1';
        $email = 'admin1@vtuplatform.com';
        $phone = '08010000000';
        $password = 'Admin@123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $first_name = 'Super';
        $last_name = 'Admin';
        $role = 'admin';
        $status = 'active';
        
        $stmt = $db->prepare("INSERT INTO users (username, email, phone, password, first_name, last_name, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $email, $phone, $hashedPassword, $first_name, $last_name, $role, $status);
        
        if ($stmt->execute()) {
            echo "✅ Admin user created successfully!<br>";
            echo "Username: admin1<br>";
            echo "Password: Admin@123<br>";
            
            // Create wallet for admin
            $userId = $stmt->insert_id;
            $walletStmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 1000000)");
            $walletStmt->bind_param("i", $userId);
            $walletStmt->execute();
            echo "✅ Admin wallet created with ₦1,000,000 balance<br>";
            
        } else {
            echo "❌ Failed to create admin: " . $db->error . "<br>";
        }
    }
    
    // Also create a demo user
    $check = $db->query("SELECT id FROM users WHERE username = 'demo'");
    if ($check->num_rows == 0) {
        $username = 'demo';
        $email = 'demo@vtuplatform.com';
        $phone = '08011111111';
        $password = 'Admin@123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $first_name = 'Demo';
        $last_name = 'User';
        $role = 'user';
        $status = 'active';
        
        $stmt = $db->prepare("INSERT INTO users (username, email, phone, password, first_name, last_name, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $email, $phone, $hashedPassword, $first_name, $last_name, $role, $status);
        
        if ($stmt->execute()) {
            echo "<br>✅ Demo user created successfully!<br>";
            echo "Username: demo<br>";
            echo "Password: Admin@123<br>";
            
            // Create wallet for demo
            $userId = $stmt->insert_id;
            $walletStmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 5000)");
            $walletStmt->bind_param("i", $userId);
            $walletStmt->execute();
            echo "✅ Demo wallet created with ₦5,000 balance<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

<br><br>
<a href="../auth/login.php">Go to Login Page</a>