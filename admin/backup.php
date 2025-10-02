<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'create_backup':
                try {
                    $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                    $backup_path = __DIR__ . '/../backups/';
                    
                    // Create backups directory if it doesn't exist
                    if (!is_dir($backup_path)) {
                        mkdir($backup_path, 0755, true);
                    }
                    
                    $full_path = $backup_path . $backup_name;
                    
                    // Get database credentials
                    $host = DB_HOST;
                    $username = DB_USER;
                    $password = DB_PASS;
                    $database = DB_NAME;
                    
                    // Create mysqldump command
                    $command = "mysqldump --host={$host} --user={$username}";
                    if (!empty($password)) {
                        $command .= " --password={$password}";
                    }
                    $command .= " {$database} > \"{$full_path}\"";
                    
                    // Execute backup
                    $output = [];
                    $return_var = 0;
                    exec($command, $output, $return_var);
                    
                    if ($return_var === 0 && file_exists($full_path)) {
                        // Log the backup
                        logActivity($_SESSION['user_id'], 'Database Backup', "Created database backup: {$backup_name}");
                        $success = "Backup created successfully: {$backup_name}";
                    } else {
                        // Fallback: Manual backup using PHP
                        $backup_content = createManualBackup($pdo);
                        file_put_contents($full_path, $backup_content);
                        
                        logActivity($_SESSION['user_id'], 'Database Backup', "Created manual database backup: {$backup_name}");
                        $success = "Manual backup created successfully: {$backup_name}";
                    }
                    
                } catch(Exception $e) {
                    $error = 'Failed to create backup: ' . $e->getMessage();
                }
                break;
                
            case 'delete_backup':
                $backup_file = sanitizeInput($_POST['backup_file'] ?? '');
                
                if (empty($backup_file)) {
                    $error = 'Invalid backup file.';
                } else {
                    $backup_path = __DIR__ . '/../backups/' . $backup_file;
                    
                    if (!file_exists($backup_path) || !is_file($backup_path)) {
                        $error = 'Backup file not found.';
                    } elseif (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $backup_file)) {
                        $error = 'Invalid backup file format.';
                    } else {
                        try {
                            unlink($backup_path);
                            logActivity($_SESSION['user_id'], 'Backup Deleted', "Deleted backup file: {$backup_file}");
                            $success = 'Backup file deleted successfully.';
                        } catch(Exception $e) {
                            $error = 'Failed to delete backup: ' . $e->getMessage();
                        }
                    }
                }
                break;
        }
    }
}

// Function to create manual backup
function createManualBackup($pdo) {
    $backup_content = "-- Student Information System Database Backup\n";
    $backup_content .= "-- Created on: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Get all tables
    $tables = [
        'users', 'students_info', 'employee_info', 'subjects', 
        'sections', 'payments', 'activity_logs', 'login_attempts'
    ];
    
    foreach($tables as $table) {
        try {
            // Get table structure
            $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $backup_content .= "\n-- Table structure for `{$table}`\n";
                $backup_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $backup_content .= $row['Create Table'] . ";\n\n";
                
                // Get table data
                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $backup_content .= "-- Data for table `{$table}`\n";
                    
                    foreach($rows as $row) {
                        $columns = array_keys($row);
                        $values = array_map(function($value) use ($pdo) {
                            return $value === null ? 'NULL' : $pdo->quote($value);
                        }, array_values($row));
                        
                        $backup_content .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    
                    $backup_content .= "\n";
                }
            }
        } catch(Exception $e) {
            $backup_content .= "-- Error backing up table {$table}: " . $e->getMessage() . "\n";
        }
    }
    
    return $backup_content;
}

