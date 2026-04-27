<?php

require_once 'init.php';

// Your cloud database credentials
$cloud_config = [
    'host' => 'sql123.infinityfree.com',
    'db' => 'if0_41761335_student_db',
    'user' => 'if0_41761335',
    'pass' => 'YOUR_CLOUD_DB_PASSWORD'
];

function syncToCloud($pdo_local, $cloud_config) {
    try {
        // Connect to cloud database
        $pdo_cloud = new PDO(
            "mysql:host={$cloud_config['host']};dbname={$cloud_config['db']}",
            $cloud_config['user'],
            $cloud_config['pass']
        );
        $pdo_cloud->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Tables to sync
        $tables = ['users', 'students_info', 'payments', 'student_course_completion', 'activity_logs', 'subjects', 'sections', 'student_sections', 'class_schedule'];
        
        $synced_count = 0;
        $errors = [];
        
        foreach ($tables as $table) {
            // Check if table exists
            $check_table = $pdo_local->query("SHOW TABLES LIKE '$table'");
            if ($check_table->rowCount() == 0) continue;
            
            // Get records from last 24 hours
            $stmt = $pdo_local->prepare("SELECT * FROM $table WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $stmt->execute();
            $new_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($new_records)) {
                foreach ($new_records as $record) {
                    try {
                        // Check if already exists
                        $check_stmt = $pdo_cloud->prepare("SELECT id FROM $table WHERE id = ?");
                        $check_stmt->execute([$record['id']]);
                        
                        if (!$check_stmt->fetch()) {
                            // Insert new record
                            $columns = implode(',', array_keys($record));
                            $placeholders = ':' . implode(',:', array_keys($record));
                            $insert = $pdo_cloud->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
                            $insert->execute($record);
                            $synced_count++;
                            echo "✓ Synced $table ID: {$record['id']}\n";
                        }
                    } catch (Exception $e) {
                        $errors[] = "$table: " . $e->getMessage();
                    }
                }
            }
        }
        
        // Log sync
        $log = date('Y-m-d H:i:s') . " - Synced $synced_count records\n";
        if (!empty($errors)) {
            $log .= "Errors: " . implode(', ', $errors) . "\n";
        }
        file_put_contents('sync_log.txt', $log, FILE_APPEND);
        
        return ['success' => true, 'synced' => $synced_count, 'errors' => $errors];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Run sync
echo "Starting sync to cloud...\n";
$result = syncToCloud($pdo, $cloud_config);

if ($result['success']) {
    echo "✅ Sync completed! Synced: " . $result['synced'] . " records\n";
    if (!empty($result['errors'])) {
        echo "⚠️ Errors: " . implode(', ', $result['errors']) . "\n";
    }
} else {
    echo "❌ Sync failed: " . $result['error'] . "\n";
}
?>