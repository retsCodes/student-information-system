<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
        logActivity($user_id, 'Security Error', 'Invalid CSRF token in payment processing');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'process_payment':
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $amount = floatval($_POST['amount'] ?? 0);
                $description = sanitizeInput($_POST['description'] ?? '');
                $school_year = sanitizeInput($_POST['school_year'] ?? '');
                $payment_status = $_POST['payment_status'] ?? 'unpaid';
                $payment_type = $_POST['payment_type'] ?? 'other';
                $units = intval($_POST['units'] ?? 0);
                
                if (empty($student_id)) {
                    $error = 'Student ID is required.';
                    logActivity($user_id, 'Payment Processing Failed', 'Student ID missing for payment processing');
                } elseif ($amount <= 0) {
                    $error = 'Amount must be greater than 0.';
                    logActivity($user_id, 'Payment Processing Failed', "Invalid amount {$amount} for payment processing");
                } elseif (empty($description)) {
                    $error = 'Description is required.';
                    logActivity($user_id, 'Payment Processing Failed', 'Description missing for payment processing');
                } else {
                    // Verify student exists
                    $stmt = $pdo->prepare("SELECT name FROM students_info WHERE user_id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                    
                    if (!$student) {
                        $error = 'Student not found.';
                        logActivity($user_id, 'Payment Processing Failed', "Student {$student_id} not found for payment processing");
                    } else {
                        try {
                            $permit_number = generatePermitNumber();
                            $amount_text = numberToWords($amount) . ' pesos';
                            $remaining_balance = ($payment_status === 'paid') ? 0 : $amount;
                            
                            $stmt = $pdo->prepare("INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year, payment_category, units) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)");
                            $stmt->execute([$student_id, $permit_number, $amount, $amount_text, $remaining_balance, $payment_status, $description, $user_id, $school_year, $payment_type, $units]);
                            
                            $payment_id = $pdo->lastInsertId();
                            
                            logActivity($user_id, 'Payment Processed', 
                                "Processed payment ID {$payment_id} for {$student['name']} ({$student_id}) - ₱{$amount} - {$description} - Status: {$payment_status} - Type: {$payment_type}");
                            
                            $success = "Payment processed successfully. Permit Number: {$permit_number}";
                            
                            $_SESSION['last_payment'] = [
                                'permit_number' => $permit_number,
                                'student_id' => $student_id,
                                'student_name' => $student['name'],
                                'amount' => $amount,
                                'description' => $description,
                                'payment_status' => $payment_status,
                                'issued_date' => date('Y-m-d'),
                                'cashier_name' => $_SESSION['name'],
                                'payment_type' => $payment_type,
                                'units' => $units
                            ];
                            
                            echo '<script>document.addEventListener("DOMContentLoaded", function() { resetProcessPaymentForm(); });</script>';
                            
                        } catch(Exception $e) {
                            $error = 'Failed to process payment: ' . $e->getMessage();
                            logActivity($user_id, 'Payment Processing Error', 
                                "Failed to process payment for {$student_id}: " . $e->getMessage());
                        }
                    }
                }
                break;
                
            case 'record_payment':
                $payment_id = intval($_POST['payment_id'] ?? 0);
                $amount_paid = floatval($_POST['amount_paid'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if ($payment_id <= 0) {
                    $error = 'Invalid payment ID.';
                    logActivity($user_id, 'Payment Recording Failed', 'Invalid payment ID for payment recording');
                } elseif ($amount_paid <= 0) {
                    $error = 'Amount paid must be greater than 0.';
                    logActivity($user_id, 'Payment Recording Failed', "Invalid amount paid {$amount_paid} for payment recording");
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT p.*, si.name as student_name FROM payments p 
                                               JOIN students_info si ON p.student_id = si.user_id 
                                               WHERE p.id = ?");
                        $stmt->execute([$payment_id]);
                        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$payment) {
                            $error = 'Payment not found.';
                            logActivity($user_id, 'Payment Recording Failed', "Payment ID {$payment_id} not found for recording");
                        } else {
                            $pdo->beginTransaction();
                            
                            $new_balance = $payment['remaining_balance'] - $amount_paid;
                            $payment_status = ($new_balance <= 0) ? 'paid' : 'partial';
                            
                            $stmt = $pdo->prepare("UPDATE payments SET remaining_balance = ?, payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                            $stmt->execute([$new_balance, $payment_status, $payment_id]);
                            
                            $transaction_id = 'TXN' . date('YmdHis') . rand(100, 999);
                            $stmt = $pdo->prepare("INSERT INTO transaction_history 
                                (transaction_id, student_id, transaction_type, amount, 
                                previous_balance, new_balance, description, reference_id, issued_by, notes) 
                                VALUES (?, ?, 'payment', ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $transaction_id, 
                                $payment['student_id'], 
                                $amount_paid, 
                                $payment['remaining_balance'], 
                                $new_balance,
                                "Payment received: {$payment['description']}", 
                                $payment['permit_number'], 
                                $user_id,
                                "Method: Cash" . ($notes ? ", Notes: {$notes}" : "")
                            ]);
                            
                            $pdo->commit();
                            
                            logActivity($user_id, 'Payment Recorded', 
                                "Recorded payment of ₱{$amount_paid} for {$payment['student_name']} ({$payment['student_id']}) - Permit: {$payment['permit_number']} - Method: Cash" . 
                                " - New balance: ₱{$new_balance} - Status: {$payment_status}");
                            
                            $success = "Payment recorded successfully! ";
                            if ($payment_status === 'partial') {
                                $success .= "Remaining balance: ₱" . number_format($new_balance, 2);
                            } else {
                                $success .= "Payment fully settled.";
                            }
                            
                            echo '<script>document.addEventListener("DOMContentLoaded", function() { cancelRecordPayment(); });</script>';
                        }
                    } catch(Exception $e) {
                        $pdo->rollBack();
                        $error = 'Failed to record payment: ' . $e->getMessage();
                        logActivity($user_id, 'Payment Recording Error', 
                            "Failed to record payment for payment ID {$payment_id}: " . $e->getMessage());
                    }
                }
                break;
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = ["p.issued_by = ?"];
$params = [$user_id];

if (!empty($status_filter)) {
    $where_conditions[] = "p.payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(p.description LIKE ? OR p.permit_number LIKE ? OR si.name LIKE ? OR si.user_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$query = "SELECT p.*, si.name as student_name, si.program, si.year_level
          FROM payments p
          JOIN students_info si ON p.student_id = si.user_id
          {$where_clause}
          ORDER BY p.issued_date DESC, p.id DESC
          LIMIT 200";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$summary_sql = "SELECT 
                COUNT(*) as total_count,
                SUM(amount) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
                SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END) as partial_balance
               FROM payments p
               JOIN students_info si ON p.student_id = si.user_id
               WHERE p.issued_by = ?";
$stmt = $pdo->prepare($summary_sql);
$stmt->execute([$user_id]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$summary['outstanding'] = max(0, ($summary['unpaid_amount'] ?? 0) + ($summary['partial_balance'] ?? 0));

// Get students for dropdown
$stmt = $pdo->query("SELECT user_id, name FROM students_info WHERE status = 'active' ORDER BY name");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get global unit price for exam fee calculation
$stmt = $pdo->query("SELECT value FROM settings WHERE name = 'unit_price'");
$global_unit_price_result = $stmt->fetch(PDO::FETCH_ASSOC);
$global_unit_price = $global_unit_price_result ? floatval($global_unit_price_result['value']) : 2400.00;

$show_receipt = isset($_SESSION['last_payment']);
$receipt_data = $_SESSION['last_payment'] ?? null;
if ($show_receipt) {
    unset($_SESSION['last_payment']);
}

renderPageStart('Manage Payments', 'cashier', 'payments.php');
?>

<style>
.receipt-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px 10px 0 0;
}
.receipt-body {
    padding: 30px;
    border: 2px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 10px 10px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Payments</h2>
    <div>
        <button class="btn btn-primary me-2" onclick="toggleProcessPaymentForm()">
            <i class="fas fa-credit-card"></i> Process Payment
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Receipt Modal -->
<?php if ($show_receipt): ?>
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header receipt-header">
                <h5 class="modal-title text-white">Payment Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body receipt-body">
                <div class="text-center mb-4">
                    <h3 class="text-primary">OFFICIAL RECEIPT</h3>
                    <p class="text-muted">Student Information System</p>
                    <hr>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr><th>Permit Number:</th><td><strong><?php echo $receipt_data['permit_number']; ?></strong></td></tr>
                            <tr><th>Student ID:</th><td><?php echo $receipt_data['student_id']; ?></td></tr>
                            <tr><th>Student Name:</th><td><strong><?php echo $receipt_data['student_name']; ?></strong></td></tr>
                            <tr><th>Payment Type:</th><td><?php echo ucfirst($receipt_data['payment_type']); ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr><th>Amount:</th><td><h4 class="text-success">₱<?php echo number_format($receipt_data['amount'], 2); ?></h4></td></tr>
                            <tr><th>Status:</th><td><span class="badge bg-success"><?php echo ucfirst($receipt_data['payment_status']); ?></span></td></tr>
                            <tr><th>Date:</th><td><?php echo date('F j, Y', strtotime($receipt_data['issued_date'])); ?></td></tr>
                            <tr><th>Processed By:</th><td><?php echo $receipt_data['cashier_name']; ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Description:</h6>
                    <div class="alert alert-light"><?php echo $receipt_data['description']; ?></div>
                </div>
                
                <div class="text-center mt-4 pt-4 border-top">
                    <small class="text-muted">Official Receipt - Keep for your records</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">Print Receipt</button>
            </div>
        </div>
    </div>
</div>
<script>
function printReceipt() {
    const receiptContent = document.querySelector('.receipt-body').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .text-center { text-align: center; }
                .text-primary { color: #007bff; }
                .text-success { color: #28a745; }
                table { width: 100%; margin-bottom: 20px; }
                th, td { padding: 5px; text-align: left; }
                .border-top { border-top: 1px solid #dee2e6; padding-top: 20px; }
            </style>
        </head>
        <body>${receiptContent}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
new bootstrap.Modal(document.getElementById('receiptModal')).show();
</script>
<?php endif; ?>

<!-- Process Payment Form -->
<div class="card mb-4" id="processPaymentCard" style="display: none;">
    <div class="card-header">
        <h5 class="card-title mb-0">Process Payment</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="processPaymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="process_payment">
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Student</label>
                    <input type="text" class="form-control" id="student_search" placeholder="Type to search..." 
                           autocomplete="off" onkeyup="filterStudents()" onfocus="showStudentDropdown()">
                    <div class="dropdown-menu w-100" id="student_dropdown" style="display: none; max-height: 200px; overflow-y: auto;">
                        <?php foreach($students as $student): ?>
                            <button type="button" class="dropdown-item student-option" 
                                    data-value="<?php echo htmlspecialchars($student['user_id']); ?>"
                                    onclick="selectStudent('<?php echo htmlspecialchars($student['user_id']); ?>', '<?php echo htmlspecialchars($student['name']); ?>')">
                                <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['user_id']); ?>)
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="student_id" name="student_id" required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">Payment Type</label>
                    <select class="form-select" id="payment_type" name="payment_type" onchange="handlePaymentTypeChange()">
                        <option value="other">Other</option>
                        <option value="exam">Examination Fee</option>
                        <option value="tuition">Tuition Fee</option>
                        <option value="misc">Miscellaneous</option>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label">School Year</label>
                    <input type="text" class="form-control bg-light" name="school_year" value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>" readonly>
                </div>

                <div class="col-md-2 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="payment_status" name="payment_status" required>
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="partial">Partial</option>
                    </select>
                </div>
            </div>

            <!-- Student Info Display -->
            <div class="row mb-3" id="student_info_section" style="display: none;">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-3"><small><strong>Program:</strong> <span id="info_program">-</span></small></div>
                                <div class="col-md-2"><small><strong>Year Level:</strong> <span id="info_year_level">-</span></small></div>
                                <div class="col-md-2"><small><strong>Units:</strong> <span id="info_total_units">-</span></small></div>
                                <div class="col-md-3"><small><strong>Student Type:</strong> <span id="info_student_type">-</span></small></div>
                                <div class="col-md-2"><small><strong>Status:</strong> <span id="info_status">-</span></small></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Units Section for Exam Fees -->
            <div class="row mb-3" id="units_section" style="display: none;">
                <div class="col-md-3">
                    <label class="form-label">Exam Type</label>
                    <select class="form-select" id="exam_type" onchange="updateExamDescription()">
                        <option value="prelim">Prelim</option>
                        <option value="midterm">Midterm</option>
                        <option value="prefinals">Prefinals</option>
                        <option value="finals">Final</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Units</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="units" name="units" readonly>
                        <button type="button" class="btn btn-outline-secondary" onclick="recalcStudentUnits(document.getElementById('student_id').value)">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit Price</label>
                    <input type="text" class="form-control" value="₱<?php echo number_format($global_unit_price, 2); ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Exam Fee</label>
                    <input type="number" class="form-control" id="exam_amount" readonly>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Amount (₱)</label>
                    <input type="number" class="form-control" id="amount" name="amount" min="0" step="0.01" required oninput="calculateRemainingBalance()">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Amount Paid (₱)</label>
                    <input type="number" class="form-control" id="amount_paid" name="amount_paid" min="0" step="0.01" value="0" oninput="calculateRemainingBalance()">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Remaining Balance</label>
                    <div class="form-control bg-light" id="remaining_balance_display">₱0.00</div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="2" required></textarea>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Process Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="resetProcessPaymentForm()">Cancel</button>
                    <button type="button" class="btn btn-outline-info" onclick="clearPaymentForm()">Clear</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Record Payment Form -->
<div class="card mb-4" id="recordPaymentCard" style="display: none;">
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0">Record Payment</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="payment_id" id="record_payment_id">
            
            <div class="alert alert-info">
                <p>Permit: <strong id="record_permit_number"></strong></p>
                <p>Record payment for existing unpaid/partial payment. <strong>Cash only</strong>.</p>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Payment Details</h6>
                            <p><strong>Student:</strong> <span id="record_student_name"></span></p>
                            <p><strong>Description:</strong> <span id="record_description"></span></p>
                            <p><strong>Total Amount:</strong> <span id="record_total_amount"></span></p>
                            <p><strong>Balance Due:</strong> <span id="record_balance_due" class="text-danger"></span></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Payment Information</h6>
                            <div class="mb-3">
                                <label class="form-label">Amount Paid (₱)</label>
                                <input type="number" class="form-control" name="amount_paid" min="0" step="0.01" required oninput="updateRemainingAfterPayment()">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <div class="form-control bg-light"><strong>Cash</strong></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-light">
                        <strong>Remaining after payment:</strong> <span id="remaining_after_payment">₱0.00</span>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button type="submit" class="btn btn-success">Record Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="cancelRecordPayment()">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-card-container mb-4">
    <?php echo renderStatsCard('Total Payments', number_format($summary['total_count']), 'fas fa-file-invoice-dollar', 'primary'); ?>
    <?php echo renderStatsCard('Total Amount', '₱' . number_format($summary['total_amount'], 2), 'fas fa-money-bill-wave', 'info'); ?>
    <?php echo renderStatsCard('Paid Amount', '₱' . number_format($summary['paid_amount'], 2), 'fas fa-check-circle', 'success'); ?>
    <?php echo renderStatsCard('Outstanding', '₱' . number_format($summary['outstanding'], 2), 'fas fa-exclamation-triangle', 'warning'); ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>Partial</option>
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by description, permit, student name or ID">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Search</button>
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
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr><th>Date</th><th>Permit</th><th>Student</th><th>Amount</th><th>Status</th><th>Balance</th><th>Description</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('M j', strtotime($payment['issued_date'])); ?></td>
                            <td><code><?php echo htmlspecialchars($payment['permit_number']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($payment['student_name']); ?></strong><br><small><?php echo htmlspecialchars($payment['student_id']); ?></small></td>
                            <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td><span class="badge bg-<?php echo $payment['payment_status'] === 'paid' ? 'success' : ($payment['payment_status'] === 'partial' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($payment['payment_status']); ?></span></td>
                            <td><?php echo $payment['remaining_balance'] > 0 ? '<span class="text-danger">₱' . number_format($payment['remaining_balance'], 2) . '</span>' : '₱0.00'; ?></span></td>
                            <td><small><?php echo htmlspecialchars(substr($payment['description'], 0, 30)); ?></small></td>
                            <td>
                                <?php if (in_array($payment['payment_status'], ['unpaid', 'partial'])): ?>
                                <button class="btn btn-sm btn-success" onclick="recordPayment('<?php echo $payment['id']; ?>', '<?php echo htmlspecialchars($payment['permit_number']); ?>', '<?php echo htmlspecialchars($payment['student_name']); ?>', '<?php echo htmlspecialchars($payment['description']); ?>', <?php echo $payment['amount']; ?>, <?php echo $payment['remaining_balance']; ?>)">
                                    <i class="fas fa-money-bill-wave"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Use admin's ajax_handler.php for API calls

function filterStudents() {
    const search = document.getElementById('student_search').value.toLowerCase();
    const options = document.querySelectorAll('.student-option');
    let hasVisible = false;
    options.forEach(option => {
        const text = option.textContent.toLowerCase();
        if (text.includes(search)) {
            option.style.display = 'block';
            hasVisible = true;
        } else {
            option.style.display = 'none';
        }
    });
    document.getElementById('student_dropdown').style.display = hasVisible ? 'block' : 'none';
}

function showStudentDropdown() {
    document.getElementById('student_dropdown').style.display = 'block';
    filterStudents();
}

function selectStudent(studentId, studentName) {
    document.getElementById('student_id').value = studentId;
    document.getElementById('student_search').value = studentName;
    document.getElementById('student_dropdown').style.display = 'none';
    loadStudentInfo(studentId);
}

function loadStudentInfo(studentId) {
    document.getElementById('student_info_section').style.display = 'block';
    fetch(`../admin/ajax_handler.php?action=get_student_info&student_id=${encodeURIComponent(studentId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('info_program').textContent = data.student.program || '-';
                document.getElementById('info_year_level').textContent = data.student.year_level || '-';
                document.getElementById('info_total_units').textContent = data.student.total_units || '0';
                document.getElementById('info_student_type').textContent = data.student.student_type || '-';
                document.getElementById('info_status').textContent = data.student.status || '-';
                document.getElementById('units').value = data.student.total_units || 0;
                if (document.getElementById('payment_type').value === 'exam') calculateExamFee();
            }
        });
}

function recalcStudentUnits(studentId) {
    if (!studentId) { alert('Select a student first'); return; }
    document.getElementById('units').value = 'Loading...';
    fetch(`../admin/ajax_handler.php?action=get_student_total_units&student_id=${encodeURIComponent(studentId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('units').value = data.total_units || 0;
                calculateExamFee();
                updateExamDescription();
            }
        });
}

function handlePaymentTypeChange() {
    const isExam = document.getElementById('payment_type').value === 'exam';
    document.getElementById('units_section').style.display = isExam ? 'flex' : 'none';
    if (!isExam) document.getElementById('units').value = '0';
}

function calculateExamFee() {
    const units = parseInt(document.getElementById('units').value) || 0;
    const unitPrice = <?php echo $global_unit_price; ?>;
    const amount = units * unitPrice;
    document.getElementById('exam_amount').value = amount;
    document.getElementById('amount').value = amount;
    document.getElementById('amount_paid').value = amount;
    calculateRemainingBalance();
    updateExamDescription();
}

function updateExamDescription() {
    const examType = document.getElementById('exam_type').value;
    const units = document.getElementById('units').value || 0;
    const examNames = { prelim: 'Prelim', midterm: 'Midterm', prefinals: 'Prefinals', finals: 'Final' };
    document.getElementById('description').value = `${examNames[examType]} Examination Fee (${units} units)`;
}

function calculateRemainingBalance() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const paid = parseFloat(document.getElementById('amount_paid').value) || 0;
    const remaining = amount - paid;
    document.getElementById('remaining_balance_display').textContent = '₱' + remaining.toFixed(2);
    const status = document.getElementById('payment_status');
    if (paid >= amount) status.value = 'paid';
    else if (paid > 0) status.value = 'partial';
    else status.value = 'unpaid';
}

function updateRemainingAfterPayment() {
    const balanceDue = parseFloat(document.getElementById('record_balance_due').textContent.replace('₱', '')) || 0;
    const paid = parseFloat(document.querySelector('#recordPaymentForm input[name="amount_paid"]').value) || 0;
    const remaining = balanceDue - paid;
    const span = document.getElementById('remaining_after_payment');
    span.textContent = '₱' + remaining.toFixed(2);
    span.className = remaining < 0 ? 'text-danger' : (remaining === 0 ? 'text-success' : 'text-warning');
}

function toggleProcessPaymentForm() {
    const card = document.getElementById('processPaymentCard');
    card.style.display = card.style.display === 'none' ? 'block' : 'none';
    if (card.style.display === 'block') clearPaymentForm();
}

function resetProcessPaymentForm() { document.getElementById('processPaymentCard').style.display = 'none'; clearPaymentForm(); }
function clearPaymentForm() {
    document.getElementById('processPaymentForm').reset();
    document.getElementById('student_info_section').style.display = 'none';
    document.getElementById('units_section').style.display = 'none';
    document.getElementById('remaining_balance_display').textContent = '₱0.00';
    document.getElementById('student_id').value = '';
    document.getElementById('student_search').value = '';
}

function recordPayment(id, permit, student, desc, total, balance) {
    document.getElementById('record_payment_id').value = id;
    document.getElementById('record_permit_number').textContent = permit;
    document.getElementById('record_student_name').textContent = student;
    document.getElementById('record_description').textContent = desc;
    document.getElementById('record_total_amount').textContent = '₱' + total.toFixed(2);
    document.getElementById('record_balance_due').textContent = '₱' + balance.toFixed(2);
    document.getElementById('recordPaymentCard').style.display = 'block';
    document.querySelector('#recordPaymentForm input[name="amount_paid"]').value = '';
    document.getElementById('remaining_after_payment').textContent = '₱' + balance.toFixed(2);
}

function cancelRecordPayment() {
    document.getElementById('recordPaymentCard').style.display = 'none';
    document.getElementById('recordPaymentForm').reset();
}

document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('student_dropdown');
    const search = document.getElementById('student_search');
    if (dropdown && !dropdown.contains(e.target) && e.target !== search) dropdown.style.display = 'none';
});
</script>

<?php renderPageEnd(); ?>