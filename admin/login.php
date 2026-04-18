<?php
require_once '../init.php';
require_once '../theme.php';

$errors = [
    'user_id' => false,
    'password' => false,
    'general' => ''
];
$user_id = '';
$password = '';

// Redirect if already logged in as admin
if (checkSession()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        redirect('/students_information_system/admin/index.php');
    } else {
        // If logged in as non-admin, redirect to their dashboard
        switch ($role) {
            case 'cashier':
                redirect('/students_information_system/cashier/index.php');
                break;
            case 'registrar':
                redirect('/students_information_system/registrar/index.php');
                break;
            case 'student':
                redirect('/students_information_system/student/index.php');
                break;
            default:
                redirect('/students_information_system/index.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = sanitizeInput($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $errors['general'] = 'Invalid security token. Please try again.';
    }
    // Validate inputs
else if (empty($user_id) && empty($password)) {
    $errors['user_id'] = true;
    $errors['password'] = true;
    $errors['general'] = 'Admin ID and Password are required.';
}
else if (empty($user_id)) {
    $errors['user_id'] = true;
    $errors['general'] = 'Admin ID is required.';
}
else if (empty($password)) {
    $errors['password'] = true;
    $errors['general'] = 'Password is required.';
}

    else {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Check login attempts
        if (!checkLoginAttempts($user_id, $ip_address)) {
            $errors['general'] = 'Too many failed login attempts. Please try again later.';
            $errors['user_id'] = true;
            $errors['password'] = true;
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
                redirect('/students_information_system/admin/index.php');
            } else {
                // Failed login
                recordLoginAttempt($user_id, $ip_address, false);
                $errors['general'] = 'Invalid admin credentials or account is locked.';
                $errors['user_id'] = true;
                $errors['password'] = true;
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
                        
                        <form method="POST" action="" id="adminLoginForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="user_id" class="form-label">
                                    <i class="fas fa-user-shield"></i> Admin ID
                                </label>
                                <input type="text" 
                                       class="form-control <?php echo $errors['user_id'] ? 'is-invalid' : ''; ?>" 
                                       id="user_id" 
                                       name="user_id" 
                                       value="<?php echo htmlspecialchars($user_id); ?>" 
                                       placeholder="Enter Admin ID"
                                       autocomplete="username"
                                       >
                                <?php if ($errors['user_id']): ?>
                                    <div class="invalid-feedback">
                                        Please enter Admin ID
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-key"></i> Admin Password
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control <?php echo $errors['password'] ? 'is-invalid' : ''; ?>" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Enter Admin Password"
                                           autocomplete="current-password"
                                           >
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($errors['password']): ?>
                                        <div class="invalid-feedback">
                                            Please enter Admin Password
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-sign-in-alt"></i> Admin Login
                            </button>
                            
                            <div class="mt-3 text-center">
                                <a href="/students_information_system/index.php" class="text-decoration-none">
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
        
        // Auto-focus on first field with error or user_id field
        document.addEventListener('DOMContentLoaded', function() {
            const errorFields = document.querySelectorAll('.is-invalid');
            if (errorFields.length > 0) {
                errorFields[0].focus();
            } else {
                document.getElementById('user_id').focus();
            }
            
            // Add shake animation to error fields
            errorFields.forEach(field => {
                field.addEventListener('animationend', function() {
                    this.classList.remove('shake-error');
                });
                field.classList.add('shake-error');
            });
        });
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
        .is-invalid {
            border-color: #dc3545 !important;
            background-color: #fff5f5;
        }
        .shake-error {
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .invalid-feedback {
            display: block;
            font-size: 0.875em;
            color: #dc3545;
        }
        .form-control:focus.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
    </style>
</body>
</html>