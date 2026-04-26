<?php
// check_db.php - Check cloud database
require_once 'init.php';

echo "<h1>Cloud Database Check</h1>";

try {
    $pdo = getDBConnection();
    echo "✅ Database connected successfully!<br><br>";
    
    // Check users table
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    echo "📊 Total users in cloud: $userCount<br>";
    
    // Check for student user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute(['C26-02-9927-MAN121']);
    $student = $stmt->fetch();
    
    if ($student) {
        echo "✅ Student user FOUND in cloud!<br>";
        echo "Name: " . $student['name'] . "<br>";
    } else {
        echo "❌ Student user NOT FOUND in cloud!<br>";
        echo "You need to import your local database to cloud.<br>";
    }
    
    // Check password
    if ($student) {
        echo "<br>🔐 Password verification:<br>";
        $testPassword = 'rets123';
        if (verifyPassword($testPassword, $student['password'])) {
            echo "✅ Password 'rets123' is CORRECT!<br>";
        } else {
            echo "❌ Password 'rets123' is WRONG!<br>";
            echo "Hash in DB: " . $student['password'] . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>