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
        logActivity($_SESSION['user_id'], 'Security Error', 'Invalid CSRF token in payment management');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'update_payment_info':
                $payment_id = intval($_POST['payment_id'] ?? 0);
                $amount = floatval($_POST['amount'] ?? 0);
                $description = sanitizeInput($_POST['description'] ?? '');
                $school_year = sanitizeInput($_POST['school_year'] ?? '');
                $admin_note = sanitizeInput($_POST['admin_note'] ?? '');
                $payment_status = $_POST['payment_status'] ?? 'unpaid';
                
                if ($payment_id <= 0) {
                    $error = 'Invalid payment ID.';
                    logActivity($_SESSION['user_id'], 'Payment Update Failed', 'Invalid payment ID provided');
                } elseif ($amount <= 0) {
                    $error = 'Amount must be greater than 0.';
                    logActivity($_SESSION['user_id'], 'Payment Update Failed', 'Invalid amount provided for payment update');
                } elseif (empty($description)) {
                    $error = 'Description is required.';
                    logActivity($_SESSION['user_id'], 'Payment Update Failed', 'Missing description for payment update');
                } else {
                    try {
                        // Get current payment info
                        $stmt = $pdo->prepare("SELECT p.*, si.name as student_name FROM payments p 
                                               JOIN students_info si ON p.student_id = si.user_id 
                                               WHERE p.id = ?");
                        $stmt->execute([$payment_id]);
                        $old_payment = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$old_payment) {
                            $error = 'Payment not found.';
                            logActivity($_SESSION['user_id'], 'Payment Update Failed', "Payment ID {$payment_id} not found");
                        } else {
                            $amount_text = numberToWords($amount) . ' pesos';
                            
                            // Calculate remaining balance based on new status
                            $remaining_balance = 0;
                            if ($payment_status === 'unpaid') {
                                $remaining_balance = $amount;
                            } elseif ($payment_status === 'partial') {
                                // For partial, keep the current balance but ensure it's not more than amount
                                $remaining_balance = min($old_payment['remaining_balance'], $amount);
                            } else { // paid
                                $remaining_balance = 0;
                            }
                            
                            // Update payment information
                            $stmt = $pdo->prepare("UPDATE payments SET amount = ?, amount_text = ?, description = ?, school_year = ?, payment_status = ?, remaining_balance = ? WHERE id = ?");
                            $stmt->execute([$amount, $amount_text, $description, $school_year, $payment_status, $remaining_balance, $payment_id]);
                            
                            // Log the changes
                            $changes = [];
                            if ($old_payment['amount'] != $amount) {
                                $changes[] = "amount from ₱{$old_payment['amount']} to ₱{$amount}";
                            }
                            if ($old_payment['description'] != $description) {
                                $changes[] = "description from '{$old_payment['description']}' to '{$description}'";
                            }
                            if ($old_payment['school_year'] != $school_year) {
                                $changes[] = "school year from '{$old_payment['school_year']}' to '{$school_year}'";
                            }
                            if ($old_payment['payment_status'] != $payment_status) {
                                $changes[] = "status from '{$old_payment['payment_status']}' to '{$payment_status}'";
                            }
                            
                            $change_text = !empty($changes) ? implode(', ', $changes) : 'no changes detected';
                            $log_description = "Updated payment info {$old_payment['permit_number']} for {$old_payment['student_name']}: {$change_text}";
                            if ($admin_note) {
                                $log_description .= ". Admin note: {$admin_note}";
                            }
                            
                            logActivity($_SESSION['user_id'], 'Payment Info Updated', $log_description);
                            $success = 'Payment information updated successfully.';
                            
                            // Hide edit form after successful update
                            echo '<script>document.addEventListener("DOMContentLoaded", function() { cancelUpdatePaymentInfo(); });</script>';
                        }
                    } catch(Exception $e) {
                        $error = 'Failed to update payment information: ' . $e->getMessage();
                        logActivity($_SESSION['user_id'], 'Payment Update Error', "Failed to update payment {$payment_id}: " . $e->getMessage());
                    }
                }
                break;
                
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
                    logActivity($_SESSION['user_id'], 'Payment Processing Failed', 'Student ID missing for payment processing');
                } elseif ($amount <= 0) {
                    $error = 'Amount must be greater than 0.';
                    logActivity($_SESSION['user_id'], 'Payment Processing Failed', "Invalid amount {$amount} for payment processing");
                } elseif (empty($description)) {
                    $error = 'Description is required.';
                    logActivity($_SESSION['user_id'], 'Payment Processing Failed', 'Description missing for payment processing');
                } else {
                    // Verify student exists
                    $stmt = $pdo->prepare("SELECT name FROM students_info WHERE user_id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                    
                    if (!$student) {
                        $error = 'Student not found.';
                        logActivity($_SESSION['user_id'], 'Payment Processing Failed', "Student {$student_id} not found for payment processing");
                    } else {
                        try {
                            $permit_number = generatePermitNumber();
                            $amount_text = numberToWords($amount) . ' pesos';
                            $remaining_balance = ($payment_status === 'paid') ? 0 : $amount;
                            
                            // Insert payment with additional fields
                            $stmt = $pdo->prepare("INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year, payment_category, units) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)");
                            $stmt->execute([$student_id, $permit_number, $amount, $amount_text, $remaining_balance, $payment_status, $description, $_SESSION['user_id'], $school_year, $payment_type, $units]);
                            
                            $payment_id = $pdo->lastInsertId();
                            
                            logActivity($_SESSION['user_id'], 'Payment Processed', 
                                "Processed payment ID {$payment_id} for {$student['name']} ({$student_id}) - ₱{$amount} - {$description} - Status: {$payment_status} - Type: {$payment_type}");
                            
                            $success = "Payment processed successfully. Permit Number: {$permit_number}";
                            
                            // Clear the form after successful submission
                            echo '<script>document.addEventListener("DOMContentLoaded", function() { resetProcessPaymentForm(); });</script>';
                        } catch(Exception $e) {
                            $error = 'Failed to process payment: ' . $e->getMessage();
                            logActivity($_SESSION['user_id'], 'Payment Processing Error', 
                                "Failed to process payment for {$student_id}: " . $e->getMessage());
                        }
                    }
                }
                break;

            case 'add_bulk_payments':
                $bulk_payment_type = $_POST['bulk_payment_type'] ?? 'misc';
                $bulk_amount = floatval($_POST['bulk_amount'] ?? 0);
                $description = sanitizeInput($_POST['bulk_description'] ?? '');
                $school_year = sanitizeInput($_POST['bulk_school_year'] ?? '');
                $program_filter = $_POST['program_filter'] ?? '';
                $year_level_filter = $_POST['year_level_filter'] ?? '';
                
                if (empty($description)) {
                    $error = 'Description is required.';
                    logActivity($_SESSION['user_id'], 'Bulk Payment Failed', 'Description missing for bulk payments');
                } elseif ($bulk_payment_type === 'misc' && $bulk_amount <= 0) {
                    $error = 'Amount must be greater than 0 for miscellaneous payments.';
                    logActivity($_SESSION['user_id'], 'Bulk Payment Failed', "Invalid amount {$bulk_amount} for bulk miscellaneous payments");
                } else {
                    try {
                        // Build query for filtered students
                        $where_conditions = ["status = 'active'"];
                        $params = [];
                        
                        if (!empty($program_filter)) {
                            $where_conditions[] = "program = ?";
                            $params[] = $program_filter;
                        }
                        
                        if (!empty($year_level_filter)) {
                            $where_conditions[] = "year_level = ?";
                            $params[] = $year_level_filter;
                        }
                        
                        $where_clause = implode(' AND ', $where_conditions);
                        
                        // Get filtered active students
                        $stmt = $pdo->prepare("SELECT user_id, name, program, year_level, total_units FROM students_info WHERE {$where_clause}");
                        $stmt->execute($params);
                        $filtered_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($filtered_students)) {
                            $error = 'No active students found matching your filters.';
                            logActivity($_SESSION['user_id'], 'Bulk Payment Failed', 
                                "No active students found for bulk payment with filters: program={$program_filter}, year_level={$year_level_filter}");
                        } else {
                            $success_count = 0;
                            $error_count = 0;
                            $payment_ids = [];
                            
                            foreach ($filtered_students as $student) {
                                try {
                                    $permit_number = generatePermitNumber();
                                    
                                    // Calculate amount based on payment type
                                    if ($bulk_payment_type === 'exam') {
                                        $amount = $student['total_units'] * 2400; // ₱2,400 per unit
                                        $units = $student['total_units'];
                                        $final_description = "{$description} - {$student['total_units']} units";
                                    } else {
                                        $amount = $bulk_amount;
                                        $units = 0;
                                        $final_description = $description;
                                    }
                                    
                                    $amount_text = numberToWords($amount) . ' pesos';
                                    
                                    // Insert payment for each student
                                    $stmt = $pdo->prepare("INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year, payment_category, units) VALUES (?, ?, ?, ?, ?, 'unpaid', ?, CURDATE(), ?, ?, ?, ?)");
                                    $stmt->execute([
                                        $student['user_id'], 
                                        $permit_number, 
                                        $amount, 
                                        $amount_text, 
                                        $amount, // remaining_balance = full amount for unpaid
                                        $final_description, 
                                        $_SESSION['user_id'], 
                                        $school_year,
                                        $bulk_payment_type,
                                        $units
                                    ]);
                                    
                                    $payment_id = $pdo->lastInsertId();
                                    $payment_ids[] = $payment_id;
                                    $success_count++;
                                    
                                } catch(Exception $e) {
                                    $error_count++;
                                    error_log("Failed to add payment for student {$student['user_id']}: " . $e->getMessage());
                                    logActivity($_SESSION['user_id'], 'Bulk Payment Error', 
                                        "Failed to create payment for student {$student['user_id']}: " . $e->getMessage());
                                }
                            }
                            
                            $filter_info = "";
                            if (!empty($program_filter)) $filter_info .= " Program: {$program_filter}";
                            if (!empty($year_level_filter)) $filter_info .= " Year: {$year_level_filter}";
                            
                            logActivity($_SESSION['user_id'], 'Bulk Payments Created', 
                                "Created {$success_count} bulk payments for students{$filter_info} - {$description}. Payment IDs: " . implode(', ', $payment_ids));
                            
                            $success = "Bulk payments created successfully. {$success_count} payments created{$filter_info}.";
                            
                            if ($error_count > 0) {
                                $error = "{$error_count} payments failed to create. Check error logs for details.";
                            }
                            
                            // Hide bulk form after successful submission
                            echo '<script>document.addEventListener("DOMContentLoaded", function() { cancelBulkPayments(); });</script>';
                        }
                    } catch(Exception $e) {
                        $error = 'Failed to create bulk payments: ' . $e->getMessage();
                        logActivity($_SESSION['user_id'], 'Bulk Payment Error', 
                            "Failed to create bulk payments: " . $e->getMessage());
                    }
                }
                break;
                
            case 'record_payment':
                $payment_id = intval($_POST['payment_id'] ?? 0);
                $amount_paid = floatval($_POST['amount_paid'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if ($payment_id <= 0) {
                    $error = 'Invalid payment ID.';
                    logActivity($_SESSION['user_id'], 'Payment Recording Failed', 'Invalid payment ID for payment recording');
                } elseif ($amount_paid <= 0) {
                    $error = 'Amount paid must be greater than 0.';
                    logActivity($_SESSION['user_id'], 'Payment Recording Failed', "Invalid amount paid {$amount_paid} for payment recording");
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
                            logActivity($_SESSION['user_id'], 'Payment Recording Failed', "Payment ID {$payment_id} not found for recording");
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
                                $_SESSION['user_id'],
                                "Method: Cash" . ($notes ? ", Notes: {$notes}" : "")
                            ]);
                            
                            $pdo->commit();
                            
                            logActivity($_SESSION['user_id'], 'Payment Recorded', 
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
                        logActivity($_SESSION['user_id'], 'Payment Recording Error', 
                            "Failed to record payment for payment ID {$payment_id}: " . $e->getMessage());
                    }
                }
                break;
                
            case 'delete_payment':
                $payment_id = intval($_POST['payment_id'] ?? 0);
                
                if ($payment_id <= 0) {
                    $error = 'Invalid payment ID.';
                    logActivity($_SESSION['user_id'], 'Payment Deletion Failed', 'Invalid payment ID for deletion');
                } else {
                    try {
                        // Get payment info for logging
                        $stmt = $pdo->prepare("SELECT p.*, si.name as student_name FROM payments p 
                                               JOIN students_info si ON p.student_id = si.user_id 
                                               WHERE p.id = ?");
                        $stmt->execute([$payment_id]);
                        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$payment) {
                            $error = 'Payment not found.';
                            logActivity($_SESSION['user_id'], 'Payment Deletion Failed', "Payment ID {$payment_id} not found for deletion");
                        } else {
                            // Delete payment
                            $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
                            $stmt->execute([$payment_id]);
                            
                            logActivity($_SESSION['user_id'], 'Payment Deleted', 
                                "Deleted payment {$payment['permit_number']} for {$payment['student_name']} ({$payment['student_id']}) - ₱{$payment['amount']} - {$payment['description']} - ID: {$payment_id}");
                            
                            $success = 'Payment deleted successfully.';
                        }
                    } catch(Exception $e) {
                        $error = 'Failed to delete payment: ' . $e->getMessage();
                        logActivity($_SESSION['user_id'], 'Payment Deletion Error', 
                            "Failed to delete payment ID {$payment_id}: " . $e->getMessage());
                    }
                }
                break;
        }
    }
}

