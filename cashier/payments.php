<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'process_payment') {
            $student_id = sanitizeInput($_POST['student_id'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $description = sanitizeInput($_POST['description'] ?? '');
            $school_year = sanitizeInput($_POST['school_year'] ?? '');
            $payment_type = $_POST['payment_type'] ?? 'full';
            
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
                        $payment_status = ($payment_type === 'full') ? 'paid' : 'partial';
                        $remaining_balance = ($payment_type === 'partial') ? $amount : 0;
                        
                        // Insert payment
                        $stmt = $pdo->prepare("INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)");
                        $stmt->execute([$student_id, $permit_number, $amount, $amount_text, $remaining_balance, $payment_status, $description, $user_id, $school_year]);
                        
                        logActivity($user_id, 'Payment Processed', "Processed payment for {$student_id} - ₱{$amount} - {$description}");
                        $success = "Payment processed successfully. Permit Number: {$permit_number}";
                        
                        // Store for receipt display
                        $_SESSION['last_payment'] = [
                            'permit_number' => $permit_number,
                            'student_id' => $student_id,
                            'student_name' => $student['name'],
                            'amount' => $amount,
                            'description' => $description,
                            'payment_status' => $payment_status,
                            'issued_date' => date('Y-m-d'),
                            'cashier_name' => $_SESSION['name']
                        ];
                        
                    } catch(Exception $e) {
                        $error = 'Failed to process payment: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'update_status') {
            $payment_id = intval($_POST['payment_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? '';
            
            if ($payment_id <= 0) {
                $error = 'Invalid payment ID.';
            } elseif (!in_array($new_status, ['paid', 'partial'])) {
                $error = 'Invalid payment status.';
            } else {
                // Get current payment info
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
                $stmt->execute([$payment_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$payment) {
                    $error = 'Payment not found.';
                } elseif ($payment['payment_status'] === 'paid') {
                    $error = 'Cannot modify paid payments.';
                } else {
                    try {
                        $remaining_balance = ($new_status === 'paid') ? 0 : $payment['remaining_balance'];
                        
                        $stmt = $pdo->prepare("UPDATE payments SET payment_status = ?, remaining_balance = ? WHERE id = ?");
                        $stmt->execute([$new_status, $remaining_balance, $payment_id]);
                        
                        logActivity($user_id, 'Payment Status Update', "Updated payment {$payment['permit_number']} status to {$new_status}");
                        $success = 'Payment status updated successfully.';
                        
                    } catch(Exception $e) {
                        $error = 'Failed to update payment: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Get recent payments
$stmt = $pdo->prepare("SELECT p.*, si.name as student_name, si.program, si.year_level
                       FROM payments p
                       JOIN students_info si ON p.student_id = si.user_id
                       ORDER BY p.issued_date DESC, p.id DESC
                       LIMIT 50");
$stmt->execute();
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if there's a receipt to show
$show_receipt = isset($_SESSION['last_payment']);
$receipt_data = $_SESSION['last_payment'] ?? null;
if ($show_receipt) {
    unset($_SESSION['last_payment']); // Clear after displaying
}

renderPageStart('Manage Payments', 'cashier', 'payments.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Payments</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#processPaymentModal">
        <i class="fas fa-plus"></i> Process Payment
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

<!-- Receipt Modal -->
<?php if ($show_receipt): ?>
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <h4>PAYMENT RECEIPT</h4>
                    <p class="text-muted">Student Information System</p>
                </div>
                
                <table class="table table-borderless">
                    <tr>
                        <th>Permit Number:</th>
                        <td><strong><?php echo $receipt_data['permit_number']; ?></strong></td>
                    </tr>
                    <tr>
                        <th>Student ID:</th>
                        <td><?php echo $receipt_data['student_id']; ?></td>
                    </tr>
                    <tr>
                        <th>Student Name:</th>
                        <td><?php echo $receipt_data['student_name']; ?></td>
                    </tr>
                    <tr>
                        <th>Amount:</th>
                        <td><strong>₱<?php echo number_format($receipt_data['amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo $receipt_data['description']; ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><span class="badge bg-success"><?php echo ucfirst($receipt_data['payment_status']); ?></span></td>
                    </tr>
                    <tr>
                        <th>Date:</th>
                        <td><?php echo date('F j, Y', strtotime($receipt_data['issued_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Processed By:</th>
                        <td><?php echo $receipt_data['cashier_name']; ?></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
});
</script>
<?php endif; ?>

<!-- Student Search -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <input type="text" class="form-control" id="studentSearch" placeholder="Search by Student ID, Name, or Email...">
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-primary w-100" onclick="searchStudent()">
                    <i class="fas fa-search"></i> Search Student
                </button>
            </div>
        </div>
        <div id="searchResults" class="mt-3" style="display: none;"></div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Recent Payments</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Permit #</th>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_payments as $payment): ?>
                    <tr>
                        <td><code><?php echo $payment['permit_number']; ?></code></td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['student_name']); ?></strong><br>
                            <small class="text-muted"><?php echo $payment['student_id']; ?></small>
                        </td>
                        <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
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
                        <td><?php echo date('M j, Y', strtotime($payment['issued_date'])); ?></td>
                        <td><?php echo htmlspecialchars(substr($payment['description'], 0, 30)); ?>...</td>
                        <td>
                            <?php if ($payment['payment_status'] === 'unpaid'): ?>
                            <button class="btn btn-sm btn-success" 
                                    onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'paid')">
                                <i class="fas fa-check"></i> Mark Paid
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Process Payment Modal -->
<div class="modal fade" id="processPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Process Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="process_payment">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_type" class="form-label">Payment Type</label>
                            <select class="form-select" id="payment_type" name="payment_type" required>
                                <option value="full">Full Payment</option>
                                <option value="partial">Partial Payment</option>
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
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> Make sure to verify student information before processing payment.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function searchStudent() {
    const searchTerm = document.getElementById('studentSearch').value.trim();
    const resultsDiv = document.getElementById('searchResults');
    
    if (searchTerm.length < 2) {
        alert('Please enter at least 2 characters to search.');
        return;
    }
    
    // This would normally be an AJAX call
    resultsDiv.innerHTML = '<div class="alert alert-info">Search functionality will be enhanced with AJAX in the next update.</div>';
    resultsDiv.style.display = 'block';
}

function updatePaymentStatus(paymentId, status) {
    if (confirm('Are you sure you want to mark this payment as ' + status + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="payment_id" value="${paymentId}">
            <input type="hidden" name="new_status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php renderPageEnd(); ?>
