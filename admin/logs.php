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
          JOIN users u ON al.user_id = u.user_id
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
.edit-description-form {
    display: none;
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

<!-- Filters -->
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
                            <th width="80">Actions</th>
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
                                <strong><?php echo htmlspecialchars($log['user_name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo ucfirst($log['user_role']); ?> | 
                                    <code><?php echo htmlspecialchars($log['user_id']); ?></code>
                                </small>
                            </td>
                            <td>
                                <span class="log-action text-primary">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="log-description">
                                <div class="description-display" id="desc-display-<?php echo $log['id']; ?>">
                                    <?php echo nl2br(htmlspecialchars($log['description'])); ?>
                                </div>
                                <div class="edit-description-form" id="desc-form-<?php echo $log['id']; ?>">
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="edit_description">
                                        <input type="hidden" name="log_id" value="<?php echo $log['log_id']; ?>">
                                        <textarea class="form-control form-control-sm" name="description" rows="2" required><?php echo htmlspecialchars($log['description']); ?></textarea>
                                        <div class="d-flex flex-column gap-1">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary" 
                                                    onclick="cancelEdit(<?php echo $log['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editDescription(<?php echo $log['id']; ?>)"
                                        title="Edit Description">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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
function editDescription(logId) {
    document.getElementById('desc-display-' + logId).style.display = 'none';
    document.getElementById('desc-form-' + logId).style.display = 'block';
}

function cancelEdit(logId) {
    document.getElementById('desc-display-' + logId).style.display = 'block';
    document.getElementById('desc-form-' + logId).style.display = 'none';
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

<?php renderPageEnd(); ?>
