<?php
echo "<h1>Database Connection Test</h1>";

$hosts_to_test = [
    'sql123.infinityfree.com',
    'sql1.infinityfree.com', 
    'sql309.infinityfree.com',
    'localhost'
];

$db_user = 'if0_41761335';  
$db_pass = 'passthenword';  
$db_name = 'if0_41761335_student_db';

foreach ($hosts_to_test as $host) {
    echo "<h2>Testing: $host</h2>";
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ CONNECTION SUCCESSFUL!<br>";
        
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll();
        echo "📊 Found " . count($tables) . " tables:<br>";
        foreach ($tables as $table) {
            echo "- " . $table[0] . "<br>";
        }
        
        echo "<strong style='color:green'>USE THIS HOST: $host</strong><br>";
        break; 
        
    } catch (PDOException $e) {
        echo "❌ Failed: " . $e->getMessage() . "<br><br>";
    }
}
?>