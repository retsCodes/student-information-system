<?php
require_once '../init.php';
require_once '../theme.php';

$error = '';
$success = '';

// Redirect if already logged in as admin
if (checkSession()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        redirect('/student\'s-information-system/admin/index.php');
    } else {
        // If logged in as non-admin, redirect to their dashboard
        switch ($role) {
            case 'cashier':
                redirect('/student\'s-information-system/cashier/index.php');
                break;
            case 'registrar':
                redirect('/student\'s-information-system/registrar/index.php');
                break;
            case 'student':
                redirect('/student\'s-information-system/student/index.php');
                break;
            default:
                redirect('/student\'s-information-system/index.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = sanitizeInput($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    }
    // Validate inputs
    else if (empty($user_id)) {
        $error = 'Admin ID is required.';
    }
    else if (empty($password)) {
        $error = 'Password is required.';
    }
    else {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Check login attempts
        if (!checkLoginAttempts($user_id, $ip_address)) {
            $error = 'Too many failed login attempts. Please try again later.';
        } else {
            // Verify credentials - ONLY ADMINS on this page
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND user_status = 'active' AND role = 'admin'");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Successful login
                recordLoginAttempt($user_id, $ip_address, true);
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Update last active
                updateLastActive($user['user_id']);
                
                // Log activity
                logActivity($user['user_id'], 'Admin Login', 'Admin logged in successfully');
                
                // Redirect to admin dashboard
                redirect('/student\'s-information-system/admin/index.php');
            } else {
                // Failed login
                recordLoginAttempt($user_id, $ip_address, false);
                $error = 'Invalid admin credentials or account is locked.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" <?php echo getThemeAttributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Student Information System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php echo getThemeCSS(); ?>
</head>
<body class="<?php echo getThemeClasses(); ?>">
    <div class="bg"></div>
    <div class="logo">
        <img src="../images/logo.png" alt="Logo">
    </div>
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="row w-100">
            <div class="col-md-6 col-lg-4 mx-auto">
                <div class="card shadow <?php echo getCardTheme(); ?>">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-shield fa-3x text-danger mb-3"></i>
                            <h3 class="card-title">Admin Portal</h3>
                            <p class="text-muted">Administrator login only</p>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> Restricted Access
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
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="user_id" class="form-label">
                                    <i class="fas fa-user-shield"></i> Admin ID
                                </label>
                                <input type="text" class="form-control" id="user_id" name="user_id" 
                                       value="<?php echo htmlspecialchars($user_id ?? ''); ?>" 
                                       placeholder="Enter Admin ID" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-key"></i> Admin Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter Admin Password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-sign-in-alt"></i> Admin Login
                            </button>
                            
                            <div class="mt-3 text-center">
                                <a href="/student's-information-system/index.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left"></i> Back to Main Login
                                </a>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Default Admin: ADMIN001 / admin123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php echo getThemeToggleButton(); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto-focus on user_id field
        document.getElementById('user_id').focus();
    </script>
    <style>
        .bg {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: url('../images/background.jpg') no-repeat center center fixed;
            background-size: cover;
            filter: blur(8px) brightness(0.7);
            z-index: -1;
        }
        .logo {
            position: fixed;
            top: 20px; left: 20px;
        }
        .logo img {
            height: 120px;
            width: auto;
        }
        .card {
            border: 2px solid #dc3545;
        }
    </style>
</body>
</html>