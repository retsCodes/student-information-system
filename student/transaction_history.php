<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('student');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get filters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for payments
$where_conditions = ["student_id = ?"];
$params = [$user_id];

if (!empty($status_filter)) {
    $where_conditions[] = "payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "issued_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "issued_date <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(description LIKE ? OR permit_number LIKE ? OR amount_text LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get all payment transactions
$query = "SELECT p.*, 
                 u.name as issued_by_name, 
                 u.role as issued_by_role,
                 'payment' as transaction_type,
                 p.amount as transaction_amount,
                 p.issued_date as transaction_date,
                 p.payment_status as status,
                 CONCAT('Permit: ', p.permit_number) as transaction_reference
          FROM payments p
          JOIN users u ON p.issued_by = u.user_id
          {$where_clause}
          ORDER BY p.issued_date DESC, p.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summary_query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
                    SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END) as partial_balance,
                    COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
                    COUNT(CASE WHEN payment_status = 'unpaid' THEN 1 END) as unpaid_count,
                    COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_count
                  FROM payments 
                  {$where_clause}";

$stmt = $pdo->prepare($summary_query);
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle cases where there are no transactions
if (!$summary) {
    $summary = [
        'total_transactions' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'unpaid_amount' => 0,
        'partial_balance' => 0,
        'paid_count' => 0,
        'unpaid_count' => 0,
        'partial_count' => 0
    ];
}

// Calculate payment completion rate
$completion_rate = $summary['total_amount'] > 0 ? 
    (($summary['paid_amount'] + ($summary['total_amount'] - $summary['unpaid_amount'] - $summary['partial_balance'])) / $summary['total_amount']) * 100 : 0;

// Separate transactions by status for better organization
$paid_transactions = array_filter($transactions, fn($t) => $t['status'] === 'paid');
$partial_transactions = array_filter($transactions, fn($t) => $t['status'] === 'partial');
$unpaid_transactions = array_filter($transactions, fn($t) => $t['status'] === 'unpaid');

renderPageStart('Transaction History', 'student', 'transaction_history.php');
?>

