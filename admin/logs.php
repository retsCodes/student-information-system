<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle log actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'edit_description') {
            $log_id = sanitizeInput($_POST['log_id'] ?? '');
            $new_description = sanitizeInput($_POST['description'] ?? '');
            
            if (empty($log_id)) {
                $error = 'Invalid log ID.';
            } elseif (empty($new_description)) {
                $error = 'Description is required.';
            } else {
                try {
                    // Get old description for logging
                    $stmt = $pdo->prepare("SELECT description FROM activity_logs WHERE log_id = ?");
                    $stmt->execute([$log_id]);
                    $old_description = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("UPDATE activity_logs SET description = ? WHERE log_id = ?");
                    $stmt->execute([$new_description, $log_id]);
                    
                    logActivity($_SESSION['user_id'], 'Log Description Updated', 
                               "Updated log {$log_id} description from '{$old_description}' to '{$new_description}'");
                    $success = 'Log description updated successfully.';
                } catch(Exception $e) {
                    $error = 'Failed to update log: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete_log') {
            $log_id = sanitizeInput($_POST['log_id'] ?? '');
            
            if (empty($log_id)) {
                $error = 'Invalid log ID.';
            } else {
                try {
                    // Get log info for confirmation message
                    $stmt = $pdo->prepare("SELECT action, description FROM activity_logs WHERE log_id = ?");
                    $stmt->execute([$log_id]);
                    $log = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$log) {
                        $error = 'Log entry not found.';
                    } else {
                        // Delete the log
                        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE log_id = ?");
                        $stmt->execute([$log_id]);
                        
                        logActivity($_SESSION['user_id'], 'Log Deleted', 
                                   "Deleted log: {$log['action']} - {$log['description']}");
                        $success = 'Log entry deleted successfully.';
                    }
                } catch(Exception $e) {
                    $error = 'Failed to delete log: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'clear_logs') {
            $clear_type = $_POST['clear_type'] ?? 'all';
            $confirmation = $_POST['confirmation'] ?? '';
            
            if ($confirmation !== 'DELETE_LOGS') {
                $error = 'Please type "DELETE_LOGS" to confirm clearing logs.';
            } else {
                try {
                    $where_conditions = [];
                    $params = [];
                    
                    if ($clear_type === 'old') {
                        // Delete logs older than 30 days
                        $where_conditions[] = "created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    } elseif ($clear_type === 'filtered') {
                        // Delete based on current filters
                        $action_filter = $_POST['filter_action'] ?? '';
                        $user_filter = $_POST['filter_user'] ?? '';
                        $date_from = $_POST['filter_date_from'] ?? '';
                        $date_to = $_POST['filter_date_to'] ?? '';
                        
                        if (!empty($action_filter)) {
                            $where_conditions[] = "action LIKE ?";
                            $params[] = "%{$action_filter}%";
                        }
                        
                        if (!empty($user_filter)) {
                            $where_conditions[] = "user_id = ?";
                            $params[] = $user_filter;
                        }
                        
                        if (!empty($date_from)) {
                            $where_conditions[] = "DATE(created_at) >= ?";
                            $params[] = $date_from;
                        }
                        
                        if (!empty($date_to)) {
                            $where_conditions[] = "DATE(created_at) <= ?";
                            $params[] = $date_to;
                        }
                    }
                    // For 'all', no conditions - delete everything
                    
                    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                    
                    // Get count for logging
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs {$where_clause}");
                    $stmt->execute($params);
                    $logs_count = $stmt->fetchColumn();
                    
                    // Delete logs
                    $stmt = $pdo->prepare("DELETE FROM activity_logs {$where_clause}");
                    $stmt->execute($params);
                    $deleted_count = $stmt->rowCount();
                    
                    logActivity($_SESSION['user_id'], 'Logs Cleared', 
                               "Cleared {$deleted_count} logs (type: {$clear_type})");
                    $success = "Successfully cleared {$deleted_count} logs.";
                    
                } catch(Exception $e) {
                    $error = 'Failed to clear logs: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get filters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($action_filter)) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%{$action_filter}%";
}

if (!empty($user_filter)) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(al.description LIKE ? OR al.action LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get activity logs with user information
$query = "SELECT al.*, u.name as user_name, u.role as user_role
          FROM activity_logs al
          LEFT JOIN users u ON al.user_id = u.user_id
          {$where_clause}
          ORDER BY al.created_at DESC
          LIMIT 500";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct actions for filter
$stmt = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$stmt = $pdo->query("SELECT DISTINCT u.user_id, u.name FROM users u 
                     JOIN activity_logs al ON u.user_id = al.user_id 
                     ORDER BY u.name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs");
$stats['total_logs'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()");
$stats['today_logs'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['week_logs'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()");
$stats['active_users_today'] = $stmt->fetch()['total'];

renderPageStart('Activity Logs', 'admin', 'logs.php');
?>

<style>
.log-description {
    max-width: 300px;
    word-wrap: break-word;
}
.log-action {
    font-weight: bold;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Activity Logs</h2>
    <div class="text-muted">
        Showing last 500 logs
    </div>
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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Logs', number_format($stats['total_logs']), 'fas fa-list', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Today\'s Logs', $stats['today_logs'], 'fas fa-calendar-day', 'success'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('This Week', $stats['week_logs'], 'fas fa-calendar-week', 'info'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Active Users Today', $stats['active_users_today'], 'fas fa-users', 'warning'); ?>
    </div>
</div>

<!-- Filters and Clear Logs Button -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All Actions</option>
                    <?php foreach($actions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action); ?>" 
                                <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="user" class="form-label">User</label>
                <select class="form-select" id="user" name="user">
                    <option value="">All Users</option>
                    <?php foreach($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['user_id']); ?>" 
                                <?php echo $user_filter === $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search description or action">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Clear Logs Button -->
        <div class="mt-3 pt-3 border-top">
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                <i class="fas fa-trash"></i> Clear Logs
            </button>
            <small class="text-muted ms-2">Clear logs based on current filters or all logs</small>
        </div>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5>No logs found</h5>
                <p class="text-muted">No activity logs match your current filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th width="120">Date/Time</th>
                            <th width="150">User</th>
                            <th width="150">Action</th>
                            <th>Description</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td>
                                <small>
                                    <?php echo date('M j, Y', strtotime($log['created_at'])); ?><br>
                                    <?php echo date('g:i A', strtotime($log['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($log['user_name']): ?>
                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo ucfirst($log['user_role']); ?> | 
                                        <code><?php echo htmlspecialchars($log['user_id']); ?></code>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">User Deleted</span><br>
                                    <small class="text-muted">
                                        <code><?php echo htmlspecialchars($log['user_id']); ?></code>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="log-action text-primary">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="log-description">
                                <?php echo nl2br(htmlspecialchars($log['description'])); ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <!-- View Button -->
                                    <button class="btn btn-sm btn-outline-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewLogModal<?php echo $log['id']; ?>"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <!-- Delete Button -->
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteLog('<?php echo $log['log_id']; ?>', '<?php echo htmlspecialchars($log['action'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($log['description'], ENT_QUOTES); ?>')"
                                            title="Delete Log">
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

<!-- View Log Modals -->
<?php foreach($logs as $log): ?>
<div class="modal fade" id="viewLogModal<?php echo $log['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="40%">Log ID:</th>
                                <td><code><?php echo htmlspecialchars($log['log_id']); ?></code></td>
                            </tr>
                            <tr>
                                <th>Action:</th>
                                <td><strong><?php echo htmlspecialchars($log['action']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>User:</th>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <strong><?php echo htmlspecialchars($log['user_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo ucfirst($log['user_role']); ?> | 
                                            <code><?php echo htmlspecialchars($log['user_id']); ?></code>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">User Deleted</span><br>
                                        <small class="text-muted">
                                            <code><?php echo htmlspecialchars($log['user_id']); ?></code>
                                        </small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Timing Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="40%">Created:</th>
                                <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <div class="alert alert-light">
                            <?php echo nl2br(htmlspecialchars($log['description'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" 
                        onclick="deleteLog('<?php echo $log['log_id']; ?>', '<?php echo htmlspecialchars($log['action'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($log['description'], ENT_QUOTES); ?>')">
                    <i class="fas fa-trash"></i> Delete Log
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Clear Activity Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="clear_logs">
                    <!-- Pass current filter values -->
                    <input type="hidden" name="filter_action" value="<?php echo htmlspecialchars($action_filter); ?>">
                    <input type="hidden" name="filter_user" value="<?php echo htmlspecialchars($user_filter); ?>">
                    <input type="hidden" name="filter_date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    <input type="hidden" name="filter_date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">Warning: This action cannot be undone!</h6>
                        <p class="mb-0">All cleared logs will be permanently deleted.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="clear_type" class="form-label">Clear Type</label>
                        <select class="form-select" id="clear_type" name="clear_type" required>
                            <option value="all">All Logs</option>
                            <option value="old">Logs Older Than 30 Days</option>
                            <option value="filtered">Current Filtered Results Only</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmation" class="form-label">Confirmation</label>
                        <input type="text" class="form-control" id="confirmation" name="confirmation" 
                               placeholder="Type DELETE_LOGS to confirm" required>
                        <div class="form-text">Type "DELETE_LOGS" to confirm you want to clear the logs.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Clear Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Export Options</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" onclick="exportLogs('csv')">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </button>
                    <button class="btn btn-outline-danger" onclick="exportLogs('pdf')">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </button>
                </div>
                <small class="text-muted">Export current filtered results</small>
            </div>
        </div>
    </div>
</div>

<script>
function deleteLog(logId, action, description) {
    if (confirm(`Are you sure you want to delete this log?\n\nAction: ${action}\nDescription: ${description}\n\nThis action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_log">
            <input type="hidden" name="log_id" value="${logId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportLogs(format) {
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', format);
    
    // Create a temporary link to trigger download
    const exportUrl = 'export_logs.php?' + urlParams.toString();
    window.open(exportUrl, '_blank');
}

// Auto-set date range for common filters
document.addEventListener('DOMContentLoaded', function() {
    // Add quick filter buttons
    const quickFilters = document.createElement('div');
    quickFilters.className = 'mb-3';
    quickFilters.innerHTML = `
        <div class="btn-group" role="group" aria-label="Quick filters">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('today')">Today</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('week')">This Week</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('month')">This Month</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearFilters()">Clear All</button>
        </div>
    `;
    
    document.querySelector('.card-body form').prepend(quickFilters);
});

function setDateRange(range) {
    const today = new Date();
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    switch(range) {
        case 'today':
            const todayStr = today.toISOString().split('T')[0];
            dateFrom.value = todayStr;
            dateTo.value = todayStr;
            break;
        case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(today.getDate() - 7);
            dateFrom.value = weekAgo.toISOString().split('T')[0];
            dateTo.value = today.toISOString().split('T')[0];
            break;
        case 'month':
            const monthAgo = new Date(today);
            monthAgo.setMonth(today.getMonth() - 1);
            dateFrom.value = monthAgo.toISOString().split('T')[0];
            dateTo.value = today.toISOString().split('T')[0];
            break;
    }
    
    // Submit form
    document.querySelector('.card-body form').submit();
}

function clearFilters() {
    // Clear all form inputs
    document.getElementById('action').value = '';
    document.getElementById('user').value = '';
    document.getElementById('date_from').value = '';
    document.getElementById('date_to').value = '';
    document.getElementById('search').value = '';
    
    // Submit form
    document.querySelector('.card-body form').submit();
}
</script>

<?php
renderPageEnd(); 
?>