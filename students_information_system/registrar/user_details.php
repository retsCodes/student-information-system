<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('registrar');

$pdo = getDBConnection();

// Get user_id from URL
$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    header('Location: manage_users.php');
    exit;
}

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*, 
           COALESCE(si.program, ei.role) as additional_info,
           COALESCE(si.year_level, '') as year_level,
           si.student_type,
           si.enrollment_status,
           si.enrollment_date,
           si.number as contact_number,
           si.address
    FROM users u
    LEFT JOIN students_info si ON u.user_id = si.user_id
    LEFT JOIN employee_info ei ON u.user_id = ei.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    header('Location: manage_users.php');
    exit;
}

// Get additional information based on role
if ($user['role'] === 'student') {
    // Get assigned section
    $stmt = $pdo->prepare("
        SELECT s.section_code, s.program, s.year_level 
        FROM student_sections ss 
        JOIN sections s ON ss.section_id = s.id 
        WHERE ss.student_id = ?
    ");
    $stmt->execute([$user_id]);
    $assigned_section = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get enrolled subjects
    $stmt = $pdo->prepare("
        SELECT sub.subject_code, sub.subject_name, sub.units
        FROM student_subjects ss 
        JOIN subjects sub ON ss.subject_id = sub.id 
        WHERE ss.student_id = ?
    ");
    $stmt->execute([$user_id]);
    $enrolled_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payment summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
            SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
            SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END) as partial_amount,
            SUM(amount) as total_amount,
            SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount
        FROM payments 
        WHERE student_id = ?
    ");
    $stmt->execute([$user_id]);
    $payment_summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent payments - FIXED: Removed payment_types reference
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as issued_by_name
        FROM payments p
        LEFT JOIN users u ON p.issued_by = u.user_id
        WHERE p.student_id = ?
        ORDER BY p.issued_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // For employees, get payment issuance summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_issued,
            SUM(amount) as total_amount_issued,
            COUNT(DISTINCT student_id) as unique_students_served
        FROM payments 
        WHERE issued_by = ?
    ");
    $stmt->execute([$user_id]);
    $issuance_summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent payments issued - FIXED: Removed payment_types reference
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as student_name
        FROM payments p
        LEFT JOIN users u ON p.student_id = u.user_id
        WHERE p.issued_by = ?
        ORDER BY p.issued_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_issued_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user activity log
$stmt = $pdo->prepare("
    SELECT action, description, created_at 
    FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$user_id]);
$activity_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('User Details - ' . $user['name'], 'registrar', 'manage_users.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>User Details</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="manage_users.php">Manage Users</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($user['name']); ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <a href="manage_users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
        <button class="btn btn-warning" onclick="editUserFromDetails('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>')">
            <i class="fas fa-edit"></i> Edit User
        </button>
    </div>
</div>

<div class="row">
    <!-- Basic Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>Basic Information
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="30%">User ID:</th>
                        <td><code><?php echo htmlspecialchars($user['user_id']); ?></code></td>
                    </tr>
                    <tr>
                        <th>Name:</th>
                        <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                    </tr>
                    <tr>
                        <th>Role:</th>
                        <td>
                            <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?php echo $user['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($user['user_status']); ?>
                            </span>
                        </td>
                    </tr>
                    
                    <?php if ($user['role'] === 'student'): ?>
                    <tr>
                        <th>Program:</th>
                        <td><?php echo htmlspecialchars($user['additional_info'] ?? 'Not set'); ?></td>
                    </tr>
                    <tr>
                        <th>Year Level:</th>
                        <td><?php echo $user['year_level'] ? 'Year ' . $user['year_level'] : 'Not Set'; ?></td>
                    </tr>
                    <tr>
                        <th>Student Type:</th>
                        <td>
                            <span class="badge bg-<?php echo $user['student_type'] === 'regular' ? 'primary' : 'warning'; ?>">
                                <?php echo ucfirst($user['student_type'] ?? 'Not set'); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Enrollment Status:</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $user['enrollment_status'] === 'enrolled' ? 'success' : 
                                    ($user['enrollment_status'] === 'dropped' ? 'danger' : 'info'); 
                            ?>">
                                <?php echo ucfirst($user['enrollment_status'] ?? 'Not set'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($user['enrollment_date']): ?>
                    <tr>
                        <th>Enrollment Date:</th>
                        <td><?php echo date('M j, Y', strtotime($user['enrollment_date'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($user['contact_number']): ?>
                    <tr>
                        <th>Contact Number:</th>
                        <td><?php echo htmlspecialchars($user['contact_number']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($user['address']): ?>
                    <tr>
                        <th>Address:</th>
                        <td><?php echo htmlspecialchars($user['address']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <tr>
                        <th>Position:</th>
                        <td><?php echo htmlspecialchars($user['additional_info'] ?? 'Not set'); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Account Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Account Information
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="40%">Account Created:</th>
                        <td><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Last Active:</th>
                        <td>
                            <?php echo $user['last_active'] ? date('M j, Y g:i A', strtotime($user['last_active'])) : 'Never'; ?>
                        </td>
                    </tr>
                </table>

                <!-- Quick Actions -->
                <div class="mt-4">
                    <h6>Quick Actions</h6>
                    <div class="btn-group" role="group">
                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                        <form method="POST" action="manage_users.php" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            <button type="submit" class="btn btn-<?php echo $user['user_status'] === 'active' ? 'warning' : 'success'; ?> btn-sm"
                                    onclick="return confirm('Are you sure you want to <?php echo $user['user_status'] === 'active' ? 'lock' : 'unlock'; ?> this user?')">
                                <i class="fas fa-<?php echo $user['user_status'] === 'active' ? 'lock' : 'unlock'; ?>"></i>
                                <?php echo $user['user_status'] === 'active' ? 'Lock' : 'Unlock'; ?> User
                            </button>
                        </form>
                        <?php endif; ?>

                        <button class="btn btn-info btn-sm" 
                                onclick="resetPasswordFromDetails('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')">
                            <i class="fas fa-key"></i> Reset Password
                        </button>

                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                        <button class="btn btn-danger btn-sm" 
                                onclick="deleteUserFromDetails('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')">
                            <i class="fas fa-trash"></i> Delete User
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($user['role'] === 'student'): ?>
<!-- Student Specific Information -->
<div class="row">
    <!-- Academic Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>Academic Information
                </h5>
            </div>
            <div class="card-body">
                <?php if ($assigned_section): ?>
                <div class="mb-3">
                    <strong>Assigned Section:</strong><br>
                    <?php echo htmlspecialchars($assigned_section['section_code']); ?> - 
                    <?php echo htmlspecialchars($assigned_section['program']); ?> 
                    (Year <?php echo $assigned_section['year_level']; ?>)
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No section assigned
                </div>
                <?php endif; ?>

                <?php if (!empty($enrolled_subjects)): ?>
                <div>
                    <strong>Enrolled Subjects (<?php echo count($enrolled_subjects); ?>):</strong>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Subject Name</th>
                                    <th>Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($enrolled_subjects as $subject): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                    <td><?php echo $subject['units']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No subjects enrolled
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Summary -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>Payment Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-primary"><?php echo $payment_summary['total_payments']; ?></h4>
                            <small class="text-muted">Total Payments</small>
                        </div>
                    </div>
                    <div class="col-4 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-success"><?php echo $payment_summary['paid_count']; ?></h4>
                            <small class="text-muted">Paid</small>
                        </div>
                    </div>
                    <div class="col-4 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-warning"><?php echo $payment_summary['partial_count']; ?></h4>
                            <small class="text-muted">Partial</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <h4 class="text-danger"><?php echo $payment_summary['unpaid_count']; ?></h4>
                            <small class="text-muted">Unpaid</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <h4 class="text-warning">₱<?php echo number_format(($payment_summary['unpaid_amount'] + $payment_summary['partial_amount']), 2); ?></h4>
                            <small class="text-muted">Outstanding</small>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <a href="manage_payments.php?student=<?php echo $user['user_id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-list"></i> View All Payments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-receipt me-2"></i>Recent Payments
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($recent_payments)): ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Permit #</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Issued By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_payments as $payment): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($payment['issued_date'])); ?></td>
                        <td><code><?php echo htmlspecialchars($payment['permit_number']); ?></code></td>
                        <td><?php echo htmlspecialchars($payment['description']); ?></td>
                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                        <td>₱<?php echo number_format($payment['remaining_balance'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $payment['payment_status'] === 'paid' ? 'success' : 
                                    ($payment['payment_status'] === 'partial' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($payment['payment_status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($payment['issued_by_name']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No payment records found.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Employee Specific Information -->
<div class="row">
    <!-- Work Summary -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Work Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border rounded p-3">
                            <h4 class="text-primary"><?php echo $issuance_summary['total_issued']; ?></h4>
                            <small class="text-muted">Total Payments Issued</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3">
                            <h4 class="text-success">₱<?php echo number_format($issuance_summary['total_amount_issued'], 2); ?></h4>
                            <small class="text-muted">Total Amount Issued</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3">
                            <h4 class="text-info"><?php echo $issuance_summary['unique_students_served']; ?></h4>
                            <small class="text-muted">Students Served</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments Issued -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-receipt me-2"></i>Recent Payments Issued
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($recent_issued_payments)): ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Permit #</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_issued_payments as $payment): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($payment['issued_date'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($payment['permit_number']); ?></code></td>
                        <td><?php echo htmlspecialchars($payment['description']); ?></td>
                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $payment['payment_status'] === 'paid' ? 'success' : 
                                    ($payment['payment_status'] === 'partial' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($payment['payment_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No payment issuance records found.
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Activity Log -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-history me-2"></i>Recent Activity
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
            <i class="fas fa-info-circle"></i> No recent activity found.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_users.php">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="reset_user_id" id="reset_user_id">
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">Reset Password for: <span id="reset_user_name"></span></h6>
                        <p class="mb-0">The user will need to use the new password to log in.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               minlength="6" required>
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUserFromDetails(userId) {
    // Redirect to manage_users.php with edit parameter
    window.location.href = `manage_users.php?edit=${userId}`;
}

function resetPasswordFromDetails(userId, userName) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = userName;
    document.getElementById('new_password').value = '';
    
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

function deleteUserFromDetails(userId, userName) {
    if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_users.php';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="delete_user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Add auto-focus to search input when page loads
document.addEventListener('DOMContentLoaded', function() {
    // You can add any page-specific JavaScript here
});
</script>

<?php renderPageEnd(); ?>