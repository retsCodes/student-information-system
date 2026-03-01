<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check for profile picture success message in session
if (isset($_SESSION['profile_picture_success'])) {
    $success = $_SESSION['profile_picture_success'];
    unset($_SESSION['profile_picture_success']);
}

// Get current admin information - NOW FROM USERS TABLE
$stmt = $pdo->prepare("SELECT u.*, ei.number, ei.role as employee_role 
                       FROM users u 
                       LEFT JOIN employee_info ei ON u.user_id = ei.user_id 
                       WHERE u.user_id = ?");
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
                            
                            // Update employee_info table (only number, since name/email are in users table)
                            $stmt = $pdo->prepare("UPDATE employee_info SET name = ?, email = ?, number = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $number, $user_id]);
                            
                            // Update session data
                            $_SESSION['name'] = $name;
                            
                            logActivity($user_id, 'Profile Update', 'Updated personal information');
                            $success = 'Profile updated successfully.';
                            
                            // Refresh admin info
                            $stmt = $pdo->prepare("SELECT u.*, ei.number, ei.role as employee_role 
                                                   FROM users u 
                                                   LEFT JOIN employee_info ei ON u.user_id = ei.user_id 
                                                   WHERE u.user_id = ?");
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
                    // Get file info
                    $file_name = $_FILES['profile_picture']['name'];
                    $file_tmp = $_FILES['profile_picture']['tmp_name'];
                    $file_size = $_FILES['profile_picture']['size'];
                    $file_error = $_FILES['profile_picture']['error'];
                    
                    // Get file extension
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Allowed file types and extensions
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'jfif', 'webp'];
                    $allowed_mime_types = [
                        'image/jpeg',
                        'image/jpg', 
                        'image/png', 
                        'image/gif',
                        'image/pjpeg',
                        'image/x-png',
                        'image/webp'
                    ];
                    
                    // Get actual MIME type using finfo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $file_mime = finfo_file($finfo, $file_tmp);
                    finfo_close($finfo);
                    
                    // Debug information
                    error_log("Profile Picture Upload Debug:");
                    error_log("File name: $file_name");
                    error_log("File extension: $file_ext");
                    error_log("File MIME type: $file_mime");
                    error_log("File size: $file_size bytes");
                    error_log("File temp: $file_tmp");
                    
                    // Check if file extension is allowed
                    if (!in_array($file_ext, $allowed_extensions)) {
                        $error = 'Only JPG, JPEG, PNG, GIF, JFIF, and WebP images are allowed.';
                        error_log("Upload error: Invalid file extension - $file_ext");
                    }
                    // Check if MIME type is allowed
                    elseif (!in_array($file_mime, $allowed_mime_types)) {
                        $error = 'Invalid image file type. Please upload a valid image.';
                        error_log("Upload error: Invalid MIME type - $file_mime");
                    }
                    // Check file size (2MB max)
                    elseif ($file_size > 2 * 1024 * 1024) {
                        $error = 'File size must be less than 2MB.';
                        error_log("Upload error: File too large - $file_size bytes");
                    }
                    // Check for upload errors
                    elseif ($file_error !== UPLOAD_ERR_OK) {
                        $upload_errors = [
                            0 => 'There is no error, the file uploaded with success',
                            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                            3 => 'The uploaded file was only partially uploaded',
                            4 => 'No file was uploaded',
                            6 => 'Missing a temporary folder',
                            7 => 'Failed to write file to disk.',
                            8 => 'A PHP extension stopped the file upload.'
                        ];
                        $error = 'File upload error: ' . ($upload_errors[$file_error] ?? 'Unknown error');
                        error_log("Upload error: PHP error code $file_error");
                    }
                    else {
                        // Generate unique filename
                        $filename = 'admin_' . $user_id . '_' . time() . '.' . $file_ext;
                        $upload_dir = '../uploads/profile_pictures/';
                        $upload_path = $upload_dir . $filename;
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($upload_dir)) {
                            if (!mkdir($upload_dir, 0777, true)) {
                                $error = 'Failed to create upload directory.';
                                error_log("Upload error: Failed to create directory - $upload_dir");
                            }
                        }
                        
                        // Check if directory is writable
                        if (is_dir($upload_dir) && !is_writable($upload_dir)) {
                            $error = 'Upload directory is not writable. Please check permissions.';
                            error_log("Upload error: Directory not writable - $upload_dir");
                        }
                        
                        if (empty($error)) {
                            // Move uploaded file
                            if (move_uploaded_file($file_tmp, $upload_path)) {
                                // Verify the file is actually an image
                                $image_info = @getimagesize($upload_path);
                                if (!$image_info) {
                                    $error = 'Uploaded file is not a valid image.';
                                    unlink($upload_path); // Delete invalid file
                                    error_log("Upload error: Not a valid image file - $upload_path");
                                } else {
                                    // Delete old profile picture if exists
                                    deleteOldProfilePicture($user_id);
                                    
                                    // Update database
                                    if (saveProfilePictureToDatabase($user_id, $filename)) {
                                        // Update session
                                        updateProfilePictureInSession($filename);
                                        loadProfilePictureIntoSession($user_id); // Reload from database
                                        
                                        logActivity($user_id, 'Profile Picture', 'Uploaded new profile picture');
                                        
                                        // Refresh admin info
                                        $stmt = $pdo->prepare("SELECT u.*, ei.number, ei.role as employee_role 
                                                               FROM users u 
                                                               LEFT JOIN employee_info ei ON u.user_id = ei.user_id 
                                                               WHERE u.user_id = ?");
                                        $stmt->execute([$user_id]);
                                        $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        // Store success message in session and redirect
                                        $_SESSION['profile_picture_success'] = 'Profile picture updated successfully.';
                                        header("Location: " . $_SERVER['PHP_SELF'] . "?refresh=" . time());
                                        exit();
                                    } else {
                                        $error = 'Failed to save profile picture to database.';
                                        unlink($upload_path); // Delete file if database update failed
                                        error_log("Upload error: Database update failed for user $user_id");
                                    }
                                }
                            } else {
                                $error = 'Failed to move uploaded file.';
                                error_log("Upload error: move_uploaded_file failed - $file_tmp to $upload_path");
                            }
                        }
                    }
                } else {
                    $upload_error = $_FILES['profile_picture']['error'] ?? 'No file selected';
                    $error = 'Please select a valid image file. Upload error: ' . $upload_error;
                    error_log("Upload error: No file or upload error - $upload_error");
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
    
    // Total employees count (including admin, cashier, registrar)
    $stmt = $pdo->query("SELECT COUNT(*) as total_employees FROM users WHERE role IN ('admin', 'cashier', 'registrar')");
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
                <!-- Current Profile Picture with Real-time Preview -->
                <div class="profile-picture-container mb-3">
                    <div id="profilePicturePreview" class="position-relative">
                        <?php echo displayProfilePicture('md', 'rounded-circle shadow'); ?>
                        <div id="previewIndicator" class="position-absolute top-0 end-0 bg-info text-white rounded-circle p-1 d-none" style="width: 24px; height: 24px; font-size: 12px;">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                </div>
                
                <h5 class="card-title"><?php echo htmlspecialchars($admin_info['name']); ?></h5>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user_id); ?></p>
                <span class="badge bg-primary mb-3"><?php echo ucfirst($admin_info['role']); ?></span>
                
                <!-- Profile Picture Upload Form with Bootstrap Styling -->
                <form method="POST" enctype="multipart/form-data" id="profilePictureForm" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="upload_profile_picture">
                    
                    <!-- Hidden file input for actual upload -->
                    <input type="file" id="profile_picture" name="profile_picture" 
                           accept=".jpg,.jpeg,.png,.gif,.jfif,.webp" 
                           class="d-none">
                    
                    <!-- Bootstrap-styled file picker -->
                    <div class="file-upload-container mb-3">
                        <div class="input-group">
                            <button type="button" id="browseButton" class="btn btn-outline-primary">
                                <i class="fas fa-folder-open me-2"></i> Browse
                            </button>
                            <input type="text" id="fileNameDisplay" class="form-control" placeholder="No file selected" readonly>
                            <button type="button" id="clearFileButton" class="btn btn-outline-secondary d-none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <small class="text-muted mt-1 d-block">JPG, JPEG, PNG, GIF, JFIF, WebP (Max 2MB)</small>
                    </div>
                    
                    <!-- Action buttons (hidden until file is selected) -->
                    <div id="actionButtons" class="d-none">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                            <button type="button" id="discardButton" class="btn btn-outline-danger">
                                <i class="fas fa-times me-2"></i> Discard
                            </button>
                        </div>
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
                                   value="<?php echo htmlspecialchars($admin_info['email']); ?>" required>
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
                    <div class="col-md-4 col-6 mb-3">
                        <a href="manage_users.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-users"></i><br>
                            <small>Manage Users</small>
                        </a>
                    </div>
                    <div class="col-md-4 col-6 mb-3">
                        <a href="logs.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-history"></i><br>
                            <small>View Logs</small>
                        </a>
                    </div>
                    <div class="col-md-4 col-6 mb-3">
                        <a href="backup.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-database"></i><br>
                            <small>Backup System</small>
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

// Profile Picture Upload with Real-time Preview
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('profile_picture');
    const browseButton = document.getElementById('browseButton');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const clearFileButton = document.getElementById('clearFileButton');
    const actionButtons = document.getElementById('actionButtons');
    const discardButton = document.getElementById('discardButton');
    const previewIndicator = document.getElementById('previewIndicator');
    const profilePicImg = document.querySelector('.profile-picture-container img');
    const originalProfilePic = profilePicImg ? profilePicImg.src : '';
    
    // Browse button click triggers file input
    if (browseButton && fileInput) {
        browseButton.addEventListener('click', function() {
            fileInput.click();
        });
    }
    
    // File input change event
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                // Validate file size
                if (file.size > maxSize) {
                    alert('File size must be less than 2MB.');
                    this.value = '';
                    resetFileSelection();
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/jfif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, JPEG, PNG, GIF, JFIF, WebP).');
                    this.value = '';
                    resetFileSelection();
                    return;
                }
                
                // Update file name display
                fileNameDisplay.value = file.name;
                clearFileButton.classList.remove('d-none');
                
                // Show action buttons
                actionButtons.classList.remove('d-none');
                
                // Show preview indicator
                previewIndicator.classList.remove('d-none');
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (profilePicImg) {
                        profilePicImg.src = e.target.result;
                        profilePicImg.classList.add('preview-active');
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Clear file button
    if (clearFileButton) {
        clearFileButton.addEventListener('click', function() {
            resetFileSelection();
        });
    }
    
    // Discard button
    if (discardButton) {
        discardButton.addEventListener('click', function() {
            resetFileSelection();
        });
    }
    
    // Function to reset file selection
    function resetFileSelection() {
        if (fileInput) {
            fileInput.value = '';
        }
        fileNameDisplay.value = '';
        clearFileButton.classList.add('d-none');
        actionButtons.classList.add('d-none');
        previewIndicator.classList.add('d-none');
        
        // Reset profile picture to original
        if (profilePicImg && originalProfilePic) {
            profilePicImg.src = originalProfilePic;
            profilePicImg.classList.remove('preview-active');
        }
    }
    
    // Check if we need to refresh images after profile picture upload
    if (window.location.search.includes('refresh')) {
        // Clear the refresh parameter from URL
        window.history.replaceState({}, document.title, window.location.pathname);
        
        // Force reload profile images
        document.querySelectorAll('img').forEach(img => {
            if (img.src.includes('profile_pictures')) {
                const originalSrc = img.src.split('?')[0];
                img.src = originalSrc + '?t=' + new Date().getTime();
            }
        });
    }
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

/* Profile picture styling */
.profile-picture-container img {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border: 4px solid #dee2e6;
    transition: all 0.3s ease;
}

.profile-picture-container img.preview-active {
    border-color: #0dcaf0;
    box-shadow: 0 0 15px rgba(13, 202, 240, 0.4);
}

/* File upload styling */
.file-upload-container .input-group {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.file-upload-container .form-control {
    border-left: none;
}

.file-upload-container .btn-outline-primary:hover {
    background-color: #0d6efd;
    color: white;
}

/* Preview indicator */
#previewIndicator {
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Action buttons animation */
#actionButtons {
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Quick actions styling */
.quick-action-btn {
    padding: 15px 5px;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<?php renderPageEnd(); ?>