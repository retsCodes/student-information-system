<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current admin information
$stmt = $pdo->prepare("SELECT ei.*, u.email as user_email, u.user_status, u.created_at, u.last_active 
                       FROM employee_info ei 
                       JOIN users u ON ei.user_id = u.user_id 
                       WHERE ei.user_id = ?");
$stmt->execute([$user_id]);
$admin_info = $stmt->fetch(PDO::FETCH_ASSOC);

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
                            // Update users table
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $user_id]);
                            
                            // Update employee_info table
                            $stmt = $pdo->prepare("UPDATE employee_info SET name = ?, email = ?, number = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $number, $user_id]);
                            
                            // Update session name
                            $_SESSION['name'] = $name;
                            
                            logActivity($user_id, 'Profile Update', 'Updated personal information');
                            $success = 'Profile updated successfully.';
                            
                            // Refresh admin info
                            $stmt = $pdo->prepare("SELECT ei.*, u.email as user_email, u.user_status, u.created_at, u.last_active 
                                                   FROM employee_info ei 
                                                   JOIN users u ON ei.user_id = u.user_id 
                                                   WHERE ei.user_id = ?");
                            $stmt->execute([$user_id]);
                            $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                    } catch(Exception $e) {
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
                
            case 'upload_profile_picture':
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    $file_type = $_FILES['profile_picture']['type'];
                    $file_size = $_FILES['profile_picture']['size'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $error = 'Only JPG, PNG, and GIF images are allowed.';
                    } elseif ($file_size > $max_size) {
                        $error = 'File size must be less than 2MB.';
                    } else {
                        // Generate unique filename
                        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                        $filename = 'admin_' . $user_id . '_' . time() . '.' . $file_extension;
                        $upload_path = '../uploads/profile_pictures/' . $filename;
                        
                        // Create directory if it doesn't exist
                        if (!is_dir('../uploads/profile_pictures/')) {
                            mkdir('../uploads/profile_pictures/', 0777, true);
                        }
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                            // Delete old profile picture if exists
                            if (!empty($admin_info['profile_picture'])) {
                                $old_file = '../uploads/profile_pictures/' . $admin_info['profile_picture'];
                                if (file_exists($old_file)) {
                                    unlink($old_file);
                                }
                            }
                            
                            // Update database
                            $stmt = $pdo->prepare("UPDATE employee_info SET profile_picture = ? WHERE user_id = ?");
                            $stmt->execute([$filename, $user_id]);
                            
                            logActivity($user_id, 'Profile Picture', 'Uploaded new profile picture');
                            $success = 'Profile picture updated successfully.';
                            
                            // Refresh admin info
                            $stmt = $pdo->prepare("SELECT ei.*, u.email as user_email, u.user_status, u.created_at, u.last_active 
                                                   FROM employee_info ei 
                                                   JOIN users u ON ei.user_id = u.user_id 
                                                   WHERE ei.user_id = ?");
                            $stmt->execute([$user_id]);
                            $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        } else {
                            $error = 'Failed to upload profile picture.';
                        }
                    }
                } else {
                    $error = 'Please select a valid image file.';
                }
                break;
        }
    }
}

// Get admin statistics for dashboard
$stats = [];
try {
    // Total users count
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Total students count
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM users WHERE role = 'student'");
    $stats['total_students'] = $stmt->fetchColumn();
    
    // Total employees count
    $stmt = $pdo->query("SELECT COUNT(*) as total_employees FROM users WHERE role IN ('admin', 'cashier')");
    $stats['total_employees'] = $stmt->fetchColumn();
    
    // Total payments count
    $stmt = $pdo->query("SELECT COUNT(*) as total_payments FROM payments");
    $stats['total_payments'] = $stmt->fetchColumn();
    
    // Recent activity count (last 7 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) as recent_activity FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $stats['recent_activity'] = $stmt->fetchColumn();
    
} catch(Exception $e) {
    // If stats fail, set defaults
    $stats = [
        'total_users' => 0,
        'total_students' => 0,
        'total_employees' => 0,
        'total_payments' => 0,
        'recent_activity' => 0
    ];
}

