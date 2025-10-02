<?php
require_once 'init.php';

if (checkSession()) {
    // Log activity
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
    
    // Destroy session
    session_destroy();
}

// Redirect to login page
redirect('/student\'s-information-system/login.php');
?>
