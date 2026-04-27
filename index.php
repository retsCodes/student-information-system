<?php
require_once 'init.php';

if (checkSession()) {
    $role = $_SESSION['role'];
    
    switch ($role) {
        case 'admin':
            redirect('students_information_system/admin/dashboard.php');
            break;
        case 'cashier':
            redirect('students_information_system/cashier/dashboard.php');
            break;
        case 'student':
            redirect('students_information_system/student/dashboard.php');
            break;
        case 'registrar':
            redirect('students_information_system/registrar/dashboard.php');
        default:
            session_destroy();
            redirect('students_information_system/login.php');
    }
} else {
    redirect('students_information_system/login.php');
}
?>
