<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for cashier-specific logs
$where_conditions = ["(al.user_id = ? OR al.description LIKE ?)"];
$params = [$user_id, "%{$user_id}%"];

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

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get activity logs related to this cashier
$query = "SELECT al.*, u.name as user_name, u.role as user_role
          FROM activity_logs al
          JOIN users u ON al.user_id = u.user_id
          {$where_clause}
          ORDER BY al.created_at DESC
          LIMIT 200";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for this cashier
$stats = [];

// Total activities by this cashier
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['total_activities'] = $stmt->fetch()['total'];

// Activities today
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$user_id]);
$stats['today_activities'] = $stmt->fetch()['total'];

// Payments processed by this cashier
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payments WHERE issued_by = ?");
$stmt->execute([$user_id]);
$stats['total_payments'] = $stmt->fetch()['total'];

// Payments modified by admin (originally by this cashier)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs 
                       WHERE action LIKE '%Payment%' 
                       AND user_id != ? 
                       AND description LIKE ?");
$stmt->execute([$user_id, "%{$user_id}%"]);
$stats['admin_modifications'] = $stmt->fetch()['total'];

renderPageStart('Audit Log', 'cashier', 'audit_log.php');
?>

<style>
.audit-card {
    border-left: 4px solid #007bff;
}
.my-activity {
    border-left-color: #28a745;
}
.admin-modification {
    border-left-color: #ffc107;
}
.activity-description {
    max-width: 400px;
    word-wrap: break-word;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Audit Log</h2>
    <div class="text-muted">
        Showing last 200 activities
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Activities', number_format($stats['total_activities']), 'fas fa-list', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Today\'s Activities', $stats['today_activities'], 'fas fa-calendar-day', 'success'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Payments Processed', number_format($stats['total_payments']), 'fas fa-money-bill-wave', 'info'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Admin Modifications', $stats['admin_modifications'], 'fas fa-edit', 'warning'); ?>
    </div>
</div>

<!-- Information Alert -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h5 class="alert-heading">
                <i class="fas fa-info-circle"></i> Audit Log Information
            </h5>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Green Border:</strong> Activities performed by you</p>
                    <p><strong>Yellow Border:</strong> Your activities modified by admin</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Blue Border:</strong> System activities related to your work</p>
                    <p><strong>Note:</strong> This log shows activities you performed and modifications made to your work</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search description or action">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Activity Logs -->
<div class="row">
    <div class="col-12">
        <?php if (empty($logs)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5>No activities found</h5>
                    <p class="text-muted">No activities match your current filters.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach($logs as $log): ?>
            <?php
            $is_my_activity = ($log['user_id'] === $user_id);
            $is_admin_modification = (!$is_my_activity && strpos($log['description'], $user_id) !== false);
            
            $card_class = 'audit-card';
            if ($is_my_activity) {
                $card_class .= ' my-activity';
            } elseif ($is_admin_modification) {
                $card_class .= ' admin-modification';
            }
            ?>
            <div class="card <?php echo $card_class; ?> mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-<?php echo $is_my_activity ? 'user' : ($is_admin_modification ? 'user-shield' : 'info-circle'); ?>"></i>
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </h6>
                                <span class="badge bg-<?php echo $is_my_activity ? 'success' : ($is_admin_modification ? 'warning' : 'primary'); ?>">
                                    <?php echo $is_my_activity ? 'My Activity' : ($is_admin_modification ? 'Admin Modified' : 'System'); ?>
                                </span>
                            </div>
                            
                            <div class="activity-description">
                                <?php echo nl2br(htmlspecialchars($log['description'])); ?>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> 
                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                    (<?php echo ucfirst($log['user_role']); ?>) |
                                    <code><?php echo htmlspecialchars($log['user_id']); ?></code>
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-md-4 text-end">
                            <div class="text-muted">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j, Y', strtotime($log['created_at'])); ?>
                            </div>
                            <div class="text-muted">
                                <i class="fas fa-clock"></i>
                                <?php echo date('g:i A', strtotime($log['created_at'])); ?>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    Log ID: <code><?php echo htmlspecialchars($log['log_id']); ?></code>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Filter Buttons -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Quick Filters</h6>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('today')">
                        Today
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('week')">
                        This Week
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('month')">
                        This Month
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearFilters()">
                        Clear All
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
    document.querySelector('form').submit();
}

function clearFilters() {
    document.getElementById('date_from').value = '';
    document.getElementById('date_to').value = '';
    document.getElementById('search').value = '';
    document.querySelector('form').submit();
}
</script>

<?php renderPageEnd(); ?>
