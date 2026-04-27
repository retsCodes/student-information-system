<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// Payments processed today
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payments WHERE issued_by = ? AND DATE(issued_date) = CURDATE()");
$stmt->execute([$user_id]);
$stats['payments_today'] = $stmt->fetch()['total'];

// Total amount processed today
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE issued_by = ? AND DATE(issued_date) = CURDATE()");
$stmt->execute([$user_id]);
$stats['amount_today'] = $stmt->fetch()['total'];

// Total unpaid payments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'unpaid'");
$stats['unpaid_payments'] = $stmt->fetch()['total'];

// Recent payments processed by this cashier
$stmt = $pdo->prepare("SELECT p.*, si.name as student_name, si.program, si.year_level
                       FROM payments p
                       JOIN students_info si ON p.student_id = si.user_id
                       WHERE p.issued_by = ?
                       ORDER BY p.issued_date DESC, p.updated_at DESC
                       LIMIT 10");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent paid students
$stmt = $pdo->prepare("SELECT p.*, si.name as student_name
                       FROM payments p
                       JOIN students_info si ON p.student_id = si.user_id
                       WHERE p.issued_by = ? AND p.payment_status = 'paid'
                       ORDER BY p.updated_at DESC
                       LIMIT 5");
$stmt->execute([$user_id]);
$recent_paid = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Cashier Dashboard', 'cashier', 'dashboard.php');
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h4 class="alert-heading">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h4>
            <p class="mb-0">Manage student payments efficiently and track your daily transactions.</p>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-card-container mb-4">
    <?php echo renderStatsCard('Payments Today', $stats['payments_today'], 'fas fa-file-invoice-dollar', 'primary'); ?>
    <?php echo renderStatsCard('Amount Today', '₱' . number_format($stats['amount_today'], 2), 'fas fa-money-bill-wave', 'success'); ?>
    <?php echo renderStatsCard('Total Unpaid', $stats['unpaid_payments'], 'fas fa-exclamation-triangle', 'warning'); ?>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="payments.php" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-plus"></i> Process Payment
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="audit_log.php" class="btn btn-outline-secondary btn-lg w-100">
                            <i class="fas fa-history"></i> View Audit Log
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="../admin/manage_payments.php" class="btn btn-outline-info btn-lg w-100">
                            <i class="fas fa-chart-line"></i> Payment Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Payments -->
    <div class="col-md-7 mb-4">
        <?php
        $content = '';
        if (empty($recent_payments)) {
            $content = '<p class="text-muted">No payments processed yet.</p>';
        } else {
            $content = '<div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Student</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';
            foreach($recent_payments as $p) {
                $status_class = match($p['payment_status']) {
                    'paid' => 'success', 'unpaid' => 'danger', 'partial' => 'warning', default => 'secondary'
                };
                $content .= "<tr>
                    <td><strong>" . htmlspecialchars($p['student_name']) . "</strong><br><small>" . htmlspecialchars($p['student_id']) . "</small></td>
                    <td><strong>₱" . number_format($p['amount'], 2) . "</strong></td>
                    <td><span class='badge bg-{$status_class}'>" . ucfirst($p['payment_status']) . "</span></td>
                    <td>" . date('M j', strtotime($p['issued_date'])) . "</td>
                </tr>";
            }
            $content .= '</tbody></table></div>';
        }
        echo renderCard('Recent Payments', $content, '<a href="payments.php" class="btn btn-primary btn-sm">View All</a>');
        ?>
    </div>
    
    <!-- Recently Paid Students -->
    <div class="col-md-5 mb-4">
        <?php
        $content = '';
        if (empty($recent_paid)) {
            $content = '<p class="text-muted">No recent payments.</p>';
        } else {
            $content = '<div class="list-group list-group-flush">';
            foreach($recent_paid as $p) {
                $content .= '<div class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <strong>' . htmlspecialchars($p['student_name']) . '</strong>
                        <small>' . date('M j, g:i A', strtotime($p['updated_at'])) . '</small>
                    </div>
                    <div>₱' . number_format($p['amount'], 2) . '</div>
                    <small class="text-muted">Permit: ' . htmlspecialchars($p['permit_number']) . '</small>
                </div>';
            }
            $content .= '</div>';
        }
        echo renderCard('Recently Paid', $content);
        ?>
    </div>
</div>

<?php renderPageEnd(); ?>