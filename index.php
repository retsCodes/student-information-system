<?php
// Student Information System - Main Index (Redirect File)
require_once 'init.php';

// Check if user is logged in
if (checkSession()) {
    // Redirect based on user role
    $role = $_SESSION['role'];
    
    switch ($role) {
        case 'admin':
            redirect('/student\'s-information-system/admin/dashboard.php');
            break;
        case 'cashier':
            redirect('/student\'s-information-system/cashier/dashboard.php');
            break;
        case 'student':
            redirect('/student\'s-information-system/student/dashboard.php');
            break;
        case 'registrar':
            redirect('/student\'s-information-system/registrar/dashboard.php');
        default:
            // Invalid role, destroy session and redirect to login
            session_destroy();
            redirect('/student\'s-information-system/login.php');
    }
} else {
    // User not logged in, redirect to login page
    redirect('/student\'s-information-system/login.php');
}
?>
