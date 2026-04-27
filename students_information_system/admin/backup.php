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

// Function to create manual backup with ALL tables
function createManualBackup($pdo) {
    $backup_content = "-- Student Information System Database Backup\n";
    $backup_content .= "-- Created on: " . date('Y-m-d H:i:s') . "\n";
    $backup_content .= "-- Database: " . DB_NAME . "\n";
    $backup_content .= "-- Includes all system tables and data\n\n";
    
    // Set SQL modes for compatibility
    $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $backup_content .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $backup_content .= "SET AUTOCOMMIT = 0;\n";
    $backup_content .= "START TRANSACTION;\n\n";
    
    // Get all tables in the database (removed bulk_payment_logs)
    $tables = [
        'users', 'students_info', 'employee_info', 'subjects', 
        'sections', 'student_sections', 'student_subjects',
        'payments', 'payment_installments', 'transaction_history',
        'activity_logs', 'login_attempts', 'class_schedule'
    ];
    
    foreach($tables as $table) {
        try {
            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if (!$stmt->fetch()) {
                $backup_content .= "-- Table `{$table}` does not exist\n\n";
                continue;
            }
            
            // Get table structure
            $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $backup_content .= "-- --------------------------------------------------------\n";
                $backup_content .= "-- Table structure for `{$table}`\n";
                $backup_content .= "-- --------------------------------------------------------\n";
                $backup_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $backup_content .= $row['Create Table'] . ";\n\n";
                
                // Get table data
                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $backup_content .= "-- Dumping data for table `{$table}`\n";
                    $backup_content .= "LOCK TABLES `{$table}` WRITE;\n";
                    
                    foreach($rows as $row) {
                        $columns = array_keys($row);
                        $values = array_map(function($value) use ($pdo) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            // Handle boolean values
                            if (is_bool($value)) {
                                return $value ? '1' : '0';
                            }
                            // Handle numeric values
                            if (is_numeric($value)) {
                                return $value;
                            }
                            // Handle string values with proper escaping
                            return $pdo->quote($value);
                        }, array_values($row));
                        
                        $backup_content .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    
                    $backup_content .= "UNLOCK TABLES;\n\n";
                } else {
                    $backup_content .= "-- Table `{$table}` is empty\n\n";
                }
            }
        } catch(Exception $e) {
            $backup_content .= "-- Error backing up table {$table}: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Add database functions and procedures
    $backup_content .= "-- --------------------------------------------------------\n";
    $backup_content .= "-- Database Functions and Procedures\n";
    $backup_content .= "-- --------------------------------------------------------\n";
    
    try {
        // Backup functions
        $stmt = $pdo->query("SHOW FUNCTION STATUS WHERE Db = '" . DB_NAME . "'");
        $functions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($functions as $func) {
            $stmt = $pdo->query("SHOW CREATE FUNCTION `{$func['Name']}`");
            $create_func = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($create_func) {
                $backup_content .= "DROP FUNCTION IF EXISTS `{$func['Name']}`;\n";
                $backup_content .= "DELIMITER //\n";
                $backup_content .= $create_func['Create Function'] . "//\n";
                $backup_content .= "DELIMITER ;\n\n";
            }
        }
    } catch(Exception $e) {
        $backup_content .= "-- Error backing up functions: " . $e->getMessage() . "\n\n";
    }
    
    // Add indexes and constraints
    $backup_content .= "-- --------------------------------------------------------\n";
    $backup_content .= "-- Indexes and Constraints\n";
    $backup_content .= "-- --------------------------------------------------------\n";
    
    foreach($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM `{$table}`");
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($indexes)) {
                $backup_content .= "-- Indexes for table `{$table}`\n";
                foreach($indexes as $index) {
                    if ($index['Key_name'] !== 'PRIMARY') {
                        $backup_content .= "-- INDEX: {$index['Key_name']} on {$index['Column_name']}\n";
                    }
                }
                $backup_content .= "\n";
            }
        } catch(Exception $e) {
            // Ignore index errors
        }
    }
    
    // Finalize backup
    $backup_content .= "-- --------------------------------------------------------\n";
    $backup_content .= "-- Cleanup and Finalization\n";
    $backup_content .= "-- --------------------------------------------------------\n";
    $backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $backup_content .= "COMMIT;\n";
    $backup_content .= "SET AUTOCOMMIT = 1;\n";
    $backup_content .= "-- Backup completed successfully\n";
    
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

