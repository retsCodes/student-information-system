<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user has required role
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'cashier'])) {
    // Log the access attempt
    logActivity($_SESSION['user_id'], 'Access Denied', "User tried to access payment_details.php without proper role");
    header('Location: access_denied.php');
    exit;
}

$pdo = getDBConnection();

// Get payment_id from URL
$payment_id = $_GET['payment_id'] ?? '';

if (empty($payment_id)) {
    $_SESSION['error'] = 'Payment ID is required.';
    header('Location: manage_payments.php');
    exit;
}

// Get payment details with student and issuer information
$stmt = $pdo->prepare("
    SELECT p.*, 
           si.name as student_name, 
           si.program, 
           si.year_level,
           si.student_type,
           si.enrollment_status,
           u.name as issued_by_name, 
           u.role as issued_by_role
    FROM payments p
    JOIN students_info si ON p.student_id = si.user_id
    JOIN users u ON p.issued_by = u.user_id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    $_SESSION['error'] = 'Payment not found.';
    header('Location: manage_payments.php');
    exit;
}

// Get payment activity log
$stmt = $pdo->prepare("
    SELECT action, description, created_at 
    FROM activity_logs 
    WHERE description LIKE ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$search_term = "%{$payment['permit_number']}%";
$stmt->execute([$search_term]);
$activity_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Payment Details - ' . $payment['permit_number'], $user_role, 'manage_payments.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Payment Details</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="manage_payments.php">Manage Payments</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($payment['permit_number']); ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <a href="manage_payments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Payments
        </a>
        <?php if ($user_role === 'admin'): ?>
        <button class="btn btn-warning" onclick="editPaymentFromDetails('<?php echo $payment['id']; ?>')">
            <i class="fas fa-edit"></i> Edit Payment
        </button>
        <?php endif; ?>
        <button class="btn btn-info" onclick="printPaymentDetails()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="row">
    <!-- Payment Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-receipt me-2"></i>Payment Information
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="40%">Permit Number:</th>
                        <td><code class="fs-5"><?php echo htmlspecialchars($payment['permit_number']); ?></code></td>
                    </tr>
                    <tr>
                        <th>Amount:</th>
                        <td><strong class="fs-5 text-success">₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $payment['payment_status'] === 'paid' ? 'success' : 
                                    ($payment['payment_status'] === 'partial' ? 'warning' : 'danger'); 
                            ?> fs-6">
                                <?php echo ucfirst($payment['payment_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Remaining Balance:</th>
                        <td>
                            <?php if ($payment['remaining_balance'] > 0): ?>
                                <span class="text-danger fs-6">₱<?php echo number_format($payment['remaining_balance'], 2); ?></span>
                            <?php else: ?>
                                <span class="text-success fs-6">₱0.00</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo htmlspecialchars($payment['description']); ?></td>
                    </tr>
                    <?php if ($payment['amount_text']): ?>
                    <tr>
                        <th>Amount in Words:</th>
                        <td><em><?php echo htmlspecialchars($payment['amount_text']); ?></em></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Student & Issue Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user-graduate me-2"></i>Student & Issue Information
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="40%">Student:</th>
                        <td><strong><?php echo htmlspecialchars($payment['student_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Student ID:</th>
                        <td><code><?php echo htmlspecialchars($payment['student_id']); ?></code></td>
                    </tr>
                    <?php if ($payment['program']): ?>
                    <tr>
                        <th>Program:</th>
                        <td><?php echo htmlspecialchars($payment['program']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($payment['year_level']): ?>
                    <tr>
                        <th>Year Level:</th>
                        <td>Year <?php echo $payment['year_level']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Student Type:</th>
                        <td>
                            <span class="badge bg-<?php echo $payment['student_type'] === 'regular' ? 'primary' : 'warning'; ?>">
                                <?php echo ucfirst($payment['student_type']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Enrollment Status:</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $payment['enrollment_status'] === 'enrolled' ? 'success' : 
                                    ($payment['enrollment_status'] === 'dropped' ? 'danger' : 'info'); 
                            ?>">
                                <?php echo ucfirst($payment['enrollment_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Issue Date:</th>
                        <td><?php echo date('F j, Y', strtotime($payment['issued_date'])); ?></td>
                    </tr>
                    <?php if ($payment['school_year']): ?>
                    <tr>
                        <th>School Year:</th>
                        <td><?php echo htmlspecialchars($payment['school_year']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Issued By:</th>
                        <td>
                            <?php echo htmlspecialchars($payment['issued_by_name']); ?><br>
                            <small class="text-muted"><?php echo ucfirst($payment['issued_by_role']); ?></small>
                        </td>
                    </tr>
                    <tr>
                        <th>Last Updated:</th>
                        <td><?php echo date('M j, Y g:i A', strtotime($payment['updated_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header bg-warning">
        <h5 class="card-title mb-0">
            <i class="fas fa-bolt me-2"></i>Quick Actions
        </h5>
    </div>
    <div class="card-body">
        <div class="btn-group" role="group">
            <!-- Change Status Button -->
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-<?php 
                    echo $payment['payment_status'] === 'paid' ? 'success' : 
                        ($payment['payment_status'] === 'partial' ? 'warning' : 'danger'); 
                ?>"
                        onclick="changePaymentStatusFromDetails('<?php echo $payment['id']; ?>', '<?php echo htmlspecialchars($payment['permit_number'], ENT_QUOTES); ?>', '<?php echo $payment['payment_status']; ?>')">
                    <i class="fas fa-edit"></i> Change Status
                </button>
            </div>

            <!-- Mark as Paid Button (if not already paid) -->
            <?php if ($payment['payment_status'] !== 'paid'): ?>
            <form method="POST" action="manage_payments.php" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="change_status">
                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                <input type="hidden" name="new_status" value="paid">
                <input type="hidden" name="admin_note" value="Marked as paid from payment details page">
                <button type="submit" class="btn btn-success" 
                        onclick="return confirm('Mark payment <?php echo $payment['permit_number']; ?> as PAID?')">
                    <i class="fas fa-check-circle"></i> Mark as Paid
                </button>
            </form>
            <?php endif; ?>

            <?php if ($user_role === 'admin'): ?>
            <!-- Delete Payment Button (Admin only) -->
            <button class="btn btn-danger" 
                    onclick="deletePaymentFromDetails('<?php echo $payment['id']; ?>', '<?php echo htmlspecialchars($payment['permit_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($payment['student_name'], ENT_QUOTES); ?>', <?php echo $payment['amount']; ?>)">
                <i class="fas fa-trash"></i> Delete Payment
            </button>
            <?php endif; ?>

            <!-- View Student Details -->
            <a href="user_details.php?user_id=<?php echo $payment['student_id']; ?>" 
               class="btn btn-info">
                <i class="fas fa-user"></i> View Student
            </a>

            <!-- View All Student Payments -->
            <a href="manage_payments.php?student=<?php echo $payment['student_id']; ?>" 
               class="btn btn-secondary">
                <i class="fas fa-list"></i> All Student Payments
            </a>
        </div>
    </div>
</div>

<!-- Payment Receipt (Printable Section) -->
<div class="card mb-4" id="printableReceipt">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">
            <i class="fas fa-print me-2"></i>Payment Receipt
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <!-- School/Organization Header -->
                <div class="text-center mb-4">
                    <h3 class="mb-1">ACME UNIVERSITY</h3>
                    <p class="mb-1">123 Education Street, Learning City</p>
                    <p class="mb-1">Phone: (123) 456-7890 | Email: info@acmeuniversity.edu</p>
                    <hr>
                    <h4 class="text-primary">OFFICIAL PAYMENT RECEIPT</h4>
                </div>

                <!-- Receipt Details -->
                <div class="row">
                    <div class="col-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th>Receipt No:</th>
                                <td><strong><?php echo htmlspecialchars($payment['permit_number']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Date Issued:</th>
                                <td><?php echo date('F j, Y', strtotime($payment['issued_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Student ID:</th>
                                <td><?php echo htmlspecialchars($payment['student_id']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $payment['payment_status'] === 'paid' ? 'success' : 
                                            ($payment['payment_status'] === 'partial' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>School Year:</th>
                                <td><?php echo htmlspecialchars($payment['school_year']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="mb-3">
                    <h6>Student Information</h6>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <th width="30%">Full Name</th>
                            <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Program</th>
                            <td><?php echo htmlspecialchars($payment['program']); ?> - Year <?php echo $payment['year_level']; ?></td>
                        </tr>
                        <tr>
                            <th>Student Type</th>
                            <td><?php echo ucfirst($payment['student_type']); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Payment Details -->
                <div class="mb-3">
                    <h6>Payment Details</h6>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <th width="30%">Description</th>
                            <td><?php echo htmlspecialchars($payment['description']); ?></td>
                        </tr>
                        <tr>
                            <th>Amount</th>
                            <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                        </tr>
                        <?php if ($payment['amount_text']): ?>
                        <tr>
                            <th>Amount in Words</th>
                            <td><em><?php echo htmlspecialchars($payment['amount_text']); ?></em></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Remaining Balance</th>
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

                <!-- Issued By -->
                <div class="row mt-4">
                    <div class="col-6">
                        <p class="mb-1">Student's Signature:</p>
                        <div style="border-bottom: 1px solid #000; height: 30px; margin-bottom: 20px;"></div>
                    </div>
                    <div class="col-6 text-end">
                        <p class="mb-1">Issued By:</p>
                        <div style="border-bottom: 1px solid #000; height: 30px; margin-bottom: 20px;"></div>
                        <p class="mb-0"><?php echo htmlspecialchars($payment['issued_by_name']); ?></p>
                        <small class="text-muted"><?php echo ucfirst($payment['issued_by_role']); ?></small>
                    </div>
                </div>

                <!-- Footer Note -->
                <div class="text-center mt-4">
                    <small class="text-muted">
                        This is an official receipt from ACME UNIVERSITY. Please keep this receipt for your records.<br>
                        For inquiries, please contact the accounting office.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Log -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-history me-2"></i>Payment Activity Log
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($activity_log)): ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Action</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($activity_log as $activity): ?>
                    <tr>
                        <td width="20%"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></td>
                        <td width="20%">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($activity['action']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No activity log found for this payment.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Change Status Modal -->
<div class="modal fade" id="changeStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_payments.php">
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
function editPaymentFromDetails(paymentId) {
    // Redirect to manage_payments.php with edit parameter
    window.location.href = `manage_payments.php?edit=${paymentId}`;
}

function changePaymentStatusFromDetails(paymentId, permitNumber, currentStatus) {
    document.getElementById('change_payment_id').value = paymentId;
    document.getElementById('change_permit_number').textContent = permitNumber;
    document.getElementById('change_current_status').textContent = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
    document.getElementById('new_status').value = currentStatus;
    
    new bootstrap.Modal(document.getElementById('changeStatusModal')).show();
}

function deletePaymentFromDetails(paymentId, permitNumber, studentName, amount) {
    if (confirm(`Are you sure you want to delete payment ${permitNumber} for ${studentName} (₱${amount})? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_payments.php';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_payment">
            <input type="hidden" name="payment_id" value="${paymentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function printPaymentDetails() {
    const printableElement = document.getElementById('printableReceipt');
    const originalContents = document.body.innerHTML;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Receipt - <?php echo $payment['permit_number']; ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
            <style>
                @media print {
                    body { margin: 0; }
                    .no-print { display: none !important; }
                    .card { border: none !important; box-shadow: none !important; }
                    .table-bordered th, .table-bordered td { border: 1px solid #000 !important; }
                }
                .receipt-header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                ${printableElement.innerHTML}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Wait for content to load then print
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

// Add auto-focus to search input when page loads
document.addEventListener('DOMContentLoaded', function() {
    // You can add any page-specific JavaScript here
});
</script>

<?php renderPageEnd(); ?>