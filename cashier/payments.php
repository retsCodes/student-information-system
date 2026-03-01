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
                            
                            // Insert payment with additional fields
                            $stmt = $pdo->prepare("INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year, payment_category, units) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)");
                            $stmt->execute([$student_id, $permit_number, $amount, $amount_text, $remaining_balance, $payment_status, $description, $user_id, $school_year, $payment_type, $units]);
                            
                            $payment_id = $pdo->lastInsertId();
                            
                            logActivity($user_id, 'Payment Processed', 
                                "Processed payment ID {$payment_id} for {$student['name']} ({$student_id}) - ₱{$amount} - {$description} - Status: {$payment_status} - Type: {$payment_type}");
                            
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
                                'cashier_name' => $_SESSION['name'],
                                'payment_type' => $payment_type,
                                'units' => $units
                            ];
                            
                            // Clear the form after successful submission
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
                        // Get current payment info
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
                            
                            // Calculate new balance and status
                            $new_balance = $payment['remaining_balance'] - $amount_paid;
                            $payment_status = ($new_balance <= 0) ? 'paid' : 'partial';
                            
                            // Update payment
                            $stmt = $pdo->prepare("UPDATE payments SET remaining_balance = ?, payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                            $stmt->execute([$new_balance, $payment_status, $payment_id]);
                            
                            // Record transaction
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
                            
                            // Hide payment form after successful submission
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

// Build query
$where_conditions = [];
$params = [];

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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get payments with student information
$query = "SELECT p.*, si.name as student_name, si.program, si.year_level
          FROM payments p
          JOIN students_info si ON p.student_id = si.user_id
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
                       {$where_clause}");
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get students for dropdown
$stmt = $pdo->query("SELECT user_id, name FROM students_info WHERE status = 'active' ORDER BY name");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if there's a receipt to show
$show_receipt = isset($_SESSION['last_payment']);
$receipt_data = $_SESSION['last_payment'] ?? null;
if ($show_receipt) {
    unset($_SESSION['last_payment']); // Clear after displaying
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header receipt-header">
                <h5 class="modal-title text-white">
                    <i class="fas fa-receipt me-2"></i>Payment Receipt
                </h5>
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
                            <tr>
                                <th width="40%">Permit Number:</th>
                                <td><strong class="text-primary"><?php echo $receipt_data['permit_number']; ?></strong></td>
                            </tr>
                            <tr>
                                <th>Student ID:</th>
                                <td><code><?php echo $receipt_data['student_id']; ?></code></td>
                            </tr>
                            <tr>
                                <th>Student Name:</th>
                                <td><strong><?php echo $receipt_data['student_name']; ?></strong></td>
                            </tr>
                            <tr>
                                <th>Payment Type:</th>
                                <td><?php echo ucfirst($receipt_data['payment_type']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Amount:</th>
                                <td><h4 class="text-success">₱<?php echo number_format($receipt_data['amount'], 2); ?></h4></td>
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
                </div>
                
                <div class="mt-4">
                    <h6>Description:</h6>
                    <div class="alert alert-light">
                        <?php echo $receipt_data['description']; ?>
                        <?php if ($receipt_data['units'] > 0): ?>
                            <br><small class="text-muted">Units: <?php echo $receipt_data['units']; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-4 pt-4 border-top">
                    <small class="text-muted">
                        This is an official receipt. Please keep it for your records.<br>
                        For inquiries, please contact the cashier's office.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
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
            <title>Payment Receipt - <?php echo $receipt_data['permit_number']; ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .text-center { text-align: center; }
                .text-primary { color: #007bff; }
                .text-success { color: #28a745; }
                .text-muted { color: #6c757d; }
                table { width: 100%; margin-bottom: 20px; }
                th { text-align: left; padding: 5px; }
                td { padding: 5px; }
                .border-top { border-top: 1px solid #dee2e6; padding-top: 20px; }
                .alert-light { background-color: #f8f9fa; padding: 10px; border-radius: 5px; }
            </style>
        </head>
        <body>
            ${receiptContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
});
</script>
<?php endif; ?>

<!-- Process Payment Form (Hidden by default) -->
<div class="card mb-4" id="processPaymentCard" style="display: none;">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-credit-card me-2"></i>Process Payment
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="processPaymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="process_payment">
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="student_id" class="form-label">Student</label>
                    <div class="dropdown">
                        <input type="text" class="form-control" id="student_search" 
                               placeholder="Type to search students..." 
                               autocomplete="off"
                               onkeyup="filterStudents()"
                               onfocus="showStudentDropdown()">
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
                        <div id="selected_student" class="form-text text-muted mt-1" style="display: none;"></div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="payment_type" class="form-label">Payment Type</label>
                    <select class="form-select" id="payment_type" name="payment_type" onchange="handlePaymentTypeChange()">
                        <option value="other">Other</option>
                        <option value="exam">Examination Fee</option>
                        <option value="tuition">Tuition Fee</option>
                        <option value="misc">Miscellaneous</option>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="school_year" class="form-label">School Year</label>
                    <input type="text" class="form-control bg-light" id="school_year" name="school_year" 
                           value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>" readonly>
                </div>

                <div class="col-md-2 mb-3">
                    <label for="payment_status" class="form-label">Status</label>
                    <select class="form-select" id="payment_status" name="payment_status" required>
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="partial">Partial</option>
                    </select>
                </div>
            </div>

            <!-- Student Information Display -->
            <div class="row mb-3" id="student_info_section" style="display: none;">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-3">
                                    <small><strong>Program:</strong> <span id="info_program">-</span></small>
                                </div>
                                <div class="col-md-2">
                                    <small><strong>Year Level:</strong> <span id="info_year_level">-</span></small>
                                </div>
                                <div class="col-md-2">
                                    <small><strong>Total Units:</strong> <span id="info_total_units">-</span></small>
                                </div>
                                <div class="col-md-3">
                                    <small><strong>Student Type:</strong> <span id="info_student_type">-</span></span></small>
                                </div>
                                <div class="col-md-2">
                                    <small><strong>Status:</strong> <span id="info_status" class="badge bg-success">-</span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Units Section for Exam Fees -->
            <div class="row mb-3" id="units_section" style="display: none;">
                <div class="col-md-6">
                    <label for="units" class="form-label">Number of Units</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="units" name="units" 
                               min="0" max="50" value="0">
                        <button type="button" class="btn btn-outline-primary" onclick="calculateExamFee()">
                            <i class="fas fa-calculator"></i> Calculate
                        </button>
                    </div>
                    <div class="form-text">Exam fee: ₱2,400 per unit</div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-info mt-4">
                        <small><i class="fas fa-info-circle"></i> Exam fee will be automatically calculated based on units</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="amount" class="form-label">Amount (₱)</label>
                    <input type="number" class="form-control" id="amount" name="amount" 
                           min="0" step="0.01" required oninput="calculateRemainingBalance()">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="amount_paid" class="form-label">Amount Paid (₱)</label>
                    <input type="number" class="form-control" id="amount_paid" name="amount_paid" 
                           min="0" step="0.01" value="0" oninput="calculateRemainingBalance()">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Remaining Balance</label>
                    <div class="form-control bg-light" id="remaining_balance_display">
                        ₱0.00
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="2" 
                              placeholder="e.g., Prelim Examination Fee, Tuition Fee, Library Fine, etc." required></textarea>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-credit-card"></i> Process Payment
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetProcessPaymentForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="clearPaymentForm()">
                        <i class="fas fa-broom"></i> Clear Form
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Record Payment Form (Hidden by default) -->
<div class="card mb-4" id="recordPaymentCard" style="display: none;">
    <div class="card-header bg-success">
        <h5 class="card-title mb-0">
            <i class="fas fa-money-bill-wave me-2"></i>Record Payment
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="recordPaymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="payment_id" id="record_payment_id">
            
            <div class="alert alert-info">
                <h6 class="alert-heading">Permit: <span id="record_permit_number"></span></h6>
                <p class="mb-0">Record a payment received from a student for an existing unpaid or partial payment. Only cash payments are accepted.</p>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Payment Details</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Student:</strong></td>
                                    <td id="record_student_name">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td id="record_description">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Amount:</strong></td>
                                    <td id="record_total_amount">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Balance Due:</strong></td>
                                    <td id="record_balance_due" class="text-danger">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Payment Method:</strong></td>
                                    <td class="text-success"><strong>Cash Only</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Payment Information</h6>
                            <div class="mb-3">
                                <label for="amount_paid" class="form-label">Amount Paid (₱)</label>
                                <input type="number" class="form-control" id="amount_paid" name="amount_paid" 
                                       min="0" step="0.01" required oninput="updateRemainingAfterPayment()">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <div class="form-control bg-light">
                                    <strong>Cash</strong>
                                </div>
                                <div class="form-text">Only cash payments are accepted</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-3">
                    <label for="notes" class="form-label">Notes (Optional)</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" 
                              placeholder="Additional payment notes..."></textarea>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="alert alert-light">
                        <strong>Remaining after payment:</strong> 
                        <span id="remaining_after_payment" class="fw-bold">₱0.00</span>
                    </div>
                </div>
                <div class="col-md-6 mb-3 text-end">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-check-circle"></i> Record Payment
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cancelRecordPayment()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

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

<!-- Minimized Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>Partial</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by description, permit number, student name or ID">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="payments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-refresh"></i> Clear
                    </a>
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
                                <?php if ($payment['payment_category'] && $payment['payment_category'] !== 'other'): ?>
                                    <br><small class="text-muted"><?php echo ucfirst($payment['payment_category']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <!-- Record Payment (for unpaid/partial payments) -->
                                    <?php if (in_array($payment['payment_status'], ['unpaid', 'partial'])): ?>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="recordPayment('<?php echo $payment['id']; ?>', '<?php echo htmlspecialchars($payment['permit_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($payment['student_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($payment['description'], ENT_QUOTES); ?>', '<?php echo $payment['amount']; ?>', '<?php echo $payment['remaining_balance']; ?>', '<?php echo $payment['payment_status']; ?>')"
                                            title="Record Payment">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </button>
                                    <?php endif; ?>
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

<script>
// Toggle process payment form visibility
function toggleProcessPaymentForm() {
    const processPaymentCard = document.getElementById('processPaymentCard');
    if (processPaymentCard.style.display === 'none') {
        processPaymentCard.style.display = 'block';
    } else {
        processPaymentCard.style.display = 'none';
    }
}

// Reset process payment form
function resetProcessPaymentForm() {
    document.getElementById('processPaymentForm').reset();
    document.getElementById('student_info_section').style.display = 'none';
    document.getElementById('units_section').style.display = 'none';
    document.getElementById('remaining_balance_display').textContent = '₱0.00';
    document.getElementById('remaining_balance_display').className = 'form-control bg-light';
    document.getElementById('student_id').value = '';
    document.getElementById('student_search').value = '';
    document.getElementById('selected_student').style.display = 'none';
}

// Clear payment form
function clearPaymentForm() {
    resetProcessPaymentForm();
}

// Student search and dropdown functionality
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
    
    const dropdown = document.getElementById('student_dropdown');
    dropdown.style.display = hasVisible ? 'block' : 'none';
}

function showStudentDropdown() {
    const dropdown = document.getElementById('student_dropdown');
    const search = document.getElementById('student_search').value;
    
    if (search === '') {
        document.querySelectorAll('.student-option').forEach(option => {
            option.style.display = 'block';
        });
    }
    
    dropdown.style.display = 'block';
    filterStudents();
}

function selectStudent(studentId, studentName) {
    document.getElementById('student_id').value = studentId;
    document.getElementById('student_search').value = studentName;
    document.getElementById('selected_student').textContent = `Selected: ${studentName}`;
    document.getElementById('selected_student').style.display = 'block';
    document.getElementById('student_dropdown').style.display = 'none';
    
    // Load student information
    loadStudentInfo(studentId);
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('student_dropdown');
    const search = document.getElementById('student_search');
    
    if (!dropdown.contains(event.target) && event.target !== search) {
        dropdown.style.display = 'none';
    }
});
// TODO: change get_student_info ajax and add a genereal ajax handler (admin for ref)
// Load student information via AJAX
function loadStudentInfo(studentId) {
    // Show loading state
    document.getElementById('student_info_section').style.display = 'block';
    document.getElementById('info_program').textContent = 'Loading...';
    document.getElementById('info_year_level').textContent = 'Loading...';
    document.getElementById('info_total_units').textContent = 'Loading...';
    document.getElementById('info_student_type').textContent = 'Loading...';
    document.getElementById('info_status').textContent = 'Loading...';
    
    // Create a simple AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `get_student_info.php?student_id=${encodeURIComponent(studentId)}`, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    const student = data.student;
                    document.getElementById('info_program').textContent = student.program || '-';
                    document.getElementById('info_year_level').textContent = student.year_level || '-';
                    document.getElementById('info_total_units').textContent = student.total_units || '0';
                    document.getElementById('info_student_type').textContent = student.student_type || '-';
                    
                    const statusBadge = document.getElementById('info_status');
                    statusBadge.textContent = student.status || '-';
                    statusBadge.className = student.status === 'active' ? 'badge bg-success' : 'badge bg-danger';
                    
                    // Auto-fill units for exam calculation
                    document.getElementById('units').value = student.total_units || 0;
                } else {
                    document.getElementById('student_info_section').style.display = 'none';
                    console.error('Failed to load student info:', data.message);
                }
            } catch (e) {
                document.getElementById('student_info_section').style.display = 'none';
                console.error('Error parsing student info:', e);
            }
        }
    };
    xhr.send();
}

// Payment type handling
function handlePaymentTypeChange() {
    const paymentType = document.getElementById('payment_type').value;
    const unitsSection = document.getElementById('units_section');
    
    if (paymentType === 'exam') {
        unitsSection.style.display = 'block';
        // Auto-fill description
        document.getElementById('description').value = 'Examination Fee';
    } else {
        unitsSection.style.display = 'none';
        // Clear units-related fields
        document.getElementById('units').value = '0';
        if (paymentType === 'tuition') {
            document.getElementById('description').value = 'Tuition Fee';
        } else if (paymentType === 'misc') {
            document.getElementById('description').value = 'Miscellaneous Fee';
        } else {
            document.getElementById('description').value = '';
        }
    }
}

// Calculate exam fee (₱2,400 per unit)
function calculateExamFee() {
    const units = parseInt(document.getElementById('units').value) || 0;
    const examFeePerUnit = 2400;
    const totalAmount = units * examFeePerUnit;
    
    if (units > 0) {
        document.getElementById('amount').value = totalAmount;
        document.getElementById('amount_paid').value = totalAmount;
        calculateRemainingBalance();
        
        // Auto-fill description with unit count
        document.getElementById('description').value = `Examination Fee - ${units} units`;
    } else {
        alert('Please enter a valid number of units.');
        document.getElementById('amount').value = '';
        document.getElementById('amount_paid').value = '0';
        calculateRemainingBalance();
    }
}

// Calculate remaining balance in real-time
function calculateRemainingBalance() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
    const remainingBalance = amount - amountPaid;
    
    const balanceDisplay = document.getElementById('remaining_balance_display');
    balanceDisplay.textContent = '₱' + remainingBalance.toFixed(2);
    
    // Update payment status based on amounts
    const paymentStatus = document.getElementById('payment_status');
    if (amountPaid >= amount) {
        balanceDisplay.className = 'form-control bg-success text-white';
        paymentStatus.value = 'paid';
    } else if (amountPaid > 0) {
        balanceDisplay.className = 'form-control bg-warning text-dark';
        paymentStatus.value = 'partial';
    } else {
        balanceDisplay.className = 'form-control bg-light';
        paymentStatus.value = 'unpaid';
    }
}

// Record payment functions
function recordPayment(paymentId, permitNumber, studentName, description, totalAmount, balanceDue, paymentStatus) {
    document.getElementById('record_payment_id').value = paymentId;
    document.getElementById('record_permit_number').textContent = permitNumber;
    document.getElementById('record_student_name').textContent = studentName;
    document.getElementById('record_description').textContent = description;
    document.getElementById('record_total_amount').textContent = '₱' + parseFloat(totalAmount).toFixed(2);
    document.getElementById('record_balance_due').textContent = '₱' + parseFloat(balanceDue).toFixed(2);
    
    document.getElementById('recordPaymentCard').style.display = 'block';
    updateRemainingAfterPayment();
}

function updateRemainingAfterPayment() {
    const balanceDue = parseFloat(document.getElementById('record_balance_due').textContent.replace('₱', ''));
    const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
    const remaining = balanceDue - amountPaid;
    
    document.getElementById('remaining_after_payment').textContent = '₱' + remaining.toFixed(2);
    
    if (remaining <= 0) {
        document.getElementById('remaining_after_payment').className = 'fw-bold text-success';
    } else {
        document.getElementById('remaining_after_payment').className = 'fw-bold text-danger';
    }
}

function cancelRecordPayment() {
    document.getElementById('recordPaymentCard').style.display = 'none';
    document.getElementById('recordPaymentForm').reset();
}
</script>