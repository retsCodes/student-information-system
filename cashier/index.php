<?php
// Cashier directory index - Redirect unauthorized users
require_once '../init.php';

// Check if user is logged in and has cashier role
if (!checkSession() || $_SESSION['role'] !== 'cashier') {
    // User not logged in or not a cashier, redirect to main index
    redirect('/student\'s-information-system/index.php');
} else {
    // User is a cashier, redirect to cashier dashboard
    redirect('/student\'s-information-system/cashier/dashboard.php');
}
?>