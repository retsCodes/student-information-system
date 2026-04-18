<?php
// Student Information System - Main Index (Redirect File)
require_once 'init.php';

// Check if user is logged in
if (checkSession()) {
    // Redirect based on user role
    $role = $_SESSION['role'];
    
    switch ($role) {
        case 'admin':
            redirect('/students_information_system/admin/dashboard.php');
            break;
        case 'cashier':
            redirect('/students_information_system/cashier/dashboard.php');
            break;
        case 'student':
            redirect('/students_information_system/student/dashboard.php');
            break;
        case 'registrar':
            redirect('/students_information_system/registrar/dashboard.php');
        default:
            // Invalid role, destroy session and redirect to login
            session_destroy();
            redirect('/students_information_system/login.php');
    }
} else {
    // User not logged in, redirect to login page
    redirect('/students_information_system/login.php');
}
?>
