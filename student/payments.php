<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('student');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Initialize variables to prevent errors
$payments = [];
$summary = [
    'total_count' => 0,
    'total_amount' => 0,
    'paid_amount' => 0,
    'unpaid_amount' => 0,
    'partial_balance' => 0
];

try {
    // Get filters
    $status_filter = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $search = $_GET['search'] ?? '';

    // Build query
    $where_conditions = ["p.student_id = ?"];
    $params = [$user_id];

    if (!empty($status_filter)) {
        $where_conditions[] = "p.payment_status = ?";
        $params[] = $status_filter;
    }

    if (!empty($date_from)) {
        $where_conditions[] = "p.issued_date >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $where_conditions[] = "p.issued_date <= ?";
        $params[] = $date_to;
    }

    if (!empty($search)) {
        $where_conditions[] = "(p.description LIKE ? OR p.permit_number LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // Get payments
    $query = "SELECT p.*, u.name as issued_by_name, u.role as issued_by_role
              FROM payments p
              JOIN users u ON p.issued_by = u.user_id
              {$where_clause}
              ORDER BY p.issued_date DESC, p.id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total_count,
                            SUM(amount) as total_amount,
                            SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                            SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
                            SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END) as partial_balance
                           FROM payments 
                           {$where_clause}");
    $stmt->execute($params);
    $summary_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($summary_result) {
        $summary = array_merge($summary, $summary_result);
    }

} catch (Exception $e) {
    // Log error but don't show it to user
    error_log("Student payments page error: " . $e->getMessage());
    // Continue execution - the page will show "no payments" message
}

renderPageStart('My Payments', 'student', 'payments.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Payments</h2>
    <div class="text-muted">
        Total: <?php echo $summary['total_count']; ?> payment(s)
    </div>
</div>

<!-- Summary Cards - Only show if we have payments -->
<?php if (!empty($payments)): ?>
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Amount', '₱' . number_format($summary['total_amount'], 2), 'fas fa-money-bill-wave', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Paid Amount', '₱' . number_format($summary['paid_amount'], 2), 'fas fa-check-circle', 'success'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Unpaid Amount', '₱' . number_format($summary['unpaid_amount'], 2), 'fas fa-exclamation-triangle', 'danger'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Partial Balance', '₱' . number_format($summary['partial_balance'], 2), 'fas fa-clock', 'warning'); ?>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>Partial</option>
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
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Description or Permit Number">
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

<!-- Payments Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($payments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                <h5>No payments found</h5>
                <p class="text-muted">
                    <?php 
                    $hasFilters = !empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search);
                    echo $hasFilters 
                        ? 'No payments match your current filters. Try adjusting your search criteria.' 
                        : 'You don\'t have any payments yet.';
                    ?>
                </p>
                <?php if ($hasFilters): ?>
                    <a href="?status=&date_from=&date_to=&search=" class="btn btn-outline-primary">
                        Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Permit #</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Balance</th>
                            <th>Issue Date</th>
                            <th>Issued By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $payment): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($payment['permit_number'] ?? 'N/A'); ?></code></td>
                            <td>
                                <?php echo htmlspecialchars($payment['description'] ?? 'No description'); ?>
                                <?php if (!empty($payment['school_year'])): ?>
                                    <br><small class="text-muted">S.Y. <?php echo htmlspecialchars($payment['school_year']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong>₱<?php echo number_format($payment['amount'] ?? 0, 2); ?></strong></td>
                            <td>
                                <?php
                                $status = $payment['payment_status'] ?? 'unknown';
                                $status_class = match($status) {
                                    'paid' => 'success',
                                    'unpaid' => 'danger',
                                    'partial' => 'warning',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <?php $balance = $payment['remaining_balance'] ?? 0; ?>
                                <?php if ($balance > 0): ?>
                                    <span class="text-danger">₱<?php echo number_format($balance, 2); ?></span>
                                <?php else: ?>
                                    <span class="text-success">₱0.00</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($payment['issued_date']) ? date('M j, Y', strtotime($payment['issued_date'])) : 'N/A'; ?></td>
                            <td>
                                <?php echo htmlspecialchars($payment['issued_by_name'] ?? 'Unknown'); ?>
                                <br><small class="text-muted"><?php echo ucfirst($payment['issued_by_role'] ?? 'unknown'); ?></small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#paymentModal<?php echo $payment['id']; ?>">
                                    <i class="fas fa-eye"></i> View
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

<!-- Payment Detail Modals -->
<?php foreach($payments as $payment): ?>
<div class="modal fade" id="paymentModal<?php echo $payment['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details - <?php echo htmlspecialchars($payment['permit_number'] ?? 'Unknown'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Payment Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Permit Number:</th>
                                <td><code><?php echo htmlspecialchars($payment['permit_number'] ?? 'N/A'); ?></code></td>
                            </tr>
                            <tr>
                                <th>Amount:</th>
                                <td><strong>₱<?php echo number_format($payment['amount'] ?? 0, 2); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Amount in Words:</th>
                                <td><?php echo htmlspecialchars($payment['amount_text'] ?? numberToWords($payment['amount'] ?? 0) . ' pesos'); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <?php
                                    $status = $payment['payment_status'] ?? 'unknown';
                                    $status_class = match($status) {
                                        'paid' => 'success',
                                        'unpaid' => 'danger',
                                        'partial' => 'warning',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Remaining Balance:</th>
                                <td>
                                    <?php $balance = $payment['remaining_balance'] ?? 0; ?>
                                    <?php if ($balance > 0): ?>
                                        <span class="text-danger">₱<?php echo number_format($balance, 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-success">₱0.00</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Issue Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Issue Date:</th>
                                <td><?php echo !empty($payment['issued_date']) ? date('F j, Y', strtotime($payment['issued_date'])) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Issued By:</th>
                                <td><?php echo htmlspecialchars($payment['issued_by_name'] ?? 'Unknown'); ?></td>
                            </tr>
                            <tr>
                                <th>Issued By Role:</th>
                                <td><span class="badge bg-info"><?php echo ucfirst($payment['issued_by_role'] ?? 'unknown'); ?></span></td>
                            </tr>
                            <tr>
                                <th>School Year:</th>
                                <td><?php echo htmlspecialchars($payment['school_year'] ?? 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <th>Last Updated:</th>
                                <td><?php echo !empty($payment['updated_at']) ? date('F j, Y g:i A', strtotime($payment['updated_at'])) : 'N/A'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($payment['description'])): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <div class="alert alert-light">
                            <?php echo nl2br(htmlspecialchars($payment['description'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php renderPageEnd(); ?>