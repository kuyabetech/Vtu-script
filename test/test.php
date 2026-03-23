<?php
// test_db.php
require_once '../config.php';
require_once '../includes/db_connection.php';

echo "<h2>Database Connection Test</h2>";

try {
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connected successfully!<br><br>";
    
    // Check users table
    $result = $db->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "✅ Users table exists<br>";
        
        // Count users
        $result = $db->query("SELECT COUNT(*) as count FROM users");
        $row = $result->fetch_assoc();
        echo "📊 Total users: " . $row['count'] . "<br><br>";
        
        // Show all users
        echo "<h3>Users in database:</h3>";
        $result = $db->query("SELECT id, username, email, role, status FROM users");
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['username'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . $row['role'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "❌ Users table does not exist!<br>";
        echo "Please import the database.sql file<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>