// Get comprehensive database statistics
$stats = [];
try {
    $stmt = $pdo->query("SELECT 
                            (SELECT COUNT(*) FROM users) as users_count,
                            (SELECT COUNT(*) FROM students_info) as students_count,
                            (SELECT COUNT(*) FROM employee_info) as employees_count,
                            (SELECT COUNT(*) FROM payments) as payments_count,
                            (SELECT COUNT(*) FROM payment_installments) as installments_count,
                            (SELECT COUNT(*) FROM transaction_history) as transactions_count,
                            (SELECT COUNT(*) FROM activity_logs) as logs_count,
                            (SELECT COUNT(*) FROM subjects) as subjects_count,
                            (SELECT COUNT(*) FROM sections) as sections_count,
                            (SELECT COUNT(*) FROM student_sections) as student_sections_count,
                            (SELECT COUNT(*) FROM student_subjects) as student_subjects_count,
                            (SELECT COUNT(*) FROM class_schedule) as schedules_count,
                            (SELECT COUNT(*) FROM login_attempts) as login_attempts_count,
                            (SELECT SUM(amount) FROM payments) as total_payments_amount,
                            (SELECT SUM(remaining_balance) FROM payments) as total_outstanding_balance");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $stats = ['error' => $e->getMessage()];
}

// Get database size information
try {
    $stmt = $pdo->query("SELECT 
                            table_schema as 'database_name',
                            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as 'size_mb'
                         FROM information_schema.tables 
                         WHERE table_schema = '" . DB_NAME . "'
                         GROUP BY table_schema");
    $db_size = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['database_size'] = $db_size['size_mb'] ?? 0;
} catch(Exception $e) {
    $stats['database_size'] = 0;
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
.stat-card {
    border-left: 4px solid #007bff;
}
.stat-card.success {
    border-left-color: #28a745;
}
.stat-card.warning {
    border-left-color: #ffc107;
}
.stat-card.danger {
    border-left-color: #dc3545;
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
    <div class="col-md-12">
        <div class="card backup-stats">
            <div class="card-body">
                <h5 class="card-title text-white">
                    <i class="fas fa-database"></i> Database Overview
                </h5>
                <?php if (isset($stats['error'])): ?>
                    <p class="text-white-50">Error loading statistics: <?php echo $stats['error']; ?></p>
                <?php else: ?>
                    <div class="row text-center">
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['users_count']); ?></h4>
                            <small>Total Users</small>
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
                            <h4><?php echo number_format($stats['subjects_count']); ?></h4>
                            <small>Subjects</small>
                        </div>
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['sections_count']); ?></h4>
                            <small>Sections</small>
                        </div>
                        <div class="col-md-2">
                            <h4><?php echo number_format($stats['database_size'], 2); ?> MB</h4>
                            <small>Database Size</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="card-title text-primary">
                    <i class="fas fa-money-bill-wave"></i> Financial Data
                </h6>
                <p class="mb-1"><strong>Total Payments:</strong> ₱<?php echo number_format($stats['total_payments_amount'] ?? 0, 2); ?></p>
                <p class="mb-1"><strong>Outstanding:</strong> ₱<?php echo number_format($stats['total_outstanding_balance'] ?? 0, 2); ?></p>
                <p class="mb-0"><strong>Transactions:</strong> <?php echo number_format($stats['transactions_count']); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <h6 class="card-title text-success">
                    <i class="fas fa-users"></i> User Management
                </h6>
                <p class="mb-1"><strong>Employees:</strong> <?php echo number_format($stats['employees_count']); ?></p>
                <p class="mb-1"><strong>Student Sections:</strong> <?php echo number_format($stats['student_sections_count']); ?></p>
                <p class="mb-0"><strong>Student Subjects:</strong> <?php echo number_format($stats['student_subjects_count']); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <h6 class="card-title text-warning">
                    <i class="fas fa-history"></i> System Logs
                </h6>
                <p class="mb-1"><strong>Activity Logs:</strong> <?php echo number_format($stats['logs_count']); ?></p>
                <p class="mb-1"><strong>Login Attempts:</strong> <?php echo number_format($stats['login_attempts_count']); ?></p>
                <p class="mb-0"><strong>Payment Installments:</strong> <?php echo number_format($stats['installments_count']); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="card-title text-info">
                    <i class="fas fa-cogs"></i> System Data
                </h6>
                <p class="mb-1"><strong>Class Schedules:</strong> <?php echo number_format($stats['schedules_count']); ?></p>
                <p class="mb-0"><strong>Backup Files:</strong> <?php echo count($backup_files); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Backup Information -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h5 class="alert-heading">
                <i class="fas fa-info-circle"></i> Comprehensive Backup Information
            </h5>
            <p class="mb-2"><strong>What's included in backups:</strong></p>
            <div class="row">
                <div class="col-md-6">
                    <ul class="mb-2">
                        <li><strong>User Data:</strong> All users, students, and employee information</li>
                        <li><strong>Academic Data:</strong> Subjects, sections, and student assignments</li>
                        <li><strong>Financial Data:</strong> Payments, transactions, and installments</li>
                        <li><strong>System Logs:</strong> Activity logs and login attempts</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="mb-0">
                        <li><strong>Scheduling:</strong> Class schedules and timetables</li>
                        <li><strong>Relationships:</strong> All student-section and student-subject mappings</li>
                        <li><strong>Database Structure:</strong> Complete table structures and indexes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Existing Backups -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-archive"></i> Existing Backups
                </h5>
                <span class="badge bg-primary"><?php echo count($backup_files); ?> backups</span>
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
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Backup File</th>
                                    <th>Date Created</th>
                                    <th>Size</th>
                                    <th>Age</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($backup_files as $backup): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-archive text-primary me-2"></i>
                                        <code><?php echo htmlspecialchars($backup['name']); ?></code>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', $backup['date']); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $size = $backup['size'];
                                        if ($size >= 1048576) {
                                            echo number_format($size / 1048576, 2) . ' MB';
                                        } else {
                                            echo number_format($size / 1024, 2) . ' KB';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getBackupAgeColor($backup['date']); ?>">
                                            <?php echo timeAgo($backup['date']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="download_backup.php?file=<?php echo urlencode($backup['name']); ?>" 
                                               class="btn btn-outline-primary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button class="btn btn-outline-info" 
                                                    onclick="viewBackupInfo('<?php echo htmlspecialchars($backup['name'], ENT_QUOTES); ?>', <?php echo $backup['size']; ?>, <?php echo $backup['date']; ?>)"
                                                    title="View Info">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="deleteBackup('<?php echo htmlspecialchars($backup['name'], ENT_QUOTES); ?>')"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Backup Modal -->
<div class="modal fade" id="createBackupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Comprehensive Database Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="create_backup">
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-triangle"></i> Backup Scope
                        </h6>
                        <p class="mb-2">This backup will include all system data:</p>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="mb-2">
                                    <li>All user accounts and profiles</li>
                                    <li>Student and employee information</li>
                                    <li>Subjects, sections, and schedules</li>
                                    <li>Payment records and transactions</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li>Activity logs and system events</li>
                                    <li>Student assignments and enrollments</li>
                                    <li>Payment installments</li>
                                    <li>Database structure and relationships</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="fas fa-database"></i> Current Database Statistics
                        </h6>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Total Records:</strong> <?php echo number_format(array_sum([$stats['users_count'], $stats['students_count'], $stats['payments_count'], $stats['subjects_count']])); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Database Size:</strong> <?php echo number_format($stats['database_size'], 2); ?> MB
                            </div>
                            <div class="col-md-4">
                                <strong>Estimated Time:</strong> < 30 seconds
                            </div>
                        </div>
                    </div>
                    
                    <p class="mb-0">The backup will be created with timestamp: <code>backup_<?php echo date('Y-m-d_H-i-s'); ?>.sql</code></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Create Comprehensive Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Backup Info Modal -->
<div class="modal fade" id="backupInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Backup Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Filename:</th>
                        <td id="info-filename"></td>
                    </tr>
                    <tr>
                        <th>Created:</th>
                        <td id="info-date"></td>
                    </tr>
                    <tr>
                        <th>Size:</th>
                        <td id="info-size"></td>
                    </tr>
                    <tr>
                        <th>Age:</th>
                        <td id="info-age"></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
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

function viewBackupInfo(filename, size, timestamp) {
    const date = new Date(timestamp * 1000);
    const sizeFormatted = size >= 1048576 ? 
        (size / 1048576).toFixed(2) + ' MB' : 
        (size / 1024).toFixed(2) + ' KB';
    
    document.getElementById('info-filename').textContent = filename;
    document.getElementById('info-date').textContent = date.toLocaleString();
    document.getElementById('info-size').textContent = sizeFormatted;
    document.getElementById('info-age').textContent = timeAgo(timestamp);
    
    new bootstrap.Modal(document.getElementById('backupInfoModal')).show();
}

function timeAgo(timestamp) {
    const diff = Math.floor((Date.now() / 1000) - timestamp);
    
    if (diff < 60) return diff + ' seconds ago';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' days ago';
    if (diff < 31536000) return Math.floor(diff / 2592000) + ' months ago';
    return Math.floor(diff / 31536000) + ' years ago';
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

function getBackupAgeColor($timestamp) {
    $diff = time() - $timestamp;
    $days = floor($diff / 86400);
    
    if ($days < 7) return 'success';      // Less than 1 week - green
    if ($days < 30) return 'warning';     // 1 week to 1 month - yellow
    return 'danger';                      // More than 1 month - red
}

renderPageEnd(); 
?>