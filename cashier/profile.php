<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current cashier information
$stmt = $pdo->prepare("SELECT u.*, ei.number, ei.role as employee_role 
                       FROM users u 
                       LEFT JOIN employee_info ei ON u.user_id = ei.user_id 
                       WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$cashier_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get cashier statistics
$stmt = $pdo->prepare("SELECT 
                        COUNT(*) as total_payments,
                        SUM(amount) as total_amount,
                        COUNT(DISTINCT student_id) as total_students,
                        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as completed_payments
                       FROM payments 
                       WHERE issued_by = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent payments
$stmt = $pdo->prepare("SELECT p.*, si.name as student_name 
                       FROM payments p 
                       JOIN students_info si ON p.student_id = si.user_id 
                       WHERE p.issued_by = ? 
                       ORDER BY p.issued_date DESC 
                       LIMIT 5");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'update_info':
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $number = sanitizeInput($_POST['number'] ?? '');
                
                if (empty($name)) {
                    $error = 'Name is required.';
                } elseif (empty($email) || !validateEmail($email)) {
                    $error = 'Valid email is required.';
                } else {
                    try {
                        // Check if email is already used by another user
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND user_id != ?");
                        $stmt->execute([$email, $user_id]);
                        if ($stmt->fetch()) {
                            $error = 'Email is already used by another user.';
                        } else {
                            $pdo->beginTransaction();
                            
                            // Update users table
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $user_id]);
                            
                            // Update or insert employee_info
                            $stmt = $pdo->prepare("SELECT id FROM employee_info WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            
                            if ($stmt->fetch()) {
                                $stmt = $pdo->prepare("UPDATE employee_info SET name = ?, email = ?, number = ? WHERE user_id = ?");
                                $stmt->execute([$name, $email, $number, $user_id]);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO employee_info (user_id, name, email, number, role) VALUES (?, ?, ?, ?, 'Cashier')");
                                $stmt->execute([$user_id, $name, $email, $number]);
                            }
                            
                            $pdo->commit();
                            
                            // Update session name
                            $_SESSION['name'] = $name;
                            
                            logActivity($user_id, 'Profile Update', 'Updated personal information');
                            $success = 'Profile updated successfully.';
                            
                            // Refresh cashier info
                            $stmt = $pdo->prepare("SELECT u.*, ei.number, ei.role as employee_role 
                       FROM users u 
                       LEFT JOIN employee_info ei ON u.user_id = ei.user_id 
                       WHERE u.user_id = ?");
                            $stmt->execute([$user_id]);
                            $cashier_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                    } catch(Exception $e) {
                        $pdo->rollBack();
                        $error = 'Failed to update profile: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password)) {
                    $error = 'Current password is required.';
                } elseif (empty($new_password) || strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New password confirmation does not match.';
                } else {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $current_hash = $stmt->fetchColumn();
                    
                    if (!verifyPassword($current_password, $current_hash)) {
                        $error = 'Current password is incorrect.';
                    } else {
                        // Update password
                        $new_hash = hashPassword($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$new_hash, $user_id]);
                        
                        logActivity($user_id, 'Password Change', 'Changed account password');
                        $success = 'Password changed successfully.';
                    }
                }
                break;
        }
    }
}

renderPageStart('Cashier Profile', 'cashier', 'profile.php');
?>

<style>
.avatar-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: bold;
}
.stats-card {
    transition: transform 0.2s ease;
}
.stats-card:hover {
    transform: translateY(-2px);
}
</style>

<div class="row">
    <div class="col-md-4 mb-4">
        <!-- Profile Picture Card -->
        <div class="card text-center">
            <div class="card-body">
                <div class="avatar-circle mx-auto mb-3" style="width: 100px; height: 100px; font-size: 36px;">
                    <?php echo strtoupper(substr($cashier_info['name'], 0, 1)); ?>
                </div>
                <h5 class="card-title"><?php echo htmlspecialchars($cashier_info['name']); ?></h5>
                <p class="text-muted">Cashier ID: <?php echo htmlspecialchars($user_id); ?></p>
                <div class="row text-center">
                    <div class="col">
                        <h6 class="text-primary"><?php echo htmlspecialchars($cashier_info['employee_role'] ?? 'Cashier'); ?></h6>
                        <small class="text-muted">Position</small>
                    </div>
                    <div class="col">
                        <h6 class="text-success">Active</h6>
                        <small class="text-muted">Status</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Performance Stats</h6>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-8"><strong>Total Payments Processed:</strong></div>
                    <div class="col-4 text-end"><span class="badge bg-primary"><?php echo number_format($stats['total_payments']); ?></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-8"><strong>Total Amount:</strong></div>
                    <div class="col-4 text-end"><span class="badge bg-success">₱<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-8"><strong>Students Served:</strong></div>
                    <div class="col-4 text-end"><span class="badge bg-info"><?php echo number_format($stats['total_students']); ?></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-8"><strong>Completed Payments:</strong></div>
                    <div class="col-4 text-end"><span class="badge bg-success"><?php echo number_format($stats['completed_payments']); ?></span></div>
                </div>
            </div>
        </div>

        <!-- Quick Info Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Account Information</h6>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-4"><strong>User ID:</strong></div>
                    <div class="col-8"><code><?php echo htmlspecialchars($user_id); ?></code></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4"><strong>Status:</strong></div>
                    <div class="col-8"><span class="badge bg-success"><?php echo ucfirst($cashier_info['user_status'] ?? 'active'); ?></span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4"><strong>Role:</strong></div>
                    <div class="col-8">Cashier</div>
                </div>
                <div class="row mb-2">
                    <div class="col-4"><strong>Last Active:</strong></div>
                    <div class="col-8"><?php echo $cashier_info['last_active'] ? date('M j, Y g:i A', strtotime($cashier_info['last_active'])) : 'Never'; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
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
        
        <!-- Personal Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user"></i> Personal Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="updateInfoForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_info">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($cashier_info['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($cashier_info['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="number" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="number" name="number" 
                                   value="<?php echo htmlspecialchars($cashier_info['number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_id" class="form-label">Cashier ID</label>
                            <input type="text" class="form-control" id="user_id" 
                                   value="<?php echo htmlspecialchars($user_id); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Position</label>
                            <input type="text" class="form-control" id="role" 
                                   value="<?php echo htmlspecialchars($cashier_info['employee_role'] ?? 'Cashier'); ?>" readonly>
                            <div class="form-text">Position changes require admin approval</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Account Status</label>
                            <input type="text" class="form-control" id="status" 
                                   value="<?php echo ucfirst($cashier_info['user_status'] ?? 'active'); ?>" readonly>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Information
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Recent Payments -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Recent Payments Processed
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_payments)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-receipt fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No payments processed yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Permit #</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_payments as $payment): ?>
                                <tr>
                                    <td><small><?php echo date('M j', strtotime($payment['issued_date'])); ?></small></td>
                                    <td>
                                        <small><?php echo htmlspecialchars($payment['student_name']); ?></small>
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
                                    <td><code><?php echo htmlspecialchars($payment['permit_number']); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2">
                        <a href="payments.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list"></i> View All Payments
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
<script>

</script>

<?php renderPageEnd(); ?>