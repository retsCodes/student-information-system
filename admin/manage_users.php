<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = $_POST['user_id'] ?? '';
        
        switch($action) {
            case 'toggle_status':
                if (!empty($user_id)) {
                    $stmt = $pdo->prepare("SELECT user_status FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $current_status = $stmt->fetchColumn();
                    
                    $new_status = ($current_status === 'active') ? 'locked' : 'active';
                    
                    $stmt = $pdo->prepare("UPDATE users SET user_status = ? WHERE user_id = ?");
                    if ($stmt->execute([$new_status, $user_id])) {
                        logActivity($_SESSION['user_id'], 'User Status Change', 
                                   "Changed user {$user_id} status from {$current_status} to {$new_status}");
                        $success = "User status updated successfully.";
                    } else {
                        $error = "Failed to update user status.";
                    }
                }
                break;
                
            case 'add_user':
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = $_POST['role'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (empty($name)) {
                    $error = 'Name is required.';
                } elseif (empty($email) || !validateEmail($email)) {
                    $error = 'Valid email is required.';
                } elseif (empty($role) || !in_array($role, ['admin', 'cashier', 'student'])) {
                    $error = 'Valid role is required.';
                } elseif (empty($password) || strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    try {
                        // Generate user ID based on role
                        if ($role === 'student') {
                            $new_user_id = generateStudentID();
                        } else {
                            $prefix = strtoupper($role);
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
                            $stmt->execute([$role]);
                            $count = $stmt->fetchColumn() + 1;
                            $new_user_id = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
                        }
                        
                        // Check if email already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            $error = 'Email already exists.';
                        } else {
                            $hashed_password = hashPassword($password);
                            
                            // Insert user
                            $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$new_user_id, $name, $email, $hashed_password, $role]);
                            
                            // Insert additional info based on role
                            if ($role === 'student') {
                                $stmt = $pdo->prepare("INSERT INTO students_info (user_id, name, email) VALUES (?, ?, ?)");
                                $stmt->execute([$new_user_id, $name, $email]);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO employee_info (user_id, name, email, role) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$new_user_id, $name, $email, ucfirst($role)]);
                            }
                            
                            logActivity($_SESSION['user_id'], 'User Created', 
                                       "Created new {$role} user: {$new_user_id} - {$name}");
                            $success = "User created successfully. User ID: {$new_user_id}";
                        }
                    } catch(Exception $e) {
                        $error = "Failed to create user: " . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_user':
                $edit_user_id = sanitizeInput($_POST['edit_user_id'] ?? '');
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                
                if (empty($edit_user_id)) {
                    $error = 'Invalid user ID.';
                } elseif (empty($name)) {
                    $error = 'Name is required.';
                } elseif (empty($email) || !validateEmail($email)) {
                    $error = 'Valid email is required.';
                } else {
                    // Check if email is already used by another user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND user_id != ?");
                    $stmt->execute([$email, $edit_user_id]);
                    if ($stmt->fetch()) {
                        $error = 'Email is already used by another user.';
                    } else {
                        try {
                            // Get old user info for logging
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                            $stmt->execute([$edit_user_id]);
                            $old_user = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Update users table
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $edit_user_id]);
                            
                            // Update additional info based on role
                            if ($old_user['role'] === 'student') {
                                $stmt = $pdo->prepare("UPDATE students_info SET name = ?, email = ? WHERE user_id = ?");
                                $stmt->execute([$name, $email, $edit_user_id]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE employee_info SET name = ?, email = ? WHERE user_id = ?");
                                $stmt->execute([$name, $email, $edit_user_id]);
                            }
                            
                            logActivity($_SESSION['user_id'], 'User Updated', 
                                       "Updated user {$edit_user_id}: name from '{$old_user['name']}' to '{$name}', email from '{$old_user['email']}' to '{$email}'");
                            $success = 'User updated successfully.';
                        } catch(Exception $e) {
                            $error = 'Failed to update user: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'delete_user':
                $delete_user_id = sanitizeInput($_POST['delete_user_id'] ?? '');
                
                if (empty($delete_user_id)) {
                    $error = 'Invalid user ID.';
                } elseif ($delete_user_id === $_SESSION['user_id']) {
                    $error = 'Cannot delete your own account.';
                } else {
                    // Get user info
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->execute([$delete_user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user) {
                        $error = 'User not found.';
                    } else {
                        // Check if user has payments (for students) or issued payments (for cashiers/admins)
                        if ($user['role'] === 'student') {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE student_id = ?");
                            $stmt->execute([$delete_user_id]);
                            $payment_count = $stmt->fetchColumn();
                            
                            if ($payment_count > 0) {
                                $error = 'Cannot delete student: Student has payment records.';
                            }
                        } else {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE issued_by = ?");
                            $stmt->execute([$delete_user_id]);
                            $issued_count = $stmt->fetchColumn();
                            
                            if ($issued_count > 0) {
                                $error = 'Cannot delete user: User has issued payment records.';
                            }
                        }
                        
                        if (empty($error)) {
                            try {
                                // Delete user (CASCADE will handle related tables)
                                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                                $stmt->execute([$delete_user_id]);
                                
                                logActivity($_SESSION['user_id'], 'User Deleted', "Deleted user: {$delete_user_id} - {$user['name']} ({$user['role']})");
                                $success = 'User deleted successfully.';
                            } catch(Exception $e) {
                                $error = 'Failed to delete user: ' . $e->getMessage();
                            }
                        }
                    }
                }
                break;
                
            case 'reset_password':
                $reset_user_id = sanitizeInput($_POST['reset_user_id'] ?? '');
                $new_password = $_POST['new_password'] ?? '';
                
                if (empty($reset_user_id)) {
                    $error = 'Invalid user ID.';
                } elseif (empty($new_password) || strlen($new_password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    try {
                        $hashed_password = hashPassword($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$hashed_password, $reset_user_id]);
                        
                        logActivity($_SESSION['user_id'], 'Password Reset', "Admin reset password for user: {$reset_user_id}");
                        $success = 'Password reset successfully.';
                    } catch(Exception $e) {
                        $error = 'Failed to reset password: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get filters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.user_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.user_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with additional info
$query = "SELECT u.*, 
                 COALESCE(si.program, ei.role) as additional_info,
                 COALESCE(si.year_level, '') as year_level
          FROM users u
          LEFT JOIN students_info si ON u.user_id = si.user_id
          LEFT JOIN employee_info ei ON u.user_id = ei.user_id
          {$where_clause}
          ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Manage Users', 'admin', 'manage_users.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Users</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-plus"></i> Add User
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

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="cashier" <?php echo $role_filter === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                    <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="locked" <?php echo $status_filter === 'locked' ? 'selected' : ''; ?>>Locked</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Name, Email, or User ID">
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

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Additional Info</th>
                        <th>Status</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($user['user_id']); ?></code></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span></td>
                        <td>
                            <?php 
                            if ($user['role'] === 'student' && $user['additional_info']) {
                                echo htmlspecialchars($user['additional_info']);
                                if ($user['year_level']) {
                                    echo ' - Year ' . $user['year_level'];
                                }
                            } elseif ($user['additional_info']) {
                                echo htmlspecialchars($user['additional_info']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $user['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($user['user_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $user['last_active'] ? date('M j, Y g:i A', strtotime($user['last_active'])) : 'Never'; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-info" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewUserModal<?php echo $user['id']; ?>"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" 
                                        onclick="editUser('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>')"
                                        title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="resetPassword('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')"
                                        title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-<?php echo $user['user_status'] === 'active' ? 'warning' : 'success'; ?>"
                                            onclick="return confirm('Are you sure you want to <?php echo $user['user_status'] === 'active' ? 'lock' : 'unlock'; ?> this user?')"
                                            title="<?php echo $user['user_status'] === 'active' ? 'Lock' : 'Unlock'; ?> User">
                                        <i class="fas fa-<?php echo $user['user_status'] === 'active' ? 'lock' : 'unlock'; ?>"></i>
                                    </button>
                                </form>
                                <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteUser('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')"
                                        title="Delete User">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               minlength="6" required>
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View User Modals -->
<?php foreach($users as $user): ?>
<div class="modal fade" id="viewUserModal<?php echo $user['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details - <?php echo htmlspecialchars($user['name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th>User ID:</th>
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
                                <td><span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo $user['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($user['user_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Account Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th>Created:</th>
                                <td><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Last Active:</th>
                                <td><?php echo $user['last_active'] ? date('M j, Y g:i A', strtotime($user['last_active'])) : 'Never'; ?></td>
                            </tr>
                            <?php if ($user['role'] === 'student' && $user['additional_info']): ?>
                            <tr>
                                <th>Program:</th>
                                <td><?php echo htmlspecialchars($user['additional_info']); ?></td>
                            </tr>
                            <tr>
                                <th>Year Level:</th>
                                <td><?php echo $user['year_level'] ? 'Year ' . $user['year_level'] : 'Not Set'; ?></td>
                            </tr>
                            <?php elseif ($user['role'] !== 'student' && $user['additional_info']): ?>
                            <tr>
                                <th>Position:</th>
                                <td><?php echo htmlspecialchars($user['additional_info']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <?php if ($user['role'] === 'student'): ?>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6>Payment Summary</h6>
                        <?php
                        // Get payment summary for this student
                        $stmt = $pdo->prepare("SELECT 
                                                COUNT(*) as total_payments,
                                                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                                                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                                                SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount
                                               FROM payments WHERE student_id = ?");
                        $stmt->execute([$user['user_id']]);
                        $payment_summary = $stmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <div class="row text-center">
                            <div class="col-3">
                                <h5><?php echo $payment_summary['total_payments']; ?></h5>
                                <small class="text-muted">Total Payments</small>
                            </div>
                            <div class="col-3">
                                <h5 class="text-success"><?php echo $payment_summary['paid_count']; ?></h5>
                                <small class="text-muted">Paid</small>
                            </div>
                            <div class="col-3">
                                <h5 class="text-danger"><?php echo $payment_summary['unpaid_count']; ?></h5>
                                <small class="text-muted">Unpaid</small>
                            </div>
                            <div class="col-3">
                                <h5 class="text-warning">₱<?php echo number_format($payment_summary['unpaid_amount'], 2); ?></h5>
                                <small class="text-muted">Outstanding</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="manage_payments.php?student=<?php echo $user['user_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-money-bill-wave"></i> View Payments
                </a>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="edit_user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
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
function editUser(userId, name, email) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function resetPassword(userId, name) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = name;
    document.getElementById('new_password').value = '';
    
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

function deleteUser(userId, name) {
    if (confirm(`Are you sure you want to delete user "${name}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="delete_user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php renderPageEnd(); ?>
