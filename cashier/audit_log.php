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

$query = "SELECT al.*, u.name as user_name, u.role as user_role
          FROM activity_logs al
          JOIN users u ON al.user_id = u.user_id
          {$where_clause}
          ORDER BY al.created_at DESC
          LIMIT 200";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['total_activities'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$user_id]);
$stats['today_activities'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payments WHERE issued_by = ?");
$stmt->execute([$user_id]);
$stats['total_payments'] = $stmt->fetch()['total'];

renderPageStart('My Audit Log', 'cashier', 'audit_log.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Audit Log</h2>
</div>

<!-- Statistics -->
<div class="stats-card-container mb-4">
    <?php echo renderStatsCard('Total Activities', number_format($stats['total_activities']), 'fas fa-list', 'primary'); ?>
    <?php echo renderStatsCard("Today's Activities", $stats['today_activities'], 'fas fa-calendar-day', 'success'); ?>
    <?php echo renderStatsCard('Payments Processed', number_format($stats['total_payments']), 'fas fa-money-bill-wave', 'info'); ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search...">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Activity Logs -->
<div class="row">
    <div class="col-12">
        <?php if (empty($logs)): ?>
            <div class="card"><div class="card-body text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5>No activities found</h5>
            </div></div>
        <?php else: ?>
            <?php foreach($logs as $log): ?>
            <?php $is_my = ($log['user_id'] === $user_id); ?>
            <div class="card mb-3" style="border-left: 4px solid <?php echo $is_my ? '#28a745' : '#ffc107'; ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-2"><?php echo htmlspecialchars($log['action']); ?></h6>
                            <div><?php echo nl2br(htmlspecialchars($log['description'])); ?></div>
                            <small class="text-muted">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['user_name']); ?> (<?php echo ucfirst($log['user_role']); ?>)
                            </small>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-muted"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></div>
                            <div class="text-muted"><?php echo date('g:i A', strtotime($log['created_at'])); ?></div>
                            <small class="text-muted">Log ID: <?php echo htmlspecialchars($log['log_id']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('student_dropdown');
    if (dropdown && !dropdown.contains(e.target)) dropdown.style.display = 'none';
});
</script>

<?php renderPageEnd(); ?>