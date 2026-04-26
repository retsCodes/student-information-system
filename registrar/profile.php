<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('registrar');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user information
$stmt = $pdo->prepare("
    SELECT u.*, ei.position, ei.department, ei.number as contact_number, ei.hire_date
    FROM users u
    LEFT JOIN employee_info ei ON u.user_id = ei.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'update_profile':
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
                
                if (empty($name) || empty($email)) {
                    $error = 'Name and email are required.';
                } elseif (!validateEmail($email)) {
                    $error = 'Valid email is required.';
                } else {
                    try {
                        // Check if email exists for other users
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND user_id != ?");
                        $stmt->execute([$email, $user_id]);
                        if ($stmt->fetch()) {
                            $error = 'Email already used by another user.';
                        } else {
                            $pdo->beginTransaction();
                            
                            // Update users table
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $user_id]);
                            
                            // Update employee_info
                            $stmt = $pdo->prepare("UPDATE employee_info SET name = ?, email = ?, number = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $contact_number, $user_id]);
                            
                            $_SESSION['name'] = $name;
                            
                            $pdo->commit();
                            logActivity($user_id, 'Profile Updated', 'Updated profile information');
                            $success = 'Profile updated successfully.';
                            
                            // Refresh user data
                            $stmt = $pdo->prepare("
                                SELECT u.*, ei.position, ei.department, ei.number as contact_number, ei.hire_date
                                FROM users u
                                LEFT JOIN employee_info ei ON u.user_id = ei.user_id
                                WHERE u.user_id = ?
                            ");
                            $stmt->execute([$user_id]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $error = 'Failed to update profile: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = 'All password fields are required.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $stored_password = $stmt->fetchColumn();
                    
                    if (!password_verify($current_password, $stored_password)) {
                        $error = 'Current password is incorrect.';
                    } else {
                        $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$new_hashed, $user_id]);
                        
                        logActivity($user_id, 'Password Changed', 'Changed account password');
                        $success = 'Password changed successfully.';
                    }
                }
                break;
                
            case 'update_profile_picture':
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['profile_picture']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (!in_array($ext, $allowed)) {
                        $error = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
                    } else {
                        $upload_dir = '../uploads/profile_pictures/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $new_filename = $user_id . '_' . time() . '.' . $ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                            // Delete old profile picture
                            if (!empty($user['profile_picture']) && file_exists($upload_dir . $user['profile_picture'])) {
                                unlink($upload_dir . $user['profile_picture']);
                            }
                            
                            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                            $stmt->execute([$new_filename, $user_id]);
                            
                            $_SESSION['profile_picture'] = $new_filename;
                            logActivity($user_id, 'Profile Picture Updated', 'Updated profile picture');
                            $success = 'Profile picture updated successfully.';
                            
                            // Refresh user data
                            $stmt = $pdo->prepare("
                                SELECT u.*, ei.position, ei.department, ei.number as contact_number, ei.hire_date
                                FROM users u
                                LEFT JOIN employee_info ei ON u.user_id = ei.user_id
                                WHERE u.user_id = ?
                            ");
                            $stmt->execute([$user_id]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        } else {
                            $error = 'Failed to upload image.';
                        }
                    }
                } else {
                    $error = 'Please select a file to upload.';
                }
                break;
        }
    }
}

renderPageStart('My Profile', 'registrar', 'profile.php');
?>

<style>
.profile-avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.avatar-circle-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: bold;
    margin: 0 auto;
    border: 4px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.profile-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s;
}
.profile-card:hover {
    transform: translateY(-5px);
}
.info-row {
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
}
.info-label {
    font-weight: 600;
    color: #495057;
    width: 140px;
    display: inline-block;
}
.info-value {
    color: #212529;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-circle me-2"></i>My Profile</h2>
        <span class="badge bg-primary fs-6">Registrar</span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Picture Column -->
        <div class="col-md-4 mb-4">
            <div class="card profile-card text-center">
                <div class="card-body">
                    <?php
                    $profile_picture = $user['profile_picture'] ?? null;
                    if ($profile_picture && !empty($profile_picture) && file_exists('../uploads/profile_pictures/' . $profile_picture)):
                    ?>
                        <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" 
                             class="profile-avatar mb-3" 
                             alt="<?php echo htmlspecialchars($user['name']); ?>">
                    <?php else: ?>
                        <div class="avatar-circle-large mb-3">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="mt-2 mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($user['user_id']); ?></p>
                    <p><span class="badge bg-primary">Registrar</span></p>
                    
                    <hr>
                    
                    <!-- Upload Profile Picture Form -->
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_profile_picture">
                        
                        <div class="mb-3">
                            <label class="form-label">Change Profile Picture</label>
                            <input type="file" class="form-control" name="profile_picture" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-upload me-2"></i>Upload New Picture
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Profile Information Column -->
        <div class="col-md-8">
            <!-- Basic Information Card -->
            <div class="card profile-card mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">User ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['user_id']); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="Registrar" disabled>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['position'] ?? 'Registrar'); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" placeholder="Optional">
                            </div>
                        </div>
                        
                        <?php if (!empty($user['department'])): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['department']); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hire Date</label>
                                <input type="text" class="form-control" value="<?php echo $user['hire_date'] ? date('M j, Y', strtotime($user['hire_date'])) : 'N/A'; ?>" disabled>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Change Password Card -->
            <div class="card profile-card">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" minlength="6" required>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" minlength="6" required>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Information Card -->
            <div class="card profile-card mt-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Account Created:</span>
                        <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Active:</span>
                        <span class="info-value"><?php echo $user['last_active'] ? date('F j, Y g:i A', strtotime($user['last_active'])) : 'Never'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Account Status:</span>
                        <span class="info-value">
                            <span class="badge bg-<?php echo $user['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($user['user_status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderPageEnd(); ?>