// Get all filters
$status_filter = $_GET['status'] ?? '';
$student_filter = $_GET['student'] ?? '';
$program_filter = $_GET['program'] ?? '';
$year_level_filter = $_GET['year_level'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$payment_type_filter = $_GET['payment_type'] ?? '';

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

if (!empty($program_filter)) {
    $where_conditions[] = "si.program = ?";
    $params[] = $program_filter;
}

if (!empty($year_level_filter)) {
    $where_conditions[] = "si.year_level = ?";
    $params[] = $year_level_filter;
}

if (!empty($payment_type_filter)) {
    $where_conditions[] = "p.payment_category = ?";
    $params[] = $payment_type_filter;
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
    $where_conditions[] = "(p.description LIKE ? OR p.permit_number LIKE ? OR si.name LIKE ? OR si.user_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
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

// Get programs and year levels for filters
$stmt = $pdo->query("SELECT DISTINCT program FROM students_info WHERE program IS NOT NULL AND program != '' ORDER BY program");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT DISTINCT year_level FROM students_info WHERE year_level IS NOT NULL ORDER BY year_level");
$year_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment categories for filter
$stmt = $pdo->query("SELECT DISTINCT payment_category FROM payments WHERE payment_category IS NOT NULL ORDER BY payment_category");
$payment_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active students count for bulk payments
$stmt = $pdo->query("SELECT COUNT(*) as active_count FROM students_info WHERE status = 'active'");
$active_count = $stmt->fetch(PDO::FETCH_ASSOC)['active_count'];

renderPageStart('Manage Payments', 'admin', 'manage_payments.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Payments</h2>
    <div>
        <button class="btn btn-primary me-2" onclick="toggleProcessPaymentForm()">
            <i class="fas fa-credit-card"></i> Process Payment
        </button>
        <button class="btn btn-success" onclick="toggleBulkPaymentForm()">
            <i class="fas fa-users"></i> Create Bulk Payments
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

<!-- Bulk Payment Form (Hidden by default) -->
<div class="card mb-4" id="bulkPaymentCard" style="display: none;">
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-users me-2"></i>Create Bulk Payments
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="bulkPaymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="add_bulk_payments">
            
            <div class="alert alert-info">
                <h6 class="alert-heading">
                    <i class="fas fa-info-circle me-2"></i>Bulk Payment Information
                </h6>
                <p class="mb-0">
                    This will create <strong>unpaid</strong> payments for selected students. Students will see these as pending payments that need to be settled.
                </p>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="bulk_payment_type" class="form-label">Payment Type</label>
                    <select class="form-select" id="bulk_payment_type" name="bulk_payment_type" onchange="handleBulkPaymentTypeChange()">
                        <option value="exam">Examination Fee</option>
                        <option value="misc">Miscellaneous Fee</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3" id="bulk_amount_section">
                    <label for="bulk_amount" class="form-label">Amount per Student (₱)</label>
                    <input type="number" class="form-control" id="bulk_amount" name="bulk_amount" 
                           min="0" step="0.01" value="0">
                    <div class="form-text">Fixed amount for each student</div>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="bulk_school_year" class="form-label">School Year</label>
                    <input type="text" class="form-control" id="bulk_school_year" name="bulk_school_year" 
                           value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="program_filter" class="form-label">Filter by Program (Optional)</label>
                    <select class="form-select" id="program_filter" name="program_filter">
                        <option value="">All Programs</option>
                        <?php foreach($programs as $program): ?>
                            <option value="<?php echo htmlspecialchars($program['program']); ?>">
                                <?php echo htmlspecialchars($program['program']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="year_level_filter" class="form-label">Filter by Year Level (Optional)</label>
                    <select class="form-select" id="year_level_filter" name="year_level_filter">
                        <option value="">All Year Levels</option>
                        <?php foreach($year_levels as $year): ?>
                            <option value="<?php echo htmlspecialchars($year['year_level']); ?>">
                                Year <?php echo htmlspecialchars($year['year_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-warning" id="bulk_payment_info">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <span id="bulk_info_text">
                            Examination fees will be calculated individually based on each student's total units (₱2,400 per unit).
                        </span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-3">
                    <label for="bulk_description" class="form-label">Description</label>
                    <textarea class="form-control" id="bulk_description" name="bulk_description" rows="2" 
                              placeholder="e.g., Midterm Examination Fee, Student Organization Fee, Library Fee, etc." required></textarea>
                    <div class="form-text" id="bulk_description_help">
                        This description will be used for all created payments.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-success" onclick="return confirmBulkPayment()">
                        <i class="fas fa-users"></i> Create Bulk Payments
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cancelBulkPayments()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Update Payment Info Form (Hidden by default) -->
<div class="card mb-4" id="updatePaymentInfoCard" style="display: none;">
    <div class="card-header bg-warning">
        <h5 class="card-title mb-0">
            <i class="fas fa-edit me-2"></i>Update Payment Information
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="updatePaymentInfoForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_payment_info">
            <input type="hidden" name="payment_id" id="update_payment_id">
            
            <div class="alert alert-warning">
                <h6 class="alert-heading">Permit: <span id="update_permit_number"></span></h6>
                <p class="mb-0">You are updating payment information. Use this to correct errors in amount, description, school year, or payment status.</p>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="update_amount" class="form-label">Amount</label>
                    <input type="number" class="form-control" id="update_amount" name="amount" 
                           min="0" step="0.01" required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="update_school_year" class="form-label">School Year</label>
                    <input type="text" class="form-control" id="update_school_year" name="school_year" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="update_payment_status" class="form-label">Payment Status</label>
                    <select class="form-select" id="update_payment_status" name="payment_status" required>
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="partial">Partial</option>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label">Current Balance</label>
                    <div class="form-control bg-light" id="update_current_balance_display">
                        ₱0.00
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-3">
                    <label for="update_description" class="form-label">Description</label>
                    <textarea class="form-control" id="update_description" name="description" rows="2" required></textarea>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-3">
                    <label for="admin_note" class="form-label">Admin Note (Required for changes)</label>
                    <textarea class="form-control" id="admin_note" name="admin_note" rows="2" 
                              placeholder="Explain why this payment information needs to be updated..." required></textarea>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Payment Information
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cancelUpdatePaymentInfo()">
                        <i class="fas fa-times"></i> Cancel
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
<div class="stats-card-container mb-4">
    <div>
        <?php echo renderStatsCard('Total Payments', number_format($summary['total_count']), 'fas fa-file-invoice-dollar', 'primary'); ?>
    </div>
    <div>
        <?php echo renderStatsCard('Total Amount', '₱' . number_format($summary['total_amount'], 2), 'fas fa-money-bill-wave', 'info'); ?>
    </div>
    <div>
        <?php echo renderStatsCard('Paid Amount', '₱' . number_format($summary['paid_amount'], 2), 'fas fa-check-circle', 'success'); ?>
    </div>
    <div>
        <?php echo renderStatsCard('Outstanding', '₱' . number_format($summary['unpaid_amount'] + $summary['partial_balance'], 2), 'fas fa-exclamation-triangle', 'warning'); ?>
    </div>
</div>

<!-- Enhanced Filters -->
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
                <label for="payment_type" class="form-label">Payment Type</label>
                <select class="form-select" id="payment_type_filter" name="payment_type">
                    <option value="">All Types</option>
                    <?php foreach($payment_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['payment_category']); ?>" 
                                <?php echo $payment_type_filter === $category['payment_category'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(htmlspecialchars($category['payment_category'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="program" class="form-label">Program</label>
                <select class="form-select" id="program" name="program">
                    <option value="">All Programs</option>
                    <?php foreach($programs as $program): ?>
                        <option value="<?php echo htmlspecialchars($program['program']); ?>" 
                                <?php echo $program_filter === $program['program'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($program['program']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="year_level" class="form-label">Year Level</label>
                <select class="form-select" id="year_level" name="year_level">
                    <option value="">All Years</option>
                    <?php foreach($year_levels as $year): ?>
                        <option value="<?php echo htmlspecialchars($year['year_level']); ?>" 
                                <?php echo $year_level_filter === $year['year_level'] ? 'selected' : ''; ?>>
                            Year <?php echo htmlspecialchars($year['year_level']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="student_filter" class="form-label">Student</label>
                <div class="dropdown">
                    <input type="text" class="form-control" id="student_filter" name="student" 
                           value="<?php echo htmlspecialchars($student_filter); ?>"
                           placeholder="Type student ID or name..."
                           autocomplete="off"
                           onkeyup="filterStudentOptions()"
                           onfocus="showStudentFilterDropdown()">
                    <div class="dropdown-menu w-100" id="student_filter_dropdown" style="display: none; max-height: 200px; overflow-y: auto;">
                        <?php foreach($students as $student): ?>
                            <button type="button" class="dropdown-item student-filter-option" 
                                    data-value="<?php echo htmlspecialchars($student['user_id']); ?>"
                                    onclick="selectStudentFilter('<?php echo htmlspecialchars($student['user_id']); ?>', '<?php echo htmlspecialchars($student['name']); ?>')">
                                <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['user_id']); ?>)
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
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
        
        <!-- Quick Date Filters -->
        <div class="row mt-3">
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
                       placeholder="Description, Permit #, Student Name or ID">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <a href="manage_payments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-refresh"></i> Clear
                    </a>
                </div>
            </div>
        </div>
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
                                <?php if ($payment['payment_category'] && $payment['payment_category'] !== 'other'): ?>
                                    <br><small class="text-muted"><?php echo ucfirst($payment['payment_category']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php echo htmlspecialchars($payment['issued_by_name']); ?><br>
                                    <span class="text-muted"><?php echo ucfirst($payment['issued_by_role']); ?></span>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <!-- View Details -->
                                    <a href="payment_details.php?payment_id=<?php echo $payment['id']; ?>" 
                                       class="btn btn-sm btn-outline-info" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <!-- Record Payment (for unpaid/partial payments) -->
                                    <?php if (in_array($payment['payment_status'], ['unpaid', 'partial'])): ?>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="recordPayment('<?php echo $payment['id']; ?>', '<?php echo htmlspecialchars($payment['permit_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($payment['student_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($payment['description'], ENT_QUOTES); ?>', '<?php echo $payment['amount']; ?>', '<?php echo $payment['remaining_balance']; ?>', '<?php echo $payment['payment_status']; ?>')"
                                            title="Record Payment">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </button>
                                    <?php endif; ?>

                                    <!-- Update Payment Info -->
                                    <button class="btn btn-sm btn-warning" 
                                            onclick="updatePaymentInfo('<?php echo $payment['id']; ?>', '<?php echo $payment['amount']; ?>', '<?php echo htmlspecialchars($payment['description'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($payment['school_year'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($payment['permit_number'], ENT_QUOTES); ?>', '<?php echo $payment['payment_status']; ?>', '<?php echo $payment['remaining_balance']; ?>')"
                                            title="Update Payment Information">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <!-- Delete Payment -->
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deletePayment('<?php echo $payment['id']; ?>', '<?php echo htmlspecialchars($payment['permit_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($payment['student_name'], ENT_QUOTES); ?>', '<?php echo $payment['amount']; ?>')"
                                            title="Delete Payment">
                                        <i class="fas fa-trash"></i>
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

<script>
// Student search and dropdown functionality for process payment form
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

// Student filter functionality for filter form
function filterStudentOptions() {
    const search = document.getElementById('student_filter').value.toLowerCase();
    const dropdown = document.getElementById('student_filter_dropdown');
    
    if (search.length < 2) {
        dropdown.innerHTML = '<div class="dropdown-item text-muted">Type at least 2 characters...</div>';
        dropdown.style.display = 'block';
        return;
    }
    
    // Show loading
    dropdown.innerHTML = '<div class="dropdown-item text-muted">Searching...</div>';
    dropdown.style.display = 'block';
    
    fetch(`ajax_handler.php?action=get_student_info&search=${encodeURIComponent(search)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                dropdown.innerHTML = '';
                
                if (data.students && data.students.length > 0) {
                    data.students.forEach(student => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'dropdown-item student-filter-option';
                        option.textContent = `${student.name} (${student.user_id})`;
                        option.onclick = () => selectStudentFilter(student.user_id, student.name);
                        dropdown.appendChild(option);
                    });
                } else {
                    dropdown.innerHTML = '<div class="dropdown-item text-muted">No students found</div>';
                }
            } else {
                dropdown.innerHTML = `<div class="dropdown-item text-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            dropdown.innerHTML = '<div class="dropdown-item text-danger">Error searching students</div>';
        });
}


function showStudentFilterDropdown() {
    const dropdown = document.getElementById('student_filter_dropdown');
    const search = document.getElementById('student_filter').value;
    
    if (search === '') {
        document.querySelectorAll('.student-filter-option').forEach(option => {
            option.style.display = 'block';
        });
    }
    
    dropdown.style.display = 'block';
    filterStudentOptions();
}

function selectStudentFilter(studentId, studentName) {
    document.getElementById('student_filter').value = studentId;
    document.getElementById('student_filter_dropdown').style.display = 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('student_dropdown');
    const search = document.getElementById('student_search');
    const filterDropdown = document.getElementById('student_filter_dropdown');
    const filterSearch = document.getElementById('student_filter');
    
    if (!dropdown.contains(event.target) && event.target !== search) {
        dropdown.style.display = 'none';
    }
    if (!filterDropdown.contains(event.target) && event.target !== filterSearch) {
        filterDropdown.style.display = 'none';
    }
});

// Load student information via AJAX        
function loadStudentInfo(studentId) {    
    // Show loading state
    document.getElementById('student_info_section').style.display = 'block';
    document.getElementById('info_program').textContent = 'Loading...';
    document.getElementById('info_year_level').textContent = 'Loading...';
    document.getElementById('info_total_units').textContent = 'Loading...';
    document.getElementById('info_student_type').textContent = 'Loading...';
    document.getElementById('info_status').textContent = 'Loading...';
    
    // Use consolidated AJAX handler
    fetch(`ajax_handler.php?action=get_student_info&student_id=${encodeURIComponent(studentId)}`)
        .then(response => response.json())
        .then(data => {
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
                alert('Error loading student information: ' + data.message);
            }
        })
        .catch(error => {
            document.getElementById('student_info_section').style.display = 'none';
            console.error('Error loading student info:', error);
            alert('Failed to load student information. Please try again.');
        });
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

// Bulk payment type handling
function handleBulkPaymentTypeChange() {
    const bulkPaymentType = document.getElementById('bulk_payment_type').value;
    const bulkAmountSection = document.getElementById('bulk_amount_section');
    const bulkInfoText = document.getElementById('bulk_info_text');
    const bulkDescriptionHelp = document.getElementById('bulk_description_help');
    
    if (bulkPaymentType === 'exam') {
        bulkAmountSection.style.display = 'none';
        bulkInfoText.textContent = 'Examination fees will be calculated individually based on each student\'s total units (₱2,400 per unit).';
        bulkDescriptionHelp.textContent = 'This description will be used for all created payments. Unit count will be appended automatically.';
    } else {
        bulkAmountSection.style.display = 'block';
        bulkInfoText.textContent = 'All students will receive the same fixed amount.';
        bulkDescriptionHelp.textContent = 'This description will be used for all created payments.';
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

// Update remaining balance after payment
function updateRemainingAfterPayment() {
    const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
    const currentBalance = parseFloat(document.getElementById('record_balance_due').textContent.replace('₱', '').replace(',', '')) || 0;
    const remaining = currentBalance - amountPaid;
    
    const display = document.getElementById('remaining_after_payment');
    display.textContent = '₱' + remaining.toFixed(2);
    
    if (remaining < 0) {
        display.className = 'fw-bold text-danger';
    } else if (remaining === 0) {
        display.className = 'fw-bold text-success';
    } else {
        display.className = 'fw-bold text-warning';
    }
}

// Clear form function (without confirmation)
function clearPaymentForm() {
    document.getElementById('student_search').value = '';
    document.getElementById('student_id').value = '';
    document.getElementById('selected_student').style.display = 'none';
    document.getElementById('student_info_section').style.display = 'none';
    document.getElementById('payment_type').value = 'other';
    document.getElementById('units_section').style.display = 'none';
    document.getElementById('units').value = '0';
    document.getElementById('amount').value = '';
    document.getElementById('amount_paid').value = '0';
    document.getElementById('description').value = '';
    document.getElementById('remaining_balance_display').textContent = '₱0.00';
    document.getElementById('remaining_balance_display').className = 'form-control bg-light';
    document.getElementById('payment_status').value = 'unpaid';
    
    handlePaymentTypeChange();
    calculateRemainingBalance();
}

// Enhanced reset function
function resetProcessPaymentForm() {
    clearPaymentForm();
    document.getElementById('processPaymentCard').style.display = 'none';
}

// Toggle Process Payment form visibility
function toggleProcessPaymentForm() {
    const processPaymentCard = document.getElementById('processPaymentCard');
    if (processPaymentCard.style.display === 'none') {
        processPaymentCard.style.display = 'block';
        // Hide other forms if they're visible
        document.getElementById('bulkPaymentCard').style.display = 'none';
        document.getElementById('updatePaymentInfoCard').style.display = 'none';
        document.getElementById('recordPaymentCard').style.display = 'none';
        // Clear form when opening (no confirmation)
        clearPaymentForm();
        // Scroll to process form
        processPaymentCard.scrollIntoView({ behavior: 'smooth' });
    } else {
        processPaymentCard.style.display = 'none';
    }
}

// Toggle Bulk Payment form visibility
function toggleBulkPaymentForm() {
    const bulkPaymentCard = document.getElementById('bulkPaymentCard');
    if (bulkPaymentCard.style.display === 'none') {
        bulkPaymentCard.style.display = 'block';
        // Hide other forms if they're visible
        document.getElementById('processPaymentCard').style.display = 'none';
        document.getElementById('updatePaymentInfoCard').style.display = 'none';
        document.getElementById('recordPaymentCard').style.display = 'none';
        // Initialize bulk payment type
        handleBulkPaymentTypeChange();
        // Scroll to bulk form
        bulkPaymentCard.scrollIntoView({ behavior: 'smooth' });
    } else {
        bulkPaymentCard.style.display = 'none';
    }
}

function cancelBulkPayments() {
    document.getElementById('bulkPaymentForm').reset();
    document.getElementById('bulkPaymentCard').style.display = 'none';
}

function confirmBulkPayment() {
    const paymentType = document.getElementById('bulk_payment_type').value;
    const description = document.getElementById('bulk_description').value;
    const programFilter = document.getElementById('program_filter').value;
    const yearLevelFilter = document.getElementById('year_level_filter').value;
    
    let filterInfo = "";
    if (programFilter) filterInfo += ` Program: ${programFilter}`;
    if (yearLevelFilter) filterInfo += ` Year: ${yearLevelFilter}`;
    if (!filterInfo) filterInfo = " all active students";
    
    let amountInfo = "";
    if (paymentType === 'misc') {
        const amount = document.getElementById('bulk_amount').value;
        if (!amount || amount <= 0) {
            alert('Please enter a valid amount for miscellaneous payments.');
            return false;
        }
        amountInfo = `Amount: ₱${amount}`;
    } else {
        amountInfo = "Amount: Calculated per student based on units (₱2,400/unit)";
    }
    
    return confirm(`Are you sure you want to create unpaid payments for${filterInfo}?\n\n${amountInfo}\nDescription: ${description}\n\nThese will appear as pending payments for students.`);
}

// Update Payment Information
function updatePaymentInfo(paymentId, amount, description, schoolYear, permitNumber, currentStatus, currentBalance) {
    // Show update form and hide other forms
    document.getElementById('updatePaymentInfoCard').style.display = 'block';
    document.getElementById('processPaymentCard').style.display = 'none';
    document.getElementById('bulkPaymentCard').style.display = 'none';
    document.getElementById('recordPaymentCard').style.display = 'none';
    
    // Populate update form fields
    document.getElementById('update_payment_id').value = paymentId;
    document.getElementById('update_amount').value = amount;
    document.getElementById('update_description').value = description;
    document.getElementById('update_school_year').value = schoolYear;
    document.getElementById('update_payment_status').value = currentStatus;
    document.getElementById('update_permit_number').textContent = permitNumber;
    document.getElementById('update_current_balance_display').textContent = '₱' + parseFloat(currentBalance).toFixed(2);
    
    // Scroll to update form
    document.getElementById('updatePaymentInfoCard').scrollIntoView({ behavior: 'smooth' });
}

function cancelUpdatePaymentInfo() {
    // Hide update form
    document.getElementById('updatePaymentInfoCard').style.display = 'none';
    
    // Clear update form fields
    document.getElementById('update_payment_id').value = '';
    document.getElementById('update_amount').value = '';
    document.getElementById('update_description').value = '';
    document.getElementById('update_school_year').value = '';
    document.getElementById('update_payment_status').value = 'unpaid';
    document.getElementById('update_permit_number').textContent = '';
    document.getElementById('update_current_balance_display').textContent = '₱0.00';
    document.getElementById('admin_note').value = '';
}

// Record Payment
function recordPayment(paymentId, permitNumber, studentName, description, totalAmount, balanceDue, currentStatus) {
    // Show record form and hide other forms
    document.getElementById('recordPaymentCard').style.display = 'block';
    document.getElementById('processPaymentCard').style.display = 'none';
    document.getElementById('bulkPaymentCard').style.display = 'none';
    document.getElementById('updatePaymentInfoCard').style.display = 'none';
    
    // Populate record form fields
    document.getElementById('record_payment_id').value = paymentId;
    document.getElementById('record_permit_number').textContent = permitNumber;
    document.getElementById('record_student_name').textContent = studentName;
    document.getElementById('record_description').textContent = description;
    document.getElementById('record_total_amount').textContent = '₱' + parseFloat(totalAmount).toFixed(2);
    document.getElementById('record_balance_due').textContent = '₱' + parseFloat(balanceDue).toFixed(2);
    
    // Reset amount paid
    document.getElementById('amount_paid').value = '';
    document.getElementById('notes').value = '';
    document.getElementById('remaining_after_payment').textContent = '₱' + parseFloat(balanceDue).toFixed(2);
    document.getElementById('remaining_after_payment').className = 'fw-bold text-warning';
    
    // Scroll to record form
    document.getElementById('recordPaymentCard').scrollIntoView({ behavior: 'smooth' });
}

function cancelRecordPayment() {
    // Hide record form
    document.getElementById('recordPaymentCard').style.display = 'none';
    
    // Clear record form fields
    document.getElementById('record_payment_id').value = '';
    document.getElementById('record_permit_number').textContent = '';
    document.getElementById('record_student_name').textContent = '-';
    document.getElementById('record_description').textContent = '-';
    document.getElementById('record_total_amount').textContent = '-';
    document.getElementById('record_balance_due').textContent = '-';
    document.getElementById('amount_paid').value = '';
    document.getElementById('notes').value = '';
}

function deletePayment(paymentId, permitNumber, studentName, amount) {
    if (confirm(`Are you sure you want to delete payment ${permitNumber} for ${studentName} (₱${amount})? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_payment">
            <input type="hidden" name="payment_id" value="${paymentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize bulk payment type on page load
document.addEventListener('DOMContentLoaded', function() {
    handleBulkPaymentTypeChange();
});
</script>

<?php renderPageEnd(); ?>