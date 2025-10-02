<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'change_status':
                $payment_id = intval($_POST['payment_id'] ?? 0);
                $new_status = $_POST['new_status'] ?? '';
                $admin_note = sanitizeInput($_POST['admin_note'] ?? '');
                
                if ($payment_id <= 0) {
                    $error = 'Invalid payment ID.';
                } elseif (!in_array($new_status, ['paid', 'unpaid', 'partial'])) {
                    $error = 'Invalid payment status.';
                } else {
                    try {
                        // Get current payment info
                        $stmt = $pdo->prepare("SELECT p.*, si.name as student_name, u.name as issued_by_name 
                                               FROM payments p 
                                               JOIN students_info si ON p.student_id = si.user_id 
                                               JOIN users u ON p.issued_by = u.user_id 
                                               WHERE p.id = ?");
                        $stmt->execute([$payment_id]);
                        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$payment) {
                            $error = 'Payment not found.';
                        } else {
                            $old_status = $payment['payment_status'];
                            $remaining_balance = ($new_status === 'paid') ? 0 : $payment['remaining_balance'];
                            
                            // Update payment status
                            $stmt = $pdo->prepare("UPDATE payments SET payment_status = ?, remaining_balance = ? WHERE id = ?");
                            $stmt->execute([$new_status, $remaining_balance, $payment_id]);
                            
                            // Log the change
                            $description = "Admin changed payment status for {$payment['student_name']} (Permit: {$payment['permit_number']}) from '{$old_status}' to '{$new_status}'";
                            if ($admin_note) {
                                $description .= ". Admin note: {$admin_note}";
                            }
                            
                            logActivity($_SESSION['user_id'], 'Payment Status Changed', $description);
                            $success = 'Payment status updated successfully.';
                        }
                    } catch(Exception $e) {
                        $error = 'Failed to update payment status: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'add_payment':
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $amount = floatval($_POST['amount'] ?? 0);
                $description = sanitizeInput($_POST['description'] ?? '');
                $school_year = sanitizeInput($_POST['school_year'] ?? '');
                $payment_status = $_POST['payment_status'] ?? 'unpaid';
                
                if (empty($student_id)) {
                    $error = 'Student ID is required.';
                } elseif ($amount <= 0) {
                    $error = 'Amount must be greater than 0.';
                } elseif (empty($description)) {
                    $error = 'Description is required.';
                } else {
                    // Verify student exists
                    $stmt = $pdo->prepare("SELECT name FROM students_info WHERE user_id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                    
                    if (!$student) {
                        $error = 'Student not found.';
                    } else {
                        try {
                            $permit_number = generatePermitNumber();
                            $amount_text = numberToWords($amount) . ' pesos';
                            $remaining_balance = ($payment_status === 'paid') ? 0 : $amount;
                            
                            // Insert payment
                            $stmt = $pdo->prepare("INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)");
                            $stmt->execute([$student_id, $permit_number, $amount, $amount_text, $remaining_balance, $payment_status, $description, $_SESSION['user_id'], $school_year]);
                            
                            logActivity($_SESSION['user_id'], 'Payment Added', "Admin added payment for {$student['name']} - ₱{$amount} - {$description}");
                            $success = "Payment added successfully. Permit Number: {$permit_number}";
                        } catch(Exception $e) {
                            $error = 'Failed to add payment: ' . $e->getMessage();
                        }
                    }
                }
                break;
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$student_filter = $_GET['student'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "p.payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($student_filter)) {
    $where_conditions[] = "p.student_id = ?";
    $params[] = $student_filter;
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
    $where_conditions[] = "(p.description LIKE ? OR p.permit_number LIKE ? OR si.name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get payments with student and issuer information
$query = "SELECT p.*, si.name as student_name, si.program, si.year_level,
                 u.name as issued_by_name, u.role as issued_by_role
          FROM payments p
          JOIN students_info si ON p.student_id = si.user_id
          JOIN users u ON p.issued_by = u.user_id
          {$where_clause}
          ORDER BY p.issued_date DESC, p.id DESC
          LIMIT 200";

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
                       FROM payments p
                       JOIN students_info si ON p.student_id = si.user_id
                       JOIN users u ON p.issued_by = u.user_id
                       {$where_clause}");
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get students for dropdown
$stmt = $pdo->query("SELECT user_id, name FROM students_info ORDER BY name");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Manage Payments', 'admin', 'manage_payments.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Payments</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
        <i class="fas fa-plus"></i> Add Payment
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

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Payments', number_format($summary['total_count']), 'fas fa-file-invoice-dollar', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Amount', '₱' . number_format($summary['total_amount'], 2), 'fas fa-money-bill-wave', 'info'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Paid Amount', '₱' . number_format($summary['paid_amount'], 2), 'fas fa-check-circle', 'success'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Outstanding', '₱' . number_format($summary['unpaid_amount'] + $summary['partial_balance'], 2), 'fas fa-exclamation-triangle', 'warning'); ?>
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
                <label for="student" class="form-label">Student</label>
                <select class="form-select" id="student" name="student">
                    <option value="">All Students</option>
                    <?php foreach($students as $student): ?>
                        <option value="<?php echo htmlspecialchars($student['user_id']); ?>" 
                                <?php echo $student_filter === $student['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['name']); ?>
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
                       placeholder="Description, Permit #, or Student">
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

<!-- Payments Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($payments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                <h5>No payments found</h5>
                <p class="text-muted">No payments match your current filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Permit #</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Balance</th>
                            <th>Description</th>
                            <th>Issued By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $payment): ?>
                        <tr>
                            <td>
                                <small><?php echo date('M j, Y', strtotime($payment['issued_date'])); ?></small>
                            </td>
                            <td><code><?php echo htmlspecialchars($payment['permit_number']); ?></code></td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['student_name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($payment['student_id']); ?>
                                    <?php if ($payment['program']): ?>
                                        | <?php echo htmlspecialchars($payment['program']); ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td>
                                <?php
                                $status_class = match($payment['payment_status']) {
                                    'paid' => 'success',
                                    'unpaid' => 'danger',
                                    'partial' => 'warning',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo ucfirst($payment['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($payment['remaining_balance'] > 0): ?>
                                    <span class="text-danger">₱<?php echo number_format($payment['remaining_balance'], 2); ?></span>
                                <?php else: ?>
                                    <span class="text-success">₱0.00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span title="<?php echo htmlspecialchars($payment['description']); ?>">
                                    <?php echo htmlspecialchars(substr($payment['description'], 0, 30)); ?>
                                    <?php echo strlen($payment['description']) > 30 ? '...' : ''; ?>
                                </span>
                            </td>
                            <td>
                                <small>
                                    <?php echo htmlspecialchars($payment['issued_by_name']); ?><br>
                                    <span class="text-muted"><?php echo ucfirst($payment['issued_by_role']); ?></span>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#paymentModal<?php echo $payment['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" 
                                            onclick="changeStatus(<?php echo $payment['id']; ?>, '<?php echo $payment['payment_status']; ?>', '<?php echo htmlspecialchars($payment['permit_number'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-edit"></i>
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

<!-- Payment Detail Modals -->
<?php foreach($payments as $payment): ?>
<div class="modal fade" id="paymentModal<?php echo $payment['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details - <?php echo $payment['permit_number']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Payment Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th>Permit Number:</th>
                                <td><code><?php echo htmlspecialchars($payment['permit_number']); ?></code></td>
                            </tr>
                            <tr>
                                <th>Amount:</th>
                                <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo match($payment['payment_status']) {
                                        'paid' => 'success',
                                        'unpaid' => 'danger',
                                        'partial' => 'warning',
                                        default => 'secondary'
                                    }; ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Remaining Balance:</th>
                                <td>
                                    <?php if ($payment['remaining_balance'] > 0): ?>
                                        <span class="text-danger">₱<?php echo number_format($payment['remaining_balance'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-success">₱0.00</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Student & Issue Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th>Student:</th>
                                <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Student ID:</th>
                                <td><code><?php echo htmlspecialchars($payment['student_id']); ?></code></td>
                            </tr>
                            <tr>
                                <th>Issue Date:</th>
                                <td><?php echo date('F j, Y', strtotime($payment['issued_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Issued By:</th>
                                <td>
                                    <?php echo htmlspecialchars($payment['issued_by_name']); ?><br>
                                    <small class="text-muted"><?php echo ucfirst($payment['issued_by_role']); ?></small>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($payment['description']): ?>
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

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_payment">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">Student</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach($students as $student): ?>
                                    <option value="<?php echo htmlspecialchars($student['user_id']); ?>">
                                        <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['user_id']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status" required>
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="school_year" class="form-label">School Year</label>
                            <input type="text" class="form-control" id="school_year" name="school_year" 
                                   value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="e.g., Prelim Examination Fee, Tuition Fee, etc." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Status Modal -->
<div class="modal fade" id="changeStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Change Payment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="payment_id" id="change_payment_id">
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">Permit: <span id="change_permit_number"></span></h6>
                        <p class="mb-0">Current Status: <span id="change_current_status" class="badge bg-secondary"></span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_note" class="form-label">Admin Note (Optional)</label>
                        <textarea class="form-control" id="admin_note" name="admin_note" rows="2" 
                                  placeholder="Reason for status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changeStatus(paymentId, currentStatus, permitNumber) {
    document.getElementById('change_payment_id').value = paymentId;
    document.getElementById('change_permit_number').textContent = permitNumber;
    document.getElementById('change_current_status').textContent = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
    document.getElementById('new_status').value = currentStatus;
    
    new bootstrap.Modal(document.getElementById('changeStatusModal')).show();
}
</script>

<?php renderPageEnd(); ?>
