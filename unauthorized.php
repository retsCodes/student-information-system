<?php
require_once 'init.php';
require_once 'theme.php';
?>
<!DOCTYPE html>
<html lang="en" <?php echo getThemeAttributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Student Information System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php echo getThemeCSS(); ?>
</head>
<body class="<?php echo getThemeClasses(); ?>">
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <i class="fas fa-exclamation-triangle fa-5x text-warning mb-4"></i>
            <h1 class="display-4">Access Denied</h1>
            <p class="lead">You don't have permission to access this page.</p>
            <div class="mt-4">
                <a href="/student's-information-system/index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
                <a href="/student's-information-system/login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>
        </div>
    </div>
    
    <?php echo getThemeToggleButton(); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
