<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('student');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current student information
$stmt = $pdo->prepare("SELECT si.*, u.email as user_email 
                       FROM students_info si 
                       JOIN users u ON si.user_id = u.user_id 
                       WHERE si.user_id = ?");
$stmt->execute([$user_id]);
$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

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
                $address = sanitizeInput($_POST['address'] ?? '');
                
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
                            
                            // Update students_info table
                            $stmt = $pdo->prepare("UPDATE students_info SET name = ?, email = ?, number = ?, address = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $number, $address, $user_id]);
                            
                            // Update session name
                            $_SESSION['name'] = $name;
                            
                            logActivity($user_id, 'Profile Update', 'Updated personal information');
                            $success = 'Profile updated successfully.';
                            
                            // Refresh student info
                            $stmt = $pdo->prepare("SELECT si.*, u.email as user_email 
                                                   FROM students_info si 
                                                   JOIN users u ON si.user_id = u.user_id 
                                                   WHERE si.user_id = ?");
                            $stmt->execute([$user_id]);
                            $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
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
        }
    }
}

renderPageStart('My Profile', 'student', 'profile.php');
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <!-- Profile Picture Card -->
        <div class="card text-center">
            <div class="card-body">
                <div class="avatar-circle mx-auto mb-3" style="width: 100px; height: 100px; font-size: 36px;">
                    <?php echo strtoupper(substr($student_info['name'], 0, 1)); ?>
                </div>
                <h5 class="card-title"><?php echo htmlspecialchars($student_info['name']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($user_id); ?></p>
                <div class="row text-center">
                    <div class="col">
                        <h6 class="text-primary"><?php echo htmlspecialchars($student_info['program'] ?? 'Not Set'); ?></h6>
                        <small class="text-muted">Program</small>
                    </div>
                    <div class="col">
                        <h6 class="text-success"><?php echo $student_info['year_level'] ?? 'Not Set'; ?></h6>
                        <small class="text-muted">Year Level</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Info Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Information</h6>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-4"><strong>Status:</strong></div>
                    <div class="col-8">
                        <span class="badge bg-success"><?php echo ucfirst($student_info['enrollment_status']); ?></span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-4"><strong>Type:</strong></div>
                    <div class="col-8"><?php echo ucfirst($student_info['student_type']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4"><strong>Enrolled:</strong></div>
                    <div class="col-8"><?php echo $student_info['enrollment_date'] ? date('M j, Y', strtotime($student_info['enrollment_date'])) : 'Not Set'; ?></div>
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
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_info">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($student_info['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($student_info['user_email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="number" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="number" name="number" 
                                   value="<?php echo htmlspecialchars($student_info['number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" class="form-control" id="student_id" 
                                   value="<?php echo htmlspecialchars($user_id); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student_info['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="program" class="form-label">Program</label>
                            <input type="text" class="form-control" id="program" 
                                   value="<?php echo htmlspecialchars($student_info['program'] ?? 'Not Set'); ?>" readonly>
                            <div class="form-text">Contact admin to update your program</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="year_level" class="form-label">Year Level</label>
                            <input type="text" class="form-control" id="year_level" 
                                   value="<?php echo $student_info['year_level'] ?? 'Not Set'; ?>" readonly>
                            <div class="form-text">Contact admin to update your year level</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Information
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card">
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
</script>

<?php renderPageEnd(); ?>
