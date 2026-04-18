<?php
require_once 'init.php';

if (checkSession()) {
    // Log activity
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
    
    unset($_SESSION['profile_picture']);
    
    // Destroy session
    session_destroy();
}

// Redirect to login page
redirect('/students_information_system/login.php');
?>