// Get existing backups
$backup_files = [];
$backup_path = __DIR__ . '/../backups/';
if (is_dir($backup_path)) {
    $files = scandir($backup_path);
    foreach($files as $file) {
        if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $file)) {
            $file_path = $backup_path . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'date' => filemtime($file_path),
                'path' => $file_path
            ];
        }
    }
    // Sort by date (newest first)
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Get database statistics
$stats = [];
try {
    $stmt = $pdo->query("SELECT 
                            (SELECT COUNT(*) FROM users) as users_count,
                            (SELECT COUNT(*) FROM students_info) as students_count,
                            (SELECT COUNT(*) FROM payments) as payments_count,
                            (SELECT COUNT(*) FROM activity_logs) as logs_count,
                            (SELECT COUNT(*) FROM subjects) as subjects_count,
                            (SELECT COUNT(*) FROM sections) as sections_count");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $stats = ['error' => $e->getMessage()];
}

renderPageStart('Backup System', 'admin', 'backup.php');
?>

<style>
.backup-card {
    transition: all 0.3s ease;
}
.backup-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.backup-stats {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Database Backup System</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
        <i class="fas fa-download"></i> Create Backup
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<!-- System Information -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card backup-stats">
            <div class="card-body">
                <h5 class="card-title text-white">
                    <i class="fas fa-database"></i> Database Statistics
                </h5>
                <?php if (isset($stats['error'])): ?>
                    <p class="text-white-50">Error loading statistics: <?php echo $stats['error']; ?></p>
                <?php else: ?>
                    <div class="row text-center">
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['users_count']); ?></h4>
                            <small>Users</small>
                        </div>
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['students_count']); ?></h4>
                            <small>Students</small>
                        </div>
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['payments_count']); ?></h4>
                            <small>Payments</small>
                        </div>
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['logs_count']); ?></h4>
                            <small>Logs</small>
                        </div>
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['subjects_count']); ?></h4>
                            <small>Subjects</small>
                        </div>
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['sections_count']); ?></h4>
                            <small>Sections</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">
                    <i class="fas fa-shield-alt text-success"></i> Backup Status
                </h5>
                <h3 class="text-success"><?php echo count($backup_files); ?></h3>
                <p class="text-muted mb-0">Available Backups</p>
                <?php if (!empty($backup_files)): ?>
                    <small class="text-muted">
                        Last: <?php echo date('M j, Y g:i A', $backup_files[0]['date']); ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Backup Information -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h5 class="alert-heading">
                <i class="fas fa-info-circle"></i> Important Information
            </h5>
            <ul class="mb-0">
                <li><strong>Automatic Backups:</strong> It's recommended to create backups regularly, especially before major system updates.</li>
                <li><strong>Storage Location:</strong> Backups are stored in the <code>backups/</code> directory within your application folder.</li>
                <li><strong>Security:</strong> Backup files contain sensitive data. Ensure proper access controls are in place.</li>
                <li><strong>Recovery:</strong> In case of data loss, contact your system administrator to restore from backup.</li>
            </ul>
        </div>
    </div>
</div>

<!-- Existing Backups -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-archive"></i> Existing Backups
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($backup_files)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                        <h5>No backups found</h5>
                        <p class="text-muted">Create your first backup to protect your data.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                            <i class="fas fa-download"></i> Create First Backup
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($backup_files as $backup): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card backup-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-file-archive text-primary"></i>
                                        <?php echo htmlspecialchars($backup['name']); ?>
                                    </h6>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> 
                                            <?php echo date('M j, Y g:i A', $backup['date']); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-hdd"></i> 
                                            <?php echo number_format($backup['size'] / 1024, 2); ?> KB
                                        </small>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="download_backup.php?file=<?php echo urlencode($backup['name']); ?>" 
                                           class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteBackup('<?php echo htmlspecialchars($backup['name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <small class="text-muted">
                                        Age: <?php echo timeAgo($backup['date']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Backup Modal -->
<div class="modal fade" id="createBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Database Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="create_backup">
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-triangle"></i> Important Notes
                        </h6>
                        <ul class="mb-0">
                            <li>This will create a complete backup of all system data</li>
                            <li>The process may take a few moments depending on data size</li>
                            <li>The backup will include all users, payments, logs, and settings</li>
                            <li>Backup files are stored securely on the server</li>
                        </ul>
                    </div>
                    
                    <p>Are you sure you want to create a new backup? The backup will be named automatically with the current date and time.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Create Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteBackup(filename) {
    if (confirm(`Are you sure you want to delete backup "${filename}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_backup">
            <input type="hidden" name="backup_file" value="${filename}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php 
function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

renderPageEnd(); 
?>
