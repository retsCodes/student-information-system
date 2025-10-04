<?php
// Admin directory index - Redirect unauthorized users
require_once '../init.php';

// Check if user is logged in and has admin role
if (!checkSession() || $_SESSION['role'] !== 'admin') {
    // User not logged in or not an admin, redirect to main index
    redirect('/student\'s-information-system/index.php');
} else {
    // User is an admin, redirect to admin dashboard
    redirect('/student\'s-information-system/admin/dashboard.php');
}
?>