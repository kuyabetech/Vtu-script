<?php
require_once '../config.php';
require_once '../includes/db_connection.php';

$db = db();

echo "<h2>Database Fix Tool</h2>";

// Check current structure
echo "<h3>Current Table Structure:</h3>";
$result = $db->query("DESCRIBE transactions");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Fix the column
echo "<h3>Attempting to fix...</h3>";

try {
    // Check if column exists
    $check = $db->query("SHOW COLUMNS FROM transactions LIKE 'wallet_id'");
    if ($check->num_rows > 0) {
        // Column exists, modify it
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $db->query("ALTER TABLE transactions MODIFY wallet_id INT NULL");
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo "<p style='color:green'>✅ Success! wallet_id column modified to allow NULL</p>";
    } else {
        // Column doesn't exist, add it
        $db->query("ALTER TABLE transactions ADD COLUMN wallet_id INT NULL");
        echo "<p style='color:green'>✅ Success! wallet_id column added</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Show updated structure
echo "<h3>Updated Table Structure:</h3>";
$result = $db->query("DESCRIBE transactions");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><a href='transactions.php?tab=funding'>Go back to admin panel</a></p>";
?>