renderPageStart('Admin Profile', 'admin', 'profile.php');
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Users', $stats['total_users'], 'fas fa-users', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Students', $stats['total_students'], 'fas fa-user-graduate', 'success'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Employees', $stats['total_employees'], 'fas fa-user-tie', 'info'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Recent Activity', $stats['recent_activity'], 'fas fa-history', 'warning'); ?>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <!-- Profile Information Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-id-card"></i> Profile Information
                </h5>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($admin_info['profile_picture'])): ?>
                    <div class="mb-3">
                        <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($admin_info['profile_picture']); ?>" 
                             alt="Profile Picture" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                    </div>
                <?php else: ?>
                    <div class="avatar-circle mx-auto mb-3" style="width: 120px; height: 120px; font-size: 48px;">
                        <?php echo strtoupper(substr($admin_info['name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                
                <h5 class="card-title"><?php echo htmlspecialchars($admin_info['name']); ?></h5>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user_id); ?></p>
                <span class="badge bg-primary mb-3"><?php echo ucfirst($admin_info['role']); ?></span>
                
                <!-- Profile Picture Upload Form -->
                <form method="POST" enctype="multipart/form-data" id="profilePictureForm" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="upload_profile_picture">
                    
                    <div class="d-grid gap-2">
                        <input type="file" id="profile_picture" name="profile_picture" 
                               accept="image/jpeg,image/jpg,image/png,image/gif" 
                               class="form-control form-control-sm" onchange="document.getElementById('profilePictureForm').submit()">
                        <small class="text-muted">JPG, PNG, GIF (Max 2MB)</small>
                    </div>
                </form>
            </div>
        </div>

        <!-- Account Status Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Account Status
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-6"><strong>Status:</strong></div>
                    <div class="col-6 text-end">
                        <span class="badge bg-<?php echo $admin_info['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($admin_info['user_status']); ?>
                        </span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-6"><strong>Member Since:</strong></div>
                    <div class="col-6 text-end"><?php echo date('M j, Y', strtotime($admin_info['created_at'])); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-6"><strong>Last Active:</strong></div>
                    <div class="col-6 text-end"><?php echo $admin_info['last_active'] ? date('M j, Y g:i A', strtotime($admin_info['last_active'])) : 'Never'; ?></div>
                </div>
                <div class="row">
                    <div class="col-6"><strong>User ID:</strong></div>
                    <div class="col-6 text-end"><code><?php echo htmlspecialchars($user_id); ?></code></div>
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
                    <i class="fas fa-user-edit"></i> Update Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_info">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($admin_info['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($admin_info['user_email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="number" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="number" name="number" 
                                   value="<?php echo htmlspecialchars($admin_info['number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="admin_id" class="form-label">Admin ID</label>
                            <input type="text" class="form-control" id="admin_id" 
                                   value="<?php echo htmlspecialchars($user_id); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role</label>
                            <input type="text" class="form-control" id="role" 
                                   value="<?php echo htmlspecialchars(ucfirst($admin_info['role'])); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Account Status</label>
                            <input type="text" class="form-control" id="status" 
                                   value="<?php echo ucfirst($admin_info['user_status']); ?>" readonly>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Information
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-lock"></i> Change Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="6" required>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   minlength="6" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6 mb-3">
                        <a href="manage_users.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-users"></i><br>
                            <small>Manage Users</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="logs.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-history"></i><br>
                            <small>View Logs</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="backup.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-database"></i><br>
                            <small>Backup System</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="../logout.php" class="btn btn-outline-danger w-100">
                            <i class="fas fa-sign-out-alt"></i><br>
                            <small>Logout</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Real-time password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthIndicator = document.getElementById('password-strength');
    
    if (!strengthIndicator) {
        // Create strength indicator if it doesn't exist
        const indicator = document.createElement('div');
        indicator.id = 'password-strength';
        indicator.className = 'mt-2';
        this.parentNode.appendChild(indicator);
    }
    
    const indicator = document.getElementById('password-strength');
    let strength = 0;
    let feedback = '';
    
    if (password.length >= 6) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;
    
    switch(strength) {
        case 0:
        case 1:
            feedback = '<small class="text-danger"><i class="fas fa-times-circle"></i> Weak password</small>';
            break;
        case 2:
            feedback = '<small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Moderate password</small>';
            break;
        case 3:
            feedback = '<small class="text-info"><i class="fas fa-check-circle"></i> Good password</small>';
            break;
        case 4:
            feedback = '<small class="text-success"><i class="fas fa-shield-alt"></i> Strong password</small>';
            break;
    }
    
    indicator.innerHTML = feedback;
});
</script>

<style>
.avatar-circle {
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin: 0 auto;
}
</style>

<?php renderPageEnd(); ?>