<style>
.transaction-card {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}
.transaction-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.payment-paid { 
    border-left-color: #28a745;
    background: linear-gradient(135deg, #f8fff8 0%, #f0fff0 100%);
}
.payment-unpaid { 
    border-left-color: #dc3545;
    background: linear-gradient(135deg, #fff8f8 0%, #fff0f0 100%);
}
.payment-partial { 
    border-left-color: #ffc107;
    background: linear-gradient(135deg, #fffdf8 0%, #fffbf0 100%);
}
.transaction-type-badge {
    font-size: 0.7rem;
    padding: 3px 8px;
}
.amount-paid {
    font-size: 1.1rem;
    font-weight: bold;
}
.payment-breakdown {
    background: #f8f9fa;
    border-radius: 5px;
    padding: 10px;
    margin-top: 10px;
}
.status-section {
    margin-bottom: 30px;
}
.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
}
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Transaction History</h2>
    <div class="text-muted">
        Total: <?php echo $summary['total_transactions']; ?> transaction(s)
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">₱<?php echo number_format($summary['total_amount'], 2); ?></h4>
                        <small>Total Amount</small>
                    </div>
                    <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">₱<?php echo number_format($summary['paid_amount'], 2); ?></h4>
                        <small>Paid Amount</small>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">₱<?php echo number_format($summary['partial_balance'], 2); ?></h4>
                        <small>Pending Balance</small>
                    </div>
                    <i class="fas fa-clock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($completion_rate, 1); ?>%</h4>
                        <small>Payment Completion</small>
                    </div>
                    <i class="fas fa-chart-line fa-2x opacity-50"></i>
                </div>
                <div class="progress mt-2 bg-white bg-opacity-25">
                    <div class="progress-bar bg-white" style="width: <?php echo $completion_rate; ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

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
                       placeholder="Description, Permit #, or Amount in Words">
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

<div class="row">
    <!-- Transactions List -->
    <div class="col-lg-8">
        <!-- Paid Transactions Section -->
        <div class="status-section">
            <div class="section-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>Paid Transactions
                    <span class="badge bg-light text-dark ms-2"><?php echo count($paid_transactions); ?></span>
                </h5>
                <div class="text-success fw-bold">
                    Total Paid: ₱<?php echo number_format($summary['paid_amount'], 2); ?>
                </div>
            </div>
            
            <?php if (empty($paid_transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt fa-3x mb-3"></i>
                    <h5>No Paid Transactions</h5>
                    <p class="text-muted">You don't have any paid transactions yet.</p>
                </div>
            <?php else: ?>
                <?php foreach($paid_transactions as $transaction): ?>
                <div class="card transaction-card payment-paid mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1 text-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                </h6>
                                <div class="row text-muted small">
                                    <div class="col-md-6">
                                        <strong>Reference:</strong> <?php echo htmlspecialchars($transaction['transaction_reference']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Date Paid:</strong> <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                    </div>
                                </div>
                                <?php if ($transaction['school_year']): ?>
                                    <div class="mt-1">
                                        <strong>School Year:</strong> <?php echo htmlspecialchars($transaction['school_year']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <div class="amount-paid text-success">
                                    ₱<?php echo number_format($transaction['transaction_amount'], 2); ?>
                                </div>
                                <span class="badge bg-success transaction-type-badge">
                                    <i class="fas fa-check me-1"></i>Fully Paid
                                </span>
                            </div>
                        </div>
                        
                        <!-- Payment Details -->
                        <div class="payment-breakdown">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Amount Paid:</strong>
                                    <span class="text-success fw-bold">
                                        ₱<?php echo number_format($transaction['transaction_amount'], 2); ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Remaining Balance:</strong>
                                    <span class="text-success fw-bold">₱0.00</span>
                                </div>
                            </div>
                            <?php if ($transaction['amount_text']): ?>
                                <div class="mt-2">
                                    <strong>Amount in Words:</strong>
                                    <em class="text-muted"><?php echo htmlspecialchars($transaction['amount_text']); ?></em>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#transactionModal<?php echo $transaction['id']; ?>">
                                <i class="fas fa-eye me-1"></i> View Full Details
                            </button>
                            <button class="btn btn-sm btn-outline-success">
                                <i class="fas fa-print me-1"></i> Print Receipt
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Partial Payments Section -->
        <div class="status-section">
            <div class="section-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Partial Payments
                    <span class="badge bg-light text-dark ms-2"><?php echo count($partial_transactions); ?></span>
                </h5>
                <div class="text-warning fw-bold">
                    Pending: ₱<?php echo number_format($summary['partial_balance'], 2); ?>
                </div>
            </div>
            
            <?php if (empty($partial_transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-clock fa-3x mb-3"></i>
                    <h5>No Partial Payments</h5>
                    <p class="text-muted">You don't have any partial payments.</p>
                </div>
            <?php else: ?>
                <?php foreach($partial_transactions as $transaction): ?>
                <div class="card transaction-card payment-partial mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1 text-warning">
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                </h6>
                                <div class="row text-muted small">
                                    <div class="col-md-6">
                                        <strong>Reference:</strong> <?php echo htmlspecialchars($transaction['transaction_reference']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Issue Date:</strong> <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="amount-paid text-warning">
                                    ₱<?php echo number_format($transaction['transaction_amount'], 2); ?>
                                </div>
                                <span class="badge bg-warning transaction-type-badge">
                                    <i class="fas fa-clock me-1"></i>Partial
                                </span>
                            </div>
                        </div>
                        
                        <!-- Payment Breakdown -->
                        <div class="payment-breakdown">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Total Amount:</strong>
                                    <span class="fw-bold">₱<?php echo number_format($transaction['transaction_amount'], 2); ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Amount Paid:</strong>
                                    <span class="text-success fw-bold">
                                        ₱<?php echo number_format($transaction['transaction_amount'] - $transaction['remaining_balance'], 2); ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Balance Due:</strong>
                                    <span class="text-danger fw-bold">
                                        ₱<?php echo number_format($transaction['remaining_balance'], 2); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($transaction['amount_text']): ?>
                                <div class="mt-2">
                                    <strong>Amount in Words:</strong>
                                    <em class="text-muted"><?php echo htmlspecialchars($transaction['amount_text']); ?></em>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#transactionModal<?php echo $transaction['id']; ?>">
                                <i class="fas fa-eye me-1"></i> View Details
                            </button>
                            <button class="btn btn-sm btn-outline-success">
                                <i class="fas fa-credit-card me-1"></i> Pay Balance
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Unpaid Transactions Section -->
        <div class="status-section">
            <div class="section-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Unpaid Transactions
                    <span class="badge bg-light text-dark ms-2"><?php echo count($unpaid_transactions); ?></span>
                </h5>
                <div class="text-danger fw-bold">
                    Due: ₱<?php echo number_format($summary['unpaid_amount'], 2); ?>
                </div>
            </div>
            
            <?php if (empty($unpaid_transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-check fa-3x mb-3"></i>
                    <h5>No Unpaid Transactions</h5>
                    <p class="text-muted">Great! You don't have any unpaid transactions.</p>
                </div>
            <?php else: ?>
                <?php foreach($unpaid_transactions as $transaction): ?>
                <div class="card transaction-card payment-unpaid mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1 text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                </h6>
                                <div class="row text-muted small">
                                    <div class="col-md-6">
                                        <strong>Reference:</strong> <?php echo htmlspecialchars($transaction['transaction_reference']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Due Date:</strong> <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="amount-paid text-danger">
                                    ₱<?php echo number_format($transaction['transaction_amount'], 2); ?>
                                </div>
                                <span class="badge bg-danger transaction-type-badge">
                                    <i class="fas fa-times me-1"></i>Unpaid
                                </span>
                            </div>
                        </div>
                        
                        <!-- Payment Details -->
                        <div class="payment-breakdown">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Amount Due:</strong>
                                    <span class="text-danger fw-bold">
                                        ₱<?php echo number_format($transaction['transaction_amount'], 2); ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong>
                                    <span class="text-danger fw-bold">Payment Required</span>
                                </div>
                            </div>
                            <?php if ($transaction['amount_text']): ?>
                                <div class="mt-2">
                                    <strong>Amount in Words:</strong>
                                    <em class="text-muted"><?php echo htmlspecialchars($transaction['amount_text']); ?></em>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#transactionModal<?php echo $transaction['id']; ?>">
                                <i class="fas fa-eye me-1"></i> View Details
                            </button>
                            <button class="btn btn-sm btn-outline-success">
                                <i class="fas fa-credit-card me-1"></i> Pay Now
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics & Quick Actions -->
    <div class="col-lg-4">
        <!-- Transaction Summary -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Transaction Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Total Transactions:</strong>
                    <span class="float-end fw-bold"><?php echo $summary['total_transactions']; ?></span>
                </div>
                <div class="mb-3">
                    <strong>Fully Paid:</strong>
                    <span class="float-end text-success"><?php echo $summary['paid_count']; ?></span>
                </div>
                <div class="mb-3">
                    <strong>Partial Payments:</strong>
                    <span class="float-end text-warning"><?php echo $summary['partial_count']; ?></span>
                </div>
                <div class="mb-3">
                    <strong>Unpaid:</strong>
                    <span class="float-end text-danger"><?php echo $summary['unpaid_count']; ?></span>
                </div>
                <hr>
                <div class="mb-3">
                    <strong>Total Amount:</strong>
                    <span class="float-end fw-bold">₱<?php echo number_format($summary['total_amount'], 2); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Amount Paid:</strong>
                    <span class="float-end text-success">₱<?php echo number_format($summary['paid_amount'], 2); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Pending Balance:</strong>
                    <span class="float-end text-warning">₱<?php echo number_format($summary['partial_balance'], 2); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Amount Due:</strong>
                    <span class="float-end text-danger">₱<?php echo number_format($summary['unpaid_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="payments.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-invoice-dollar me-2"></i>View Payments
                    </a>
                    <button class="btn btn-outline-success" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Statement
                    </button>
                    <button class="btn btn-outline-info" id="exportBtn">
                        <i class="fas fa-download me-2"></i>Export to Excel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Detail Modals -->
<?php foreach($transactions as $transaction): ?>
<div class="modal fade" id="transactionModal<?php echo $transaction['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-receipt me-2"></i>
                    Transaction Details - <?php echo $transaction['permit_number']; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Transaction Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Permit Number:</th>
                                <td><code class="fs-6"><?php echo htmlspecialchars($transaction['permit_number']); ?></code></td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td class="fw-bold"><?php echo htmlspecialchars($transaction['description']); ?></td>
                            </tr>
                            <tr>
                                <th>Total Amount:</th>
                                <td class="fs-5 fw-bold text-primary">
                                    ₱<?php echo number_format($transaction['amount'], 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Amount in Words:</th>
                                <td class="fst-italic text-muted">
                                    <?php echo htmlspecialchars($transaction['amount_text'] ?? numberToWords($transaction['amount']) . ' pesos only'); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Payment Status</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo match($transaction['status']) {
                                        'paid' => 'success',
                                        'unpaid' => 'danger',
                                        'partial' => 'warning',
                                        default => 'secondary'
                                    }; ?> fs-6">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Amount Paid:</th>
                                <td class="text-success fw-bold">
                                    ₱<?php echo number_format($transaction['amount'] - $transaction['remaining_balance'], 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Remaining Balance:</th>
                                <td class="<?php echo $transaction['remaining_balance'] > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">
                                    ₱<?php echo number_format($transaction['remaining_balance'], 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment Completion:</th>
                                <td>
                                    <?php
                                    $completion = $transaction['amount'] > 0 ? (($transaction['amount'] - $transaction['remaining_balance']) / $transaction['amount']) * 100 : 0;
                                    ?>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-<?php echo $completion == 100 ? 'success' : 'warning'; ?>" 
                                             style="width: <?php echo $completion; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($completion, 1); ?>% Complete</small>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Issue Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Issue Date:</th>
                                <td><?php echo date('F j, Y', strtotime($transaction['issued_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>School Year:</th>
                                <td><?php echo htmlspecialchars($transaction['school_year'] ?? 'Not specified'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Issued By</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Name:</th>
                                <td><?php echo htmlspecialchars($transaction['issued_by_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Role:</th>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst($transaction['issued_by_role']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Last Updated:</th>
                                <td><?php echo date('F j, Y g:i A', strtotime($transaction['updated_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($transaction['status'] !== 'paid'): ?>
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-credit-card me-2"></i>Make Payment
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-success">
                        <i class="fas fa-print me-2"></i>Print Receipt
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Export functionality
    document.getElementById('exportBtn').addEventListener('click', function() {
        if (confirm('Export your transaction history to Excel format?')) {
            // In a real implementation, this would make an AJAX call to generate Excel
            alert('Export feature would generate an Excel file with all your transaction data.');
        }
    });

    // Add print functionality for receipts
    document.querySelectorAll('.btn-outline-success').forEach(btn => {
        if (btn.textContent.includes('Print Receipt')) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Receipt printing functionality would be implemented here.');
            });
        }
    });
});
</script>

<?php renderPageEnd(); ?>