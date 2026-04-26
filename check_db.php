<?php
// test_db_connection.php - Upload to your cloud
echo "<h1>Database Connection Test</h1>";

// Try different possible hostnames
$hosts_to_test = [
    'sql123.infinityfree.com',
    'sql1.infinityfree.com', 
    'sql309.infinityfree.com',
    'localhost'
];

$db_user = 'if0_41761335';  // Your database username
$db_pass = 'passthenword';  // PUT YOUR REAL PASSWORD HERE
$db_name = 'if0_41761335_student_db';

foreach ($hosts_to_test as $host) {
    echo "<h2>Testing: $host</h2>";
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ CONNECTION SUCCESSFUL!<br>";
        
        // Test query
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll();
        echo "📊 Found " . count($tables) . " tables:<br>";
        foreach ($tables as $table) {
            echo "- " . $table[0] . "<br>";
        }
        
        echo "<strong style='color:green'>USE THIS HOST: $host</strong><br>";
        break; // Stop testing once we find a working host
        
    } catch (PDOException $e) {
        echo "❌ Failed: " . $e->getMessage() . "<br><br>";
    }
}
?>